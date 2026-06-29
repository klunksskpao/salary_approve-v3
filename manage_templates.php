<?php
require_once 'db.php';
require_once 'baht_text.php';

// ตรวจสอบสิทธิ์ ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานหน้านี้");
}

$message = '';
$message_type = 'success';

function getSetting($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

// ==========================================
// 1. กำหนดรูปแบบเอกสารเริ่มต้น (Default Templates)
// ==========================================
$default_cert = '
<div style="text-align: center; margin-bottom: 20px;">
<h1 style="font-size: 24px;"><img src="crud.png" width="95" height="105"></h1>
<table style="border-collapse: collapse; width: 100%; border-width: 0px; margin-left: auto; margin-right: auto; height: 67.2px;" border="1"><colgroup><col style="width: 71.3956%;"><col style="width: 28.5815%;"></colgroup>
<tbody>
<tr>
<td style="height: 22.4px; border-width: 0px; text-align: left;">ที่ ศก 51004/{ID}&nbsp; &nbsp; &nbsp;</td>
<td style="height: 22.4px; border-width: 0px; text-align: right;">องค์การบริหารส่วนจังหวัดศรีสะเกษ&nbsp;&nbsp;</td>
</tr>
<tr>
<td style="height: 22.4px; border-width: 0px;"></td>
<td style="height: 22.4px; border-width: 0px; text-align: right;">350 หมู่ 3 ตำบลหนองไผ่ อำเภอเมือง</td>
</tr>
<tr>
<td style="height: 22.4px; border-width: 0px;"></td>
<td style="height: 22.4px; border-width: 0px; text-align: right;">&nbsp;จังหวัดศรีสะเกษ 33000&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;</td>
</tr>
</tbody>
</table>
</div>
<div style="line-height: 1.8; text-align: justify;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; หนังสือฉบับนี้ให้ไว้เพื่อรับรองว่า <strong>{TITLE}{FULLNAME}</strong>&nbsp;&nbsp;ปัจจุบันดำรงตำแหน่ง <strong>{POSITION}</strong> สังกัด <strong>{DEPARTMENT} </strong>องค์การบริหารส่วนจังหวัดศรีสะเกษ เริ่มปฏิบัติงานเมื่อวันที่&nbsp;<strong>{JOIN_DATE}<br></strong>โดยมีรายละเอียดรายรับดังนี้:&nbsp;</div>
<div style="line-height: 1.8; margin-top: 10px;">
<table style="width: 80%; margin: 0px auto; border-collapse: collapse; height: 172.8px;">
<tbody>
<tr>
<td style="width: 63.2009%; height: 28.8px;">1. อัตราเงินเดือน</td>
<td style="text-align: right; width: 36.7775%; height: 28.8px;">{SALARY} บาท</td>
</tr>
<tr>
<td style="width: 63.2009%; height: 28.8px;">2. เงินประจำตำแหน่ง</td>
<td style="text-align: right; width: 36.7775%; height: 28.8px;">{POS_ALLOWANCE} บาท</td>
</tr>
<tr>
<td style="width: 63.2009%; height: 28.8px;">3. เงินค่าตอบแทนรายเดือน</td>
<td style="text-align: right; width: 36.7775%; height: 28.8px;">{MONTHLY_REMUNERATION} บาท</td>
</tr>
<tr>
<td style="width: 63.2009%; height: 28.8px;">4. ค่าครองชีพ</td>
<td style="text-align: right; width: 36.7775%; height: 28.8px;">{LIVING_ALLOWANCE} บาท</td>
</tr>
<tr>
<td style="width: 63.2009%; height: 28.8px;"><strong>รวมรายรับทั้งสิ้น</strong></td>
<td style="text-align: right; width: 36.7775%; height: 28.8px;"><strong>{TOTAL_INCOME} บาท</strong></td>
</tr>
<tr>
<td style="width: 63.2009%;"><strong><span>({TOTAL_INCOME_TEXT})</span></strong></td>
<td style="text-align: right; width: 36.7775%;"><strong>&nbsp;</strong></td>
</tr>
</tbody>
</table>
</div>
<div style="line-height: 1.8; margin-top: 20px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ออกหนังสือรับรองฉบับนี้เพื่อนำไปใช้สำหรับ: <strong>{PURPOSE}<br></strong>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp;ให้ไว้ ณ วันที่ {CURRENT_DATE}<strong></strong></div>
<table style="width: 100%; text-align: center; border: none; height: 112px;">
<tbody>
<tr>
<td style="width: 50%; border: none; vertical-align: bottom; height: 112px;">(ลงชื่อผู้ขอรับรอง)<br><br>{USER_SIGNATURE}<br>({TITLE}{FULLNAME})</td>
<td style="width: 50%; border: none; vertical-align: bottom; height: 112px;">(ลงชื่อผู้อนุมัติ/ผู้รับรอง)<br><br>{APPR_SIGNATURE}<br>({APPR_NAME})<br>{APPR_POSITION}</td>
</tr>
</tbody>
</table>
<p></p>
<p>{QR_CODE}<br><span style="font-size: 11px; color: #666;">*เอกสารนี้ ออกโดยผ่านระบบอินเตอร์เน็ต (สำนักคลัง อบจ.ศรีสะเกษ)</span><br><span style="font-size: 11px; color: #666;">รหัสอ้างอิง: {TRACKING_CODE}&nbsp;<br></span></p>';

$default_payslip = '
<div style="border: 1px solid #000; padding: 20px;">
<div style="text-align: right; margin-bottom: -15px;">{QR_CODE}<br><span style="font-size: 12px; color: #666;">รหัสอ้างอิง: {TRACKING_CODE} (ID: {REQUEST_ID})</span></div>
<table style="width: 100%; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; border-collapse: collapse;">
<tbody>
<tr>
<td style="width: 100%; text-align: center; vertical-align: middle; border: none;"><img src="logo.png" width="50" height="50">
<h3 style="margin: 0; color: #333;">สลิปเงินเดือน (Payslip)</h3>
</td>
</tr>
</tbody>
</table>
<table style="width: 100%; margin-bottom: 20px; border: none; height: 67.2px;">
<tbody>
<tr>
<td style="border: none; height: 22.4px; width: 46.2874%;"><strong>ชื่อ-สกุล:</strong> {TITLE}{FULLNAME}</td>
<td style="text-align: right; border: none; height: 22.4px; width: 53.7608%;"><strong>ประจำเดือน:</strong> {REQ_MONTH}/{REQ_YEAR}</td>
</tr>
<tr>
<td style="border: none; height: 22.4px; width: 46.2874%;"><strong>ตำแหน่ง:</strong> {POSITION}</td>
<td style="text-align: right; border: none; height: 22.4px; width: 53.7608%;"><strong>สังกัด:</strong> {DEPARTMENT}</td>
</tr>
<tr>
<td style="border: none; height: 22.4px; width: 46.2874%;"></td>
<td style="border: none; height: 22.4px; text-align: right; width: 53.7608%;">องค์การบริหารส่วนจังหวัดศรีสะเกษ</td>
</tr>
</tbody>
</table>
<table style="width: 100%; border-collapse: collapse; border: 1px solid rgb(0, 0, 0); height: 127.8px;">
<tbody>
<tr>
<th style="border: 1px solid rgb(0, 0, 0); padding: 10px; width: 50%; height: 22.4px;">รายได้ (Earnings)</th>
<th style="border: 1px solid rgb(0, 0, 0); padding: 10px; width: 50%; height: 22.4px;">รายการหัก (Deductions)</th>
</tr>
<tr>
<td style="border: 1px solid rgb(0, 0, 0); padding: 10px; vertical-align: top; height: 37.4px;">{INCOMES_HTML}
<table style="width: 100%; border: none; margin-right: 0px; margin-left: auto;"></table>
</td>
<td style="border: 1px solid rgb(0, 0, 0); padding: 10px; vertical-align: top; height: 37.4px;">{EXPENSES_HTML}
<table style="width: 100%; border: none;"></table>
</td>
</tr>
<tr>
<td style="border: 1px solid rgb(0, 0, 0); padding: 10px; height: 22.4px;"><strong>รวมรายได้:&nbsp;</strong> <span style="float: right;">{TOTAL_INCOMES}</span></td>
<td style="border: 1px solid rgb(0, 0, 0); padding: 10px; height: 22.4px;"><strong>รวมรายการหัก:&nbsp;</strong> <span style="float: right;">{TOTAL_EXPENSES}</span></td>
</tr>
<tr>
<td style="border: 1px solid rgb(0, 0, 0); padding: 15px; text-align: center; font-size: 20px; background-color: rgb(240, 240, 240); height: 30.8px;" colspan="2"><strong>เงินได้สุทธิ (Net Pay): {NET_PAY} บาท</strong></td>
</tr>
<tr>
<td style="border: 1px solid rgb(0, 0, 0); padding: 15px; text-align: center; font-size: 20px; background-color: rgb(240, 240, 240); height: 14.8px;" colspan="2"><strong><span>({TOTAL_INCOME_TEXT})</span></strong></td>
</tr>
</tbody>
</table>
<br>
<table style="width: 100%; text-align: center; border: none; height: 112px;">
<tbody>
<tr>
<td style="width: 100%; border: none; vertical-align: bottom; height: 112px;">(ลงชื่อผู้รับรอง)<br><br>{APPR_SIGNATURE}<br>({APPR_NAME})<br>{APPR_POSITION}</td>
</tr>
</tbody>
</table>
<p font-size:10px;="">*เอกสารนี้ออกโดยสำนักคลัง อบจ.ศรีสะเกษ ผ่านระบบอินเตอร์เน็ต</p>
</div>';

// ==========================================
// 2. จัดการ Action (บันทึก หรือ รีเซ็ต)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $cert_html = $_POST['template_cert'];
        $payslip_html = $_POST['template_payslip'];

        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('template_cert', ?)")->execute([$cert_html]);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('template_payslip', ?)")->execute([$payslip_html]);
        
        $message = "บันทึกรูปแบบเอกสารเรียบร้อยแล้ว";
        
    } elseif ($action === 'reset') {
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('template_cert', ?)")->execute([$default_cert]);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('template_payslip', ?)")->execute([$default_payslip]);
        
        $message = "คืนค่ารูปแบบเอกสารมาตรฐานเรียบร้อยแล้ว (ระบบลบข้อมูลที่เคยแก้ไว้ออกทั้งหมด)";
        $message_type = 'warning';
    }
}

