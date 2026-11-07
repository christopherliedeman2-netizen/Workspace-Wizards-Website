<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";


$product_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($product_id === false || $product_id === null) {
    header("Location: products.php");
    exit;
}



$sql = "SELECT * FROM products WHERE id = ? AND is_active = 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Product query preparation failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to load this product right now.");
}

$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit("Product not found.");
}

$product = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($product['name']); ?> - WorkspaceWizards
    </title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/product.css">
</head>

<body>

<header>
    <h1>WorkspaceWizards</h1>

        <nav>
        <a href="home.php">Home</a>
        <a href="products.php">Products</a>
        
        <?php if (isset($_SESSION["user_id"])): ?>

            <?php if (
                isset($_SESSION["user_role"]) &&
                $_SESSION["user_role"] === "admin"
            ): ?>
                <a href="admin/index.php">Admin Dashboard</a>
                <a href="account.php">My Account</a>

            <?php else: ?>
                <a href="orders.php">My Orders</a>
                <a href="cart.php">Cart</a>
                <a href="account.php">My Account</a>

            <?php endif; ?>


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

    <a href="products.php">Back to Products</a>

    <div class="product-details">

        <div class="product-image">
            <?php if (!empty($product['image'])): ?>

                <img
                    src="images/<?php echo htmlspecialchars($product['image']); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                >

            <?php else: ?>

                <p>No image available</p>

            <?php endif; ?>
        </div>

        <div class="product-information">

            <p>
                <?php echo htmlspecialchars($product['category']); ?>
            </p>

            <h2>
                <?php echo htmlspecialchars($product['name']); ?>
            </h2>

            <p>
                <?php echo htmlspecialchars($product['description']); ?>
            </p>

            <h3>
                R<?php echo number_format($product['price'], 2); ?>
            </h3>

            <p>
                <?php if ($product['stock'] > 0): ?>
                    In Stock: <?php echo $product['stock']; ?>
                <?php else: ?>
                    Out of Stock
                <?php endif; ?>
            </p>

            
<?php
if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
):
?>            
  <?php if ($product['stock'] > 0): ?>

    <?php if (isset($_SESSION["user_id"])): ?>

        <form action="cart.php" method="POST">
            <?php echo csrf_field(); ?>

            <input
                type="hidden"
                name="product_id"
                value="<?php echo (int)$product['id']; ?>"
            >

            <label for="quantity">Quantity:</label>

            <input
                type="number"
                id="quantity"
                name="quantity"
                value="1"
                min="1"
                max="<?php echo (int)$product['stock']; ?>"
            >

            <button type="submit" name="add_to_cart">
                Add to Cart
            </button>
        </form>

    <?php else: ?>

        
        <p>You must be logged in to add products to your cart.</p>

        <button
            type="button"
            class="login-to-buy-button"
            onclick="window.location.href='login.php';"
        >
            Login to Add to Cart
        </button>


    <?php endif; ?>
	

    <?php else: ?>

        <button disabled>
            Out of Stock
        </button>

    <?php endif; ?>

<?php endif; ?>

        </div>

    </div>

</main>

</body>
</html>