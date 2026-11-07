
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

$error = "";

// Validate the product ID from the URL.
$productId = filter_var(
    $_GET["id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($productId === false || $productId === null || $productId < 1) {
    header("Location: products.php");
    exit;
}

// Update the product when the form is submitted.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_product"])) {
	if (!csrf_validate()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
	}
	
	
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $priceInput = $_POST["price"] ?? "";
    $category = trim($_POST["category"] ?? "");
	$image = trim($_POST["image"] ?? "");
    $stockInput = $_POST["stock"] ?? "";
	
	$validImage = $image === "" ||
    preg_match('/\A[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp|gif)\z/i', $image);

    $validPrice = is_scalar($priceInput)
        && is_numeric($priceInput)
        && is_finite((float)$priceInput)
        && (float)$priceInput > 0
        && (float)$priceInput <= 99999999.99;

    $validStock = filter_var(
        $stockInput,
        FILTER_VALIDATE_INT
    );

    if (
        $name === "" ||
        strlen($name) > 150 ||
		strlen($description) > 5000 ||
        $category === "" ||
        strlen($category) > 100 ||
        strlen($image) > 255 ||
		strlen($image) > 255 ||
		!$validImage ||
		!$validPrice ||
        !$validPrice ||
        $validStock === false ||
        $validStock === null ||
        $validStock < 0
    ) {
        $error = "Please enter a valid product name, category, price, image file and stock quantity.";
    } else {
        $price = (float)$priceInput;
        $stock = (int)$validStock;

        $stmt = $conn->prepare(
            "UPDATE products
             SET name = ?, description = ?, price = ?,
                 category = ?, image = ?, stock = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssdssii",
            $name,
            $description,
            $price,
            $category,
            $image,
            $stock,
            $productId
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: products.php?updated=1");
            exit;
        }

        $error = "The product could not be updated. Please try again.";
        $stmt->close();
    }
}

// Retrieve the latest product details.
$stmt = $conn->prepare(
    "SELECT name, description, price, category, image, stock
     FROM products
     WHERE id = ?"
);
$stmt->bind_param("i", $productId);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: products.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | Workspace Wizards</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-edit-product.css">
</head>
<body>

<header>
    <h1>Edit Product</h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="products.php">Manage Products</a>
		<a href="../products.php">View Store</a>
        <form method="POST" action="../logout.php">
			<?php echo csrf_field(); ?>
			<button type="submit">Logout</button>
		</form>
    </nav>
</header>

<main>
    <h2>
        Editing:
        <?php echo htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8"); ?>
    </h2>

    <?php if ($error !== ""): ?>
        <p style="color: red;">
            <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php endif; ?>

    <form method="POST" class="product-card">
	<?php echo csrf_field(); ?>
        <label for="name">Product name</label><br>
        <input type="text" id="name" name="name"
               maxlength="150" required
               value="<?php echo htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8"); ?>">
        <br><br>

        <label for="description">Description</label><br>
		
		<textarea id="description" name="description"
				  rows="4" maxlength="10000"
				  style="width: 100%;"><?php
		?></textarea><?php
            echo htmlspecialchars($product["description"] ?? "", ENT_QUOTES, "UTF-8");
        ?></textarea>
		
        <br><br>

        <label for="price">Price (R)</label><br>
        <input type="number" id="price" name="price"
               min="0.01" max="99999999.99" step="0.01" required
               value="<?php echo htmlspecialchars((string)$product["price"], ENT_QUOTES, "UTF-8"); ?>">
        <br><br>

        <label for="category">Category</label><br>
        <input type="text" id="category" name="category"
               maxlength="100" required
               value="<?php echo htmlspecialchars($product["category"], ENT_QUOTES, "UTF-8"); ?>">
        <br><br>

        <label for="image">Image filename</label><br>
        <input type="text" id="image" name="image"
               maxlength="255"
               value="<?php echo htmlspecialchars($product["image"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
        <br><br>

        <label for="stock">Stock quantity</label><br>
        <input type="number" id="stock" name="stock"
               min="0" step="1" required
               value="<?php echo (int)$product["stock"]; ?>">
        <br><br>

        <button type="submit" name="update_product">Save Changes</button>
        <a href="products.php">Cancel</a>
    </form>
</main>

</body>
</html>