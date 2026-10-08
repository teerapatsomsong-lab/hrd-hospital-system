<?php
// process_attendance.php - Engine ประมวลผลเวลาปฏิบัติงาน (รองรับ FIXED, FLEXIBLE, SHIFT + การลาครึ่งวัน)
require_once 'auth_check.php';
require_once 'config.php';

// 1. ดึงช่วงวันที่ทั้งหมดที่มีข้อมูลสแกน
$datesStmt = $pdo->query("SELECT DISTINCT scan_date FROM face_scan_logs ORDER BY scan_date ASC");
$allDates  = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($allDates)) {
    $allDates = [date('Y-m-d')];
}

// 2. ดึงรายการบุคลากรที่เปิดใช้งาน (Active) พร้อมรูปแบบการทำงาน
$empStmt = $pdo->query("SELECT employee_id, 
                               IFNULL(work_start_time, '08:00:00') as work_start_time, 
                               IFNULL(work_end_time, '16:00:00') as work_end_time,
                               IFNULL(work_mode, 'FIXED') as work_mode 
                        FROM employees 
                        WHERE app_status = 'เปิดใช้งาน'");
$allEmployees = $empStmt->fetchAll();

foreach ($allDates as $workDate) {

    $dayOfWeek = date('N', strtotime($workDate)); // 6 = เสาร์, 7 = อาทิตย์
    $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7);

    foreach ($allEmployees as $emp) {
        $empId      = $emp['employee_id'];
        $shiftStart = $emp['work_start_time'];
        $shiftEnd   = $emp['work_end_time'];
        $workMode   = $emp['work_mode'];

        // ดึง Log สแกนหน้า
        $scanStmt = $pdo->prepare("SELECT first_scan_time, last_scan_time FROM face_scan_logs WHERE employee_id = ? AND scan_date = ?");
        $scanStmt->execute([$empId, $workDate]);
        $scanData = $scanStmt->fetch();

        $cIn  = $scanData['first_scan_time'] ?? NULL;
        $cOut = $scanData['last_scan_time'] ?? NULL;

        $cInReal  = (!empty($cIn) && $cIn !== '00:00:00') ? $cIn : NULL;
        $cOutReal = (!empty($cOut) && $cOut !== '00:00:00') ? $cOut : NULL;

        // เช็กการขอลา / ไปราชการ
        $chkLeave = $pdo->prepare("SELECT request_type, IFNULL(leave_period, 'FULL') as leave_period 
                                   FROM leave_requests 
                                   WHERE employee_id = ? AND status = 'APPROVED' 
                                     AND ? BETWEEN start_date AND end_date LIMIT 1");
        $chkLeave->execute([$empId, $workDate]);
        $leaveData = $chkLeave->fetch();

        $workType = 'WORKDAY';
        $status   = 'PRESENT';
        $lateMin  = 0; 
        $earlyMin = 0;

        // -------------------------------------------------------------
        // กรณีมีใบลา/ไปราชการ (มีผลลำดับแรกสุดสำหรับทุกรูปแบบงาน)
        // -------------------------------------------------------------
        if ($leaveData) {
            $period  = $leaveData['leave_period'];
            $reqType = $leaveData['request_type'];

            if ($period === 'FULL') {
                $workType = ($reqType === 'LEAVE') ? 'LEAVE_FULL' : 'OFFICIAL_FULL';
                $status   = ($reqType === 'LEAVE') ? 'LEAVE' : 'HOLIDAY';
            } 
            else if ($period === 'MORNING') {
                // ลาครึ่งเช้า: เวลาเริ่มปฏิบัติงานบ่ายคือ 13:00:00 น.
                $workType  = 'LEAVE_MORNING';
                $targetIn  = '13:00:00';
                $targetOut = $shiftEnd;

                if ($cInReal || $cOutReal) {
                    $checkInTime = $cInReal ?? $cOutReal;
                    
                    // สแกนเข้าหลัง 13:00 น. คำนวณเป็นมาสาย
                    if (strtotime($checkInTime) > strtotime($targetIn)) {
                        $lateMin = ceil((strtotime($checkInTime) - strtotime($targetIn)) / 60);
                    }
                    // สแกนออกก่อนเวลาเลิกกะ
                    if ($cOutReal && strtotime($cOutReal) < strtotime($targetOut)) {
                        $earlyMin = floor((strtotime($targetOut) - strtotime($cOutReal)) / 60);
                    }
                    $status = ($lateMin > 0) ? 'LATE' : 'PRESENT';
                } else {
                    $status = 'ABSENT'; // ลาเช้า แต่ช่วงบ่ายไม่มาสแกนงาน
                }
            } 
            else if ($period === 'AFTERNOON') {
                // ลาครึ่งบ่าย: เวลาเริ่มกะปกติ (เช่น 08:00 น.) กำหนดออกงานเที่ยง 12:00:00 น.
                $workType  = 'LEAVE_AFTERNOON';
                $targetIn  = $shiftStart;
                $targetOut = '12:00:00';

                if ($cInReal) {
                    // สแกนเข้าหลังเวลาเริ่มกะ
                    if (strtotime($cInReal) > strtotime($targetIn)) {
                        $lateMin = ceil((strtotime($cInReal) - strtotime($targetIn)) / 60);
                    }
                    // สแกนออกก่อน 12:00 น.
                    if ($cOutReal && strtotime($cOutReal) < strtotime($targetOut)) {
                        $earlyMin = floor((strtotime($targetOut) - strtotime($cOutReal)) / 60);
                    }
                    $status = ($lateMin > 0) ? 'LATE' : 'PRESENT';
                } else {
                    $status = 'ABSENT'; // ลาบ่าย แต่ช่วงเช้าไม่มาสแกนงาน
                }
            }
        }
        // -------------------------------------------------------------
        // กรณีไม่ได้ยื่นใบลา: คำนวณตามรูปแบบงาน (work_mode)
        // -------------------------------------------------------------
        else if ($workMode === 'FLEXIBLE') {
            // === 1. พนักงาน Part-time / มาบางวัน ===
            if ($cInReal || $cOutReal) {
                $status   = 'PRESENT'; // มีสแกน = มาทำงาน
                $workType = 'WORKDAY_FLEX';
            } else {
                $status   = 'OFF_DAY';  // ไม่มีสแกน = ไม่ได้มาทำงาน (ไม่นับเป็น ABSENT)
                $workType = 'OFF_DAY';
            }
            $lateMin = 0; $earlyMin = 0;
        }
        else if ($workMode === 'SHIFT') {
            // === 2. พนักงานขึ้นเวรตามตารางรายเดือน ===
            $schStmt = $pdo->prepare("SELECT * FROM work_schedules WHERE employee_id = ? AND work_date = ?");
            $schStmt->execute([$empId, $workDate]);
            $sch = $schStmt->fetch();

            if ($sch && intval($sch['is_off_day']) === 0) {
                // วันนี้มีเวรตามตาราง
                $targetIn  = $sch['work_start_time'];
                $targetOut = $sch['work_end_time'];
                $workType  = 'SHIFT_WORK';

                if ($cInReal) {
                    if (strtotime($cInReal) > strtotime($targetIn)) {
                        $status  = 'LATE';
                        $lateMin = ceil((strtotime($cInReal) - strtotime($targetIn)) / 60);
                    } else {
                        $status  = 'PRESENT';
                    }

                    if ($cOutReal && strtotime($cOutReal) < strtotime($targetOut)) {
                        $earlyMin = floor((strtotime($targetOut) - strtotime($cOutReal)) / 60);
                    }
                } else {
                    $status = 'ABSENT'; // มีเวรแต่ไม่มาสแกน
                }
            } else {
                // วันนี้ไม่มีเวรในตาราง
                if ($cInReal) {
                    $status   = 'PRESENT'; // มาสแกนนอกเวร
                    $workType = 'OT_EXTRA';
                } else {
                    $status   = 'OFF_DAY';
                    $workType = 'OFF_DAY';
                }
            }
        }
        else {
            // === 3. พนักงานประจำปกติ (FIXED 08:00 - 16:00 น.) ===
            if (empty($cInReal) && empty($cOutReal)) {
                if ($isWeekend) {
                    $status   = 'WEEKEND';
                    $workType = 'WEEKEND';
                } else {
                    $status   = 'ABSENT';
                    $workType = 'WORKDAY';
                }
            } else {
                if ($cInReal && strtotime($cInReal) > strtotime($shiftStart)) {
                    $status  = 'LATE';
                    $lateMin = ceil((strtotime($cInReal) - strtotime($shiftStart)) / 60);
                }

                if ($cOutReal && strtotime($cOutReal) < strtotime($shiftEnd)) {
                    $status   = ($status === 'LATE') ? 'LATE' : 'EARLY_LEAVE';
                    $earlyMin = floor((strtotime($shiftEnd) - strtotime($cOutReal)) / 60);
                }
            }
        }

        // 3. บันทึกสรุปลงตาราง attendance_records
        $upsert = $pdo->prepare("INSERT INTO attendance_records 
            (employee_id, work_date, work_type, check_in, check_out, late_minutes, early_leave_minutes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            work_type=VALUES(work_type), check_in=VALUES(check_in), check_out=VALUES(check_out), 
            late_minutes=VALUES(late_minutes), early_leave_minutes=VALUES(early_leave_minutes), status=VALUES(status)");
        
        $upsert->execute([$empId, $workDate, $workType, $cInReal, $cOutReal, $lateMin, $earlyMin, $status]);
    }
}

echo "<script>alert('ประมวลผลตารางลงเวลาเรียบร้อยแล้ว'); window.location.href='attendance_report.php';</script>";
?>