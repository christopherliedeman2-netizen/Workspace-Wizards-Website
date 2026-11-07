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

$error = "";
$message = "";

// Retrieve pending orders only.
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
     WHERE o.status = 'Pending'
     ORDER BY o.created_at ASC"
);

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pending Orders | WorkspaceWizards</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-pending-orders.css">
</head>

<body>

<header>
    <h1>Pending Orders</h1>

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

    <h2>Orders Awaiting Processing</h2>

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
                class="product-card"
                style="margin-bottom: 25px;"
            >

                <h3>
                    Order #<?php echo (int)$order["id"]; ?>
                </h3>

                <p><strong>Order #<?php echo (int)$order["id"]; ?></strong></p>
				<p>Customer: <?php echo htmlspecialchars($order["customer_name"]); ?></p>
				<p>Email: <?php echo htmlspecialchars($order["customer_email"]); ?></p>
				<p>Date: <?php echo htmlspecialchars($order["created_at"]); ?></p>
				<p>Total: R<?php echo number_format((float)$order["total"], 2); ?></p>
				<p>Payment Status: <?php echo htmlspecialchars($order["payment_status"]); ?></p>
				<p>Payment Method: <?php echo htmlspecialchars($order["payment_method"]); ?></p>

				<a href="view_order.php?id=<?php echo (int)$order["id"]; ?>">
				View Order Details
				</a>

               
            </section>

        <?php endwhile; ?>

    <?php else: ?>

        <p>There are currently no pending orders.</p>

    <?php endif; ?>

</main>

</body>
</html>