<?php
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USERNAME', 'imp.webportal2026@gmail.com');
define('SMTP_PASSWORD', 'ignt azjv gymv ytmh');
define('SMTP_FROM',     'imp.webportal2026@gmail.com');
define('SMTP_FROM_NAME','IMP Test');

require __DIR__ . '/includes/PHPMailer/Exception.php';
require __DIR__ . '/includes/PHPMailer/PHPMailer.php';
require __DIR__ . '/includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PME;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host        = 'smtp.gmail.com';
    $mail->SMTPAuth    = true;
    $mail->Username    = 'imp.webportal2026@gmail.com';
    $mail->Password    = 'ignt azjv gymv ytmh';
    $mail->Port        = 587;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet     = 'UTF-8';
    $mail->Timeout     = 20;
    $mail->SMTPDebug   = 0;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    $mail->smtpConnect();
    echo "SMTP_CONNECT_OK\n";
    $mail->smtpClose();
} catch (PME $e) {
    echo "SMTP_FAIL: " . $e->getMessage() . " | " . $mail->ErrorInfo . "\n";
}
