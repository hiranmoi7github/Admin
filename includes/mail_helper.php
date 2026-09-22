<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendResetPasswordEmail(string $toEmail, string $toName, string $resetLink): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Your TYT Admin Password';

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;line-height:1.7;color:#111827;">
                <h2 style="margin-bottom:10px;">Reset Password</h2>
                <p>Hello ' . htmlspecialchars($toName) . ',</p>
                <p>We received a request to reset your TYT Admin password.</p>
                <p>
                    <a href="' . htmlspecialchars($resetLink) . '" 
                       style="display:inline-block;background:#6d28d9;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;">
                        Reset Password
                    </a>
                </p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request this, please ignore this email.</p>
            </div>
        ';

        $mail->AltBody = "Reset your password using this link: " . $resetLink;

        $mail->send();

        return [
            'success' => true,
            'message' => 'Reset email sent successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Mailer Error: ' . $mail->ErrorInfo
        ];
    }
}