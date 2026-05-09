<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require __DIR__ . '/../DATABASE/config.php';

function send_otp($email, $user_id, $conn, &$error_msg = '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email address";
        return false;
    }

    $otp = sprintf("%06d", random_int(100000, 999999));
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    $stmt = $conn->prepare("INSERT INTO otps (user_id, otp, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        $error_msg = "Database error";
        return false;
    }
    
    $stmt->bind_param("iss", $user_id, $otp, $expiry);
    
    if ($stmt->execute()) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = env('SMTP_USERNAME');
            $mail->Password = env('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = env('SMTP_PORT', 587);

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME', 'PESO Bongabon'));
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'PESO Bongabon - Account Verification';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='background-color: white; padding: 20px; border-radius: 8px; text-align: center;'>
                        <h2 style='color: #1e40af;'>Verification Code</h2>
                        <p>Your OTP code is:</p>
                        <h1 style='font-size: 32px; letter-spacing: 5px; color: #333;'>$otp</h1>
                        <p>This code will expire in 10 minutes.</p>
                    </div>
                </div>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            $error_msg = "Failed to send email. Please try again.";
            return false;
        }
    }
    $error_msg = "Database error";
    return false;
}
?>