<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


$status_filter = $_GET["status"] ?? "All";

$allowed_statuses = ["All", "Pending", "Completed", "Cancelled"];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "All";
}

if ($status_filter === "All") {
    $stmt = $conn->prepare(
        "SELECT id, total, status, payment_status, created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC"
    );

    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare(
        "SELECT id, total, status, payment_status, created_at
         FROM orders
         WHERE user_id = ? AND status = ?
         ORDER BY created_at DESC"
    );

    $stmt->bind_param("is", $user_id, $status_filter);
}

$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - WorkspaceWizards</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/orders.css">
</head>
<body>

<header>
    <h1>Workspace Wizards</h1>
<nav>
    <a href="home.php">Home</a>
    <a href="products.php">Products</a>

    <?php if (isset($_SESSION["user_id"])): ?>

        <a href="orders.php">My Orders</a>
        <a href="cart.php">Cart</a>
        <a href="account.php">My Account</a>

        <form method="POST" action="logout.php">
            <?php echo csrf_field(); ?>
            <button type="submit">Logout</button>
        </form>

    <?php else: ?>

        <a href="login.php">Login</a>
        <a href="register.php">Register</a>

    <?php endif; ?>
</nav>
</header>

<main>
    <h2>My Orders</h2>
    
    
<form method="GET" action="orders.php" class="order-filter">
    <label for="status">Filter by status:</label>

    <select name="status" id="status">
        <option value="All"
            <?php echo $status_filter === "All" ? "selected" : ""; ?>>
            All Orders
        </option>

        <option value="Pending"
            <?php echo $status_filter === "Pending" ? "selected" : ""; ?>>
            Pending
        </option>

        <option value="Completed"
            <?php echo $status_filter === "Completed" ? "selected" : ""; ?>>
            Completed
        </option>

        <option value="Cancelled"
            <?php echo $status_filter === "Cancelled" ? "selected" : ""; ?>>
            Cancelled
        </option>
    </select>

    <button type="submit">Apply Filter</button>
</form>

		<?php if ($orders->num_rows > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>Order Number</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Order Status</th>
                    <th>Payment Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo (int) $order["id"]; ?></td>
                        <td><?php echo htmlspecialchars($order["created_at"]); ?></td>
                        <td>R<?php echo number_format($order["total"], 2); ?></td>
                        <td><?php echo htmlspecialchars($order["status"]); ?></td>
                        <td><?php echo htmlspecialchars($order["payment_status"]); ?></td>
                        <td>
                            <a href="order_confirmation.php?id=<?php echo (int) $order["id"]; ?>">
                                View Order
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php else: ?>

        <p>No orders found.</p>
        <a href="products.php">Browse Furniture</a>

    <?php endif; ?>
</main>

</body>
</html>

