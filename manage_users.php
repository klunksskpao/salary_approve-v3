<?php
require_once 'db.php';

// ตรวจสอบสิทธิ์ ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานหน้านี้");
}

$message = '';

// ตรวจสอบและสร้างโฟลเดอร์เก็บลายเซ็นหากยังไม่มี
$target_dir = "signatures/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// ---------------------------------------------------------
// จัดการ POST Request (เพิ่ม / แก้ไข / ลบ)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // รับค่าจากฟอร์ม
    $id = $_POST['user_id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? ''); // เพิ่มบรรทัดนี้
    $role = $_POST['role'] ?? 'finance';

    // จัดการอัปโหลดรูปลายเซ็น (ถ้ามี)
    $signature_path = $_POST['old_signature'] ?? null;
    if (isset($_FILES["signature_image"]) && $_FILES["signature_image"]["size"] > 0) {
        $file_ext = strtolower(pathinfo($_FILES["signature_image"]["name"], PATHINFO_EXTENSION));
        // อนุญาตเฉพาะไฟล์ภาพ
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_filename = "sign_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
            $target_file = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES["signature_image"]["tmp_name"], $target_file)) {
                $signature_path = $target_file;
            } else {
                $message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์รูปภาพ";
            }
        } else {
            $message = "อนุญาตเฉพาะไฟล์ .jpg, .jpeg, .png, .gif เท่านั้น";
        }
    }

    try {
        if ($action === 'add' && empty($message)) {
            // เช็ค username ซ้ำ
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                $message = "ชื่อผู้ใช้งาน (Username) นี้มีในระบบแล้ว!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, position, signature_image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password, $role, $name, $position, $signature_path]);
                $message = "เพิ่มผู้ใช้งานสำเร็จเรียบร้อย";
            }

        } elseif ($action === 'edit' && empty($message)) {
            // บล็อก Update
            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, role=?, name=?, position=?, signature_image=? WHERE id=?");
                $stmt->execute([$username, $password, $role, $name, $position, $signature_path, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, name=?, position=?, signature_image=? WHERE id=?");
                $stmt->execute([$username, $role, $name, $position, $signature_path, $id]);
            }
            $message = "แก้ไขข้อมูลผู้ใช้งานสำเร็จ";

        } elseif ($action === 'delete') {
            // ไม่ให้ Admin ลบตัวเอง
            if ($id == $_SESSION['user_id']) {
                $message = "คุณไม่สามารถลบบัญชีของตนเองที่กำลังใช้งานอยู่ได้";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);
                $message = "ลบผู้ใช้งานสำเร็จ";
            }
        }
    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ---------------------------------------------------------
// ดึงข้อมูลสำหรับโหมด Edit (ถ้ามีการส่ง parameter ?edit=id)
// ---------------------------------------------------------
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

// ดึงรายชื่อผู้ใช้ทั้งหมด
$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน - ระบบเอกสารเงินเดือน</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; margin: 0; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { display: block; color: white; padding: 15px; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover { background: #495057; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { margin-top: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="password"], select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; color: white; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-primary { background: #007bff; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .alert { padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .sign-img { max-height: 40px; border: 1px solid #ccc; }
    </style>
</head>
<body>
<!-- v1
<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    <a href="import_csv.php">📁 นำเข้าข้อมูลเงินเดือน (CSV)</a>
    <a href="manage_users.php" style="background: #495057;">👥 จัดการผู้ใช้งาน</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>
    --v2
<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php">📁 นำเข้าข้อมูลเงินเดือน (CSV)</a>
        <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
    <?php endif; ?>
    
    <a href="profile.php">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>
-->
<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php">📁 นำเข้าเงินเดือน (CSV)</a>
        <a href="manage_users.php" style="background: #495057;">👥 จัดการผู้ใช้งาน</a>
        <a href="manage_templates.php">🎨 จัดการรูปแบบเอกสาร PDF</a>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'finance'])): ?>
        <a href="manage_salary.php">💰 จัดการฐานข้อมูลเงินเดือน</a>
    <?php endif; ?>
    
    <a href="profile.php">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>


