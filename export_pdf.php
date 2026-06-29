<?php
require_once 'db.php';
require_once 'baht_text.php';
require_once __DIR__ . '/vendor/autoload.php';

// รับค่าจาก URL (รองรับทั้ง id สำหรับเจ้าหน้าที่ และ code สำหรับผู้ขอ)
$request_id = $_GET['id'] ?? null;
$code = $_GET['code'] ?? null;
$type = $_GET['type'] ?? null;

if (!$type || (!$request_id && !$code)) {
    die("ข้อมูลไม่ครบถ้วน");
}

// 1. ดึงข้อมูลคำขอหลัก (ดึงชื่อ/ตำแหน่ง/ลายเซ็น จากคนที่กด Approve จริงๆ)
if ($request_id) {
    $stmt = $pdo->prepare("SELECT r.*, u.name as approver_name, u.signature_image as approver_signature, u.position as approver_position FROM requests r LEFT JOIN users u ON r.approved_by = u.id WHERE r.id = ?");
    $stmt->execute([$request_id]);
} else {
    $stmt = $pdo->prepare("SELECT r.*, u.name as approver_name, u.signature_image as approver_signature, u.position as approver_position FROM requests r LEFT JOIN users u ON r.approved_by = u.id WHERE r.tracking_code = ?");
    $stmt->execute([$code]);
}

$request = $stmt->fetch();
if (!$request) die("ไม่พบเอกสารในระบบ");

$request_id = $request['id']; // เซ็ตกลับไปเผื่อใช้ query ตารางย่อย

// เช็คสถานะ: ถ้าจะพิมพ์ cert หรือ payslip ต้อง approved เท่านั้น
if (in_array($type, ['cert', 'payslip']) && $request['status'] !== 'approved') {
    die("เอกสารนี้ยังไม่ได้รับการอนุมัติ ไม่สามารถพิมพ์ได้");
}

// ---------------------------------------------------------
// สร้าง QR Code จาก API ภายนอก (ป้องกัน mPDF Barcode Error)
// ---------------------------------------------------------
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$tracking_url = $base_url . '/track.php?code=' . $request['tracking_code'];

// เรียกใช้ API ฟรีของ qrserver.com
$qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=' . urlencode($tracking_url);
$qr_code_html = '<img src="' . $qr_api_url . '" width="70" height="70" style="margin-bottom: 5px;" />';
// ---------------------------------------------------------

// 2. ตั้งค่า mPDF
$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/fonts',
    ]),
    'fontdata' => $fontData + [
        'thasarabun' => [
            'R'  => 'THSarabunNew.ttf',
            'B'  => 'THSarabunNew Bold.ttf',
            'I'  => 'THSarabunNew Italic.ttf',
            'BI' => 'THSarabunNew BoldItalic.ttf'
        ]
    ],
    'default_font' => 'thasarabun',
    'default_font_size' => 16,
    'format' => 'A4',
    'margin_left' => 30,
    'margin_right' => 20,
    'margin_top' => 15,
    'margin_bottom' => 10,
]);

// ลายน้ำโลโก้ (ถ้ามี)
$logo_path = 'logo.png'; 
if (file_exists($logo_path)) {
    $mpdf->SetWatermarkImage($logo_path, 0.1, 'D', 'P'); 
    $mpdf->showWatermarkImage = true;
}

$html = '';

