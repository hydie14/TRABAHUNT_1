<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

function sendEmail($to, $subject, $body, &$error = null) {
    $mail = new PHPMailer(true);
    try {
        // Enable debug logging sa error_log para makita kung ano ang problema
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'error_log';

        //Server settings
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USERNAME', 'condehydie04@gmail.com'); // Matched to OTP variables
        $mail->Password   = env('SMTP_PASSWORD', 'dihhgcurdunfsffv');    // Matched to OTP variables
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)env('SMTP_PORT', 587);

        // Bypass SSL verification (Madalas na nagiging issue sa XAMPP/Localhost)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        //Recipients
        $mail->setFrom(env('SMTP_USERNAME', 'condehydie04@gmail.com'), 'PESO Bongabon');
        $mail->addAddress($to);

        //Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        $error = "Mailer Error: " . $mail->ErrorInfo;
        error_log("Message could not be sent. " . $error);
        return false;
    }
}
?>