<?php
require_once 'db.php';

// ตรวจสอบสิทธิ์ ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานหน้านี้");
}

// ---------------------------------------------------------
// ระบบดาวน์โหลดไฟล์ CSV ตัวอย่าง (อัปเดตเพิ่ม เลขที่บัญชี)
// ---------------------------------------------------------
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sample_salary_data.csv');
    echo "\xEF\xBB\xBF"; 
    
    $output = fopen("php://output", "w");
    
    // สร้างหัวคอลัมน์ (เพิ่ม เลขที่บัญชี)
    fputcsv($output, ['เลขบัตรประชาชน', 'ชื่อ-นามสกุล', 'ตำแหน่ง', 'สังกัด', 'เลขที่บัญชี', 'เงินเดือนหลัก', 'ค่าตำแหน่ง', 'ค่าสหกรณ์', 'ฌกส.', 'หมายเหตุ']);
    
    // ใส่ข้อมูลจำลอง
    fputcsv($output, ['1234567890123', 'นายสมชาย รักดี', 'นักวิเคราะห์นโยบาย', 'กองยุทธศาสตร์', '123-4-56789-0', '25000.00', '1500.00', '-500.00', '-300.00', 'ปกติ']);
    fputcsv($output, ['9876543210987', 'นางสาวสมหญิง สวยงาม', 'เจ้าพนักงานการเงิน', 'กองคลัง', '098-7-65432-1', '32000.00', '0.00', '-1000.00', '-500.00', 'ไม่มี']);
    
    fclose($output);
    exit();
}

$message = '';
$message_type = 'success';

