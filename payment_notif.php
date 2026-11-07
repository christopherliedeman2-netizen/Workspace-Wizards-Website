<?php
session_start();
    
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/payfast.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


// Prevent administrators from making purchases
if (
    isset($_SESSION["user_role"]) &&
    $_SESSION["user_role"] === "admin"
) {
    unset($_SESSION["cart"]);
    header("Location: admin/index.php");
    exit;
}


// Retrieve the demonstration payment request.
$order_id = filter_input(
    INPUT_GET,
    "order_id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($order_id === false || $order_id === null) {
    http_response_code(400);
    exit("Invalid order reference.");
}

// Validate CSRF token received through the PayFast return URL.
if (
    !isset($_GET["csrf_token"], $_SESSION["csrf_token"]) ||
    !is_string($_GET["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_GET["csrf_token"]
    )
) {
    http_response_code(403);
    exit("Invalid security token.");
}

// Start database transaction.
$conn->begin_transaction();

// Retrieve and lock order.
$stmt = $conn->prepare(
    "SELECT id, total, status, payment_status
     FROM orders
     WHERE id = ? AND user_id = ?
     FOR UPDATE"
);

if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    exit("Database error.");
}

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$order) {
    $conn->rollback();
    http_response_code(404);
    exit("Order not found.");
}



// Prevent duplicate processing.
if ($order["payment_status"] === "Paid") {
    $conn->commit();
    http_response_code(200);
    exit("Payment already processed.");
}

// Cancelled orders cannot be paid.
if ($order["status"] === "Cancelled") {
    $conn->rollback();
    http_response_code(400);
    exit("Cancelled order cannot be paid.");
}

// Only Pending orders may proceed to stock deduction.
if ($order["payment_status"] !== "Pending") {
    $conn->rollback();
    http_response_code(400);
    exit("Order is not awaiting payment.");
}

// Retrieve and lock ordered products.
$stmt = $conn->prepare(
    "SELECT oi.product_id, oi.quantity, p.stock
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     FOR UPDATE"
);

if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    exit("Database error.");
}

$stmt->bind_param("i", $order_id);
$stmt->execute();

$items = $stmt->get_result();

$orderItems = [];

// Check stock availability.
while ($item = $items->fetch_assoc()) {

    $quantity = (int)$item["quantity"];
    $stock = (int)$item["stock"];

    if ($quantity <= 0) {
        $stmt->close();
        $conn->rollback();

        http_response_code(400);
        exit("Invalid product quantity.");
    }

    if ($stock < $quantity) {
        $stmt->close();
        $conn->rollback();

        http_response_code(400);
        exit("Insufficient stock.");
    }

    $orderItems[] = $item;
}

$stmt->close();

// Ensure the order actually contains products.
if (empty($orderItems)) {
    $conn->rollback();

    http_response_code(400);
    exit("No products found for this order.");
}


// Deduct stock.
foreach ($orderItems as $item) {

    $product_id = (int)$item["product_id"];
    $quantity = (int)$item["quantity"];

    $stmt = $conn->prepare(
        "UPDATE products
         SET stock = stock - ?
         WHERE id = ?
           AND stock >= ?"
    );

    if (!$stmt) {
        $conn->rollback();
        http_response_code(500);
        exit("Database error.");
    }

    $stmt->bind_param(
        "iii",
        $quantity,
        $product_id,
        $quantity
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        $conn->rollback();

        http_response_code(400);
        exit("Stock update failed.");
    }

    $stmt->close();
}

// Mark order as paid and move it to Processing.
$paymentReference = "Payfast" . $order_id;

$stmt = $conn->prepare(
    "UPDATE orders
     SET payment_status = 'Paid',
         status = 'Processing',
         payment_reference = ?
     WHERE id = ?
       AND payment_status <> 'Paid'
       AND status <> 'Cancelled'"
);

if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    exit("Database error.");
}

$stmt->bind_param(
    "si",
    $paymentReference,
    $order_id
);

$stmt->execute();

if ($stmt->affected_rows !== 1) {
    $stmt->close();
    $conn->rollback();

    http_response_code(400);
    exit("Payment update failed.");
}

$stmt->close();

// Complete transaction.
$conn->commit();

http_response_code(200);
header("Location: orders.php");
exit;