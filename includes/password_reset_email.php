<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Build the WorkspaceWizards password-reset email.
 */
function buildPasswordResetEmail(string $resetUrl): array
{
    $safeResetUrl = htmlspecialchars(
        $resetUrl,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );

    $subject = "Reset Your WorkspaceWizards Password";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin:0; padding:0; background-color:#F3F4F6; font-family:Arial,Helvetica,sans-serif; color:#1F2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           style="background-color:#F3F4F6; padding:30px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                       style="max-width:560px; background-color:#FFFFFF; border-radius:10px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#1E3A8A; padding:26px 24px; text-align:center;">
                            <h1 style="margin:0; color:#FFFFFF; font-size:25px;">
                                WorkspaceWizards
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px; line-height:1.6;">
                            <h2 style="margin-top:0; color:#111827;">
                                Reset Your Password
                            </h2>
                            <p>
                                We received a request to reset the password
                                for your WorkspaceWizards account.
                            </p>
                            <p>
                                Click the button below to choose a new password.
                            </p>
                            <p style="text-align:center; margin:30px 0;">
                                <a href="{$safeResetUrl}"
                                   style="display:inline-block; background-color:#2563EB; color:#FFFFFF; text-decoration:none; font-weight:bold; padding:14px 24px; border-radius:6px;">
                                    Reset My Password
                                </a>
                            </p>
                            <p>
                                If the button does not work, copy and paste
                                the following link into your browser:
                            </p>
                            <p style="overflow-wrap:anywhere; font-size:13px;">
                                <a href="{$safeResetUrl}" style="color:#2563EB;">
                                    {$safeResetUrl}
                                </a>
                            </p>
                            <p>
                                For your security, this link will expire after
                                30 minutes. It becomes invalid after your password
                                is changed.
                            </p>
                            <p>
                                If you did not request a password reset,
                                you can safely ignore this email.
                            </p>
                            <p style="margin-bottom:0;">
                                Kind regards,<br>
                                <strong>The Workspace Wizards Team</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F9FAFB; padding:18px 24px; text-align:center; color:#6B7280; font-size:12px;">
                            This is an automated account-security email
                            from Workspace Wizards.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    $text = "Workspace Wizards - Reset Your Password\n\n"
        . "We received a request to reset your password.\n\n"
        . "Open this link to choose a new password:\n"
        . $resetUrl . "\n\n"
        . "This link expires after 30 minutes. It becomes invalid after "
        . "your password is changed.\n"
        . "If you did not request a reset, ignore this email.\n";

    return [
        "subject" => $subject,
        "html" => $html,
        "text" => $text
    ];
}

/**
 * Send the password-reset email through SMTP2GO.
 */
function sendPasswordResetEmail(string $email, string $resetUrl): bool
{
    try {
        $config = require __DIR__ . "/smtp2go.php";

        require_once __DIR__ . "/phpmailer/src/Exception.php";
        require_once __DIR__ . "/phpmailer/src/PHPMailer.php";
        require_once __DIR__ . "/phpmailer/src/SMTP.php";

        $content = buildPasswordResetEmail($resetUrl);

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

        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = $content["subject"];
        $mail->Body = $content["html"];
        $mail->AltBody = $content["text"];

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            "Password reset email failed: " . $e->getMessage()
        );

        return false;

    } catch (\Throwable $e) {

        error_log(
            "Password reset error: " . $e->getMessage()
        );

        return false;
    }
}
