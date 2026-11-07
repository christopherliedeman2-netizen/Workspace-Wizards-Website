<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";

// Restrict access to administrators.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";
$search = trim($_GET["search"] ?? "");


// Add a product.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_product"])
) {
    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = $_POST["price"] ?? "";
    $category = trim($_POST["category"] ?? "");
    $image = trim($_POST["image"] ?? "");
    $stock = $_POST["stock"] ?? "";

    if (
        $name === "" ||
        strlen($name) > 150 ||
        $category === "" ||
        strlen($category) > 100 ||
        strlen($description) > 10000 ||
        strlen($image) > 255 ||
        !is_numeric($price) ||
        !is_finite((float)$price) ||
        (float)$price <= 0 ||
        filter_var($stock, FILTER_VALIDATE_INT) === false ||
        (int)$stock < 0
    ) {
        $error = "Please enter valid product details, price and stock quantity.";
    } else {
        $price = (float)$price;
        $stock = (int)$stock;

        $stmt = $conn->prepare(
            "INSERT INTO products
                (name, description, price, category, image, stock)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssdssi",
            $name,
            $description,
            $price,
            $category,
            $image,
            $stock
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: products.php?added=1");
            exit;
        } else {
            error_log("Add product error: " . $stmt->error);
            $error = "The product could not be added.";
        }

        $stmt->close();
    }
}



 // Delete a product.
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_product"])
) {
		if (!csrf_validate()) {
		http_response_code(403);
		exit("Invalid security token. Please refresh the page and try again.");
	}
	
    $productId = filter_var(
        $_POST["product_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if (
        $productId === false ||
        $productId === null ||
        $productId < 1
    ) {
        $error = "Invalid product selected.";
    } else {
        // Check whether this product appears in an existing order.
        $checkStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM order_items
             WHERE product_id = ?"
        );

        $checkStmt->bind_param("i", $productId);
        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();
        $orderData = $checkResult->fetch_assoc();

        $checkStmt->close();

        if ((int)$orderData["total"] > 0) {
            $error = "This product cannot be deleted because it is linked to existing orders. You can set its stock to 0 instead to prevent further purchases.";
        } else {
            $deleteStmt = $conn->prepare(
                "DELETE FROM products WHERE id = ?"
            );

            $deleteStmt->bind_param("i", $productId);

            if (
                $deleteStmt->execute()
                && $deleteStmt->affected_rows > 0
            ) {
                $deleteStmt->close();

                header("Location: products.php?deleted=1");
                exit;
            } else {
                $error = "The product could not be deleted. It may no longer exist.";
            }

            $deleteStmt->close();
        }
    }
}



 // Hide or show a product.
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["toggle_visibility"])
) {
    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $productId = filter_var(
        $_POST["product_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    $newStatus = filter_var(
        $_POST["new_status"] ?? "",
        FILTER_VALIDATE_INT
    );

    if (
        $productId === false ||
        $productId === null ||
        $productId < 1 ||
        !in_array($newStatus, [0, 1], true)
    ) {
        $error = "Invalid product visibility request.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE products SET is_active = ? WHERE id = ?"
        );

        $stmt->bind_param("ii", $newStatus, $productId);

        if ($stmt->execute()) {
            $stmt->close();

            header("Location: products.php?visibility_updated=1");
            exit;
        } else {
            error_log("Product visibility update failed: " . $stmt->error);
            $error = "The product visibility could not be updated.";
            $stmt->close();
        }
    }
}

// Retrieve products using the search term and visibility filter.
$visibility = $_GET["visibility"] ?? "all";

if (!in_array($visibility, ["all", "visible", "hidden"], true)) {
    $visibility = "all";
}

$visibilityCondition = "";

if ($visibility === "visible") {
    $visibilityCondition = " AND is_active = 1";
} elseif ($visibility === "hidden") {
    $visibilityCondition = " AND is_active = 0";
}

if ($search !== "") {
    $searchTerm = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT id, name, description, price, category, image, stock, is_active
         FROM products
         WHERE (name LIKE ? OR category LIKE ?)" .
         $visibilityCondition .
         " ORDER BY id DESC"
    );

    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare(
        "SELECT id, name, description, price, category, image, stock, is_active
         FROM products
         WHERE 1 = 1" .
         $visibilityCondition .
         " ORDER BY id DESC"
    );

    $stmt->execute();
    $result = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | WorkspaceWizards</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-products.css">
