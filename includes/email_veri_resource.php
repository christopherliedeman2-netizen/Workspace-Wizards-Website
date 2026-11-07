<?php

function createEmailVerificationToken(int $userId): array
{
    $config = require __DIR__ . "/smtp2go.php";

    if (
        empty($config["token_secret"]) ||
        !is_string($config["token_secret"])
    ) {
        throw new RuntimeException(
            "The token_secret configuration is missing or invalid."
        );
    }

    // The link expires after 24 hours.
    $payload = json_encode([
        "uid" => $userId,
        "exp" => time() + 86400,
        "nonce" => bin2hex(random_bytes(32))
    ]);

    if ($payload === false) {
        throw new RuntimeException(
            "Could not create verification token."
        );
    }

    // Encode the payload so it is safe to place in a URL.
    $encodedPayload = rtrim(
        strtr(base64_encode($payload), "+/", "-_"),
        "="
    );

    // Sign the payload to prevent tampering.
    $signature = hash_hmac(
        "sha256",
        $encodedPayload,
        $config["token_secret"]
    );

    $token = $encodedPayload . "." . $signature;

    return [
        "token" => $token,
        "hash" => hash("sha256", $token)
    ];
}


function sendVerificationEmail(
    string $email,
    string $name,
    string $token
): bool {

    try {

        // Load SMTP2GO configuration.
        $config = require __DIR__ . "/smtp2go.php";

        if (
            empty($config["host"]) ||
            empty($config["port"]) ||
            empty($config["username"]) ||
            empty($config["password"]) ||
            empty($config["from_email"]) ||
            empty($config["from_name"])
        ) {
            throw new RuntimeException(
                "SMTP2GO configuration is incomplete."
            );
        }

        // Load PHPMailer.
        require_once __DIR__ . "/phpmailer/src/Exception.php";
        require_once __DIR__ . "/phpmailer/src/PHPMailer.php";
        require_once __DIR__ . "/phpmailer/src/SMTP.php";

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP2GO connection.
        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];

        $mail->SMTPSecure =
            \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = (int) $config["port"];
        $mail->Timeout = 15;

        // Email sender and recipient.
        $mail->setFrom(
            $config["from_email"],
            $config["from_name"]
        );

        $mail->addAddress($email, $name);

        // Verification URL.
        $verificationUrl =
            "https://workspacewizards.ifree.page/verify_email.php?token="
            . urlencode($token);

        $safeName = htmlspecialchars(
            $name,
            ENT_QUOTES,
            "UTF-8"
        );

        $safeUrl = htmlspecialchars(
            $verificationUrl,
            ENT_QUOTES,
            "UTF-8"
        );

        // Email content.
        $mail->isHTML(true);
        $mail->CharSet = "UTF-8";

        $mail->Subject =
            "Verify your WorkspaceWizards email";

        $mail->Body = "
            <h2>Welcome to WorkspaceWizards, {$safeName}!</h2>

            <p>
                Please verify your email address by clicking
                the link below.
            </p>

            <p>
                <a href=\"{$safeUrl}\">
                    Verify my email address
                </a>
            </p>

            <p>
                This link expires in 24 hours and can only
                be used once.
            </p>

            <p>
                If you did not create this account, you can
                ignore this email.
            </p>
        ";

        $mail->AltBody =
            "Welcome to WorkspaceWizards, {$name}!\n\n"
            . "Please verify your email address using this link:\n"
            . $verificationUrl
            . "\n\nThis link expires in 24 hours and can only be used once.";

        // Send email.
        $mail->send();

        return true;

    } catch (\Throwable $e) {

        // Record the error.
        $debugMessage = date("Y-m-d H:i:s")
            . " | SMTP verification email failed: "
            . get_class($e)
            . " | "
            . $e->getMessage()
            . PHP_EOL;

        @file_put_contents(
            __DIR__ . "/../verification_debug.txt",
            $debugMessage,
            FILE_APPEND | LOCK_EX
        );

        error_log(
            "WorkspaceWizards SMTP email failed: "
            . $e->getMessage()
        );

        return false;
    }
}