// ==========================================
// แบบที่ 0: พิมพ์แบบฟอร์มคำขอ
// ==========================================
if ($type === 'request_form') {
    $req_type_text = ($request['request_type'] == 'cert') ? 'หนังสือรับรองเงินเดือน' : (($request['request_type'] == 'payslip') ? 'สลิปเงินเดือน' : 'หนังสือรับรองเงินเดือน และ สลิปเงินเดือน');
    
    $html .= '
    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td style="width: 80%; text-align: center; vertical-align: middle;">
                <h1 style="font-size: 24px; margin:0;"><img src="https://klunksskpao.great-site.net/esalary/logo.png" width="100" height="100"></h1>
                <h2 style="margin: 0;">แบบคำขอเอกสารทางการเงิน</h2>
                <p style="margin: 0;">รหัสติดตามคำขอ: <strong>' . htmlspecialchars($request['tracking_code']) . '</strong></p>
            </td>
            <td style="width: 20%; text-align: right; vertical-align: top;">
                ' . $qr_code_html . '
            </td>
        </tr>
    </table>
    <div style="line-height: 1.8;">
        <strong>วันที่ยื่นคำขอ:</strong> ' . date('d/m/Y H:i', strtotime($request['created_at'])) . '<br>
        <strong>อีเมลสำหรับติดต่อ:</strong> ' . htmlspecialchars($request['email']) . '<br>
        <strong>เอกสารที่ต้องการขอ:</strong> ' . $req_type_text . '<br><br>';

    $stmtCert = $pdo->prepare("SELECT * FROM cert_requests WHERE request_id = ?");
    $stmtCert->execute([$request_id]);
    $cert = $stmtCert->fetch();

    $stmtSlip = $pdo->prepare("SELECT * FROM payslip_requests WHERE request_id = ?");
    $stmtSlip->execute([$request_id]);
    $slip = $stmtSlip->fetch();

    if ($cert) {
        $html .= '<strong>ข้อมูลผู้ขอ (อ้างอิงจากคำขอใบรับรอง):</strong><br>';
        $html .= 'ชื่อ-นามสกุล: ' . htmlspecialchars($cert['title'] . $cert['fullname']) . '<br>';
        $html .= 'ตำแหน่ง: ' . htmlspecialchars($cert['position']) . ' สังกัด: ' . htmlspecialchars($cert['department']) . '<br>';
        if(!empty($cert['user_signature'])) {
            $html .= '<br><strong>ลายมือชื่อผู้ยื่นคำขอ:</strong><br><img src="' . htmlspecialchars($cert['user_signature']) . '" height="50">';
        }
    } elseif ($slip) {
        $html .= '<strong>ข้อมูลผู้ขอ (อ้างอิงจากคำขอสลิปเงินเดือน):</strong><br>';
        $html .= 'ชื่อ-นามสกุล: ' . htmlspecialchars($slip['title'] . $slip['fullname']) . '<br>';
        $html .= 'ตำแหน่ง: ' . htmlspecialchars($slip['position']) . ' สังกัด: ' . htmlspecialchars($slip['department']) . '<br>';
    }

    $html .= '</div>
    <hr>
    <div style="text-align: center; color: #666; font-size: 14px;">
        เอกสารฉบับนี้คือแบบคำขอจากระบบ ไม่ใช่เอกสารรับรองที่เป็นทางการ<br>
        สถานะคำขอปัจจุบัน: <strong>' . strtoupper($request['status']) . '</strong>
    </div>';
}

// ==========================================
// แบบที่ 1: การวาดหน้า หนังสือรับรองเงินเดือน
// ==========================================
elseif ($type === 'cert') {
    $stmtCert = $pdo->prepare("SELECT * FROM cert_requests WHERE request_id = ?");
    $stmtCert->execute([$request_id]);
    $cert = $stmtCert->fetch();
    if (!$cert) die("ไม่พบข้อมูลใบรับรอง");

    $join_date_th = date('d', strtotime($cert['join_date'])) . '/' . date('m', strtotime($cert['join_date'])) . '/' . (date('Y', strtotime($cert['join_date'])) + 543);
    $current_date_th = date('d/m') . '/' . (date('Y') + 543);
    
    $stmtTpl = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'template_cert'");
    $html = $stmtTpl->fetchColumn();

    $appr_name = !empty($request['approver_name']) ? htmlspecialchars($request['approver_name']) : '...................................................';
    $appr_position = !empty($request['approver_position']) ? htmlspecialchars($request['approver_position']) : 'ผู้อนุมัติ/หัวหน้าหน่วยงาน';
    $appr_sig = !empty($request['approver_signature']) ? '<img src="' . htmlspecialchars($request['approver_signature']) . '" height="50">' : '...................................................';
    $user_sig = !empty($cert['user_signature']) ? '<img src="' . htmlspecialchars($cert['user_signature']) . '" height="50">' : '...................................................';

    $replace_vars = [
        '{REQUEST_ID}' => str_pad($request['id'], 5, '0', STR_PAD_LEFT),
        '{ID}' => htmlspecialchars($request['id']),
        '{TRACKING_CODE}' => htmlspecialchars($request['tracking_code']),        
        '{QR_CODE}' => $qr_code_html, // แท็ก QR Code
        '{TITLE}' => htmlspecialchars($cert['title']),
        '{FULLNAME}' => htmlspecialchars($cert['fullname']),
        '{ID_CARD}' => htmlspecialchars($cert['id_card']),
        '{POSITION}' => htmlspecialchars($cert['position']),
        '{DEPARTMENT}' => htmlspecialchars($cert['department']),
        '{CURRENT_DATE}' => $current_date_th,
        '{JOIN_DATE}' => $join_date_th,
        '{SALARY}' => number_format($cert['salary'], 2),
        '{POS_ALLOWANCE}' => number_format($cert['position_allowance'], 2),
        '{MONTHLY_REMUNERATION}' => number_format($cert['monthly_remuneration'], 2),
        '{LIVING_ALLOWANCE}' => number_format($cert['living_allowance'], 2),
        '{TOTAL_INCOME}' => number_format($cert['total_income'], 2),
        '{TOTAL_INCOME_TEXT}' =>  baht_text($cert['total_income']),
        '{PURPOSE}' => htmlspecialchars($cert['purpose']),
        '{USER_SIGNATURE}' => $user_sig, 
        '{APPR_SIGNATURE}' => $appr_sig,
        '{APPR_NAME}' => $appr_name,
        '{APPR_POSITION}' => $appr_position
    ];

    $html = str_replace(array_keys($replace_vars), array_values($replace_vars), $html);
}