</head>
<body>

<header>
    <h1>Manage Products</h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="../products.php">View Store</a>
        <form method="POST" action="../logout.php">
			<?php echo csrf_field(); ?>
			<button type="submit">Logout</button>
		</form>
    </nav>
</header>

<main>
    <h2>Add a New Product</h2>

    <?php if ($message !== ""): ?>
        <p style="color: green;">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p style="color: red;">
            <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="products.php" class="product-card">
	<?php echo csrf_field(); ?>
        <label for="name">Product name</label><br>
        <input type="text" id="name" name="name" maxlength="150" required>
        <br><br>

        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="4"
                  style="width: 100%;"></textarea>
        <br><br>

        <label for="price">Price (R)</label><br>
        <input type="number" id="price" name="price"
               min="0.01" step="0.01" required>
        <br><br>

        <label for="category">Category</label><br>
        <input type="text" id="category" name="category"
               maxlength="100" required>
        <br><br>

        <label for="image">Image filename</label><br>
        <input type="text" id="image" name="image" maxlength="255"
               placeholder="e.g. sofa.jpg">
        <br><br>

        <label for="stock">Stock quantity</label><br>
        <input type="number" id="stock" name="stock"
               min="0" step="1" required>
        <br><br>

        <button type="submit" name="add_product">Add Product</button>
    </form>
	
		
    <form method="GET" action="products.php">
        <label for="search">Search products</label><br>
        <input
            type="search"
            id="search"
            name="search"
            placeholder="Search by name or category"
            value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>"
        >

        <label for="visibility">Product visibility</label>
        <select id="visibility" name="visibility">
            <option value="all"
                <?php echo (($_GET["visibility"] ?? "all") === "all") ? "selected" : ""; ?>>
                All Products
            </option>

            <option value="visible"
                <?php echo (($_GET["visibility"] ?? "all") === "visible") ? "selected" : ""; ?>>
                Visible to Customers
            </option>

            <option value="hidden"
                <?php echo (($_GET["visibility"] ?? "all") === "hidden") ? "selected" : ""; ?>>
                Hidden from Customers
            </option>
        </select>

        <button type="submit">Filter Products</button>

        <?php if ($search !== "" || isset($_GET["visibility"])): ?>
            <a href="products.php">Clear Filters</a>
        <?php endif; ?>
    </form>


	<br>

    <h2>Existing Products</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="product-grid">
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="product-card">
                    <h3>
                        <?php echo htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8"); ?>
                    </h3>

                    <p>
                        Category:
                        <?php echo htmlspecialchars($product["category"], ENT_QUOTES, "UTF-8"); ?>
                    </p>

                    <p class="price">
                        R<?php echo number_format((float)$product["price"], 2); ?>
                    </p>

                    <p>
                        Stock: <?php echo (int)$product["stock"]; ?>
                    </p>

                    
                    <p>
                        Visibility:
                        <?php if ((int)$product["is_active"] === 1): ?>
                            <strong>Visible to customers</strong>
                        <?php else: ?>
                            <strong>Hidden from customers</strong>
                        <?php endif; ?>
                    </p>

                    <form method="POST" action="products.php"
                          onsubmit="return confirm('Are you sure you want to change this product visibility?');">
                        <?php echo csrf_field(); ?>

                        <input type="hidden" name="product_id"
                               value="<?php echo (int)$product["id"]; ?>">

                        <input type="hidden" name="new_status"
                               value="<?php echo (int)$product["is_active"] === 1 ? 0 : 1; ?>">

                        <button type="submit" name="toggle_visibility">
                            <?php echo (int)$product["is_active"] === 1 ? "Hide Product" : "Show Product"; ?>
                        </button>
                    </form>

                    
                    <p>
                        Image:
                        <?php echo htmlspecialchars($product["image"] ?? "", ENT_QUOTES, "UTF-8"); ?>
                    </p>

                    <form method="POST" action="products.php"
                          onsubmit="return confirm('Are you sure you want to delete this product?');">
						  <?php echo csrf_field(); ?>
                        <input type="hidden" name="product_id"
                               value="<?php echo (int)$product["id"]; ?>">

                        <button type="submit" name="delete_product">
                            Delete Product
                        </button>
						<a href="edit_product.php?id=<?php echo (int)$product['id']; ?>">Edit Product</a>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No products have been added yet.</p>
    <?php endif; ?>
</main>

</body>
</html>