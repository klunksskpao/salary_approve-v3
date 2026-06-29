<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'finance'])) {
    die("ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานหน้านี้");
}

$message = '';
$message_type = 'success';

// ---------------------------------------------------------
// 1. จัดการ POST Request (เพิ่ม / แก้ไข / ลบ)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $id = $_POST['record_id'] ?? '';
    $id_card = trim($_POST['id_card'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $bank_account = trim($_POST['bank_account'] ?? ''); // เพิ่มบรรทัดนี้
    $salary = $_POST['salary'] ?? 0;
    $note = trim($_POST['note'] ?? '');
    $month = $_POST['data_month'] ?? '';
    $year = $_POST['data_year'] ?? '';

    // แปลงข้อมูล รายรับ (Array) ให้เป็น JSON
    $dynamic_incomes = [];
    if (isset($_POST['inc_name'])) {
        for ($i = 0; $i < count($_POST['inc_name']); $i++) {
            if (!empty($_POST['inc_name'][$i]) && $_POST['inc_amount'][$i] !== '') {
                $dynamic_incomes[] = ['name' => $_POST['inc_name'][$i], 'amount' => (float)$_POST['inc_amount'][$i]];
            }
        }
    }
    $json_incomes = json_encode($dynamic_incomes, JSON_UNESCAPED_UNICODE);

    // แปลงข้อมูล รายจ่าย (Array) ให้เป็น JSON
    $dynamic_expenses = [];
    if (isset($_POST['exp_name'])) {
        for ($i = 0; $i < count($_POST['exp_name']); $i++) {
            if (!empty($_POST['exp_name'][$i]) && $_POST['exp_amount'][$i] !== '') {
                $dynamic_expenses[] = ['name' => $_POST['exp_name'][$i], 'amount' => (float)$_POST['exp_amount'][$i]];
            }
        }
    }
    $json_expenses = json_encode($dynamic_expenses, JSON_UNESCAPED_UNICODE);

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO salary_data (id_card, fullname, position, department, bank_account, salary, dynamic_incomes, dynamic_expenses, note, data_month, data_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_card, $fullname, $position, $department, $bank_account, $salary, $json_incomes, $json_expenses, $note, $month, $year]);
            $message = "เพิ่มข้อมูลเงินเดือนใหม่สำเร็จ";
            
        } elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE salary_data SET id_card=?, fullname=?, position=?, department=?, bank_account=?, salary=?, dynamic_incomes=?, dynamic_expenses=?, note=?, data_month=?, data_year=? WHERE id=?");
            $stmt->execute([$id_card, $fullname, $position, $department, $bank_account, $salary, $json_incomes, $json_expenses, $note, $month, $year, $id]);
            $message = "แก้ไขข้อมูลเงินเดือนสำเร็จ";
            
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM salary_data WHERE id=?");
            $stmt->execute([$id]);
            $message = "ลบข้อมูลเงินเดือนเรียบร้อยแล้ว";
        }
    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ---------------------------------------------------------
// 2. ดึงข้อมูลและค้นหา
// ---------------------------------------------------------
$filter_month = $_GET['filter_month'] ?? date('m');
$filter_year = $_GET['filter_year'] ?? date('Y');
$search_name = trim($_GET['search_name'] ?? '');

$sql = "SELECT * FROM salary_data WHERE data_month = ? AND data_year = ?";
$params = [$filter_month, $filter_year];

if (!empty($search_name)) {
    $sql .= " AND (fullname LIKE ? OR id_card LIKE ?)";
    $params[] = "%$search_name%";
    $params[] = "%$search_name%";
}

$sql .= " ORDER BY fullname ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salary_records = $stmt->fetchAll();

