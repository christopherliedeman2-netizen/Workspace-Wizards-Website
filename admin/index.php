
<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";

// Only allow logged-in administrators.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

// Count products.
$productResult = $conn->query(
    "SELECT COUNT(*) AS total FROM products"
);
$productCount = $productResult->fetch_assoc()["total"];

// Count customers.
$customerResult = $conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"
);
$customerCount = $customerResult->fetch_assoc()["total"];

// Count orders.
$orderResult = $conn->query(
    "SELECT COUNT(*) AS total FROM orders"
);
$orderCount = $orderResult->fetch_assoc()["total"];

// Count orders awaiting payment.
$pendingResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE payment_status = 'Pending'"
);

$pendingPayments = $pendingResult->fetch_assoc()["total"];


$readyToShipResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE status = 'Processing'
       AND payment_status = 'Paid'"
);

$readyToShipCount = (int)$readyToShipResult->fetch_assoc()["total"];

$shippedResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE status = 'Shipped'
       AND payment_status = 'Paid'"
);

$shippedCount = (int)$shippedResult->fetch_assoc()["total"];


/* Get the five most recent orders. */

$recentOrdersResult = $conn->query(
    "SELECT
        o.id,
        o.total,
        o.status,
        o.payment_status,
        o.created_at,
        u.name AS customer_name
     FROM orders o
     INNER JOIN users u ON o.user_id = u.id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 5"
);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | WorkspaceWizards</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

<header>
    <h1>WorkspaceWizards Admin</h1>

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
    <h2>Admin Dashboard</h2>

    <p>
        Welcome,
        <?php echo htmlspecialchars(
            $_SESSION["user_name"] ?? "Administrator",
            ENT_QUOTES,
            "UTF-8"
        ); ?>.
    </p>

    <div class="admin-dashboard-grid">

        <div class="product-card">
            <h3>Total Products</h3>
            <p class="price"><?php echo (int)$productCount; ?></p>
            <a href="products.php">Manage Products</a>
        </div>
		
        <div class="product-card">
            <h3>Ready to Ship</h3>
            <p class="price"><?php echo $readyToShipCount; ?></p>
            <a href="ready_to_ship.php">Review Orders</a>
        </div>

        <div class="product-card">
            <h3>Total Orders</h3>
            <p class="price"><?php echo (int)$orderCount; ?></p>
            <a href="orders.php">Manage Orders</a>
        </div>
		
		<div class="product-card">
            <h3>Total Customers</h3>
            <p class="price"><?php echo (int)$customerCount; ?></p>
        </div>
        
        <div class="product-card">
            <h3>Pending Payments</h3>
            <p class="price"><?php echo (int)$pendingPayments; ?></p>
        </div>

    </div>
    
    
<section class="admin-recent-orders">
    <div class="admin-section-heading">
        <h2>Recent Orders</h2>
        <a href="orders.php">View All Orders</a>
    </div>

    <?php if ($recentOrdersResult->num_rows > 0): ?>

        <div class="admin-recent-orders-list">

            <?php while ($order = $recentOrdersResult->fetch_assoc()): ?>

                <article class="admin-recent-order">

                    <div class="admin-recent-order-main">
                        <h3>
                            Order #<?php echo (int)$order["id"]; ?>
                        </h3>

                        <p>
                            <?php echo htmlspecialchars(
                                $order["customer_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </p>

                        <p class="admin-recent-order-date">
                            <?php echo htmlspecialchars(
                                date("d M Y, H:i", strtotime($order["created_at"])),
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </p>
                    </div>

                    <div class="admin-recent-order-details">
                        <p class="admin-recent-order-total">
                            R<?php echo number_format((float)$order["total"], 2); ?>
                        </p>

                        <span class="admin-order-badge payment-<?php
                            echo strtolower(htmlspecialchars(
                                $order["payment_status"],
                                ENT_QUOTES,
                                "UTF-8"
                            ));
                        ?>">
                            Payment:
                            <?php echo htmlspecialchars(
                                $order["payment_status"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                        <span class="admin-order-badge status-<?php
                            echo strtolower(htmlspecialchars(
                                $order["status"],
                                ENT_QUOTES,
                                "UTF-8"
                            ));
                        ?>">
                            <?php echo htmlspecialchars(
                                $order["status"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>
                    </div>

                    <div class="admin-recent-order-action">
                        <a href="view_order.php?id=<?php echo (int)$order["id"]; ?>">
                            View Order
                        </a>
                    </div>

                </article>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <p class="admin-no-recent-orders">
            No orders have been placed yet.
        </p>

    <?php endif; ?>
</section>

    
</main>

</body>
</html>