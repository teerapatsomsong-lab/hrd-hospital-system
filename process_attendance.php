<?php
// process_attendance.php - ประมวลผลเวลาเข้า-ออกงาน (ยึดตามหลักเกณฑ์โรงพยาบาลอย่างยั่งยืน)
require_once 'auth_check.php';
require_once 'config.php';

// 1. ดึงช่วงวันที่ทั้งหมดที่มีข้อมูลในตารางสแกน face_scan_logs
$datesStmt = $pdo->query("SELECT DISTINCT scan_date FROM face_scan_logs ORDER BY scan_date ASC");
$allDates  = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($allDates)) {
    $allDates = [date('Y-m-d')];
}

// 2. ดึงรายการบุคลากรทุกคนที่สถานะเปิดใช้งาน
$empStmt = $pdo->query("SELECT employee_id, 
                               IFNULL(work_start_time, '08:00:00') as work_start_time, 
                               IFNULL(work_end_time, '16:00:00') as work_end_time 
                        FROM employees 
                        WHERE app_status = 'เปิดใช้งาน'");
$allEmployees = $empStmt->fetchAll();

// 3. วนลูปประมวลผลแยกตามวันและรายบุคคล
foreach ($allDates as $workDate) {

    $dayOfWeek = date('N', strtotime($workDate)); // 6 = เสาร์, 7 = อาทิตย์
    $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7);

    foreach ($allEmployees as $emp) {
        $empId      = $emp['employee_id'];
        $shiftStart = $emp['work_start_time'];
        $shiftEnd   = $emp['work_end_time'];

        // ดึงLog สแกนของวันนั้น
        $scanStmt = $pdo->prepare("SELECT first_scan_time, last_scan_time 
                                   FROM face_scan_logs 
                                   WHERE employee_id = ? AND scan_date = ?");
        $scanStmt->execute([$empId, $workDate]);
        $scanData = $scanStmt->fetch();

        $cIn  = $scanData['first_scan_time'] ?? NULL;
        $cOut = $scanData['last_scan_time'] ?? NULL;

        $cInReal  = (!empty($cIn) && $cIn !== '00:00:00') ? $cIn : NULL;
        $cOutReal = (!empty($cOut) && $cOut !== '00:00:00') ? $cOut : NULL;

        // เช็กประวัติวันลา / ราชการ
        $chkLeave = $pdo->prepare("SELECT request_type FROM leave_requests WHERE employee_id = ? AND ? BETWEEN start_date AND end_date");
        $chkLeave->execute([$empId, $workDate]);
        $leaveData = $chkLeave->fetch();

        $status   = 'PRESENT';
        $lateMin  = 0; 
        $earlyMin = 0;

        if ($leaveData) {
            $status = ($leaveData['request_type'] === 'LEAVE') ? 'LEAVE' : 'HOLIDAY';
        } else if (empty($cInReal) && empty($cOutReal)) {
            // ไม่มีการสแกนในวันนั้น
            if ($isWeekend) {
                $status = 'WEEKEND'; // เสาร์-อาทิตย์ กำหนดเป็นวันหยุด
            } else {
                $status = 'ABSENT';  // วันธรรมดา กำหนดเป็นขาดงาน
            }
        } else {
            // มีการสแกนมาทำงาน (คิดเวลาสายเกิน 1 นาทีตามกฎ)
            if ($cInReal && strtotime($cInReal) > strtotime($shiftStart)) {
                $status  = 'LATE';
                $lateMin = ceil((strtotime($cInReal) - strtotime($shiftStart)) / 60);
            }

            if ($cOutReal && strtotime($cOutReal) < strtotime($shiftEnd)) {
                $status   = ($status === 'LATE') ? 'LATE' : 'EARLY_LEAVE';
                $earlyMin = floor((strtotime($shiftEnd) - strtotime($cOutReal)) / 60);
            }
        }

        // Upsert ข้อมูลเข้าตารางสรุป
        $upsert = $pdo->prepare("INSERT INTO attendance_records 
            (employee_id, work_date, check_in, check_out, late_minutes, early_leave_minutes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            check_in=VALUES(check_in), check_out=VALUES(check_out), late_minutes=VALUES(late_minutes), 
            early_leave_minutes=VALUES(early_leave_minutes), status=VALUES(status)");
        
        $upsert->execute([$empId, $workDate, $cInReal, $cOutReal, $lateMin, $earlyMin, $status]);
    }
}

echo "<script>alert('ประมวลผลตารางลงเวลาปฏิบัติงานเรียบร้อยแล้ว'); window.location.href='attendance_report.php';</script>";
?>