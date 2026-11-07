<?php
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/email_veri_resource.php";

$message = "";
$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || strlen($name) > 100) {
        $message = "Please enter a name of no more than 100 characters.";

    } elseif (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $message = "Please enter a valid email address.";

    } elseif (
        strlen($password) < 12 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        $message = "Password requirements not met: use at least 12 characters, including an uppercase letter, a lowercase letter, a number, and a special character.";

    } elseif ($password !== $confirm_password) {
        $message = "Your passwords do not match.";

    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $message = "An account with this email already exists.";

        } else {
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users
                    (name, email, password, email_verified)
                 VALUES (?, ?, ?, 0)"
            );

            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $hashed_password
            );

            if ($stmt->execute()) {
                $user_id = (int) $stmt->insert_id;
                $stmt->close();

                try {
                    $verification = createEmailVerificationToken(
                        $user_id
                    );

                    $token_hash = $verification["hash"];

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET email_verification_token_hash = ?
                         WHERE id = ? AND email_verified = 0"
                    );

                    $stmt->bind_param(
                        "si",
                        $token_hash,
                        $user_id
                    );

                    $stored = $stmt->execute()
                        && $stmt->affected_rows === 1;

                    $stmt->close();

                    if (
                        $stored &&
                        sendVerificationEmail(
                            $email,
                            $name,
                            $verification["token"]
                        )
                    ) {
                        $message = "Your account has been created. Please check your email for a verification link before logging in.";
                        $name = "";
                        $email = "";

                    } else {
                        $message = "Your account was created, but we could not send the verification email. Please contact support before trying to register again.";
                    }

                
} catch (Throwable $e) {
    $debugMessage = date("Y-m-d H:i:s")
        . " | Verification error: "
        . get_class($e)
        . " | "
        . $e->getMessage()
        . PHP_EOL;

    @file_put_contents(
        __DIR__ . "/verification_debug.txt",
        $debugMessage,
        FILE_APPEND | LOCK_EX
    );

    error_log(
        "Workspace Wizards verification setup failed: "
        . get_class($e)
        . " | "
        . $e->getMessage()
    );

    $message = "Your account was created, but verification could not be completed. Please contact support.";
}

            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WorkspaceWizards</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>

<header>
    <h1>WorkspaceWizards</h1>
    <nav>
        <a href="home.php">Home</a>
        <a href="products.php">Products</a>
        <a href="cart.php">Cart</a>
		<a href="account.php">My Account</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<main>
    <h2>Create an Account</h2>

    <?php if ($message !== ""): ?>
    <div
        role="alert"
        style="padding: 12px; margin: 15px 0; border: 1px solid #b91c1c;
               background: #fef2f2; color: #991b1b; border-radius: 5px;"
    >
        <?php echo htmlspecialchars($message); ?>
    </div>
	<?php endif; ?>

    <form method="POST" action="register.php">
	<?php echo csrf_field(); ?>
        <label for="name">Full Name</label><br>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            value="<?php echo htmlspecialchars($name); ?>"
            required
        ><br><br>

        <label for="email">Email Address</label><br>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            value="<?php echo htmlspecialchars($email); ?>"
            required
        ><br><br>


		<label for="password">Password</label><br>
		<input
			type="password"
			id="password"
			name="password"
			minlength="12"
			pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}"
			title="At least 12 characters, including uppercase, lowercase, a number, and a special character."
			autocomplete="new-password"
			required
		>
		<button type="button" onclick="togglePassword('password', this)">
			Show password
		</button>
		<br><br>



       
		<label for="confirm_password">Confirm Password</label><br>
		<input
			type="password"
			id="confirm_password"
			name="confirm_password"
			minlength="12"
			autocomplete="new-password"
			required
		>
		<button type="button" onclick="togglePassword('confirm_password', this)">
			Show password
		</button>
		<br><br>

        <button type="submit">Create Account</button>
    </form>

    <p>Already have an account? <a href="login.php">Log in</a></p>
</main>

<script>
function togglePassword(fieldId, button) {
    const passwordField = document.getElementById(fieldId);

    if (passwordField.type === "password") {
        passwordField.type = "text";
        button.textContent = "Hide password";
    } else {
        passwordField.type = "password";
        button.textContent = "Show password";
    }
}
</script>


</body>
</html>