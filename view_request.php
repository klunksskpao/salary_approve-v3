<?php
require_once 'db.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("ไม่พบรหัสคำขอ");
}

$request_id = $_GET['id'];
$role = $_SESSION['role'];

// 1. ดึงข้อมูลคำขอหลัก
$stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    die("ไม่พบข้อมูลคำขอนี้ในระบบ");
}

// 2. ดึงข้อมูลรายละเอียดตามประเภทคำขอ
$cert_data = null;
$payslip_data = null;
$salary_match = null;

if (in_array($request['request_type'], ['cert', 'both'])) {
    $stmtCert = $pdo->prepare("SELECT * FROM cert_requests WHERE request_id = ?");
    $stmtCert->execute([$request_id]);
    $cert_data = $stmtCert->fetch();
}

if (in_array($request['request_type'], ['payslip', 'both'])) {
    $stmtSlip = $pdo->prepare("SELECT * FROM payslip_requests WHERE request_id = ?");
    $stmtSlip->execute([$request_id]);
    $payslip_data = $stmtSlip->fetch();

    if ($payslip_data) {
        $stmtSalary = $pdo->prepare("SELECT * FROM salary_data WHERE id_card = ? AND data_month = ? AND data_year = ?");
        $stmtSalary->execute([$payslip_data['slip_id_card'], $payslip_data['req_month'], $payslip_data['req_year']]);
        $salary_match = $stmtSalary->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดคำขอ #<?php echo htmlspecialchars($request['tracking_code']); ?></title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 14px; color: white; font-weight: bold; }
        .bg-warning { background: #ffc107; color: #333; }
        .bg-info { background: #17a2b8; }
        .bg-success { background: #28a745; }
        .bg-danger { background: #dc3545; }
        .section-title { background: #007bff; color: white; padding: 10px; border-radius: 4px; margin-top: 20px; }
        table.detail-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.detail-table th, table.detail-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        table.detail-table th { background: #f8f9fa; width: 30%; }
        .salary-box { background: #e9ecef; border-left: 5px solid #17a2b8; padding: 15px; margin-top: 15px; border-radius: 4px; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; color: white; display: inline-block; margin-right: 5px; }
        .btn-primary { background: #007bff; }
        .btn-secondary { background: #6c757d; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #333; }
        .actions { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; }
        .signature-img { max-width: 200px; border: 1px dashed #ccc; padding: 5px; }
        .dynamic-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #ccc; margin-top: 10px; }
        .dynamic-table th, .dynamic-table td { border: 1px solid #ccc; padding: 8px; }
        .dynamic-table th { background: #f8f9fa; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>รายละเอียดคำขอรหัส: <?php echo htmlspecialchars($request['tracking_code']); ?></h2>
        <div>
            <?php
                $status = $request['status'];
                $badge_class = 'bg-warning';
                $status_th = 'รอตรวจสอบ';
                if($status == 'verified') { $badge_class = 'bg-info'; $status_th = 'รออนุมัติ'; }
                if($status == 'approved') { $badge_class = 'bg-success'; $status_th = 'อนุมัติแล้ว'; }
                if($status == 'rejected') { $badge_class = 'bg-danger'; $status_th = 'ถูกปฏิเสธ'; }
            ?>
            <span class="badge <?php echo $badge_class; ?>">สถานะ: <?php echo $status_th; ?></span>
            <a href="export_pdf.php?type=request_form&id=<?php echo $request['id']; ?>" target="_blank" class="btn btn-info" style="background: #17a2b8; border: none; margin-left: 5px;">🖨 พิมพ์แบบคำขอ</a>
            <a href="dashboard.php" class="btn btn-secondary">← กลับหน้าแดชบอร์ด</a>
        </div>
    </div>

    <p><strong>อีเมลผู้ขอ:</strong> <?php echo htmlspecialchars($request['email']); ?></p>
    <p><strong>วันที่ส่งคำขอ:</strong> <?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></p>
    
    <?php if ($status == 'rejected'): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
            <strong>เหตุผลที่ไม่อนุมัติ:</strong> <?php echo htmlspecialchars($request['reject_reason']); ?>
        </div>
    <?php endif; ?>

    <?php if ($cert_data): ?>
        <h3 class="section-title">📄 ข้อมูลคำขอหนังสือรับรองเงินเดือน</h3>
        <table class="detail-table">
            <tr><th>ประเภทบุคลากร</th><td><?php echo $cert_data['emp_type'] == 'A' ? 'ข้าราชการ' : 'พนักงานจ้าง'; ?></td></tr>
            <tr><th>ชื่อ-นามสกุล</th><td><?php echo htmlspecialchars($cert_data['title'] . $cert_data['fullname']); ?></td></tr>
            <tr><th>เลขบัตรประชาชน</th><td><?php echo htmlspecialchars($cert_data['id_card']); ?></td></tr>
            <tr><th>ตำแหน่ง</th><td><?php echo htmlspecialchars($cert_data['position']); ?></td></tr>
            <tr><th>สังกัด</th><td><?php echo htmlspecialchars($cert_data['department']); ?></td></tr>
            <tr><th>บรรจุเมื่อ</th><td><?php echo date('d/m/Y', strtotime($cert_data['join_date'])); ?></td></tr>
            <tr><th>อัตราเงินเดือน</th><td><?php echo number_format($cert_data['salary'], 2); ?> บาท</td></tr>
            <tr><th>รวมรายรับทั้งสิ้น</th><td><strong><?php echo number_format($cert_data['total_income'], 2); ?> บาท</strong></td></tr>
            <tr><th>ขอใบรับรองเพื่อ</th><td><?php echo htmlspecialchars($cert_data['purpose']); ?></td></tr>
            <tr><th>เบอร์โทรศัพท์</th><td><?php echo htmlspecialchars($cert_data['phone']); ?></td></tr>
            <?php if ($cert_data['emp_type'] == 'B'): ?>
                <tr><th>สัญญาจ้างเลขที่</th><td><?php echo htmlspecialchars($cert_data['contract_no']); ?></td></tr>
            <?php endif; ?>
            <tr>
                <th>ลายเซ็นผู้ขอ</th>
                <td>
                    <?php if ($cert_data['user_signature']): ?>
                        <img src="<?php echo htmlspecialchars($cert_data['user_signature']); ?>" class="signature-img" alt="ลายเซ็น">
                    <?php else: ?>
                        <i>ไม่มีลายเซ็น</i>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <?php if ($payslip_data): ?>
        <h3 class="section-title">💸 ข้อมูลคำขอสลิปเงินเดือน</h3>
        <table class="detail-table">
            <tr><th>ชื่อ-นามสกุล</th><td><?php echo htmlspecialchars($payslip_data['title'] . $payslip_data['fullname']); ?></td></tr>
            <tr><th>เลขบัตรประชาชน</th><td><?php echo htmlspecialchars($payslip_data['slip_id_card'] ?? '-'); ?></td></tr>
            <tr><th>ตำแหน่ง / สังกัด</th><td><?php echo htmlspecialchars($payslip_data['position'] . ' / ' . $payslip_data['department']); ?></td></tr>
            <tr><th>ประจำเดือน/ปี</th><td><?php echo htmlspecialchars($payslip_data['req_month'] . ' / ' . $payslip_data['req_year']); ?></td></tr>
        </table>

        <div class="salary-box">
            <h4>🔍 ผลการตรวจสอบข้อมูลในฐานข้อมูลเงินเดือน (สำหรับเดือน <?php echo htmlspecialchars($payslip_data['req_month'].'/'.$payslip_data['req_year']); ?>)</h4>
            
            <?php if ($salary_match): 
                $inc_arr = json_decode($salary_match['dynamic_incomes'], true) ?: [];
                $exp_arr = json_decode($salary_match['dynamic_expenses'], true) ?: [];
                $dyn_inc_total = array_sum(array_column($inc_arr, 'amount'));
                $dyn_exp_total = array_sum(array_column($exp_arr, 'amount'));
                $total_earnings = $salary_match['salary'] + $dyn_inc_total;
                $net_pay = $total_earnings - $dyn_exp_total;
            ?>
                <p style="color: green;"><strong>✔ พบข้อมูลในระบบ (พร้อมออกสลิป)</strong></p>
                <table class="dynamic-table">
                    <tr><th style="width: 50%;">รายได้ (Earnings)</th><th style="width: 50%;">รายการหัก (Deductions)</th></tr>
                    <tr>
                        <td style="vertical-align: top;">
                            <table style="width: 100%; border: none;">
                                <tr><td>เงินเดือนหลัก</td><td style="text-align: right; color: green;"><?php echo number_format($salary_match['salary'], 2); ?></td></tr>
                                <?php foreach($inc_arr as $inc): ?>
                                    <tr><td><?php echo htmlspecialchars($inc['name']); ?></td><td style="text-align: right; color: green;">+<?php echo number_format($inc['amount'], 2); ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                        <td style="vertical-align: top;">
                            <table style="width: 100%; border: none;">
                                <?php if(empty($exp_arr)): ?>
                                    <tr><td colspan="2" style="text-align: center; color: #999;">- ไม่มีรายการหัก -</td></tr>
                                <?php else: ?>
                                    <?php foreach($exp_arr as $exp): ?>
                                        <tr><td><?php echo htmlspecialchars($exp['name']); ?></td><td style="text-align: right; color: red;">-<?php echo number_format($exp['amount'], 2); ?></td></tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>
                    <tr style="background: #f8f9fa;">
                        <td><strong>รวมรายรับ:</strong> <span style="float: right; color: green;"><strong><?php echo number_format($total_earnings, 2); ?> บาท</strong></span></td>
                        <td><strong>รวมรายจ่าย:</strong> <span style="float: right; color: red;"><strong><?php echo number_format($dyn_exp_total, 2); ?> บาท</strong></span></td>
                    </tr>
                    <tr><td colspan="2" style="padding: 15px; text-align: center; background: #d4edda; color: #155724;"><strong>เงินได้สุทธิ (Net Pay): <?php echo number_format($net_pay, 2); ?> บาท</strong></td></tr>
                </table>
            <?php else: ?>
                <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb;">
                    <p style="margin: 0;"><strong>❌ ไม่พบข้อมูลเงินเดือนในระบบ</strong> <br>
                    สำหรับเลขบัตรประชาชน: <strong><?php echo htmlspecialchars($payslip_data['slip_id_card'] ?? 'ไม่ได้ระบุ'); ?></strong></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="actions">
        <?php if (in_array($role, ['admin', 'finance'])): ?>
            <a href="edit_request.php?id=<?php echo $request['id']; ?>" class="btn btn-warning" style="margin-bottom: 10px;">✏️ แก้ไขข้อมูลคำขอ</a>
        <?php endif; ?>

        <div style="display: flex; gap: 10px; justify-content: center; align-items: center; flex-wrap: wrap; margin-top: 15px;">
            <?php if ($role === 'finance' && $status === 'pending'): ?>
                <form method="POST" action="dashboard.php" style="margin: 0;">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="verify">
                    <button type="submit" class="btn btn-success" onclick="return confirm('ยืนยันว่าข้อมูลถูกต้องและตรวจสอบผ่านแล้ว?');">✔ ตรวจสอบผ่าน (ส่งให้ผู้อนุมัติ)</button>
                </form>
            <?php endif; ?>

            <?php if ($role === 'approver' && $status === 'verified'): ?>
                <form method="POST" action="dashboard.php" style="margin: 0;">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success" onclick="return confirm('ยืนยันการอนุมัติเอกสารนี้?');">✅ อนุมัติเอกสาร</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($status, ['pending', 'verified']) && in_array($role, ['finance', 'approver'])): ?>
                <form method="POST" action="dashboard.php" style="margin: 0; display: flex; align-items: center;">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="text" name="reject_reason" placeholder="โปรดระบุเหตุผล" required style="padding: 10px; margin-right: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('ยืนยันการปฏิเสธคำขอ?');">✖ ปฏิเสธคำขอ</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($status === 'approved'): ?>
            <div style="background: #d4edda; padding: 15px; border-radius: 4px; color: #155724; margin-top: 20px;">
                <strong>เอกสารนี้ได้รับการอนุมัติเรียบร้อยแล้ว</strong><br><br>
                <?php if ($cert_data): ?>
                    <a href="export_pdf.php?type=cert&id=<?php echo $request['id']; ?>" target="_blank" class="btn btn-primary">🖨 พิมพ์ใบรับรองเงินเดือน</a>
                <?php endif; ?>
                <?php if ($payslip_data): ?>
                    <a href="export_pdf.php?type=payslip&id=<?php echo $request['id']; ?>" target="_blank" class="btn btn-primary">🖨 พิมพ์สลิปเงินเดือน</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>