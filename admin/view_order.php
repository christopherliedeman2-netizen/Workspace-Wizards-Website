<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";

// Only administrators may access this page.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

// Validate order ID.
$order_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($order_id === false || $order_id === null) {
    header("Location: pending_orders.php");
    exit;
}

// Retrieve the order and customer details.
$stmt = $conn->prepare(
    "SELECT
        o.id,
        o.user_id,
        o.total,
        o.status,
        o.payment_status,
        o.payment_method,
        o.payment_reference,
        o.delivery_address,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email
     FROM orders o
     INNER JOIN users u ON o.user_id = u.id
     WHERE o.id = ?"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();

$order_result = $stmt->get_result();

if ($order_result->num_rows !== 1) {
    header("Location: pending_orders.php");
    exit;
}

$order = $order_result->fetch_assoc();

$stmt->close();

// Retrieve all items belonging to this order.
$stmt = $conn->prepare(
    "SELECT
        oi.id,
        oi.product_id,
        oi.quantity,
        oi.price,
        p.name AS product_name
     FROM order_items oi
     INNER JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();

$items_result = $stmt->get_result();

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Order #<?php echo (int)$order["id"]; ?> | WorkspaceWizards</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-view-order.css">
</head>

<body>

<header>

    <h1>
        Order #<?php echo (int)$order["id"]; ?>
    </h1>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="products.php">Manage Products</a>
        <a href="orders.php">Manage Orders</a>
        <a href="../products.php">View Store</a>

        <form method="POST" action="../logout.php">
            <?php echo csrf_field(); ?>
            <button type="submit">Logout</button>
        </form>
    </nav>

</header>

<main>

    <h2>Order Details</h2>

    <!-- Customer Information -->
    <section
        class="product-card"
        style="margin-bottom: 25px;"
    >

        <h3>Customer Information</h3>

        <p>
            <strong>Name:</strong>
            <?php
            echo htmlspecialchars(
                $order["customer_name"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?php
            echo htmlspecialchars(
                $order["customer_email"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

    </section>


    <!-- Order Information -->
    <section
        class="product-card"
        style="margin-bottom: 25px;"
    >

        <h3>Order Information</h3>

        <p>
            <strong>Order Number:</strong>
            #<?php echo (int)$order["id"]; ?>
        </p>

        <p>
            <strong>Order Date:</strong>
            <?php
            echo htmlspecialchars(
                $order["created_at"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Order Status:</strong>
            <?php
            echo htmlspecialchars(
                $order["status"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Payment Status:</strong>
            <?php
            echo htmlspecialchars(
                $order["payment_status"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <p>
            <strong>Payment Method:</strong>
         Payfast
        </p>

        <?php if (!empty($order["payment_reference"])): ?>

            <p>
                <strong>Payment Reference:</strong>
                <?php
                echo htmlspecialchars(
                    $order["payment_reference"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </p>

        <?php endif; ?>

    </section>


    <!-- Delivery Information -->
    <section
        class="product-card"
        style="margin-bottom: 25px;"
    >

        <h3>Delivery Address</h3>

        <p>
            <?php
            echo nl2br(
                htmlspecialchars(
                    $order["delivery_address"],
                    ENT_QUOTES,
                    "UTF-8"
                )
            );
            ?>
        </p>

    </section>


    <!-- Order Items -->
    <section
        class="product-card"
        style="margin-bottom: 25px;"
    >

        <h3>Items Ordered</h3>

        <?php if ($items_result->num_rows > 0): ?>

            <?php while ($item = $items_result->fetch_assoc()): ?>

                <div
                    style="
                        border-bottom: 1px solid #ddd;
                        padding: 15px 0;
                    "
                >

                    <h4>
                        <?php
                        echo htmlspecialchars(
                            $item["product_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </h4>

                    <p>
                        <strong>Quantity:</strong>
                        <?php echo (int)$item["quantity"]; ?>
                    </p>

                    <p>
                        <strong>Price per item:</strong>
                        R<?php
                        echo number_format(
                            (float)$item["price"],
                            2
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Subtotal:</strong>
                        R<?php
                        echo number_format(
                            (float)$item["price"] *
                            (int)$item["quantity"],
                            2
                        );
                        ?>
                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No items were found for this order.</p>

        <?php endif; ?>

    </section>


    <!-- Order Total -->
    <section
        class="product-card"
        style="margin-bottom: 25px;"
    >

        <h3>
            Total:
            R<?php
            echo number_format(
                (float)$order["total"],
                2
            );
            ?>
        </h3>

    </section>


    <p>
        <a href="../admin/orders.php">
            Back to Order Management
        </a>
    </p>

</main>

</body>
</html>

