<?php

session_start();

require_once __DIR__ . "/includes/db.php";

$config = require __DIR__ . "/includes/smtp2go.php";

// --------------------------------------------------
// Check that a token was supplied.
// --------------------------------------------------

$token = $_GET["token"] ?? "";

if (!is_string($token) || $token === "") {
    exit("Invalid or missing verification link.");
}

// --------------------------------------------------
// Split the token into payload and signature.
// --------------------------------------------------

$parts = explode(".", $token);

if (count($parts) !== 2) {
    exit("Invalid verification link.");
}

[$encodedPayload, $providedSignature] = $parts;

// --------------------------------------------------
// Verify the token signature.
// --------------------------------------------------

$expectedSignature = hash_hmac(
    "sha256",
    $encodedPayload,
    $config["token_secret"]
);

if (!hash_equals($expectedSignature, $providedSignature)) {
    exit("This verification link is invalid.");
}

// --------------------------------------------------
// Decode the token payload.
// --------------------------------------------------

$base64 = strtr($encodedPayload, "-_", "+/");

$base64 .= str_repeat(
    "=",
    (4 - strlen($base64) % 4) % 4
);

$decodedPayload = base64_decode($base64, true);

if ($decodedPayload === false) {
    exit("Invalid verification link.");
}

$payload = json_decode($decodedPayload, true);

if (
    !is_array($payload) ||
    !isset($payload["uid"], $payload["exp"]) ||
    !is_numeric($payload["uid"]) ||
    !is_numeric($payload["exp"])
) {
    exit("Invalid verification link.");
}

// --------------------------------------------------
// Check expiry.
// --------------------------------------------------

if ((int) $payload["exp"] < time()) {
    exit("This verification link has expired. Please request a new one.");
}

// --------------------------------------------------
// Calculate token hash.
// --------------------------------------------------

$tokenHash = hash("sha256", $token);

try {

    // Find the account matching this token.
    $stmt = $conn->prepare("
        SELECT id, email_verified
        FROM users
        WHERE id = ?
        AND email_verification_token_hash = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $userId = (int) $payload["uid"];

    $stmt->bind_param("is", $userId, $tokenHash);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    if (!$user) {
        exit("This verification link is invalid or has already been used.");
    }

    if ((int) $user["email_verified"] === 1) {
        exit("Your email address has already been verified.");
    }

    // Mark account as verified and invalidate token.
    $stmt = $conn->prepare("
        UPDATE users
        SET email_verified = 1,
            email_verification_token_hash = NULL
        WHERE id = ?
        AND email_verification_token_hash = ?
    ");

    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param("is", $userId, $tokenHash);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        exit("Verification could not be completed. Please try again.");
    }

    $stmt->close();


} catch (Throwable $e) {

    error_log(
        "WorkspaceWizards verification error: " . $e->getMessage()
    );

    exit("An error occurred while verifying your email. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified | WorkspaceWizards</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <main class="verification-page">
        <div class="verification-card">

            <div class="verification-icon">
                &#10003;
            </div>

            <h1>Email Successfully Verified!</h1>

            <p>
                Your email address has been verified successfully.
                Your WorkspaceWizards account is now ready to use.
            </p>

            <a href="login.php" class="verification-button">
                Continue to Login
            </a>

        </div>
    </main>

</body>
</html>
';
    