$edit_record = null;
$existing_incomes = [];
$existing_expenses = [];
if (isset($_GET['edit'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM salary_data WHERE id = ?");
    $stmtEdit->execute([$_GET['edit']]);
    $edit_record = $stmtEdit->fetch();
    
    if ($edit_record) {
        $existing_incomes = json_decode($edit_record['dynamic_incomes'], true) ?: [];
        $existing_expenses = json_decode($edit_record['dynamic_expenses'], true) ?: [];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการฐานข้อมูลเงินเดือน - Salary System</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; margin: 0; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { display: block; color: white; padding: 15px; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover { background: #495057; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-primary { background: #007bff; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .dynamic-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .dynamic-box { background: #f9f9f9; padding: 15px; border: 1px dashed #ccc; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php">📁 นำเข้าเงินเดือน (CSV)</a>
        <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
        <a href="manage_templates.php">🎨 จัดการรูปแบบเอกสาร PDF</a>
    <?php endif; ?>
    <a href="manage_salary.php" style="background: #495057;">💰 จัดการฐานข้อมูลเงินเดือน</a>
    <a href="profile.php">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>

<div class="main-content">
    <h2>💰 จัดการฐานข้อมูลเงินเดือนพนักงาน (Dynamic 100%)</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="container" style="background: #f8fbff; border: 1px solid #cce5ff;">
        <h3 style="margin-top: 0;"><?php echo $edit_record ? '✏️ แก้ไขข้อมูลพนักงาน' : '➕ เพิ่มข้อมูลเงินเดือนรายบุคคล'; ?></h3>
        <form method="POST" action="manage_salary.php">
            <input type="hidden" name="action" value="<?php echo $edit_record ? 'edit' : 'add'; ?>">
            <input type="hidden" name="record_id" value="<?php echo $edit_record['id'] ?? ''; ?>">
<!--
            <div class="grid-3">
                <div class="form-group"><label>เลขบัตรประชาชน (13 หลัก):</label><input type="text" name="id_card" maxlength="13" value="<?php echo htmlspecialchars($edit_record['id_card'] ?? ''); ?>" required></div>
                <div class="form-group"><label>ชื่อ-นามสกุล:</label><input type="text" name="fullname" value="<?php echo htmlspecialchars($edit_record['fullname'] ?? ''); ?>" required></div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;"><label>เดือน (01-12):</label><input type="text" name="data_month" maxlength="2" value="<?php echo htmlspecialchars($edit_record['data_month'] ?? date('m')); ?>" required></div>
                    <div class="form-group" style="flex: 1;"><label>ปี (ค.ศ.):</label><input type="text" name="data_year" maxlength="4" value="<?php echo htmlspecialchars($edit_record['data_year'] ?? date('Y')); ?>" required></div>
                </div>
            </div>
-->
            <div class="grid-3">
                <div class="form-group"><label>เลขบัตรประชาชน:</label><input type="text" name="id_card" maxlength="13" value="<?php echo htmlspecialchars($edit_record['id_card'] ?? ''); ?>" required></div>
                <div class="form-group"><label>ชื่อ-นามสกุล:</label><input type="text" name="fullname" value="<?php echo htmlspecialchars($edit_record['fullname'] ?? ''); ?>" required></div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;"><label>เดือน (01-12):</label><input type="text" name="data_month" maxlength="2" value="<?php echo htmlspecialchars($edit_record['data_month'] ?? date('m')); ?>" required></div>
                    <div class="form-group" style="flex: 1;"><label>ปี (ค.ศ.):</label><input type="text" name="data_year" maxlength="4" value="<?php echo htmlspecialchars($edit_record['data_year'] ?? date('Y')); ?>" required></div>
                </div>
            </div>
    <!--    
            <div class="grid-3" style="margin-top: -10px; margin-bottom: 10px;">
                <div class="form-group"><label>ตำแหน่ง:</label><input type="text" name="position" value="<?php echo htmlspecialchars($edit_record['position'] ?? ''); ?>"></div>
                <div class="form-group"><label>สังกัด (หน่วยงาน):</label><input type="text" name="department" value="<?php echo htmlspecialchars($edit_record['department'] ?? ''); ?>"></div>
            </div>
    -->
            <div class="grid-3" style="margin-top: -10px; margin-bottom: 10px;">
                <div class="form-group"><label>ตำแหน่ง:</label><input type="text" name="position" value="<?php echo htmlspecialchars($edit_record['position'] ?? ''); ?>"></div>
                <div class="form-group"><label>สังกัด (หน่วยงาน):</label><input type="text" name="department" value="<?php echo htmlspecialchars($edit_record['department'] ?? ''); ?>"></div>
                <div class="form-group"><label>เลขที่บัญชี (Bank Account):</label><input type="text" name="bank_account" value="<?php echo htmlspecialchars($edit_record['bank_account'] ?? ''); ?>"></div>
            </div>            
            <div class="dynamic-box">
                <h4 style="margin-top: 0; color: #28a745;">💵 หมวดรายรับ</h4>
                <div class="form-group" style="max-width: 300px;"><label>เงินเดือนหลัก:</label><input type="number" step="0.01" name="salary" value="<?php echo $edit_record['salary'] ?? '0'; ?>" required></div>
                
                <label>รายรับอื่นๆ (เพิ่มได้ไม่จำกัด):</label>
                <div id="income_container">
                    <?php foreach ($existing_incomes as $inc): ?>
                        <div class="dynamic-row">
                            <input type="text" name="inc_name[]" value="<?php echo htmlspecialchars($inc['name']); ?>" placeholder="ชื่อรายการ เช่น โบนัส, ค่าล่วงเวลา" style="flex: 2;">
                            <input type="number" step="0.01" name="inc_amount[]" value="<?php echo htmlspecialchars($inc['amount']); ?>" placeholder="จำนวนเงิน" style="flex: 1;">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✖ ลบ</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-success btn-sm" onclick="addDynamicField('income_container', 'inc_name[]', 'inc_amount[]')">+ เพิ่มรายการรายรับ</button>
            </div>

            <div class="dynamic-box">
                <h4 style="margin-top: 0; color: #dc3545;">💸 หมวดรายจ่าย</h4>
                <label>รายการหัก/รายจ่าย (เพิ่มได้ไม่จำกัด):</label>
                <div id="expense_container">
                    <?php foreach ($existing_expenses as $exp): ?>
                        <div class="dynamic-row">
                            <input type="text" name="exp_name[]" value="<?php echo htmlspecialchars($exp['name']); ?>" placeholder="ชื่อรายการ เช่น ค่าสหกรณ์, ฌกส." style="flex: 2;">
                            <input type="number" step="0.01" name="exp_amount[]" value="<?php echo htmlspecialchars($exp['amount']); ?>" placeholder="จำนวนเงิน" style="flex: 1;">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✖ ลบ</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-danger btn-sm" style="background: #c82333;" onclick="addDynamicField('expense_container', 'exp_name[]', 'exp_amount[]')">+ เพิ่มรายการรายจ่าย</button>
            </div>

            <div class="form-group"><label>หมายเหตุ:</label><input type="text" name="note" value="<?php echo htmlspecialchars($edit_record['note'] ?? ''); ?>"></div>

            <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 10px 20px;"><?php echo $edit_record ? '💾 บันทึกการแก้ไข' : '➕ บันทึกข้อมูล'; ?></button>
            <?php if ($edit_record): ?>
                <a href="manage_salary.php" class="btn btn-warning" style="font-size: 16px; padding: 10px 20px;">ยกเลิก</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="container">
        <form method="GET" action="manage_salary.php" style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; background: #f1f1f1; padding: 15px; border-radius: 5px;">
            <div><label>เดือน:</label>
                <select name="filter_month">
                    <?php for($i=1; $i<=12; $i++): $m = str_pad($i, 2, "0", STR_PAD_LEFT); ?>
                        <option value="<?php echo $m; ?>" <?php echo $filter_month == $m ? 'selected' : ''; ?>>เดือน <?php echo $m; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div><label>ปี (ค.ศ.):</label><input type="text" name="filter_year" value="<?php echo htmlspecialchars($filter_year); ?>" style="width: 100px;"></div>
            <div><label>ค้นหาชื่อ / บัตร:</label><input type="text" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" style="width: 200px;"></div>
            <button type="submit" class="btn btn-primary">🔍 กรองข้อมูล</button>
            <?php if(!empty($search_name)): ?>
                <a href="manage_salary.php?filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>" class="btn btn-secondary" style="background: #6c757d;">ล้างคำค้นหา</a>
            <?php endif; ?>
        </form>

        <h3 style="margin-top: 0;">📋 รายการข้อมูลเงินเดือน</h3>
        <table>
            <thead>
                <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <th>เงินเดือนหลัก</th>
                    <th>รายรับอื่นๆ</th>
                    <th>รายจ่ายรวม</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salary_records as $row): 
                    $inc_arr = json_decode($row['dynamic_incomes'], true) ?: [];
                    $exp_arr = json_decode($row['dynamic_expenses'], true) ?: [];
                    
                    $dyn_inc_total = array_sum(array_column($inc_arr, 'amount'));
                    $dyn_exp_total = array_sum(array_column($exp_arr, 'amount'));
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                    <td><?php echo number_format($row['salary'], 2); ?></td>
                    <td style="color: green;">+<?php echo number_format($dyn_inc_total, 2); ?></td>
                    <td style="color: red;">-<?php echo number_format($dyn_exp_total, 2); ?></td>
                    <td>
                        <a href="manage_salary.php?edit=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">แก้ไข</a>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('ลบข้อมูล?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function addDynamicField(containerId, nameInput, amountInput) {
        const container = document.getElementById(containerId);
        const div = document.createElement('div');
        div.className = 'dynamic-row';
        div.innerHTML = `
            <input type="text" name="${nameInput}" placeholder="ระบุชื่อรายการ..." style="flex: 2;" required>
            <input type="number" step="0.01" name="${amountInput}" placeholder="จำนวนเงิน" style="flex: 1;" required>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✖ ลบ</button>
        `;
        container.appendChild(div);
    }
</script>
</body>
</html>