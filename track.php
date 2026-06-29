<?php
require_once 'db.php';

// รับค่ารหัสติดตาม จาก GET (ลิงก์ในอีเมล) หรือ POST (พิมพ์ค้นหาเอง)
$tracking_code = $_GET['code'] ?? $_POST['code'] ?? '';
$request_data = null;
$error_msg = '';

if (!empty($tracking_code)) {
    // ป้องกันช่องโหว่โดยรับเฉพาะตัวอักษรและตัวเลข
    $tracking_code = preg_replace('/[^a-zA-Z0-9]/', '', strtoupper($tracking_code));

    
    // ค้นหาข้อมูลคำขอจากรหัสติดตาม
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE tracking_code = ?");
    $stmt->execute([$tracking_code]);
    $request_data = $stmt->fetch();
    

    if (!$request_data) {
        $error_msg = "ไม่พบข้อมูลรหัสติดตาม: <strong>{$tracking_code}</strong> โปรดตรวจสอบความถูกต้องอีกครั้ง";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสถานะคำขอเอกสาร</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f6; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { max-width: 600px; width: 100%; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; text-transform: uppercase; }
        button { background: #007bff; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; transition: 0.3s; }
        button:hover { background: #0056b3; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .result-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; background: #fafafa; }
        .status-badge { display: inline-block; padding: 8px 15px; border-radius: 20px; font-weight: bold; color: white; font-size: 14px; }
        .bg-pending { background: #ffc107; color: #333; }
        .bg-verified { background: #17a2b8; }
        .bg-approved { background: #28a745; }
        .bg-rejected { background: #dc3545; }
        .detail-row { margin-bottom: 10px; font-size: 16px; }
        .detail-row strong { display: inline-block; width: 140px; color: #555; }
        .download-section { margin-top: 25px; padding-top: 20px; border-top: 1px dashed #ccc; text-align: center; }
        .btn-download { display: inline-block; background: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 5px; transition: 0.3s; }
        .btn-download:hover { background: #218838; transform: translateY(-2px); }
        .reason-box { background: #ffeeba; padding: 15px; border-radius: 5px; color: #856404; margin-top: 15px; font-size: 15px; border-left: 5px solid #ffc107; }
    </style>
</head>
<body>

<div class="container">
    <h2>🔍 ติดตามสถานะคำขอเอกสาร</h2>

    <form method="POST" action="track.php" class="search-box">
        <input type="text" name="code" placeholder="กรอกรหัสติดตาม 8 หลัก (เช่น A1B2C3D4)" value="<?php echo htmlspecialchars($tracking_code); ?>" required maxlength="8">
        <button type="submit">ค้นหา</button>
    </form>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <?php if ($request_data): ?>
        <div class="result-card">
            <?php
                // กำหนดรูปแบบการแสดงผลสถานะ
                $status = $request_data['status'];
                $status_text = '';
                $badge_class = '';

                switch ($status) {
                    case 'pending': 
                        $status_text = '⏳ กำลังรอเจ้าหน้าที่ตรวจสอบข้อมูล'; 
                        $badge_class = 'bg-pending'; 
                        break;
                    case 'verified': 
                        $status_text = '📝 ตรวจสอบผ่านแล้ว กำลังรอผู้บริหารอนุมัติ'; 
                        $badge_class = 'bg-verified'; 
                        break;
                    case 'approved': 
                        $status_text = '✅ อนุมัติเรียบร้อยแล้ว'; 
                        $badge_class = 'bg-approved'; 
                        break;
                    case 'rejected': 
                        $status_text = '❌ ไม่อนุมัติคำขอ'; 
                        $badge_class = 'bg-rejected'; 
                        break;
                }

                // แปลงประเภทเอกสารเป็นภาษาไทย
                $type = $request_data['request_type'];
                $type_text = ($type == 'cert') ? 'หนังสือรับรองเงินเดือน' : (($type == 'payslip') ? 'สลิปเงินเดือน' : 'หนังสือรับรองเงินเดือน และ สลิปเงินเดือน');
            ?>

            <div style="text-align: center; margin-bottom: 20px;">
                <span class="status-badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
            </div>

            <div class="detail-row"><strong>รหัสติดตาม:</strong> <?php echo htmlspecialchars($request_data['tracking_code']); ?></div>
            <div class="detail-row"><strong>ประเภทเอกสาร:</strong> <?php echo $type_text; ?></div>
            <div class="detail-row"><strong>วันที่ส่งคำขอ:</strong> <?php echo date('d/m/Y H:i', strtotime($request_data['created_at'])); ?></div>
            <div class="detail-row"><strong>อีเมลรับแจ้งเตือน:</strong> <?php echo htmlspecialchars($request_data['email']); ?></div>
            
            <div style="margin-top: 15px;">
                <a href="export_pdf.php?type=request_form&code=<?php echo $request_data['tracking_code']; ?>" target="_blank" style="background:#17a2b8; color:white; padding:8px 15px; border-radius:4px; text-decoration:none; font-size:14px;">📄 พิมพ์แบบฟอร์มคำขอ</a>
            </div>

            <?php if ($status == 'rejected' && !empty($request_data['reject_reason'])): ?>
                <div class="reason-box">
                    <strong>เหตุผลที่ไม่อนุมัติ:</strong><br>
                    <?php echo nl2br(htmlspecialchars($request_data['reject_reason'])); ?>
                </div>
            <?php endif; ?>

            <?php if ($status == 'approved'): ?>
                <div class="download-section">
                    <h3 style="color: #28a745; margin-top: 0;">🎉 เอกสารของคุณพร้อมแล้ว</h3>
                    <p style="font-size: 14px; color: #666;">คุณสามารถคลิกปุ่มด้านล่างเพื่อพิมพ์ หรือบันทึกเป็น PDF ได้ทันที</p>
                    
                    <?php if (in_array($type, ['cert', 'both'])): ?>
                        <a href="export_pdf.php?type=cert&id=<?php echo $request_data['id']; ?>" target="_blank" class="btn-download">📄 ดาวน์โหลดหนังสือรับรองเงินเดือน</a>
                    <?php endif; ?>

                    <?php if (in_array($type, ['payslip', 'both'])): ?>
                        <a href="export_pdf.php?type=payslip&id=<?php echo $request_data['id']; ?>" target="_blank" class="btn-download">💵 ดาวน์โหลดสลิปเงินเดือน</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.html" style="color: #007bff; text-decoration: none;">← กลับไปหน้ายื่นคำขอใหม่</a>
    </div>
</div>

</body>
</html>