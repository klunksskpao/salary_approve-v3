<?php
require_once 'db.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];

$message = '';

// ------------------------------------------------------------------
// 1. จัดการ Action ต่างๆ (เมื่อมีการกดปุ่ม ยืนยัน / อนุมัติ / ปฏิเสธ)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? '';
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'verify' && $role === 'finance') {
            // อัปเดตสถานะเป็น verified และบันทึก ID การเงิน (verified_by)
            $stmt = $pdo->prepare("UPDATE requests SET status = 'verified', verified_by = ? WHERE id = ?");
            $stmt->execute([$user_id, $request_id]);
            $message = "ตรวจสอบคำขอเรียบร้อยแล้ว ส่งต่อให้ผู้อนุมัติ";
    
        } elseif ($action === 'approve' && $role === 'approver') {
            // อัปเดตสถานะเป็น approved และบันทึก ID ผู้อนุมัติ (approved_by)
            $stmt = $pdo->prepare("UPDATE requests SET status = 'approved', approved_by = ? WHERE id = ?");
            $stmt->execute([$user_id, $request_id]);
            $message = "อนุมัติคำขอเรียบร้อยแล้ว";

            // 2. ดึงอีเมลและรหัสติดตามของผู้ขอ
            $stmtUser = $pdo->prepare("SELECT email, tracking_code FROM requests WHERE id = ?");
            $stmtUser->execute([$request_id]);
            $userData = $stmtUser->fetch();

            if ($userData) {
                require_once 'mailer.php';
                $tracking_code = $userData['tracking_code'];
                $user_email = $userData['email'];

                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $track_link = $base_url . "/track.php?code=" . $tracking_code;

                $subject = "✅ คำขอเอกสารของคุณได้รับการอนุมัติแล้ว";
                $body = "
                    <h3>เรียน ผู้ยื่นคำขอ,</h3>
                    <p>คำขอเอกสารรหัส <strong>{$tracking_code}</strong> ของคุณได้รับการ <b>อนุมัติ</b> เรียบร้อยแล้ว</p>
                    <p>คุณสามารถคลิกลิงก์ด้านล่างเพื่อตรวจสอบและดาวน์โหลดเอกสาร PDF ของคุณได้ทันที:</p>
                    <p><a href='{$track_link}' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>ดาวน์โหลดเอกสาร</a></p>
                    <br><p>ขอบคุณครับ</p>
                ";

                sendEmail($user_email, $subject, $body);
            }
        
        } elseif ($action === 'reject') {
            $reason = $_POST['reject_reason'] ?? 'ไม่ระบุเหตุผล';
            $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected', reject_reason = ? WHERE id = ?");
            $stmt->execute([$reason, $request_id]);
            $message = "ปฏิเสธคำขอพร้อมบันทึกเหตุผลแล้ว";
            
        } elseif ($action === 'forward' && $role === 'approver') {
            $next_approver = $_POST['next_approver_id'] ?? '';
            if(!empty($next_approver)){
                $stmt = $pdo->prepare("UPDATE requests SET current_approver_id = ? WHERE id = ?");
                $stmt->execute([$next_approver, $request_id]);
                $message = "ส่งต่อคำขอให้ผู้อนุมัติท่านอื่นเรียบร้อยแล้ว";
            }
        
        } elseif ($action === 'delete' && $role === 'admin') {
            $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $message = "ลบข้อมูลคำขอเรียบร้อยแล้ว";
        }

    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ------------------------------------------------------------------
