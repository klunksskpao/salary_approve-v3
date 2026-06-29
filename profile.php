<?php
require_once 'db.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success'; // success หรือ error

// ตรวจสอบและสร้างโฟลเดอร์เก็บลายเซ็นหากยังไม่มี
$target_dir = "signatures/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// ---------------------------------------------------------
// จัดการ POST Request (เมื่อกดบันทึกข้อมูล)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // ดึงข้อมูลเดิมมาเพื่อเอา path ลายเซ็นเก่า
    $stmt_old = $pdo->prepare("SELECT signature_image FROM users WHERE id = ?");
    $stmt_old->execute([$user_id]);
    $old_data = $stmt_old->fetch();
    $signature_path = $old_data['signature_image'];

    // จัดการอัปโหลดรูปลายเซ็น (ถ้ามีการเลือกไฟล์ใหม่)
    if (isset($_FILES["signature_image"]) && $_FILES["signature_image"]["size"] > 0) {
        $file_ext = strtolower(pathinfo($_FILES["signature_image"]["name"], PATHINFO_EXTENSION));
        // อนุญาตเฉพาะไฟล์ภาพ
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_filename = "sign_user_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["signature_image"]["tmp_name"], $target_file)) {
                $signature_path = $target_file;
                
                // ลบไฟล์เก่าทิ้งเพื่อไม่ให้รก Server (ถ้ามีและไม่ใช่ค่าว่าง)
                if (!empty($old_data['signature_image']) && file_exists($old_data['signature_image'])) {
                    unlink($old_data['signature_image']);
                }
            } else {
                $message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์รูปภาพ";
                $message_type = 'error';
            }
        } else {
            $message = "อนุญาตเฉพาะไฟล์ .jpg, .jpeg, .png, .gif เท่านั้น";
            $message_type = 'error';
        }
    }

    // ถ้าไม่มี error จากการอัปโหลด ให้ทำการอัปเดตข้อมูล
    if (empty($message)) {
        try {
            if (!empty($password)) {
                // อัปเดตแบบเปลี่ยนรหัสผ่านด้วย
                $stmt = $pdo->prepare("UPDATE users SET name=?, position=?, password=?, signature_image=? WHERE id=?");
                $stmt->execute([$name, $position, $password, $signature_path, $user_id]);
            } else {
                // อัปเดตเฉพาะชื่อและลายเซ็น
                $stmt = $pdo->prepare("UPDATE users SET name=?, position=?, signature_image=? WHERE id=?");
                $stmt->execute([$name, $position, $signature_path, $user_id]);
            }
            
            // อัปเดต Session ชื่อให้เป็นปัจจุบัน
            $_SESSION['name'] = $name;
            $message = "บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ---------------------------------------------------------
// ดึงข้อมูลปัจจุบันมาแสดงในฟอร์ม
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลส่วนตัว - ระบบเอกสารเงินเดือน</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; margin: 0; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { display: block; color: white; padding: 15px; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover { background: #495057; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin: auto; margin-top: 20px;}
        h2 { margin-top: 0; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
        input[type="file"] { border: 1px solid #ccc; padding: 10px; width: 100%; box-sizing: border-box; border-radius: 4px; background: #f9f9f9; }
        .btn-success { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        .btn-success:hover { background: #218838; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .sign-preview { max-width: 100%; max-height: 100px; border: 1px dashed #aaa; padding: 10px; margin-top: 10px; background: #fff; }
        .info-text { font-size: 14px; color: #888; margin-top: 5px; display: block; }
    </style>
</head>
<body>
<!--
<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php">📁 นำเข้าข้อมูลเงินเดือน</a>
        <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
    <?php endif; ?>
    
    <a href="profile.php" style="background: #495057;">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>
    -->
<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php">📊 แดชบอร์ด (Dashboard)</a>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php">📁 นำเข้าเงินเดือน (CSV)</a>
        <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
        <a href="manage_templates.php">🎨 จัดการรูปแบบเอกสาร PDF</a>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'finance'])): ?>
        <a href="manage_salary.php">💰 จัดการฐานข้อมูลเงินเดือน</a>
    <?php endif; ?>
    
    <a href="profile.php" style="background: #495057;">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>


<div class="main-content">
    
    <div class="container">
        <h2>👤 แก้ไขข้อมูลส่วนตัว</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>ชื่อผู้ใช้งาน (Username):</label>
                <input type="text" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled style="background: #e9ecef;">
                <span class="info-text">* หากต้องการเปลี่ยน Username กรุณาติดต่อ Admin</span>
            </div>

            <div class="form-group">
                <label>ชื่อ-นามสกุล (ที่ใช้แสดงในเอกสาร):</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label>ตำแหน่งงาน:</label>
                <input type="text" name="position" value="<?php echo htmlspecialchars($current_user['position']); ?>">
            </div>
            
            <div class="form-group">
                <label>รหัสผ่านใหม่ (Password):</label>
                <input type="password" name="password" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน">
            </div>

            <div class="form-group">
                <label>ลายเซ็นของคุณ (สำหรับประทับลงในเอกสาร PDF):</label>
                
                <?php if (!empty($current_user['signature_image'])): ?>
                    <div>
                        <span style="font-size: 14px; color: green;">ลายเซ็นปัจจุบัน:</span><br>
                        <img src="<?php echo htmlspecialchars($current_user['signature_image']); ?>" class="sign-preview" alt="ลายเซ็นของคุณ">
                    </div>
                <?php endif; ?>

                <input type="file" name="signature_image" accept="image/*" style="margin-top: 10px;">
                <span class="info-text">แนะนำให้ใช้ไฟล์ .PNG แบบไม่มีพื้นหลัง (โปร่งใส) เพื่อความสวยงามของเอกสาร</span>
            </div>

            <button type="submit" class="btn-success">💾 บันทึกข้อมูลส่วนตัว</button>
        </form>
    </div>

</div>

</body>
</html>