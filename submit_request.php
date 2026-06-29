<?php
// 1. เชื่อมต่อ Database ผ่านไฟล์กลาง (db.php)
require_once 'db.php';

// ฟังก์ชันดึงค่า IP Address จริงของผู้ใช้งาน
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // กรณีผ่าน Proxy อาจจะมีหลาย IP ต่อกันด้วยลูกน้ำ ให้ดึงตัวแรกสุดมาใช้
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; 
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
    return trim($ip);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $user_ip = getUserIP(); // เรียกใช้ฟังก์ชันดึง IP Address
    
    // ตรวจสอบว่าขออะไรบ้าง
    $req_types = isset($_POST['request_type']) ? $_POST['request_type'] : [];
    if(empty($req_types)) { die("กรุณาเลือกเอกสารที่ต้องการขอ"); }
    
    // กำหนด Type ของคำขอ
    $type_str = (in_array('cert', $req_types) && in_array('payslip', $req_types)) ? 'both' : $req_types[0];

    // สร้าง Tracking Code Random 8 ตัว (A-Z, 0-9)
    $tracking_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);

    try {
        $pdo->beginTransaction();

        // 1. Insert ตารางคำขอหลัก (เพิ่ม ip_address เข้าไปในฐานข้อมูล)
        $stmt = $pdo->prepare("INSERT INTO requests (tracking_code, request_type, status, email, ip_address, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())");
        $stmt->execute([$tracking_code, $type_str, $email, $user_ip]);
        $request_id = $pdo->lastInsertId();

        // 2. ถ้าขอใบรับรอง (Certificate)
        if (in_array('cert', $req_types)) {
            $purpose = $_POST['purpose'] === 'อื่นๆ' ? $_POST['purpose_other'] : $_POST['purpose'];
            
            // บันทึกลายเซ็นจาก Base64 เป็นไฟล์ภาพ (เพื่อนำไปแปะใน PDF ภายหลัง)
            $signature_data = $_POST['signature_data'];
            $sig_filename = null;
            if(!empty($signature_data) && $signature_data != 'data:,') {
                $img_parts = explode(";base64,", $signature_data);
                $img_base64 = base64_decode($img_parts[1]);
                $sig_filename = 'signatures/' . $tracking_code . '.png';
                
                // ตรวจสอบและสร้างโฟลเดอร์ signatures ถ้ายังไม่มี (ป้องกัน Error บันทึกรูปล้มเหลว)
                if (!file_exists('signatures')) {
                    mkdir('signatures', 0777, true);
                }
                
                file_put_contents($sig_filename, $img_base64);
            }

            $stmtCert = $pdo->prepare("INSERT INTO cert_requests (request_id, emp_type, title, fullname, id_card, position, department, join_date, salary, position_allowance, monthly_remuneration, living_allowance, total_income, purpose, phone, contract_no, contract_date, contract_end_date, user_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmtCert->execute([
                $request_id, $_POST['emp_type'], $_POST['cert_title'], $_POST['cert_fullname'], $_POST['cert_id_card'],
                $_POST['cert_position'], $_POST['cert_department'], $_POST['cert_join_date'], $_POST['salary'],
                $_POST['position_allowance'], $_POST['monthly_remuneration'], $_POST['living_allowance'], $_POST['total_income'],
                $purpose, $_POST['phone'], $_POST['contract_no'], $_POST['contract_date'], $_POST['contract_end_date'], $sig_filename
            ]);
        }

        // 3. ถ้าขอสลิป (Payslip)
        if (in_array('payslip', $req_types)) {
            $stmtSlip = $pdo->prepare("INSERT INTO payslip_requests (request_id, title, fullname, slip_id_card, position, department, req_month, req_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtSlip->execute([
                $request_id, $_POST['slip_title'], $_POST['slip_fullname'], $_POST['slip_id_card'], 
                $_POST['slip_position'], $_POST['slip_department'], $_POST['req_month'], $_POST['req_year']
            ]);
        }

        $pdo->commit();

        // นำเข้าฟังก์ชันส่งอีเมล
        require_once 'mailer.php';

        // 2. สร้าง URL แบบ Dynamic เพื่อให้ส่งลิงก์ในอีเมลได้ถูกต้องเสมอ ไม่ว่าจะอัปโหลดขึ้นเว็บไหน
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $base_url = $protocol . "://" . $host . $path;
        
        // สร้างลิงก์ติดตามสถานะ พร้อมแนบ Code ไปใน URL เลย (?code=...)
        $track_link = $base_url . "/track.php?code=" . $tracking_code;

        // เตรียมเนื้อหาอีเมล
        $subject = "ระบบได้รับคำขอเอกสารของคุณแล้ว";
        $body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h3>เรียน ผู้ยื่นคำขอ,</h3>
                <p>ระบบได้รับคำขอเอกสารของคุณเรียบร้อยแล้ว</p>
                <p>รหัสสำหรับติดตามสถานะคำขอของคุณคือ: <strong style='font-size: 16px; color: #007bff;'>{$tracking_code}</strong></p>
                <br>
                <p>คุณสามารถคลิกปุ่มด้านล่างเพื่อตรวจสอบสถานะได้ทันที (ไม่ต้องกรอกรหัสซ้ำ):</p>
                <p><a href='{$track_link}' style='background-color: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>🔍 ติดตามสถานะคำขอ</a></p>
                <br>
                <p style='font-size: 12px; color: #888;'><i>หมายเหตุ: นี่คืออีเมลอัตโนมัติ กรุณาอย่าตอบกลับ หากพบปัญหาโปรดติดต่อเจ้าหน้าที่โดยตรง</i></p>
            </div>
        ";

        // สั่งส่งอีเมล
        sendEmail($email, $subject, $body);        
        
        // แสดงผลหน้าเว็บให้ผู้ใช้ทราบว่าสำเร็จ
        echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>ส่งคำขอสำเร็จ</title></head>";
        echo "<body style='background: #f4f7f6; font-family: Sarabun, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;'>";
        echo "<div style='background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 500px;'>";
        echo "<h2 style='color: #28a745; margin-top: 0;'>✅ ส่งคำขอสำเร็จ!</h2>";
        echo "<p style='font-size: 16px;'>รหัสสำหรับติดตามคำขอของคุณคือ:</p>";
        echo "<h1 style='color: #007bff; letter-spacing: 2px; border: 2px dashed #007bff; padding: 10px; border-radius: 4px; display: inline-block; margin: 10px 0;'>{$tracking_code}</h1>";
        echo "<p style='color: #555;'>ระบบได้ส่งลิงก์ติดตามไปที่อีเมล<br><strong>{$email}</strong> แล้ว</p>";
        echo "<div style='margin-top: 30px;'>";
        echo "<a href='print_request.php?code={$tracking_code}' target='_blank' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block; margin-bottom: 15px;'>🖨 พิมพ์/ดาวน์โหลด คำขอเป็น PDF</a><br>";
        echo "<a href='index.html' style='color: #6c757d; text-decoration: none; font-size: 14px;'>← กลับหน้าหลัก</a>";
        echo "</div></div></body></html>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>";
        echo "<h2 style='color:red;'>เกิดข้อผิดพลาดในการส่งคำขอ</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<a href='index.html'>กลับไปลองใหม่</a>";
        echo "</div>";
    }
}
?>