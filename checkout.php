<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

if (empty($_SESSION["cart"])) {
    header("Location: cart.php");
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


$message = "";
$delivery_address = "";

function loadCheckoutCart(mysqli $conn, array $cart): array {
    $items = [];
    $total = 0;

    $stmt = $conn->prepare(
        "SELECT id, name, price, stock FROM products WHERE id = ? AND is_active = 1"
    );

    foreach ($cart as $id => $quantity) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if (!$id || !$quantity || $quantity < 1) {
            $stmt->close();
            throw new RuntimeException(
                "Your cart contains an invalid item. Please review it."
            );
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            $stmt->close();
            throw new RuntimeException(
                "A product in your cart is no longer available."
            );
        }

        if ((int)$product["stock"] < $quantity) {
            $stmt->close();
            throw new RuntimeException(
                "Insufficient stock for " . $product["name"] .
                ". Please update your cart."
            );
        }

        $product["quantity"] = $quantity;
        $product["subtotal"] = $quantity * (float)$product["price"];

        $total += $product["subtotal"];
        $items[] = $product;
    }

    $stmt->close();

    return ["items" => $items, "total" => $total];
}

try {
    $cart_data = loadCheckoutCart($conn, $_SESSION["cart"]);
} catch (RuntimeException $e) {
    $message = $e->getMessage();
    $cart_data = ["items" => [], "total" => 0];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $message === "") {
    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    
	
		$raw_address = $_POST["delivery_address"] ?? "";

		if (!is_string($raw_address)) {
			$message = "Please enter a valid delivery address.";
		} else {
			$delivery_address = trim($raw_address);

			if (
				$delivery_address === "" ||
				strlen($delivery_address) > 2000
			) {
				$message = "Please enter a valid delivery address.";
			}
		}

		if ($message === "") {
			$conn->begin_transaction();



        try {
            // Lock products and check their current stock.
            $locked_items = [];
            $total = 0;

            $stmt = $conn->prepare(
                "SELECT id, name, price, stock
                 FROM products
                 WHERE id = ? AND is_active = 1
                 FOR UPDATE"
            );

            foreach ($_SESSION["cart"] as $id => $quantity) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

                if (!$id || !$quantity || $quantity < 1) {
                    throw new RuntimeException("Invalid cart quantity.");
                }

                $stmt->bind_param("i", $id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();

                if (
                    !$product ||
                    (int)$product["stock"] < $quantity
                ) {
                    throw new RuntimeException(
                        "A product is unavailable or no longer has enough stock. Please review your cart."
                    );
                }

                $product["quantity"] = $quantity;
                $locked_items[] = $product;
                $total += $quantity * (float)$product["price"];
            }

            $stmt->close();

            if (empty($locked_items)) {
                throw new RuntimeException("Your cart is empty.");
            }

            // Create an unpaid order.
            $stmt = $conn->prepare(
                "INSERT INTO orders
                    (user_id, total, status, payment_status, delivery_address)
                 VALUES (?, ?, 'Pending', 'Pending', ?)"
            );

            $stmt->bind_param(
                "ids",
                $user_id,
                $total,
                $delivery_address
            );
            $stmt->execute();

            $order_id = $conn->insert_id;
            $stmt->close();

            // Save the ordered products WITHOUT reducing stock.
            $item_stmt = $conn->prepare(
                "INSERT INTO order_items
                    (order_id, product_id, quantity, price)
                 VALUES (?, ?, ?, ?)"
            );

            foreach ($locked_items as $item) {
                $product_id = (int)$item["id"];
                $quantity = (int)$item["quantity"];
                $price = (float)$item["price"];

                $item_stmt->bind_param(
                    "iiid",
                    $order_id,
                    $product_id,
                    $quantity,
                    $price
                );

                $item_stmt->execute();
            }

            $item_stmt->close();

            $conn->commit();

            // Clear the cart after the order is saved.
            $_SESSION["cart"] = [];

            header("Location: order_confirmation.php?id=" . $order_id);
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            if ($e instanceof RuntimeException) {
                $message = $e->getMessage();
            } else {
                error_log("Checkout error: " . $e->getMessage());
                $message = "We could not create your order. Please try again.";
            }
        }
    }
}

if (!empty($_SESSION["cart"])) {
    try {
        $cart_data = loadCheckoutCart($conn, $_SESSION["cart"]);
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $cart_data = ["items" => [], "total" => 0];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - WorkspaceWizards</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/checkout.css">
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
    <h2>Checkout</h2>

    <?php if ($message !== ""): ?>
        <div role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cart_data["items"])): ?>

        <h3>Order Summary</h3>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_data["items"] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item["name"]); ?></td>
                        <td>R<?php echo number_format($item["price"], 2); ?></td>
                        <td><?php echo (int)$item["quantity"]; ?></td>
                        <td>R<?php echo number_format($item["subtotal"], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Total: R<?php echo number_format($cart_data["total"], 2); ?></h3>

        <form method="POST" action="checkout.php">
		<?php echo csrf_field(); ?>
            <label for="delivery_address">Delivery Address</label><br>
            <textarea
                id="delivery_address"
                name="delivery_address"
                rows="4"
                maxlength="2000"
                required
            ><?php echo htmlspecialchars($delivery_address); ?></textarea>
            <br><br>

            <p>Payment will be completed in the next step.</p>

            <div class="checkout-actions">
                <a href="cart.php" class="cancel-checkout-button">Back to Cart</a>
                <button type="submit">Place Order</button>
            </div>
        </form>

    <?php else: ?>
        <p>Your cart is empty or needs updating.</p>
        <a href="cart.php">Return to Cart</a>
    <?php endif; ?>
</main>

</body>
</html>