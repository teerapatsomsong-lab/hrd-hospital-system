<?php
// attendance_report.php - รายงานตารางการลงเวลาปฏิบัติงาน (รองรับสถานะผสม + Active Only + Export Excel/PDF)
require_once 'auth_check.php';
require_once 'config.php';

// 1. ดึงเวลาอัปเดตล่าสุดของข้อมูลการลงเวลาจากคอลัมน์ updated_at
$lastUpdateStmt = $pdo->query("SELECT MAX(updated_at) as last_update FROM attendance_records");
$lastUpdateRow  = $lastUpdateStmt->fetch();
$lastUpdate     = $lastUpdateRow['last_update'] ?? NULL;

// ฟังก์ชันแปลงรูปแบบวันที่เวลาไทย
function formatThaiDateTime($datetimeStr) {
    if (!$datetimeStr) return 'ยังไม่มีการประมวลผลข้อมูล';
    $time = strtotime($datetimeStr);
    $thaiMonths = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $day   = date('j', $time);
    $month = $thaiMonths[date('n', $time)];
    $year  = date('Y', $time) + 543;
    $timeStr = date('H:i', $time);
    return "{$day} {$month} {$year} เวลา {$timeStr} น.";
}

// 2. ดึงรายชื่อแผนกทั้งหมดเฉพาะพนักงานที่เปิดใช้งาน สำหรับ Filter
$deptStmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE app_status = 'เปิดใช้งาน' AND department IS NOT NULL AND department != '' ORDER BY department ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// ค่าเริ่มต้น Filter (ตั้งค่าเป็นต้นเดือนถึงปัจจุบัน)
$startDate  = $_GET['start_date'] ?? date('Y-m-01');
$endDate    = $_GET['end_date'] ?? date('Y-m-d');
$search     = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// เงื่อนไขหลัก: กรองช่วงวันที่ และเลือกเฉพาะพนักงานที่มีสถานะเปิดใช้งาน (app_status = 'เปิดใช้งาน')
$where  = ["a.work_date BETWEEN ? AND ?", "e.app_status = 'เปิดใช้งาน'"];
$params = [$startDate, $endDate];

if ($search !== '') {
    $where[] = "(e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ?)";
    $st = "%{$search}%";
    $params = array_merge($params, [$st, $st, $st, $st]);
}

if ($filterDept !== '') {
    $where[] = "e.department = ?";
    $params[] = $filterDept;
}

if ($filterStatus !== '') {
    $where[] = "a.status = ?";
    $params[] = $filterStatus;
}

$whereClause = implode(" AND ", $where);

