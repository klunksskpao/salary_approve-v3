<?php
// รับค่ารหัสติดตามคำขอจาก URL
$code = $_GET['code'] ?? '';

if (!empty($code)) {
    // ส่งต่อ (Redirect) ไปยังไฟล์ออก PDF หลัก พร้อมระบุประเภทเป็น request_form
    $redirect_url = "export_pdf.php?type=request_form&code=" . urlencode($code);
    header("Location: " . $redirect_url);
    exit();
} else {
    // กรณีไม่มีการส่งรหัสมา
    echo "<h2>ไม่พบรหัสคำขอ</h2>";
    echo "<a href='index.html'>กลับหน้าหลัก</a>";
}
?>