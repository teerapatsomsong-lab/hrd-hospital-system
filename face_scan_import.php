<?php
// face_scan_import.php - นำเข้าข้อมูลสแกนหน้าอิงตาม Column Index ป้องกันปัญหา Header
require_once 'auth_check.php';
require_once 'config.php';
checkRole(['Super Admin', 'HR Admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['scan_file'])) {
    $file = $_FILES['scan_file'];
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        
        // ข้ามบรรทัด Header (บรรทัดแรก)
        fgetcsv($handle, 1000, ",");

        // ดึงรายชื่อพนักงานที่มีในระบบ
        $empStmt = $pdo->query("SELECT employee_id FROM employees");
        $validEmps = array_flip($empStmt->fetchAll(PDO::FETCH_COLUMN));

        $batchId = 'SCAN_' . date('Ymd_His');
        $success = 0; $failed = 0; $errorLogs = []; $rowNum = 1;

        $pdo->beginTransaction();

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNum++;
            if (count($data) < 7) continue; // หากคอลัมน์ไม่ครบให้ข้าม

            // ดึงข้อมูลตามลำดับ Index คอลัมน์จริงใน CSV พร้อมตัดช่องว่าง
            $empId     = trim($data[0] ?? '');
            $scanDate  = trim($data[3] ?? '');
            $firstScan = trim($data[5] ?? '');
            $lastScan  = trim($data[6] ?? '');
            $totalHrs  = trim($data[7] ?? '');

            // เติมวินาที :00 หากข้อมูลมาเฉพาะ HH:MM
            if ($firstScan && strlen($firstScan) === 5) $firstScan .= ':00';
            if ($lastScan && strlen($lastScan) === 5) $lastScan .= ':00';

            try {
                if (empty($empId)) throw new Exception("ไม่พบรหัสพนักงานในบรรทัดนี้");
                if (!isset($validEmps[$empId])) throw new Exception("ไม่พบรหัสพนักงาน {$empId} ในระบบ HRD");

                // Insert / Update ข้อมูลสแกนลง face_scan_logs
                $ins = $pdo->prepare("INSERT INTO face_scan_logs (employee_id, scan_date, first_scan_time, last_scan_time, total_hours, import_batch_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    first_scan_time=VALUES(first_scan_time), last_scan_time=VALUES(last_scan_time), total_hours=VALUES(total_hours)");
                
                $ins->execute([$empId, $scanDate, $firstScan, $lastScan, $totalHrs, $batchId]);
                $success++;

            } catch (Exception $e) {
                $failed++;
                $errorLogs[] = ['line' => $rowNum, 'employee_id' => $empId, 'reason' => $e->getMessage()];
            }
        }
        fclose($handle);

        $hist = $pdo->prepare("INSERT INTO import_history (batch_id, import_type, file_name, total_records, success_count, failed_count, error_log, imported_by) VALUES (?, 'FACE_SCAN', ?, ?, ?, ?, ?, ?)");
        $hist->execute([$batchId, $file['name'], ($success + $failed), $success, $failed, json_encode($errorLogs, JSON_UNESCAPED_UNICODE), $_SESSION['user_id']]);

        $pdo->commit();
        logAudit($pdo, $_SESSION['user_id'], 'IMPORT_FACE_SCAN', 'face_scan_logs', $batchId, ['success' => $success, 'failed' => $failed]);
        
        $msg = "นำเข้าไฟล์ CSV เรียบร้อยแล้ว! สำเร็จ $success รายการ (กรุณากดปุ่มประมวลผลเวลาต่อ)";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>นำเข้าข้อมูล Face Scan - HRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>นำเข้าเวลา Face Scan (Import Log)</h3>
        <div>
            <a href="process_attendance.php?auto_all=1" class="btn btn-warning btn-sm me-2"><i class="bi bi-cpu"></i> ประมวลผลเวลาทั้งหมด</a>
            <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
        </div>
    </div>
    <?php if($msg): ?><div class="alert alert-info fw-bold py-3"><?= $msg ?></div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label fw-bold">เลือกไฟล์ CSV สแกนหน้า</label>
                    <input type="file" name="scan_file" class="form-control" accept=".csv" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> นำเข้าไฟล์สแกน</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>