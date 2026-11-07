<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";
require_once "../includes/shipping_email.php";

// Only administrators may access this page.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$error = "";
$message = "";

// Process shipment/delivery updates.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_order"])
) {
    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $orderId = filter_var(
        $_POST["order_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if (
        $orderId === false ||
        $orderId === null ||
        $orderId < 1
    ) {
        $error = "Invalid order.";
    } else {

        // Retrieve the current order status and payment status.
        $stmt = $conn->prepare(
            "SELECT
                o.status,
                o.payment_status,
                u.name AS customer_name,
                u.email AS customer_email
             FROM orders o
             INNER JOIN users u ON o.user_id = u.id
             WHERE o.id = ?"
        );

        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        $stmt->close();

        if (!$order) {

            $error = "The selected order could not be found.";

        } elseif (
            $order["status"] === "Processing" &&
            $order["payment_status"] === "Paid"
        ) {

            // A paid Processing order is ready to ship.
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'Shipped'
                 WHERE id = ?
                   AND status = 'Processing'
                   AND payment_status = 'Paid'"
            );

            $stmt->bind_param("i", $orderId);

            if ($stmt->execute() && $stmt->affected_rows === 1) {

                $emailSent = sendShippingEmail(
                    $order["customer_email"],
                    $order["customer_name"],
                    (int)$orderId
                );

                if ($emailSent) {
                    $message = "Order marked as shipped. A shipping confirmation email was sent to the customer.";
                } else {
                    $message = "Order marked as shipped, but the shipping confirmation email could not be sent. Please check the PHP error log.";
                }

            } else {
                $error = "The order could not be marked as shipped.";
            }
            $stmt->close();

        } elseif (
            $order["status"] === "Shipped" &&
            $order["payment_status"] === "Paid"
        ) {

            // A shipped and paid order can be marked as delivered.
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'Completed'
                 WHERE id = ?
                   AND status = 'Shipped'
                   AND payment_status = 'Paid'"
            );

            $stmt->bind_param("i", $orderId);

            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $message = "Order marked as completed.";
            } else {
                $error = "The order could not be marked as completed.";
            }
            
            
            $stmt->close();

        } elseif ($order["status"] === "Cancelled") {

            $error = "Cancelled orders cannot be processed.";

        } elseif ($order["status"] === "Completed") {

            $error = "This order has already been completed.";

        } elseif ($order["payment_status"] !== "Paid") {

            $error = "This order cannot be shipped until payment has been confirmed.";

        } else {

            $error = "This order is not ready for the next fulfilment stage.";
        }
    }
}

// Retrieve orders that still require shipping or delivery confirmation.
$stmt = $conn->prepare(
    "SELECT
        o.id,
        o.user_id,
        o.total,
        o.status,
        o.payment_status,
        o.payment_method,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email
     FROM orders o
     INNER JOIN users u ON o.user_id = u.id
     WHERE o.payment_status = 'Paid'
       AND o.status IN ('Processing', 'Shipped')
     ORDER BY o.created_at ASC"
);

$stmt->execute();
$result = $stmt->get_result();

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>Ready to Ship | WorkspaceWizards</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-ready-to-ship.css">

</head>

<body>

    <header>
        <h1>Shipping & Delivery</h1>
        <nav>
            <a href="index.php">Dashboard</a>
            <a href="products.php">Manage Products</a>
            <a href="../products.php">View Store</a>
            <form method="POST" action="../logout.php">
                <?php echo csrf_field(); ?>
                <button type="submit">Logout</button>
            </form>
            <a href="ready_to_ship.php">Ready to Ship</a>
        </nav>
    </header>
<main>

    <h2>Orders Requiring Shipping or Delivery</h2>

    <p>
        Paid orders are shown here until they have been delivered.
    </p>


    <?php if ($message !== ""): ?>

        <p style="color: green;">

            <?php echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </p>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <p style="color: red;">

            <?php echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </p>

    <?php endif; ?>


    <?php if ($result->num_rows > 0): ?>

        <?php while ($order = $result->fetch_assoc()): ?>

            
            <section
                class="product-card shipping-order status-<?php echo strtolower(htmlspecialchars($order["status"], ENT_QUOTES, "UTF-8")); ?>"
                style="margin-bottom: 25px;"
            >


                <h3>
                    Order #<?php echo (int)$order["id"]; ?>
                </h3>


                <p>

                    <strong>Customer:</strong>

                    <?php echo htmlspecialchars(
                        $order["customer_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


                <p>

                    <strong>Email:</strong>

                    <?php echo htmlspecialchars(
                        $order["customer_email"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


                <p>

                    <strong>Order date:</strong>

                    <?php echo htmlspecialchars(
                        $order["created_at"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


                <p>

                    <strong>Total:</strong>

                    R<?php echo number_format(
                        (float)$order["total"],
                        2
                    ); ?>

                </p>


                <p>

                    <strong>Payment status:</strong>

                    <?php echo htmlspecialchars(
                        $order["payment_status"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>

                    
                <p>
                    <strong>Fulfilment status:</strong>

                    <?php
                    $status = $order["status"];

                    $badge_class = match ($status) {
                        "Processing" => "processing",
                        "Shipped" => "shipped",
                        "Completed" => "completed",
                        default => ""
                    };
                    ?>

                    <span class="status-badge <?php echo $badge_class; ?>">
                        <?php echo htmlspecialchars(
                            $status,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>
                </p>

                <p>

                    <a
                        href="view_order.php?id=<?php echo (int)$order["id"]; ?>"
                    >
                        View Order Details
                    </a>

                </p>


                <form
                    method="POST"
                    action="ready_to_ship.php"
                >

                    <?php echo csrf_field(); ?>

                    <input
                        type="hidden"
                        name="order_id"
                        value="<?php echo (int)$order["id"]; ?>"
                    >

                    <?php if ($order["status"] === "Processing"): ?>

                        <button
                            type="submit"
                            name="update_order"
                            onclick="return confirm('Mark this order as shipped?');"
                        >
                            Mark as Shipped
                        </button>

                    <?php elseif ($order["status"] === "Shipped"): ?>

                        <button
                            type="submit"
                            name="update_order"
                            onclick="return confirm('Confirm that this order has been delivered?');"
                        >
                            Mark as Delivered
                        </button>

                    <?php endif; ?>

                </form>

            </section>

        <?php endwhile; ?>

    <?php else: ?>

        <p>
            There are currently no orders requiring shipping or delivery confirmation.
        </p>

    <?php endif; ?>

</main>

</body>

</html>

