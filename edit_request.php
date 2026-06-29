<?php
require_once 'db.php';

// ตรวจสอบการล็อกอิน และสิทธิ์ (ต้องเป็น Admin หรือ Finance เท่านั้น)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'finance'])) {
    die("ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์แก้ไขข้อมูลคำขอนี้");
}

if (!isset($_GET['id']) && !isset($_POST['request_id'])) {
    die("ไม่พบรหัสคำขอ");
}

$request_id = $_GET['id'] ?? $_POST['request_id'];
$message = '';

// ---------------------------------------------------------
// จัดการ POST Request (เมื่อกดบันทึกข้อมูลการแก้ไข)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // อัปเดตข้อมูลใบรับรอง (ถ้ามีการขอ)
        if (isset($_POST['cert_edit'])) {
            $stmtCert = $pdo->prepare("UPDATE cert_requests SET 
                emp_type=?, title=?, fullname=?, id_card=?, position=?, department=?, 
                join_date=?, salary=?, position_allowance=?, monthly_remuneration=?, 
                living_allowance=?, total_income=?, purpose=?, phone=?, 
                contract_no=?, contract_date=?, contract_end_date=? 
                WHERE request_id=?");
            
            $stmtCert->execute([
                $_POST['emp_type'], $_POST['cert_title'], $_POST['cert_fullname'], $_POST['cert_id_card'], 
                $_POST['cert_position'], $_POST['cert_department'], $_POST['cert_join_date'], 
                $_POST['salary'], $_POST['position_allowance'], $_POST['monthly_remuneration'], 
                $_POST['living_allowance'], $_POST['total_income'], $_POST['purpose'], $_POST['phone'], 
                $_POST['contract_no'], $_POST['contract_date'], $_POST['contract_end_date'], 
                $request_id
            ]);
        }

        /*
        // อัปเดตข้อมูลสลิปเงินเดือน (ถ้ามีการขอ)
        if (isset($_POST['payslip_edit'])) {
            $stmtSlip = $pdo->prepare("UPDATE payslip_requests SET 
                title=?, fullname=?, position=?, department=?, req_month=?, req_year=? 
                WHERE request_id=?");
                
            $stmtSlip->execute([
                $_POST['slip_title'], $_POST['slip_fullname'], $_POST['slip_position'], 
                $_POST['slip_department'], $_POST['req_month'], $_POST['req_year'], 
                $request_id
            ]);
        }
        */
        // อัปเดตข้อมูลสลิปเงินเดือน (ถ้ามีการขอ)
        if (isset($_POST['payslip_edit'])) {
            $stmtSlip = $pdo->prepare("UPDATE payslip_requests SET 
                title=?, fullname=?, slip_id_card=?, position=?, department=?, req_month=?, req_year=? 
                WHERE request_id=?");
                
            $stmtSlip->execute([
                $_POST['slip_title'], $_POST['slip_fullname'], $_POST['slip_id_card'], $_POST['slip_position'], 
                $_POST['slip_department'], $_POST['req_month'], $_POST['req_year'], 
                $request_id
            ]);
        }

        
        $pdo->commit();
        
        // บันทึกเสร็จแล้วเด้งกลับไปหน้าดูรายละเอียด
        header("Location: view_request.php?id=" . $request_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}

// ---------------------------------------------------------
// ดึงข้อมูลเดิมมาแสดงในฟอร์ม
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

$cert_data = null;
$payslip_data = null;

if (in_array($request['request_type'], ['cert', 'both'])) {
    $stmtCert = $pdo->prepare("SELECT * FROM cert_requests WHERE request_id = ?");
    $stmtCert->execute([$request_id]);
    $cert_data = $stmtCert->fetch();
}

if (in_array($request['request_type'], ['payslip', 'both'])) {
    $stmtSlip = $pdo->prepare("SELECT * FROM payslip_requests WHERE request_id = ?");
    $stmtSlip->execute([$request_id]);
    $payslip_data = $stmtSlip->fetch();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลคำขอ #<?php echo htmlspecialchars($request['tracking_code']); ?></title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; border-bottom: 2px solid #ffc107; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="number"], input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .section-box { border: 1px solid #007bff; padding: 20px; margin-top: 20px; border-radius: 5px; background-color: #f8fbff; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; color: white; display: inline-block; }
        .btn-success { background: #28a745; width: 100%; margin-top: 20px; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
    <script>
        // ฟังก์ชันคำนวณเงินรวมอัตโนมัติ (เหมือนหน้า index.html)
        function calculateTotal() {
            let salary = parseFloat(document.getElementById('salary').value) || 0;
            let posAllowance = parseFloat(document.getElementById('position_allowance').value) || 0;
            let monthlyRem = parseFloat(document.getElementById('monthly_remuneration').value) || 0;
            let livingAllowance = parseFloat(document.getElementById('living_allowance').value) || 0;
            let total = salary + posAllowance + monthlyRem + livingAllowance;
            document.getElementById('total_income').value = total.toFixed(2);
        }
    </script>
</head>
<body>

<div class="container">
    <h2>✏️ แก้ไขข้อมูลคำขอ: <?php echo htmlspecialchars($request['tracking_code']); ?></h2>
    
    <?php if ($message): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 15px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_request.php">
        <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">

        <?php if ($cert_data): ?>
            <input type="hidden" name="cert_edit" value="1">
            <div class="section-box">
                <h3 style="margin-top:0;">📄 แก้ไขข้อมูลหนังสือรับรองเงินเดือน</h3>
                
                <div class="form-group">
                    <label>ประเภทบุคลากร:</label>
                    <input type="radio" name="emp_type" value="A" <?php echo $cert_data['emp_type'] == 'A' ? 'checked' : ''; ?>> ข้าราชการ
                    <input type="radio" name="emp_type" value="B" <?php echo $cert_data['emp_type'] == 'B' ? 'checked' : ''; ?>> พนักงานจ้าง
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>คำนำหน้า:</label>
                        <select name="cert_title">
                            <option value="นาย" <?php echo $cert_data['title'] == 'นาย' ? 'selected' : ''; ?>>นาย</option>
                            <option value="นาง" <?php echo $cert_data['title'] == 'นาง' ? 'selected' : ''; ?>>นาง</option>
                            <option value="นางสาว" <?php echo $cert_data['title'] == 'นางสาว' ? 'selected' : ''; ?>>นางสาว</option>
                        </select>
                    </div>
                    <div class="form-group"><label>ชื่อ-นามสกุล:</label><input type="text" name="cert_fullname" value="<?php echo htmlspecialchars($cert_data['fullname']); ?>"></div>
                    <div class="form-group"><label>เลขบัตรประชาชน:</label><input type="text" name="cert_id_card" value="<?php echo htmlspecialchars($cert_data['id_card']); ?>"></div>
                    <div class="form-group"><label>ตำแหน่ง:</label><input type="text" name="cert_position" value="<?php echo htmlspecialchars($cert_data['position']); ?>"></div>
                    <div class="form-group"><label>สังกัด:</label><input type="text" name="cert_department" value="<?php echo htmlspecialchars($cert_data['department']); ?>"></div>
                    <div class="form-group"><label>บรรจุเมื่อ:</label><input type="date" name="cert_join_date" value="<?php echo $cert_data['join_date']; ?>"></div>
                    <div class="form-group"><label>เบอร์โทรศัพท์:</label><input type="text" name="phone" value="<?php echo htmlspecialchars($cert_data['phone']); ?>"></div>
                    <div class="form-group"><label>เพื่อไปใช้ (วัตถุประสงค์):</label><input type="text" name="purpose" value="<?php echo htmlspecialchars($cert_data['purpose']); ?>"></div>
                </div>

                <hr style="border:0; border-top:1px dashed #ccc; margin: 20px 0;">
                <h4>ข้อมูลเงินเดือน</h4>
                <div class="grid-2">
                    <div class="form-group"><label>อัตราเงินเดือน:</label><input type="number" step="0.01" name="salary" id="salary" value="<?php echo $cert_data['salary']; ?>" oninput="calculateTotal()"></div>
                    <div class="form-group"><label>เงินประจำตำแหน่ง:</label><input type="number" step="0.01" name="position_allowance" id="position_allowance" value="<?php echo $cert_data['position_allowance']; ?>" oninput="calculateTotal()"></div>
                    <div class="form-group"><label>เงินค่าตอบแทนรายเดือน:</label><input type="number" step="0.01" name="monthly_remuneration" id="monthly_remuneration" value="<?php echo $cert_data['monthly_remuneration']; ?>" oninput="calculateTotal()"></div>
                    <div class="form-group"><label>ค่าครองชีพ:</label><input type="number" step="0.01" name="living_allowance" id="living_allowance" value="<?php echo $cert_data['living_allowance']; ?>" oninput="calculateTotal()"></div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>รวมรายรับทั้งสิ้น (ระบบคำนวณอัตโนมัติ):</label>
                        <input type="number" step="0.01" name="total_income" id="total_income" value="<?php echo $cert_data['total_income']; ?>" readonly style="background: #e9ecef; font-weight: bold;">
                    </div>
                </div>

                <hr style="border:0; border-top:1px dashed #ccc; margin: 20px 0;">
                <h4>เฉพาะพนักงานจ้าง</h4>
                <div class="grid-2">
                    <div class="form-group"><label>สัญญาจ้างเลขที่:</label><input type="text" name="contract_no" value="<?php echo htmlspecialchars($cert_data['contract_no']); ?>"></div>
                    <div class="form-group"><label>สัญญาจ้างลงวันที่:</label><input type="date" name="contract_date" value="<?php echo $cert_data['contract_date']; ?>"></div>
                    <div class="form-group"><label>สัญญาจ้างสิ้นสุดวันที่:</label><input type="date" name="contract_end_date" value="<?php echo $cert_data['contract_end_date']; ?>"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($payslip_data): ?>
            <input type="hidden" name="payslip_edit" value="1">
            <div class="section-box" style="border-color: #28a745; background-color: #f4fff6;">
                <h3 style="margin-top:0;">💸 แก้ไขข้อมูลขอสลิปเงินเดือน</h3>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>คำนำหน้า:</label>
                        <select name="slip_title">
                            <option value="นาย" <?php echo $payslip_data['title'] == 'นาย' ? 'selected' : ''; ?>>นาย</option>
                            <option value="นาง" <?php echo $payslip_data['title'] == 'นาง' ? 'selected' : ''; ?>>นาง</option>
                            <option value="นางสาว" <?php echo $payslip_data['title'] == 'นางสาว' ? 'selected' : ''; ?>>นางสาว</option>
                        </select>
                    </div>
                    <div class="form-group"><label>ชื่อ-นามสกุล:</label><input type="text" name="slip_fullname" value="<?php echo htmlspecialchars($payslip_data['fullname']); ?>"></div>
                    <div class="form-group"><label>เลขบัตรประชาชน (13 หลัก):</label><input type="text" name="slip_id_card" maxlength="13" value="<?php echo htmlspecialchars($payslip_data['slip_id_card'] ?? ''); ?>"></div>
                    <div class="form-group"><label>ตำแหน่ง:</label><input type="text" name="slip_position" value="<?php echo htmlspecialchars($payslip_data['position']); ?>"></div>
                    <div class="form-group"><label>สังกัด:</label><input type="text" name="slip_department" value="<?php echo htmlspecialchars($payslip_data['department']); ?>"></div>
                    <div class="form-group">
                        <label>ประจำเดือน (01-12):</label>
                        <input type="text" name="req_month" maxlength="2" value="<?php echo htmlspecialchars($payslip_data['req_month']); ?>">
                    </div>
                    <div class="form-group">
                        <label>ปี (ค.ศ.):</label>
                        <input type="text" name="req_year" maxlength="4" value="<?php echo htmlspecialchars($payslip_data['req_year']); ?>">
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="view_request.php?id=<?php echo $request_id; ?>" class="btn btn-secondary" style="margin-right: 10px;">ยกเลิก</a>
            <button type="submit" class="btn btn-success" style="width: auto;">💾 บันทึกการแก้ไขข้อมูล</button>
        </div>
    </form>
</div>

</body>
</html>