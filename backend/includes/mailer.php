<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function st_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = getenv('SAFARITRAK_MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SAFARITRAK_MAIL_USERNAME') ?: '';
        $mail->Password = getenv('SAFARITRAK_MAIL_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) (getenv('SAFARITRAK_MAIL_PORT') ?: 587);

        $fromEmail = getenv('SAFARITRAK_MAIL_FROM') ?: $mail->Username;
        $fromName = getenv('SAFARITRAK_MAIL_FROM_NAME') ?: 'SafariTrak';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('SafariTrak mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

function st_app_url(): string {
    $base = getenv('SAFARITRAK_APP_URL');
    if ($base) {
        return rtrim($base, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function st_send_verification_email(array $user, string $token): bool {
    $link = st_app_url() . '/verify-email.php?token=' . urlencode($token);

    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Confirm your email</h2>
            <p>Hi ' . htmlspecialchars($user['full_name']) . ',</p>
            <p>Thanks for signing up for SafariTrak. Please confirm your email address to activate your account.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . $link . '" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Verify my email</a>
            </p>
            <p style="color:#666;font-size:13px">Or paste this link into your browser:<br>' . $link . '</p>
            <p style="color:#666;font-size:13px">This link expires in 24 hours. If you did not create a SafariTrak account, you can ignore this email.</p>
        </div>
    ';

    return st_send_mail($user['email'], $user['full_name'], 'Verify your SafariTrak account', $html);
}

function st_send_welcome_email(array $user): bool {
    $html = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
            <h2 style="color:#1b6e4a">Welcome to SafariTrak, ' . htmlspecialchars($user['full_name']) . '!</h2>
            <p>Your email is verified and your account is ready to go.</p>
            <p>Next step: add a trusted contact and start your first journey so someone always knows where you are.</p>
            <p style="text-align:center;margin:28px 0">
                <a href="' . st_app_url() . '/index.php" style="background:#1b6e4a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">Open SafariTrak</a>
            </p>
        </div>
    ';

    return st_send_mail($user['email'], $user['full_name'], 'Welcome to SafariTrak', $html);
}
