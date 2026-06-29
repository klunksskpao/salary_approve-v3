<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// เรียกใช้ Autoload ของ Composer
require_once __DIR__ . '/vendor/autoload.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // ตั้งค่าเซิร์ฟเวอร์ (ตัวอย่างใช้ Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'klunk.adm.sskpao@gmail.com'; // ใส่อีเมลของคุณ
        $mail->Password   = 'sysl yjwf frcn lapr';    // ใส่ App Password 16 หลักจาก Google
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8'; // รองรับภาษาไทย

        // ผู้ส่ง และ ผู้รับ
        $mail->setFrom('klunk.adm.sskpao@gmail.com', 'ระบบขอใบรับรองเงินเดือน-สลิปเงินเดือน');
        $mail->addAddress($to);

        // เนื้อหาอีเมล
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // สามารถเก็บ Log หรือ return false ได้หากส่งไม่สำเร็จ
        return false; 
    }
}
?>