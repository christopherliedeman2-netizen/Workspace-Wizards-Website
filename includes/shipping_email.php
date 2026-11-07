
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendShippingEmail(
    string $email,
    string $customerName,
    int $orderId
): bool {

    try {
        $config = require __DIR__ . "/smtp2go.php";

        require_once __DIR__ . "/phpmailer/src/Exception.php";
        require_once __DIR__ . "/phpmailer/src/PHPMailer.php";
        require_once __DIR__ . "/phpmailer/src/SMTP.php";

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$config["port"];

        $mail->CharSet = "UTF-8";

        $mail->setFrom(
            $config["from_email"],
            $config["from_name"]
        );

        $mail->addAddress($email, $customerName);

        $mail->isHTML(true);

        $mail->Subject = "Your WorkspaceWizards Order Has Shipped";

        $safeName = htmlspecialchars(
            $customerName,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8"
        );

        $safeOrderId = (int)$orderId;

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0;padding:30px;background:#F3F4F6;font-family:Arial,sans-serif;color:#1F2937;">

    <div style="max-width:560px;margin:auto;background:#FFFFFF;border-radius:10px;overflow:hidden;">

        <div style="background:#1E3A8A;padding:25px;text-align:center;">
            <h1 style="margin:0;color:#FFFFFF;">WorkspaceWizards</h1>
        </div>

        <div style="padding:30px;line-height:1.6;">

            <h2 style="color:#1E3A8A;">Your Order Has Shipped!</h2>

            <p>Hello {$safeName},</p>

            <p>
                We're pleased to let you know that your
                WorkspaceWizards order has been shipped.
            </p>

            <p>
                <strong>Order Number:</strong> #{$safeOrderId}
            </p>

            <p>
                Thank you for shopping with WorkspaceWizards.
                We appreciate your support!
            </p>

            <p>
                Kind regards,<br>
                <strong>The WorkspaceWizards Team</strong>
            </p>

        </div>

        <div style="background:#F9FAFB;padding:18px;text-align:center;color:#6B7280;font-size:12px;">
            Automated order notification from WorkspaceWizards.
        </div>

    </div>

</body>
</html>
HTML;

        $mail->AltBody =
            "Hello " . $customerName . ",\n\n"
            . "Your WorkspaceWizards order #" . $orderId
            . " has been shipped.\n\n"
            . "Thank you for shopping with us!\n"
            . "The WorkspaceWizards Team";

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Shipping email failed: " . $e->getMessage());
        return false;

    } catch (\Throwable $e) {

        error_log("Shipping email error: " . $e->getMessage());
        return false;
    }
}
