<?php

session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/password_reset_email.php";

$email = "";
$message = "";
$submitted = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate()) {
        http_response_code(403);
        $message = "Your session has expired. Please refresh the page and try again.";

    } else {

        $email = trim($_POST["email"] ?? "");

        if (
            $email === "" ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $message = "Please enter a valid email address.";

        } else {

            $stmt = $conn->prepare(
                "SELECT id, email, email_verified, password
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                error_log("Password reset lookup failed: " . $conn->error);
                $message = "Something went wrong. Please try again later.";

            } else {

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {

                    $user = $result->fetch_assoc();

                    // Only verified accounts can request a password reset.
                    if ((int)$user["email_verified"] === 1) {

                        $config = require __DIR__ . "/includes/smtp2go.php";

                        // Create a temporary token payload.
                        $payload = [
                            "uid" => (int)$user["id"],
                            "exp" => time() + 1800,
                            "nonce" => bin2hex(random_bytes(16))
                        ];

                        $payloadJson = json_encode($payload);

                        if ($payloadJson === false) {

                            error_log("Password reset token JSON encoding failed.");

                        } else {

                            // URL-safe Base64 encoding.
                            $encodedPayload = rtrim(
                                strtr(
                                    base64_encode($payloadJson),
                                    "+/",
                                    "-_"
                                ),
                                "="
                            );

                            /*
                             * Sign the payload together with the current
                             * password hash. Changing the password invalidates
                             * previously generated reset links.
                             */
                            $signature = hash_hmac(
                                "sha256",
                                $encodedPayload . "|" . $user["password"],
                                $config["token_secret"]
                            );

                            $token = $encodedPayload . "." . $signature;

                            $resetUrl =
                                "https://workspacewizards.ifree.page/reset_password_page.php?token="
                                . urlencode($token);

                            // Send the reset email.
                            sendPasswordResetEmail(
                                $user["email"],
                                $resetUrl
                            );
                        }
                    }
                }

                $stmt->close();

                /*
                 * Use the same response whether the account exists,
                 * is unverified, or the email was sent successfully.
                 */
                $submitted = true;
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

    <title>Forgot Password | WorkspaceWizards</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>WorkspaceWizards</h1>

    <nav>
        <a href="login.php">Login</a>
        <a href="products.php">View Store</a>
    </nav>
</header>

<main class="forgot-password-container">

    <h2>Reset Your Password</h2>

    <p>
        Enter the email address associated with your account.
        If it belongs to a verified account, reset instructions
        will be sent to that address.
    </p>

    <?php if ($submitted): ?>

        <div class="forgot-password-message success" role="status">
            <h3>Request Received</h3>

            <p>
                If a verified account exists with that email address,
                password-reset instructions have been sent.
                Please check your inbox.
            </p>
        </div>

        <p>
            <a href="login.php">Return to Login</a>
        </p>

    <?php else: ?>

        <?php if ($message !== ""): ?>
            <div class="forgot-password-message error" role="alert">
                <?php echo htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">

            <?php echo csrf_field(); ?>

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
                placeholder="you@example.com"
                autocomplete="email"
                required
            >

            <button type="submit">
                Send Reset Instructions
            </button>

        </form>

        <p class="forgot-password-back">
            <a href="login.php">Back to Login</a>
        </p>

    <?php endif; ?>

</main>

</body>
</html>