// 2. ดึงข้อมูลสรุป (Stats) สำหรับแสดงใน Dashboard
// ------------------------------------------------------------------
$stats = $pdo->query("SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as total_pending,
    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as total_verified,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as total_approved
    FROM requests")->fetch();

// ------------------------------------------------------------------
// 3. ดึงรายการคำขอตาม Role ของผู้ใช้งาน + ระบบค้นหา
// ------------------------------------------------------------------
$search = $_GET['search'] ?? ''; 

$sql = "SELECT r.*, 
        c.fullname as cert_name, c.department as cert_dept,
        p.fullname as slip_name, p.department as slip_dept
        FROM requests r
        LEFT JOIN cert_requests c ON r.id = c.request_id
        LEFT JOIN payslip_requests p ON r.id = p.request_id 
        WHERE 1=1 "; 

$params = []; 

// เงื่อนไขที่ 1: กรองตาม Role
if ($role === 'finance') {
    $sql .= "AND r.status = 'pending' ";
} elseif ($role === 'approver') {
    $sql .= "AND r.status = 'verified' AND (r.current_approver_id = ? OR r.current_approver_id IS NULL) ";
    $params[] = $user_id;
}

// เงื่อนไขที่ 2: กรองตามคำค้นหา
if (!empty($search)) {
    $sql .= "AND (r.tracking_code LIKE ? OR c.fullname LIKE ? OR p.fullname LIKE ? OR c.department LIKE ? OR p.department LIKE ?) ";
    $search_term = "%{$search}%";
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
}

// เงื่อนไขที่ 3: การเรียงลำดับ
if ($role === 'approver') {
    $sql .= "ORDER BY r.created_at ASC"; 
} else {
    $sql .= "ORDER BY r.created_at DESC"; 
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// ดึงรายชื่อผู้อนุมัติทั้งหมดสำหรับ Dropdown "ส่งต่อ"
$approvers = $pdo->query("SELECT id, name FROM users WHERE role = 'approver' AND id != $user_id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - ระบบจัดการเอกสารเงินเดือน</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f6; margin: 0; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { display: block; color: white; padding: 15px; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover { background: #495057; }
        .main-content { margin-left: 250px; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-radius: 5px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
        .stat-card h3 { margin: 0; font-size: 28px; color: #007bff; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; color: white; }
        .bg-warning { background: #ffc107; color: #333; }
        .bg-info { background: #17a2b8; }
        .bg-success { background: #28a745; }
        .bg-danger { background: #dc3545; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; color: white; display: inline-block; margin-bottom: 5px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-primary { background: #007bff; }
        .btn-warning { background: #ffc107; color: #333; }
        .alert { padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px; }
        .action-form { display: inline-block; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3 style="text-align: center; border-bottom: 1px solid #4f5962; padding-bottom: 15px; margin-top:0;">Salary System</h3>
    <a href="dashboard.php" style="background: #495057;">📊 แดชบอร์ด (Dashboard)</a>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="import_csv.php">📁 นำเข้าเงินเดือน (CSV)</a>
        <a href="manage_users.php">👥 จัดการผู้ใช้งาน</a>
        <a href="manage_templates.php">🎨 จัดการรูปแบบเอกสาร PDF</a>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'finance'])): ?>
        <a href="manage_salary.php">💰 จัดการฐานข้อมูลเงินเดือน</a>
    <?php endif; ?>
    
    <a href="profile.php">👤 ข้อมูลส่วนตัว</a>
    <a href="logout.php">🚪 ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <h2>ยินดีต้อนรับ, <?php echo htmlspecialchars($name); ?> (<?php echo strtoupper($role); ?>)</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <p>รอตรวจสอบ (Pending)</p>
            <h3><?php echo $stats['total_pending'] ?? 0; ?></h3>
        </div>
        <div class="stat-card">
            <p>รออนุมัติ (Verified)</p>
            <h3><?php echo $stats['total_verified'] ?? 0; ?></h3>
        </div>
        <div class="stat-card">
            <p>อนุมัติแล้ว (Approved)</p>
            <h3><?php echo $stats['total_approved'] ?? 0; ?></h3>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0;">รายการคำขอที่ต้องดำเนินการ</h3>
        
        <form method="GET" action="dashboard.php" style="display: flex; margin: 0; min-width: 350px;">
            <input type="text" name="search" placeholder="ค้นหา รหัส, ชื่อ, สังกัด..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px 0 0 4px; border-right: none; outline: none;">
            <button type="submit" style="padding: 8px 15px; background: #007bff; color: white; border: 1px solid #007bff; border-radius: 0 4px 4px 0; cursor: pointer;">🔍 ค้นหา</button>
            <?php if (!empty($search)): ?>
                <a href="dashboard.php" style="background: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin-left: 10px; display: flex; align-items: center; font-size: 14px;">✖ ล้าง</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>รหัสติดตาม</th>
                <th>ประเภท</th>
                <th>ผู้ขอ (ชื่อ - หน่วยงาน)</th>
                <th>วันที่ขอ</th>
                <th>สถานะ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($requests) > 0): ?>
                <?php foreach ($requests as $req): 
                    $req_name = $req['cert_name'] ? $req['cert_name'] : $req['slip_name'];
                    $req_dept = $req['cert_dept'] ? $req['cert_dept'] : $req['slip_dept'];
                    
                    $badge_class = 'bg-warning';
                    if($req['status'] == 'verified') $badge_class = 'bg-info';
                    if($req['status'] == 'approved') $badge_class = 'bg-success';
                    if($req['status'] == 'rejected') $badge_class = 'bg-danger';
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($req['tracking_code']); ?></strong></td>
                    <td><?php echo strtoupper($req['request_type']); ?></td>
                    <td><?php echo htmlspecialchars($req_name) . '<br><small>' . htmlspecialchars($req_dept) . '</small>'; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></td>
                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo strtoupper($req['status']); ?></span></td>
                    <td>
                        <a href="view_request.php?id=<?php echo $req['id']; ?>" class="btn btn-primary">ดูข้อมูล</a>

                        <?php if ($role === 'finance' && $req['status'] === 'pending'): ?>
                            <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันว่าตรวจสอบผ่านแล้ว?');">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="action" value="verify">
                                <button type="submit" class="btn btn-success">ตรวจสอบผ่าน</button>
                            </form>
                            
                        <?php elseif ($role === 'approver' && $req['status'] === 'verified'): ?>
                            <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันการอนุมัติ?');">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success">อนุมัติ</button>
                            </form>
                            
                            <form method="POST" class="action-form" style="margin-top:5px;">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="action" value="forward">
                                <select name="next_approver_id" required style="padding: 5px;">
                                    <option value="">-- ส่งต่อให้ --</option>
                                    <?php foreach ($approvers as $app): ?>
                                        <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-warning">ส่งต่อ</button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($req['status'], ['pending', 'verified']) && in_array($role, ['finance', 'approver'])): ?>
                            <form method="POST" class="action-form" style="margin-top:5px;" onsubmit="return confirm('ยืนยันการปฏิเสธคำขอ?');">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="text" name="reject_reason" placeholder="เหตุผลที่ไม่อนุมัติ" required style="padding: 5px; width: 120px;">
                                <button type="submit" class="btn btn-danger">ปฏิเสธ</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($role === 'admin'): ?>
                            <form method="POST" class="action-form" style="margin-top:5px;" onsubmit="return confirm('คำเตือน: ยืนยันการลบคำขอนี้ใช่หรือไม่? ข้อมูลที่เกี่ยวข้องจะถูกลบทิ้งทั้งหมดและไม่สามารถกู้คืนได้!');">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger" style="background: #dc3545; border-color: #dc3545;">🗑️ ลบคำขอ</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align: center;">ไม่มีรายการคำขอในขณะนี้</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>