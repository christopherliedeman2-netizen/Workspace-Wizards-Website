<?php
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

// Retrieve a small selection of products for the homepage.
$stmt = $conn->prepare(
    "SELECT id, name, description, price, image
     FROM products
     ORDER BY id DESC
     LIMIT 4"
);

$stmt->execute();
$featuredProducts = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Workspace Wizards | Furniture for Your Workspace</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/home.css">
</head>

<body>

<header>
    <h1>Workspace Wizards</h1>

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

        <section class="home-intro">
            <h2>Welcome to Workspace Wizards</h2>

            <p>
                Workspace Wizards makes finding the right furniture for your workspace simple.
                We offer a selection of practical, stylish and affordable furniture designed to help you create a comfortable and productive environment.
                Whether you're furnishing a home office or upgrading your workplace, we're here to help you find the right fit.
            </p>
            
            <a href="products.php" class="browse-products-btn">
                Browse All Products
            </a>
        </section>


    <section>
        <h2>Featured Furniture</h2>

        <?php if ($featuredProducts->num_rows > 0): ?>

            <?php while ($product = $featuredProducts->fetch_assoc()): ?>

                <article class="product-card">

                    <?php if (!empty($product["image"])): ?>
                        <img
                            src="images/<?php echo htmlspecialchars(
                                $product["image"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                            alt="<?php echo htmlspecialchars(
                                $product["name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >
                    <?php endif; ?>

                    <h3>
                        <?php echo htmlspecialchars(
                            $product["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </h3>

                    <p>
                        <?php echo htmlspecialchars(
                            $product["description"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <p>
                        <strong>
                            R<?php echo number_format(
                                (float)$product["price"],
                                2
                            ); ?>
                        </strong>
                    </p>

                    <a href="product.php?id=<?php echo (int)$product["id"]; ?>">
                        View Product
                    </a>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No products are currently available.</p>

        <?php endif; ?>
    </section>


        <section class="home-call-to-action">
            <h2>Build a Better Workspace</h2>

            <p>
                Discover quality furniture designed to make your workspace
                comfortable, functional and professional.
            </p>

            <a href="products.php">Browse All Products</a>
        </section>

</main>
    
    <div class="footer-support">
    <h3>Customer Support</h3>
    <p>
        Need assistance?
        <a href="mailto:workspacewizardssupport@gmail.com">workspacewizardssupport@gmail.com</a>
    </p>
	</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Workspace Wizards. All rights reserved.</p>
</footer>

</body>
</html>