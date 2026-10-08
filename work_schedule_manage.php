<?php
// work_schedule_manage.php - ระบบจัดการตารางเวรรายเดือน (สำหรับพนักงานรูปแบบ SHIFT)
require_once 'auth_check.php';
require_once 'config.php';
checkRole(['Super Admin', 'HR Admin']);

$message = '';
$selectedMonth = $_GET['month'] ?? date('Y-m');

// 1. บันทึก/อัปเดตตารางเวร
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_schedule') {
    $empId      = trim($_POST['employee_id'] ?? '');
    $workDate   = trim($_POST['work_date'] ?? '');
    $isOffDay   = isset($_POST['is_off_day']) ? 1 : 0;
    $startTime  = trim($_POST['work_start_time'] ?? '08:00');
    $endTime    = trim($_POST['work_end_time'] ?? '16:00');

    if ($empId && $workDate) {
        $stmt = $pdo->prepare("INSERT INTO work_schedules (employee_id, work_date, work_start_time, work_end_time, is_off_day) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               work_start_time = VALUES(work_start_time), 
                               work_end_time = VALUES(work_end_time), 
                               is_off_day = VALUES(is_off_day)");
        $stmt->execute([$empId, $workDate, $startTime, $endTime, $isOffDay]);
        
        logAudit($pdo, $_SESSION['user_id'], 'UPDATE_WORK_SCHEDULE', 'work_schedules', $empId, $_POST);
        $message = '<div class="alert alert-success">บันทึกตารางเวรเรียบร้อยแล้ว!</div>';
    }
}

// 2. ดึงรายชื่อพนักงานที่มี work_mode = 'SHIFT'
$empStmt = $pdo->query("SELECT employee_id, first_name, last_name, department 
                        FROM employees 
                        WHERE app_status = 'เปิดใช้งาน' AND work_mode = 'SHIFT' 
                        ORDER BY employee_id ASC");
$shiftEmployees = $empStmt->fetchAll();

// 3. ดึงรายการตารางเวรของเดือนที่เลือก
$startDate = $selectedMonth . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

$schStmt = $pdo->prepare("SELECT ws.*, e.first_name, e.last_name, e.department 
                         FROM work_schedules ws 
                         JOIN employees e ON ws.employee_id = e.employee_id 
                         WHERE ws.work_date BETWEEN ? AND ? 
                         ORDER BY ws.work_date ASC, ws.employee_id ASC");
$schStmt->execute([$startDate, $endDate]);
$schedules = $schStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการตารางเวรรายเดือน - HRD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light py-4">

<div class="container-fluid px-4">
    <?php include 'header.php'; ?> <!-- 2. ดึง Header มาแสดง -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-calendar3 text-primary"></i> จัดการตารางเวรรายเดือน (Shift Schedule)</h3>
        <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
    </div>

    <?= $message ?>

    <div class="row g-4">
        <!-- Form กำหนดตารางเวร -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-plus-circle"></i> กำหนดเวลาเวรรายวัน
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="save_schedule">

                        <div class="mb-3">
                            <label class="form-label fw-bold">บุคลากร (เฉพาะรูปแบบ SHIFT) *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- เลือกพนักงาน --</option>
                                <?php foreach ($shiftEmployees as $e): ?>
                                    <option value="<?= htmlspecialchars($e['employee_id']) ?>">
                                        <?= htmlspecialchars($e['employee_id'] . ' - ' . $e['first_name'] . ' ' . $e['last_name'] . ' (' . ($e['department'] ?? '-') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">วันที่ปฏิบัติงาน *</label>
                            <input type="date" name="work_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_off_day" id="isOffDayCheck" onchange="toggleTimeInputs(this.checked)">
                            <label class="form-check-input-label fw-bold text-danger" for="isOffDayCheck">กำหนดเป็นวันหยุด (OFF)</label>
                        </div>

                        <div id="timeInputs">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">เวลาเข้าเวร</label>
                                    <input type="time" name="work_start_time" id="startTimeInput" class="form-control" value="08:00">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">เวลาออกเวร</label>
                                    <input type="time" name="work_end_time" id="endTimeInput" class="form-control" value="16:00">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-save"></i> บันทึกตารางเวร</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- รายการตารางเวรในเดือน -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">ตารางเวรประจำเดือน</h5>
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>วันที่</th>
                                    <th>รหัส/ชื่อ-นามสกุล</th>
                                    <th>แผนก</th>
                                    <th>กะการทำงาน</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($schedules) > 0): ?>
                                    <?php foreach ($schedules as $s): ?>
                                        <tr>
                                            <td><strong><?= $s['work_date'] ?></strong></td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($s['employee_id']) ?></small><br>
                                                <strong><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                            </td>
                                            <td><small><?= htmlspecialchars($s['department'] ?? '-') ?></small></td>
                                            <td>
                                                <?php if ($s['is_off_day']): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">
                                                        <?= substr($s['work_start_time'], 0, 5) ?> - <?= substr($s['work_end_time'], 0, 5) ?> น.
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($s['is_off_day']): ?>
                                                    <span class="badge bg-secondary">วันหยุด (OFF)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">มีเวรปฏิบัติงาน</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูลการจัดตารางเวรในเดือนนี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleTimeInputs(isOff) {
    const timeBox = document.getElementById('timeInputs');
    if (isOff) {
        timeBox.style.opacity = '0.4';
        document.getElementById('startTimeInput').disabled = true;
        document.getElementById('endTimeInput').disabled = true;
    } else {
        timeBox.style.opacity = '1';
        document.getElementById('startTimeInput').disabled = false;
        document.getElementById('endTimeInput').disabled = false;
    }
}
</script>
</body>
</html>