<div class="main-content">
    <h2>👥 จัดการเจ้าหน้าที่ในระบบ</h2>

    <?php if ($message): ?>
        <div class="alert <?php echo strpos($message, 'ข้อผิดพลาด') !== false || strpos($message, 'ซ้ำ') !== false ? 'alert-error' : ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="container" style="background: #f8fbff; border: 1px solid #cce5ff;">
        <h3><?php echo $edit_user ? '✏️ แก้ไขข้อมูลผู้ใช้งาน' : '➕ เพิ่มผู้ใช้งานใหม่'; ?></h3>
        <form method="POST" action="manage_users.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
            <input type="hidden" name="user_id" value="<?php echo $edit_user['id'] ?? ''; ?>">
            <input type="hidden" name="old_signature" value="<?php echo $edit_user['signature_image'] ?? ''; ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล (ที่แสดงในระบบ/ในเอกสาร):</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>ตำแหน่งงาน:</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($edit_user['position'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>บทบาท (Role):</label>
                    <select name="role" required>
                        <option value="finance" <?php echo ($edit_user && $edit_user['role'] == 'finance') ? 'selected' : ''; ?>>เจ้าหน้าที่การเงิน (Finance)</option>
                        <option value="approver" <?php echo ($edit_user && $edit_user['role'] == 'approver') ? 'selected' : ''; ?>>ผู้อนุมัติ (Approver)</option>
                        <option value="admin" <?php echo ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>ผู้ดูแลระบบ (Admin)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ชื่อเข้าใช้งาน (Username):</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>รหัสผ่าน (Password):</label>
                    <input type="password" name="password" placeholder="<?php echo $edit_user ? 'เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน' : 'กำหนดรหัสผ่าน'; ?>" <?php echo $edit_user ? '' : 'required'; ?>>
                </div>
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label>อัปโหลดรูปลายเซ็น (เฉพาะผู้อนุมัติ หรือผู้ที่ต้องเซ็นเอกสาร | ไฟล์ .png โปร่งใสดีที่สุด):</label>
                <?php if ($edit_user && $edit_user['signature_image']): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?php echo htmlspecialchars($edit_user['signature_image']); ?>" class="sign-img" alt="ลายเซ็นปัจจุบัน">
                        <small style="color: green;">(มีลายเซ็นในระบบแล้ว)</small>
                    </div>
                <?php endif; ?>
                <input type="file" name="signature_image" accept="image/*">
            </div>

            <button type="submit" class="btn btn-success"><?php echo $edit_user ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มผู้ใช้งาน'; ?></button>
            <?php if ($edit_user): ?>
                <a href="manage_users.php" class="btn btn-warning">ยกเลิกการแก้ไข</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="container">
        <h3>📋 รายชื่อเจ้าหน้าที่ทั้งหมด</h3>
        <table>
            <thead>
            <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ตำแหน่ง</th> <th>Username</th>
                    <th>สิทธิ์การใช้งาน (Role)</th>
                    <th>ลายเซ็น</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $index => $u): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['position']); ?></td> <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td>
                        <?php 
                            if($u['role'] == 'admin') echo '🔴 Admin';
                            elseif($u['role'] == 'approver') echo '🟢 Approver';
                            else echo '🔵 Finance';
                        ?>
                    </td>
                    <td>
                        <?php if ($u['signature_image']): ?>
                            <img src="<?php echo htmlspecialchars($u['signature_image']); ?>" class="sign-img">
                        <?php else: ?>
                            <span style="color: #999;">ไม่มีลายเซ็น</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="manage_users.php?edit=<?php echo $u['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">แก้ไข</a>
                        
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="manage_users.php" style="display:inline-block;" onsubmit="return confirm('ยืนยันการลบผู้ใช้งานรายนี้?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">ลบ</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>