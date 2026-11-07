<?php

session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/email_veri_resource.php";

$message = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } else {

        try {

            $stmt = $conn->prepare("
                SELECT id, name, email, email_verified
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Use the same response regardless of account status.
            $message = "If your account still needs verification, a new verification email will be sent.";

            if ($user && (int)$user["email_verified"] === 0) {

                $verification = createEmailVerificationToken(
                    (int)$user["id"]
                );

                $stmt = $conn->prepare("
                    UPDATE users
                    SET email_verification_token_hash = ?
                    WHERE id = ?
                    AND email_verified = 0
                ");

                $userId = (int)$user["id"];
                $tokenHash = $verification["hash"];

                $stmt->bind_param("si", $tokenHash, $userId);
                $stmt->execute();

                $updated = $stmt->affected_rows === 1;
                $stmt->close();

                if ($updated) {

                    $sent = sendVerificationEmail(
                        $user["email"],
                        $user["name"],
                        $verification["token"]
                    );

                    if (!$sent) {
                        error_log(
                            "WorkspaceWizards: Resend verification email failed."
                        );
                    }
                }
            }

        } catch (Throwable $e) {

            error_log(
                "WorkspaceWizards resend verification error: "
                . $e->getMessage()
            );

            $message = "We could not process your request right now. Please try again later.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification | WorkspaceWizards</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

<header>
    <h1>Workspace Wizards</h1>
    <nav>
        <a href="home.php">Home</a>
        <a href="products.php">Products</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </nav>
</header>

<main>
    <h2>Resend Verification Email</h2>

    <?php if ($message !== ""): ?>
        <p role="status">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="resend_verification.php">

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

        <button type="submit">
            Resend Verification Email
        </button>

    </form>

    <p>
        <a href="login.php">Return to Login</a>
    </p>
</main>

</body>
</html>