// ==========================================
// แบบที่ 2: การวาดหน้า สลิปเงินเดือน
// ==========================================
elseif ($type === 'payslip') {
    $stmtSlip = $pdo->prepare("SELECT * FROM payslip_requests WHERE request_id = ?");
    $stmtSlip->execute([$request_id]);
    $slip = $stmtSlip->fetch();
    if (!$slip) die("ไม่พบข้อมูลสลิปเงินเดือน");

    $stmtSalary = $pdo->prepare("SELECT * FROM salary_data WHERE id_card = ? AND data_month = ? AND data_year = ?");
    $stmtSalary->execute([$slip['slip_id_card'], $slip['req_month'], $slip['req_year']]);
    $salary = $stmtSalary->fetch();
    if (!$salary) die("ไม่พบข้อมูลรายการเงินเดือนในระบบของเดือนที่ระบุ");

    $inc_arr = json_decode($salary['dynamic_incomes'], true) ?: [];
    $exp_arr = json_decode($salary['dynamic_expenses'], true) ?: [];
    
    $dyn_inc_total = array_sum(array_column($inc_arr, 'amount'));
    $total_deduct = array_sum(array_column($exp_arr, 'amount'));
    
    $total_earnings = $salary['salary'] + $dyn_inc_total;
    $net_pay = $total_earnings - $total_deduct;

    // สร้าง HTML ลูปรายรับ/รายจ่าย (แก้ไขโครงสร้าง CSS ของ mPDF)
    $income_html = '<p style="margin: 0; padding: 2px 0;">เงินเดือนหลัก<span style="float: right;">&nbsp;' . number_format($salary['salary'], 2) . '</span></p>';
    foreach($inc_arr as $inc) {
        $income_html .= '<p style="margin: 0; padding: 2px 0;">' . htmlspecialchars($inc['name']) . '<span style="float: right;">&nbsp;' . number_format($inc['amount'], 2) . '</span></p>';
    }

    $expense_html = empty($exp_arr) ? '<p style="margin: 0; padding: 2px 0; text-align: right; color:#999;">- ไม่มีรายการหัก -</p>' : '';
    foreach($exp_arr as $exp) {
        $expense_html .= '<p style="margin: 0; padding: 2px 0;">' . htmlspecialchars($exp['name']) . '<span style="float: right;">&nbsp;' . number_format($exp['amount'], 2) . '</span></p>';
    }

    $stmtTpl = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'template_payslip'");
    $html = $stmtTpl->fetchColumn();

    $appr_name = !empty($request['approver_name']) ? htmlspecialchars($request['approver_name']) : '...................................................';
    $appr_position = !empty($request['approver_position']) ? htmlspecialchars($request['approver_position']) : 'ผู้อนุมัติ/หัวหน้าหน่วยงาน';
    $appr_sig = !empty($request['approver_signature']) ? '<img src="' . htmlspecialchars($request['approver_signature']) . '" height="50">' : '...................................................';

    $replace_vars = [
        '{REQUEST_ID}' => str_pad($request['id'], 5, '0', STR_PAD_LEFT),
        '{ID}' => htmlspecialchars($request['id']),
        '{TRACKING_CODE}' => htmlspecialchars($request['tracking_code']),        
        '{QR_CODE}' => $qr_code_html, // แท็ก QR Code
        '{TITLE}' => htmlspecialchars($slip['title']),
        '{FULLNAME}' => htmlspecialchars($slip['fullname']),
        '{POSITION}' => htmlspecialchars($slip['position']),
        '{DEPARTMENT}' => htmlspecialchars($slip['department']),
        '{BANK_ACCOUNT}' => htmlspecialchars($salary['bank_account'] ?? '-'),
        '{REQ_MONTH}' => htmlspecialchars($slip['req_month']),
        '{REQ_YEAR}' => htmlspecialchars($slip['req_year']),
        '{INCOMES_HTML}' => $income_html,
        '{EXPENSES_HTML}' => $expense_html,
        '{TOTAL_INCOMES}' => number_format($total_earnings, 2),
        '{TOTAL_INCOME_TEXT}' =>  baht_text($net_pay),
        '{TOTAL_EXPENSES}' => number_format($total_deduct, 2),
        '{NET_PAY}' => number_format($net_pay, 2),
        '{APPR_SIGNATURE}' => $appr_sig,
        '{APPR_NAME}' => $appr_name,
        '{APPR_POSITION}' => $appr_position
    ];

    $html = str_replace(array_keys($replace_vars), array_values($replace_vars), $html);
}

$mpdf->WriteHTML($html);
$filename = strtoupper($type) . '_' . $request['tracking_code'] . '.pdf';
$mpdf->Output($filename, 'I'); 
?>