// ---------------------------------------------------------
// ระบบนำเข้าและประมวลผลไฟล์ CSV
// ---------------------------------------------------------
if (isset($_POST["import"])) {
    $month = $_POST['data_month'];
    $year = $_POST['data_year'];
    $fileName = $_FILES["file"]["tmp_name"];

    if ($_FILES["file"]["size"] > 0) {
        $file = fopen($fileName, "r");
        
        $headers = fgetcsv($file);
        $successCount = 0;
        
        try {
            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                // ข้อมูลพื้นฐาน 6 คอลัมน์แรก
                $id_card = isset($column[0]) ? trim($column[0]) : '';
                $fullname = isset($column[1]) ? trim($column[1]) : '';
                $position = isset($column[2]) ? trim($column[2]) : '';
                $department = isset($column[3]) ? trim($column[3]) : '';
                $bank_account = isset($column[4]) ? trim($column[4]) : ''; // เพิ่มตัวแปรเลขที่บัญชี
                $salary = isset($column[5]) ? (float)$column[5] : 0;
                
                // ข้อมูลหมายเหตุ (คอลัมน์สุดท้ายของแถวเสมอ)
                $last_index = count($column) - 1;
                $note = isset($column[$last_index]) ? trim($column[$last_index]) : '';

                $dynamic_incomes = [];
                $dynamic_expenses = [];

                // วนลูปอ่านคอลัมน์ตรงกลาง (เริ่มจากคอลัมน์ที่ 7 คือ index 6)
                for ($i = 6; $i < $last_index; $i++) {
                    if (isset($column[$i]) && trim($column[$i]) !== '') {
                        $val = (float)$column[$i];
                        $itemName = isset($headers[$i]) ? trim($headers[$i]) : "รายการอื่นๆ";

                        if ($val > 0) {
                            $dynamic_incomes[] = ['name' => $itemName, 'amount' => $val];
                        } elseif ($val < 0) {
                            $dynamic_expenses[] = ['name' => $itemName, 'amount' => abs($val)];
                        }
                    }
                }

                $json_incomes = json_encode($dynamic_incomes, JSON_UNESCAPED_UNICODE);
                $json_expenses = json_encode($dynamic_expenses, JSON_UNESCAPED_UNICODE);

                // บันทึกลงฐานข้อมูล (เพิ่ม bank_account)
                if (!empty($id_card)) {
                    $stmt = $pdo->prepare("INSERT INTO salary_data (id_card, fullname, position, department, bank_account, salary, dynamic_incomes, dynamic_expenses, note, data_month, data_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_card, $fullname, $position, $department, $bank_account, $salary, $json_incomes, $json_expenses, $note, $month, $year]);
                    $successCount++;
                }
            }
            $message = "นำเข้าข้อมูลสำเร็จจำนวน $successCount รายการ (ประจำเดือน $month/$year)";
        } catch (Exception $e) {
            $message = "เกิดข้อผิดพลาดในการนำเข้า: " . $e->getMessage();
            $message_type = 'error';
        }
        
        fclose($file);
    } else {
        $message = "ไฟล์ว่างเปล่าหรือไม่สามารถอ่านได้";
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>นำเข้าข้อมูลเงินเดือน (CSV) - Salary System</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; margin: 0; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { display: block; color: white; padding: 15px; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover { background: #495057; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        select, input[type="file"] { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #218838; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background: #e2e3e5; padding: 20px; border-radius: 4px; font-size: 14px; margin-top: 25px; border-left: 5px solid #007bff; }
        .btn-download { background: #17a2b8; color: white; padding: 6px 12px; font-size: 14px; text-decoration: none; border-radius: 4px; }
        .btn-download:hover { background: #138496; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php" style="background: #495057;">📁 นำเข้าเงินเดือน (CSV)</a>
        <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
        <a href="manage_templates.php">🎨 จัดการรูปแบบเอกสาร PDF</a>
    <?php endif; ?>
    <?php if (in_array($_SESSION['role'], ['admin', 'finance'])): ?>
        <a href="manage_salary.php">💰 จัดการฐานข้อมูลเงินเดือน</a>
    <?php endif; ?>
    <a href="profile.php">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="container">
        <h2>📁 นำเข้าข้อมูลเงินเดือนด้วยไฟล์ CSV</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label>ประจำเดือน:</label>
                    <select name="data_month" required>
                        <?php for($i=1; $i<=12; $i++): $m = str_pad($i, 2, "0", STR_PAD_LEFT); ?>
                            <option value="<?php echo $m; ?>" <?php echo date('m') == $m ? 'selected' : ''; ?>>เดือน <?php echo $m; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>ปี (ค.ศ.):</label>
                    <select name="data_year" required>
                        <?php 
                        $currentYear = date('Y');
                        for($y = $currentYear - 1; $y <= $currentYear + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $currentYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>เลือกไฟล์ CSV ของคุณ:</label>
                <input type="file" name="file" accept=".csv" required style="background: #f9f9f9;">
            </div>
            <button type="submit" name="import">🚀 อัปโหลดและนำเข้าข้อมูล</button>
        </form>

        <div class="info-box">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px;">
                <strong style="font-size: 16px; color: #007bff;">📋 กฎการสร้างไฟล์ CSV (อัปเดตใหม่)</strong>
                <a href="import_csv.php?download_sample=1" class="btn-download">📥 ดาวน์โหลดไฟล์ Template</a>
            </div>
            
            <ul style="line-height: 1.6; color: #333;">
                <li><strong>คอลัมน์ที่ 1:</strong> เลขบัตรประชาชน (13 หลัก)</li>
                <li><strong>คอลัมน์ที่ 2:</strong> ชื่อ-นามสกุล</li>
                <li><strong>คอลัมน์ที่ 3:</strong> ตำแหน่ง</li>
                <li><strong>คอลัมน์ที่ 4:</strong> สังกัด (หน่วยงาน)</li>
                <li><strong>คอลัมน์ที่ 5:</strong> เลขที่บัญชี</li>
                <li><strong>คอลัมน์ที่ 6:</strong> เงินเดือนหลัก</li>
                <li><strong>คอลัมน์ที่ 7 เป็นต้นไป:</strong> <span style="color: #28a745;"><b>รายรับ</b> (ใส่ตัวเลขบวก +)</span> หรือ <span style="color: #dc3545;"><b>รายจ่าย</b> (ใส่ตัวเลขลบ -)</span></li>
                <li><strong>คอลัมน์สุดท้ายของไฟล์:</strong> หมายเหตุ (เสมอ)</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>