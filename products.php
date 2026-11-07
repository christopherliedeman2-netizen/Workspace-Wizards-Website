<?php
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

// Preserve a search term supplied through the URL.
$raw_search = $_GET["search"] ?? "";

$search = is_string($raw_search)
    ? trim($raw_search)
    : "";

// Retrieve active products only.
// Product filtering will be handled by JavaScript in the browser.
$result = $conn->query(
    "SELECT *
     FROM products
     WHERE is_active = 1
     ORDER BY created_at DESC"
);

if (!$result) {
    error_log("Product catalogue query failed: " . $conn->error);
    http_response_code(500);
    exit("The product catalogue could not be loaded. Please try again later.");
}

// Collect the products so we can display the category filters
// and then render the product cards.
$products = [];

while ($product = $result->fetch_assoc()) {
    $products[] = $product;
}

// Extract unique categories from active products.
$categories = [];

foreach ($products as $product) {
    $category = trim((string)($product["category"] ?? ""));

    if ($category !== "") {
        $categories[$category] = $category;
    }
}

natcasesort($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Products - WorkspaceWizards</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/products.css">
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

    <h2>Our Furniture</h2>

    <section class="catalogue-filters" aria-label="Filter furniture">

        <form id="productFilterForm" class="search-form">

            <div class="filter-field">
                <label for="productSearch">Search products</label>

                <input
                    type="search"
                    id="productSearch"
                    name="search"
                    placeholder="Search name, description or category..."
                    value="<?php echo htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    autocomplete="off"
                >
            </div>

            <div class="filter-field">
                <label for="categoryFilter">Category</label>

                <select id="categoryFilter" name="category">
                    <option value="">All categories</option>

                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars(
                            $category,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>">
                            <?php echo htmlspecialchars(
                                $category,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="minPrice">Minimum price (R)</label>

                <input
                    type="number"
                    id="minPrice"
                    name="min_price"
                    min="0"
                    step="0.01"
                    placeholder="e.g. 500"
                >
            </div>

            <div class="filter-field">
                <label for="maxPrice">Maximum price (R)</label>

                <input
                    type="number"
                    id="maxPrice"
                    name="max_price"
                    min="0"
                    step="0.01"
                    placeholder="e.g. 5000"
                >
            </div>

            <div class="filter-actions">
                <button type="submit">Apply filters</button>
                <button type="reset" id="resetProductFilters">
                    Clear filters
                </button>
            </div>

        </form>

        <p
            id="filterError"
            class="filter-error"
            role="alert"
            hidden
        ></p>

        <p
            id="productResultsCount"
            class="results-count"
            aria-live="polite"
        ></p>

    </section>

    
    <div class="product-grid" id="productGrid">

        <?php foreach ($products as $product): ?>

            <?php
            $productName = (string)($product["name"] ?? "");
            $productDescription = (string)($product["description"] ?? "");
            $productCategory = (string)($product["category"] ?? "");
            $productPrice = (float)$product["price"];
            $productId = (int)$product["id"];
            $productStock = (int)$product["stock"];
            ?>

            <div
                class="product-card"
                data-name="<?php echo htmlspecialchars($productName, ENT_QUOTES, "UTF-8"); ?>"
                data-description="<?php echo htmlspecialchars($productDescription, ENT_QUOTES, "UTF-8"); ?>"
                data-category="<?php echo htmlspecialchars($productCategory, ENT_QUOTES, "UTF-8"); ?>"
                data-price="<?php echo htmlspecialchars((string)$productPrice, ENT_QUOTES, "UTF-8"); ?>"
            >

                <div class="product-image">
                    <?php if (!empty($product["image"])): ?>
                        <img
                            src="images/<?php echo htmlspecialchars($product["image"], ENT_QUOTES, "UTF-8"); ?>"
                            alt="<?php echo htmlspecialchars($productName, ENT_QUOTES, "UTF-8"); ?>"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <p>No image available</p>
                    <?php endif; ?>
                </div>

                <h3>
                    <?php echo htmlspecialchars($productName, ENT_QUOTES, "UTF-8"); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($productDescription, ENT_QUOTES, "UTF-8"); ?>
                </p>

                <p class="price">
                    R<?php echo number_format($productPrice, 2); ?>
                </p>

                <p>Stock: <?php echo $productStock; ?></p>

                <a href="product.php?id=<?php echo $productId; ?>">
                    View Product
                </a>

            </div>

    <?php endforeach; ?>

</div>

    <p id="noProductsMessage" class="no-products" hidden>
        No products match your filters. Try changing your search or clearing
        the filters.
    </p>

</main>

<script src="js/products.js" defer></script>

</body>
</html>
