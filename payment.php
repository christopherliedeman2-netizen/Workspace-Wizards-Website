<?php

session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/payfast.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Prevent administrators from making purchases
if (
    isset($_SESSION["user_role"]) &&
    $_SESSION["user_role"] === "admin"
) {
    unset($_SESSION["cart"]);
    header("Location: admin/index.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];


$order_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($order_id === false || $order_id === null) {
    http_response_code(400);
    exit("Invalid order reference.");
}



// Retrieve the order.

$stmt = $conn->prepare(
    "SELECT id, total, status, payment_status
     FROM orders
     WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$order) {
    http_response_code(404);
    exit("Order not found.");
}



if ($order["status"] === "Cancelled") {
    exit("This order has been cancelled and cannot be paid.");
}

if ($order["payment_status"] === "Paid") {
    exit("This order has already been paid.");
}

if (
    $order["payment_status"] !== "Pending" &&
    $order["payment_status"] !== "Failed"
) {
    exit("This order cannot currently be paid.");
}


$stmt = $conn->prepare(
    "SELECT name, email
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$user) {
    exit("Customer account could not be found.");
}


$return_url = "https://workspacewizards.ifree.page/payment_notif.php?order_id=" . $order_id . "&csrf_token=" . urlencode(csrf_token());

$cancel_url = "https://workspacewizards.ifree.page/order_confirmation.php?id=" . $order_id;

$notify_url = NOTIFY_URL;


$amount = number_format(
    (float)$order["total"],
    2,
    ".",
    ""
);


$data = [
    "merchant_id" => MERCHANT_ID,
    "merchant_key" => MERCHANT_KEY,
    "return_url" => $return_url,
    "cancel_url" => $cancel_url,
    "notify_url" => $notify_url,
    "name_first" => $user["name"],
    "email_address" => $user["email"],
    "m_payment_id" => (string)$order_id,
    "amount" => $amount,
    "item_name" => "WorkspaceWizards Order #" . $order_id
];

$signatureString = "";

foreach ($data as $key => $value) {

    if ($value === "") {
        continue;
    }

    $signatureString .=
        $key . "=" .
        urlencode(trim((string)$value)) .
        "&";
}


// Remove the trailing & before adding the passphrase.
$signatureString = rtrim($signatureString, "&");

// Add passphrase.
$signatureString .=
    "&passphrase=" .
    urlencode(trim(PASSPHRASE));

$signature = md5($signatureString);


//Add signature
$data["signature"] = $signature;

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Secure Checkout | WorkspaceWizards</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body class="payment-page">

<main class="payment-container">

    <section class="payment-card">

        <div class="payment-icon" aria-hidden="true">
            &#128179;
        </div>

        <p class="payment-label">CHECKOUT</p>

        <h1>Complete Your Payment</h1>

        <p class="payment-description">
            Review your order details before continuing to PayFast.
        </p>

        <div class="payment-summary">

            <div class="payment-detail">
                <span>Order reference</span>
                <strong>
                    #<?php echo (int)$order["id"]; ?>
                </strong>
            </div>

            <div class="payment-total">
                <span>Total to pay</span>
                <strong>
                    R<?php echo htmlspecialchars(
                        $amount,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </strong>
            </div>

        </div>

        <div class="payment-notice">
            <span aria-hidden="true">&#128274;</span>
            <p>
                You will continue to PayFast to complete your payment.
                Your order will only be marked as paid after payment
                confirmation is processed.
            </p>
        </div>

        <form
            id="payfast-form"
            method="POST"
            action="<?php echo htmlspecialchars(
                PROCESS_URL,
                ENT_QUOTES,
                "UTF-8"
            ); ?>"
        >

            <?php foreach ($data as $key => $value): ?>
                <input
                    type="hidden"
                    name="<?php echo htmlspecialchars(
                        $key,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    value="<?php echo htmlspecialchars(
                        $value,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                >
            <?php endforeach; ?>

            <button type="submit" class="payment-button">
                Continue to PayFast
                <span aria-hidden="true">&rarr;</span>
            </button>

        </form>

        <p class="payment-footer">
            Secure checkout powered by PayFast
        </p>

        <a class="payment-back-link" href="orders.php">
            Return to My Orders
        </a>

    </section>

</main>

</body>
</html>