// ==========================================
// 3. ดึงข้อมูลล่าสุดมาแสดงผล
// ==========================================
$template_cert = getSetting($pdo, 'template_cert');
$template_payslip = getSetting($pdo, 'template_payslip');

if (!$template_cert || !$template_payslip) {
    $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('template_cert', ?)")->execute([$default_cert]);
    $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('template_payslip', ?)")->execute([$default_payslip]);
    $template_cert = $default_cert;
    $template_payslip = $default_payslip;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการรูปแบบเอกสาร (PDF) - Salary System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; margin: 0; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { display: block; color: white; padding: 15px; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover { background: #495057; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-top: 0; }
        .form-group { margin-bottom: 30px; }
        .btn { padding: 12px 25px; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn-success { background: #28a745; width: 100%; margin-bottom: 10px; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; width: 100%; }
        .btn-danger:hover { background: #c82333; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        .variable-box { background: #e9ecef; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; border-left: 5px solid #17a2b8; }
        .var-badge { background: #007bff; color: white; padding: 2px 6px; border-radius: 3px; font-family: monospace; margin-right: 5px; cursor: pointer; }
        .help-text { font-size: 13px; color: #dc3545; margin-top: 5px; }
        .action-buttons { display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; }
        .var-badge-qr { background: #28a745; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    <a href="import_csv.php">📁 นำเข้าเงินเดือน (CSV)</a>
    <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
    <a href="manage_templates.php" style="background: #495057;">🎨 จัดการรูปแบบเอกสาร PDF</a>
    <a href="manage_salary.php">💰 จัดการฐานข้อมูลเงินเดือน</a>
    <a href="profile.php">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="container">
        <h2>🎨 จัดการรูปแบบเอกสาร PDF (WYSIWYG Editor)</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="variable-box">
            <strong>💡 คำแนะนำ:</strong> พิมพ์ข้อความหรือจัดหน้าต่างตามต้องการ และคัดลอกตัวแปร (Tags) สีฟ้าด้านล่างไปวางในจุดที่ต้องการให้ระบบดึงข้อมูลมาแสดงอัตโนมัติ:<br><br>
            <span class="var-badge var-badge-qr">{QR_CODE}</span>แสดงรูป QR Code <br><br>
            <span class="var-badge">{REQUEST_ID}</span>ไอดีคำขอ 
            <span class="var-badge">{ID}</span>รหัสคำขอ
            <span class="var-badge">{TRACKING_CODE}</span>รหัสติดตาม(8หลัก) <br>
            <span class="var-badge">{TITLE}</span>คำนำหน้า 
            <span class="var-badge">{FULLNAME}</span>ชื่อ-นามสกุล 
            <span class="var-badge">{ID_CARD}</span>เลขบัตรฯ 
            <span class="var-badge">{POSITION}</span>ตำแหน่ง 
            <span class="var-badge">{DEPARTMENT}</span>สังกัด <br>
            <span class="var-badge">{CURRENT_DATE}</span>วันที่พิมพ์เอกสาร 
            <span class="var-badge">{APPR_NAME}</span>ชื่อผู้อนุมัติ 
            <span class="var-badge">{APPR_POSITION}</span>ตำแหน่งผู้อนุมัติ 
            <span class="var-badge">{APPR_SIGNATURE}</span>รูปลายเซ็นผู้อนุมัติ
            
        </div>

        <form method="POST">
            <div class="form-group">
                <h3>📄 รูปแบบ: หนังสือรับรองเงินเดือน</h3>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">ตัวแปรเฉพาะ: <span class="var-badge">{JOIN_DATE}</span> <span class="var-badge">{SALARY}</span> <span class="var-badge">{POS_ALLOWANCE}</span> <span class="var-badge">{MONTHLY_REMUNERATION}</span> <span class="var-badge">{LIVING_ALLOWANCE}</span> <span class="var-badge">{TOTAL_INCOME}</span> <span class="var-badge">{PURPOSE}</span> <span class="var-badge">{USER_SIGNATURE}</span><span class="var-badge">{TOTAL_INCOME_TEXT}</span></p>
                <textarea class="wysiwyg-editor" name="template_cert"><?php echo htmlspecialchars($template_cert); ?></textarea>
            </div>

            <div class="form-group">
                <h3>💸 รูปแบบ: สลิปเงินเดือน</h3>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">ตัวแปรเฉพาะ: <span class="var-badge">{REQ_MONTH}</span> <span class="var-badge">{REQ_YEAR}</span> <span class="var-badge">{BANK_ACCOUNT}</span> <span class="var-badge">{INCOMES_HTML}</span> <span class="var-badge">{EXPENSES_HTML}</span> <span class="var-badge">{TOTAL_INCOMES}</span> <span class="var-badge">{TOTAL_EXPENSES}</span> <span class="var-badge">{NET_PAY}</span><span class="var-badge">{TOTAL_INCOME_TEXT}</span></p>
                <textarea class="wysiwyg-editor" name="template_payslip"><?php echo htmlspecialchars($template_payslip); ?></textarea>
                <p class="help-text">* หากตาราง PDF แสดงผลเพี้ยน แนะนำให้กดปุ่ม <strong>&lt; &gt; (Source code)</strong> เพื่อตรวจสอบว่าตัวแปรอยู่ในแท็ก HTML ที่ถูกต้องหรือไม่ครับ</p>
            </div>

            <div class="action-buttons">
                <button type="submit" name="action" value="save" class="btn btn-success" style="flex: 2;">💾 บันทึกรูปแบบเอกสารทั้งหมด</button>
                <button type="submit" name="action" value="reset" class="btn btn-danger" style="flex: 1;" onclick="return confirm('⚠️ การคืนค่าเริ่มต้นจะลบรูปแบบที่คุณแก้ไขไว้ทั้งหมด และกลับไปใช้โครงสร้างมาตรฐาน\n\nแน่ใจหรือไม่ที่จะดำเนินการต่อ?');">🔄 คืนค่าเริ่มต้น (Reset)</button>
            </div>
        </form>
    </div>
</div>

<script>
    tinymce.init({
        selector: '.wysiwyg-editor',
        height: 500,
        plugins: 'table code lists link image preview fullscreen',
        toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | table | bullist numlist | code fullscreen preview',
        menubar: false,
        content_style: 'body { font-family: "Sarabun", sans-serif; font-size: 16px; }',
        valid_elements: '*[*]',
        extended_valid_elements: 'style,table[style|width|border|cellspacing|cellpadding],tr,td[style|width|colspan|rowspan|valign],th[style|width]'
    });
</script>

</body>
</html>