
<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";


 // Restrict to users logged in
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

// Initialise the cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


$message = "";

// Add a product to the cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
	if (!csrf_validate()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
	}
	
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if (!$product_id || !$quantity || $quantity < 1) {
        $message = "Please select a valid quantity.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        if (!$product) {
            $message = "Product not found.";
        } elseif ($product['stock'] < 1) {
            $message = "This product is out of stock.";
        } else {
            $current_quantity = $_SESSION['cart'][$product_id] ?? 0;
            $new_quantity = $current_quantity + $quantity;

            if ($new_quantity > $product['stock']) {
                $message = "Not enough stock available.";
            } else {
                $_SESSION['cart'][$product_id] = $new_quantity;
                $message = "Product added to your cart.";
            }
        }

        $stmt->close();
    }
}


// Update quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {

    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $quantities = $_POST['quantities'] ?? [];

    if (!is_array($quantities)) {
        $message = "Invalid cart quantities submitted.";
    } else {
        foreach ($quantities as $id => $quantity) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

            if (!$id || !isset($_SESSION['cart'][$id])) {
                continue;
            }

            if ($quantity === false || $quantity < 1) {
                $message = "Please enter a valid quantity.";
                continue;
            }

            $stmt = $conn->prepare(
                "SELECT stock FROM products WHERE id = ? AND is_active = 1"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product || (int)$product['stock'] < 1) {
                unset($_SESSION['cart'][$id]);
                $message = "An unavailable product was removed from your cart.";
                continue;
            }

            $_SESSION['cart'][$id] = min(
                $quantity,
                (int)$product['stock']
            );
        }

        if ($message === "") {
            $message = "Cart updated.";
        }
    }
}



// Remove a product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {

	if (!csrf_validate()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
}
	
    $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if ($id && isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
        $message = "Product removed from cart.";
    }
}

// Fetch current product details and calculate total
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $stmt = $conn->prepare(
        "SELECT id, name, price, stock FROM products WHERE id = ? AND is_active = 1"
    );

    foreach ($_SESSION['cart'] as $id => $quantity) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            unset($_SESSION['cart'][$id]);
            continue;
        }

        // Keep cart quantities within current stock
        $quantity = min((int)$quantity, (int)$product['stock']);

        if ($quantity < 1) {
            unset($_SESSION['cart'][$id]);
            continue;
        }

        $_SESSION['cart'][$id] = $quantity;
        $product['quantity'] = $quantity;
        $product['subtotal'] = $quantity * (float)$product['price'];

        $total += $product['subtotal'];
        $cart_items[] = $product;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Your Cart - Workspace Wizards</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cart.css">
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

        <?php if (
            isset($_SESSION["user_role"]) &&
            $_SESSION["user_role"] === "admin"
        ): ?>
            <a href="admin/index.php">Admin</a>
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
    <h2>Your Shopping Cart</h2>

    <?php if ($message !== ''): ?>
        <p role="status">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <p>Your cart is currently empty.</p>
        <a href="products.php">Browse Furniture</a>
    <?php else: ?>

        <form method="POST" action="cart.php">
		<?php echo csrf_field(); ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>R<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <input
                                    type="number"
                                    name="quantities[<?php echo $item['id']; ?>]"
                                    value="<?php echo $item['quantity']; ?>"
                                    min="1"
                                    max="<?php echo $item['stock']; ?>"
                                >
                            </td>
                            <td>R<?php echo number_format($item['subtotal'], 2); ?></td>
                            <td>
                                <button
                                    type="submit"
                                    name="remove_item"
                                    value="1"
                                    formaction="cart.php"
                                    formmethod="POST"
                                    formnovalidate
                                    onclick="this.form.product_id.value='<?php echo $item['id']; ?>'"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <input type="hidden" name="product_id" value="">

            <p class="price">
                Total: R<?php echo number_format($total, 2); ?>
            </p>

            <button type="submit" name="update_cart">Update Cart</button>
            <a href="products.php">Continue Shopping</a>
            <a href="checkout.php">Proceed to Checkout</a>
        </form>

    <?php endif; ?>
</main>

</body>
</html>