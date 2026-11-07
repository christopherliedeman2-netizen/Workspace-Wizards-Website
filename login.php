<?php

session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

$message = "";
$email = "";
$showResendVerification = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {

        $message = "Enter a valid email address and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role, email_verified
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {

            if ((int)$user["email_verified"] !== 1) {

                $message = "Your email address has not been verified. Please check your inbox or request a new verification email.";
                $showResendVerification = true;

            } else {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_role"] = $user["role"];

                header("Location: products.php");
                exit;
            }

        } else {

            $message = "Incorrect email or password.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - WorkspaceWizards</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
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

    <h2>Log In</h2>

    <?php if ($message !== ""): ?>
        <p role="alert">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <?php echo csrf_field(); ?>

        <label for="email">Email Address</label><br>

        <input
            type="email"
            id="email"
            name="email"
            value="<?php echo htmlspecialchars($email); ?>"
            required
        >

        <br><br>

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="button" id="togglePassword">
            Show password
        </button>

        <br><br>

        <button type="submit">Log In</button>

        <p class="forgot-password-link">
            <a href="forgot_password.php">Forgot Password?</a>
        </p>

        <?php if ($showResendVerification): ?>

            <p class="resend-verification-link">
                <a href="resend_verification.php">
                    Resend Verification Email
                </a>
            </p>

        <?php endif; ?>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</main>

<script>

const passwordField = document.getElementById("password");
const toggleButton = document.getElementById("togglePassword");

toggleButton.addEventListener("click", function () {

    if (passwordField.type === "password") {

        passwordField.type = "text";
        toggleButton.textContent = "Hide password";

    } else {

        passwordField.type = "password";
        toggleButton.textContent = "Show password";

    }

});

</script>

</body>
</html>
