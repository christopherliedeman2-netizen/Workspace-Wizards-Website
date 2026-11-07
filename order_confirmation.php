<?php
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

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

// Validate order ID from the URL.
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


// Handle order cancellation.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token.");
    }

    if (isset($_POST["cancel_order"])) {

        $posted_order_id = filter_input(
            INPUT_POST,
            "order_id",
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        if (
            $posted_order_id === false ||
            $posted_order_id === null ||
            $posted_order_id !== $order_id
        ) {
            http_response_code(400);
            exit("Invalid order reference.");
        }

        // Only the owner of a Pending or Processing order
        // can cancel it.
        $stmt = $conn->prepare(
			"UPDATE orders
			 SET status = 'Cancelled',
				 payment_status = 'Cancelled'
			 WHERE id = ?
			   AND user_id = ?
			   AND status IN ('Pending', 'Processing')"
		);

        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();

        $stmt->close();

        // Reload the page so the new status is displayed.
        header("Location: order_confirmation.php?id=" . $order_id);
        exit;
    }
}


// Retrieve the order.
$stmt = $conn->prepare(
    "SELECT id, total, status, payment_status, created_at
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


// Retrieve the order items.
$stmt = $conn->prepare(
    "SELECT p.name, oi.quantity, oi.price
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();

$items = $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Order #<?php echo (int)$order["id"]; ?> -
        WorkspaceWizards
    </title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/order_confirmation.css">

</head>

<body>

<header>

    <h1>Workspace Wizards</h1>

    <nav>

        <a href="home.php">Home</a>
        <a href="products.php">Products</a>
        <a href="orders.php">My Orders</a>
        <a href="account.php">My Account</a>

    </nav>

</header>


<main>

    <?php if ($order["payment_status"] === "Paid"): ?>

        <h2>Payment Successful</h2>

    <?php else: ?>

        <h2>Order Created Successfully</h2>

    <?php endif; ?>


    <p>
        Your order reference is
        <strong>
            #<?php echo (int)$order["id"]; ?>
        </strong>.
    </p>


    <p>
        <strong>Order status:</strong>
        <?php
        echo htmlspecialchars(
            $order["status"],
            ENT_QUOTES,
            "UTF-8"
        );
        ?>
    </p>


    <p>
        <strong>Payment status:</strong>
        <?php
        echo htmlspecialchars(
            $order["payment_status"],
            ENT_QUOTES,
            "UTF-8"
        );
        ?>
    </p>


    <h3>Items Ordered</h3>


    <table>

        <thead>

            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price Each</th>
                <th>Subtotal</th>
            </tr>

        </thead>


        <tbody>

            <?php while ($item = $items->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $item["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo (int)$item["quantity"]; ?>
                    </td>

                    <td>
                        R<?php
                        echo number_format(
                            (float)$item["price"],
                            2
                        );
                        ?>
                    </td>

                    <td>
                        R<?php
                        echo number_format(
                            (float)$item["price"] *
                            (int)$item["quantity"],
                            2
                        );
                        ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        </tbody>

    </table>


    <h3>
        Total:
        R<?php
        echo number_format(
            (float)$order["total"],
            2
        );
        ?>
    </h3>


    
    <?php if (
    ($order["status"] === "Pending" ||
     $order["status"] === "Processing") &&
    $order["payment_status"] !== "Paid"
	): ?>

        <form
            method="POST"
            action="order_confirmation.php?id=<?php echo (int)$order["id"]; ?>"
        >

            <?php echo csrf_field(); ?>

            <input
                type="hidden"
                name="order_id"
                value="<?php echo (int)$order["id"]; ?>"
            >

            <button
                type="submit"
                name="cancel_order"
                onclick="return confirm('Are you sure you want to cancel this order?');"
            >
                Cancel Order
            </button>

        </form>

    <?php endif; ?>


<?php if ($order["payment_status"] === "Paid"): ?>

    <p>Your payment has been confirmed.</p>

<?php elseif ($order["payment_status"] === "Pending"): ?>

    <p>
        Your order is awaiting payment. No payment has been confirmed yet.
    </p>

    <?php if ($order["status"] !== "Cancelled"): ?>

        <p>
            <a href="payment.php?id=<?php echo (int)$order["id"]; ?>">
                Pay Now
            </a>
        </p>

    <?php else: ?>

        <p>
            This order has been cancelled and cannot be paid.
        </p>

    <?php endif; ?>

<?php elseif ($order["payment_status"] === "Failed"): ?>
    <p>Payment was unsuccessful. Please try again.</p>

    <?php if ($order["status"] !== "Cancelled"): ?>

        <p>
            <a href="payment.php?id=<?php echo (int)$order["id"]; ?>">
                Pay Now
            </a>
        </p>

    <?php endif; ?>

<?php elseif ($order["payment_status"] === "Refunded"): ?>
    <p>This order's payment has been refunded.</p>
        
<?php elseif ($order["payment_status"] === "Cancelled"): ?>
    <p>This order has been cancelled.</p>

<?php endif; ?>


    <p>

        <a href="orders.php">
            Back to My Orders
        </a>

    </p>


</main>

</body>

</html>