// 3. ดึงข้อมูลการลงเวลา
$sql = "SELECT a.*, e.first_name, e.last_name, e.department, e.position, e.work_start_time, e.work_end_time, f.total_hours 
        FROM attendance_records a 
        JOIN employees e ON a.employee_id = e.employee_id 
        LEFT JOIN face_scan_logs f ON a.employee_id = f.employee_id AND a.work_date = f.scan_date 
        WHERE {$whereClause} 
        ORDER BY a.work_date DESC, e.employee_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานตารางลงเวลาปฏิบัติงาน - HRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS สำหรับ Autocomplete -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        /* จัดรูปแบบสำหรับการพิมพ์ PDF / Print */
        @media print {
            body { background-color: #fff !important; padding: 0 !important; }
            .no-print, .btn, form, nav, header { display: none !important; }
            .card { border: none !important; shadow: none !important; }
            .table-responsive { overflow: visible !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th, td { border: 1px solid #000 !important; padding: 5px !important; font-size: 12px !important; }
            .badge { border: none !important; color: #000 !important; background: none !important; font-weight: bold; }
        }
    </style>
</head>
<body class="bg-light py-4">

<div class="container-fluid px-4">
    <!-- Header Page -->
     <?php include 'header.php'; ?> <!-- 2. ดึง Header มาแสดง -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="mb-1"><i class="bi bi-clock-history text-primary"></i> รายงานตารางการลงเวลาปฏิบัติงาน (Attendance Logs)</h3>
            <p class="text-muted small mb-0">
                <i class="bi bi-arrow-repeat text-success"></i> อัปเดตข้อมูลล่าสุดเมื่อ: 
                <strong class="text-dark"><?= formatThaiDateTime($lastUpdate) ?></strong>
            </p>
        </div>
        <div>
            <a href="process_attendance.php?auto_all=1" class="btn btn-warning btn-sm me-2"><i class="bi bi-cpu"></i> ประมวลผลเวลาทั้งหมด</a>
            <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold">ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">ค้นหาบุคลากร (พิมพ์ชื่อ/รหัส)</label>
                    <select name="search" id="empSearchApi" class="form-select">
                        <?php if ($search !== ''): ?>
                            <option value="<?= htmlspecialchars($search) ?>" selected><?= htmlspecialchars($search) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">แผนก/กลุ่มงาน</label>
                    <select name="department" class="form-select">
                        <option value="">-- ทุกแผนก --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">สถานะ</label>
                    <select name="status" class="form-select">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="PRESENT" <?= $filterStatus === 'PRESENT' ? 'selected' : '' ?>>ปกติ</option>
                        <option value="LATE" <?= $filterStatus === 'LATE' ? 'selected' : '' ?>>มาสาย</option>
                        <option value="EARLY_LEAVE" <?= $filterStatus === 'EARLY_LEAVE' ? 'selected' : '' ?>>ออกก่อนเวลา</option>
                        <option value="ABSENT" <?= $filterStatus === 'ABSENT' ? 'selected' : '' ?>>ขาดงาน</option>
                        <option value="WEEKEND" <?= $filterStatus === 'WEEKEND' ? 'selected' : '' ?>>วันหยุด</option>
                        <option value="LEAVE" <?= $filterStatus === 'LEAVE' ? 'selected' : '' ?>>ลา</option>
                        <option value="HOLIDAY" <?= $filterStatus === 'HOLIDAY' ? 'selected' : '' ?>>ไปราชการ</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary me-1"><i class="bi bi-search"></i> ค้นหาข้อมูล</button>
                    <a href="attendance_report.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต</a>
                    
                    <!-- ปุ่ม Export -->
                    <button type="button" onclick="exportExcel()" class="btn btn-success me-1"><i class="bi bi-file-earmark-excel"></i> ส่งออก Excel (.xlsx)</button>
                    <button type="button" onclick="exportPDF()" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> พิมพ์ / พิมพ์เป็น PDF</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm" id="reportArea">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">ตารางประวัติเวลาปฏิบัติงาน (พบ <?= number_format(count($attendanceLogs)) ?> รายการ)</h5>
            <small class="text-muted d-none d-print-block">พิมพ์เมื่อ: <?= date('Y-m-d H:i') ?></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle" id="attendanceTable">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>รหัส</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>แผนก</th>
                            <th>เวลาเข้ากำหนด</th>
                            <th>ลงเวลาเข้า (Check In)</th>
                            <th>ลงเวลาออก (Check Out)</th>
                            <th>เวลารวม (ชั่วโมง)</th>
                            <th>มาสาย (นาที)</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendanceLogs) > 0): ?>
                            <?php foreach ($attendanceLogs as $log): ?>
                                <tr>
                                    <td>
                                        <strong><?= $log['work_date'] ?></strong>
                                        <?php 
                                            $dw = date('N', strtotime($log['work_date']));
                                            if ($dw == 6 || $dw == 7) echo ' <span class="badge bg-secondary">ส.-อา.</span>';
                                        ?>
                                    </td>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars($log['employee_id']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></td>
                                    <td><small><?= htmlspecialchars($log['department'] ?? '-') ?></small></td>
                                    <td><small class="text-primary"><?= substr($log['work_start_time'] ?? '08:00', 0, 5) ?> น.</small></td>
                                    
                                    <td>
                                        <?php if (!empty($log['check_in']) && $log['check_in'] !== '00:00:00'): ?>
                                            <span class="text-success fw-bold"><?= substr($log['check_in'], 0, 5) ?> น.</span>
                                        <?php else: ?>
                                            <span class="text-danger small fw-bold">ไม่มีสแกนหน้าเข้า</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($log['check_out']) && $log['check_out'] !== '00:00:00'): ?>
                                            <span class="text-primary fw-bold"><?= substr($log['check_out'], 0, 5) ?> น.</span>
                                        <?php else: ?>
                                            <span class="text-danger small fw-bold">ไม่มีสแกนหน้าออก</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($log['total_hours'] ?? '00:00') ?></span></td>
                                    <td>
                                        <?php if ($log['late_minutes'] > 0): ?>
                                            <span class="badge bg-danger">+<?= $log['late_minutes'] ?> นาที</span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($log['work_type'] ?? '') === 'LEAVE_MORNING'): ?>
                                            <span class="badge bg-info text-dark">ลาครึ่งเช้า</span>
                                            <?php if ($log['late_minutes'] > 0): ?>
                                                <span class="badge bg-danger">สายบ่าย +<?= $log['late_minutes'] ?> นาที</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">เข้าบ่ายปกติ</span>
                                            <?php endif; ?>

                                        <?php elseif (($log['work_type'] ?? '') === 'LEAVE_AFTERNOON'): ?>
                                            <span class="badge bg-info text-dark">ลาครึ่งบ่าย</span>
                                            <?php if ($log['late_minutes'] > 0): ?>
                                                <span class="badge bg-danger">สายเช้า +<?= $log['late_minutes'] ?> นาที</span>
                                            <?php endif; ?>
                                            <?php if ($log['early_leave_minutes'] > 0): ?>
                                                <span class="badge bg-warning text-dark">ออกก่อนเที่ยง -<?= $log['early_leave_minutes'] ?> นาที</span>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <?php
                                            switch ($log['status']) {
                                                case 'PRESENT':     echo '<span class="badge bg-success">ปกติ</span>'; break;
                                                case 'LATE':        echo '<span class="badge bg-warning text-dark">มาสาย</span>'; break;
                                                case 'EARLY_LEAVE': echo '<span class="badge bg-info text-dark">ออกก่อน</span>'; break;
                                                case 'ABSENT':      echo '<span class="badge bg-danger">ขาดงาน</span>'; break;
                                                case 'WEEKEND':     echo '<span class="badge bg-secondary">วันหยุด</span>'; break;
                                                case 'LEAVE':       echo '<span class="badge bg-secondary">ลาเต็มวัน</span>'; break;
                                                case 'HOLIDAY':     echo '<span class="badge bg-primary">ไปราชการ</span>'; break;
                                                default:            echo '<span class="badge bg-light text-dark">'.$log['status'].'</span>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">ไม่พบข้อมูลตารางการลงเวลา</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SheetJS สำหรับ Export Excel -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    $('#empSearchApi').select2({
        theme: 'bootstrap-5',
        placeholder: 'พิมพ์ชื่อ นามสกุล หรือรหัสพนักงาน...',
        allowClear: true,
        ajax: {
            url: 'api_search_employee.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                var formattedData = $.map(data, function (item) {
                    return {
                        id: item.employee_id,
                        text: item.employee_id + ' - ' + item.first_name + ' ' + item.last_name + ' (' + (item.department || '-') + ')'
                    };
                });
                return { results: formattedData };
            },
            cache: true
        }
    });
});

// ฟังก์ชันส่งออกเป็น Excel (.xlsx) ตามข้อมูลที่แสดงอยู่
function exportExcel() {
    var table = document.getElementById("attendanceTable");
    var wb = XLSX.utils.table_to_book(table, { sheet: "Attendance_Report" });
    var fileName = "Attendance_Report_" + new Date().toISOString().slice(0, 10) + ".xlsx";
    XLSX.writeFile(wb, fileName);
}

// ฟังก์ชันสั่ง พิมพ์ / บันทึกเป็น PDF ผ่าน Print Dialog ของเบราว์เซอร์
function exportPDF() {
    window.print();
}
</script>
</body>
</html>