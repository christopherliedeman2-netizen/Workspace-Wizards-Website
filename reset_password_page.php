
<?php

session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";

/**
 * Validate a signed password-reset token.
 *
 * The token is tied to the user's current password hash.
 * Changing the password therefore invalidates the token.
 */
function validatePasswordResetToken(
    string $token,
    mysqli $conn,
    string $secret
): ?array {

    if ($token === "" || strlen($token) > 2048) {
        return null;
    }

    $parts = explode(".", $token);

    if (count($parts) !== 2) {
        return null;
    }

    [$encodedPayload, $providedSignature] = $parts;

    if (
        $encodedPayload === "" ||
        !preg_match('/^[a-f0-9]{64}$/i', $providedSignature)
    ) {
        return null;
    }

    // Decode the token payload.
    $base64 = strtr($encodedPayload, "-_", "+/");
    $base64 .= str_repeat("=", (4 - strlen($base64) % 4) % 4);

    $payloadJson = base64_decode($base64, true);

    if ($payloadJson === false) {
        return null;
    }

    $payload = json_decode($payloadJson, true);

    if (!is_array($payload)) {
        return null;
    }

    $userId = filter_var(
        $payload["uid"] ?? null,
        FILTER_VALIDATE_INT
    );

    $expiry = filter_var(
        $payload["exp"] ?? null,
        FILTER_VALIDATE_INT
    );

    $nonce = $payload["nonce"] ?? "";

    if (
        !$userId ||
        !$expiry ||
        $expiry <= time() ||
        !is_string($nonce) ||
        !preg_match('/^[a-f0-9]{32}$/i', $nonce)
    ) {
        return null;
    }

    // Retrieve the current password hash.
    $stmt = $conn->prepare(
        "SELECT id, password, email_verified
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log("Password reset validation query failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    if (
        !$user ||
        (int)$user["email_verified"] !== 1
    ) {
        return null;
    }

    // Recalculate the signature using the current password hash.
    $expectedSignature = hash_hmac(
        "sha256",
        $encodedPayload . "|" . $user["password"],
        $secret
    );

    if (!hash_equals($expectedSignature, $providedSignature)) {
        return null;
    }

    return $user;
}


// Load the existing SMTP2GO configuration for token validation.
$config = require __DIR__ . "/includes/smtp2go.php";
$tokenSecret = $config["token_secret"];

$token = trim($_POST["token"] ?? $_GET["token"] ?? "");

$message = "";
$success = false;
$tokenValid = false;

$user = null;

// Validate the token when the page is opened or submitted.
$user = validatePasswordResetToken(
    $token,
    $conn,
    $tokenSecret
);

if ($user !== null) {
    $tokenValid = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate()) {

        http_response_code(403);
        $message = "Your session has expired. Please refresh the page and try again.";

    } elseif (!$tokenValid) {

        $message = "This password-reset link is invalid or has expired. Please request a new one.";

    } else {

        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (
            $password === "" ||
            $confirmPassword === ""
        ) {
            $message = "Please complete both password fields.";

        } elseif ($password !== $confirmPassword) {

            $message = "The passwords do not match.";

        } elseif (strlen($password) < 12) {

            $message = "Your password must contain at least 12 characters.";

        } elseif (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {

            $message = "Your password must contain an uppercase letter, a lowercase letter, a number and a special character.";

        } else {

            $newPasswordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($newPasswordHash === false) {

                $message = "Your password could not be updated. Please try again.";

            } else {

                /*
                 * Only update if the password hash is still the same
                 * as the one used to validate the reset link.
                 */
                $stmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE id = ?
                     AND password = ?
                     AND email_verified = 1"
                );

                if (!$stmt) {

                    error_log("Password reset update failed: " . $conn->error);
                    $message = "Something went wrong. Please try again later.";

                } else {

                    $stmt->bind_param(
                        "sis",
                        $newPasswordHash,
                        $user["id"],
                        $user["password"]
                    );

                    $stmt->execute();

                    if ($stmt->affected_rows === 1) {

                        $success = true;
                        $message = "Your password has been changed successfully. You can now log in using your new password.";

                    } else {

                        $message = "This password-reset link is no longer valid. Please request a new one.";
                    }

                    $stmt->close();
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

    <title>Reset Password | Workspace Wizards</title>

    <link rel="stylesheet" href="css/style.css?v=2">
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

    <h2>Choose a New Password</h2>

    <?php if ($success): ?>

        <div class="forgot-password-message success" role="status">
            <h3>Password Updated</h3>

            <p>
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </p>
        </div>

        <p>
            <a href="login.php">Return to Login</a>
        </p>

    <?php elseif (!$tokenValid): ?>

        <div class="forgot-password-message error" role="alert">
            <p>
                <?php echo htmlspecialchars(
                    $message !== ""
                        ? $message
                        : "This password-reset link is invalid or has expired. Please request a new one.",
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>
            </p>
        </div>

        <p>
            <a href="forgot_password.php">Request Another Reset Link</a>
        </p>

        <p>
            <a href="login.php">Return to Login</a>
        </p>

    <?php else: ?>

        <?php if ($message !== ""): ?>
            <div class="forgot-password-message error" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <p>
            Enter and confirm your new password below.
        </p>

        <form method="POST" action="reset_password_page.php">

            <?php echo csrf_field(); ?>

            <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars(
                    $token,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
            >

            
            <label for="password">New Password</label>

            <div class="password-field">
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('password', this)"
                >
                    Show
                </button>
            </div>

            <label for="confirm_password">Confirm New Password</label>

            <div class="password-field">
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('confirm_password', this)"
                >
                    Show
                </button>
            </div>


            <button type="submit">
                Update Password
            </button>

        </form>

    <?php endif; ?>

</main>

    
        <script>
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);

            if (field.type === "password") {
                field.type = "text";
                button.textContent = "Hide";
            } else {
                field.type = "password";
                button.textContent = "Show";
            }
        }
        </script>

    
</body>
</html>
