<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
function sendVerificationEmail($toEmail, $code) {
    $mail = new PHPMailer(true);


    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS']; // NOT your real password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        // Email content
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your TrailForgeX account';

        $mail->Body = "
            <h2>Welcome to TrailForgeX!</h2>
            <p>Your verification code is:</p>
            <h1 style='letter-spacing:4px;'>$code</h1>
            <p>This code expires in 15 minutes.</p>
        ";

        $mail->AltBody = "Your verification code is: $code";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return "Mailer Error: " . $mail->ErrorInfo;
    }
    
}
