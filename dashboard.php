<?php
// dashboard.php - Executive Dashboard สรุปภาพรวมการมาปฏิบัติงานประจำวัน
require_once 'auth_check.php';
require_once 'config.php';

// รับค่าวันที่ที่ต้องการดูสรุป (ค่าเริ่มต้นคือวันนี้)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// 1. สรุปสถิติภาพรวมประจำวันจาก attendance_records
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) as count_present,
        SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END) as count_late,
        SUM(CASE WHEN status = 'EARLY_LEAVE' THEN 1 ELSE 0 END) as count_early,
        SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) as count_absent,
        SUM(CASE WHEN status IN ('LEAVE', 'HOLIDAY') THEN 1 ELSE 0 END) as count_leave,
        SUM(CASE WHEN status = 'WEEKEND' THEN 1 ELSE 0 END) as count_weekend
    FROM attendance_records 
    WHERE work_date = ?
");
$statsStmt->execute([$selectedDate]);
$stats = $statsStmt->fetch();

// 2. ดึงข้อมูลรายละเอียดการลงเวลาประจำวัน
$listStmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.department, e.position, f.total_hours
    FROM attendance_records a
    JOIN employees e ON a.employee_id = e.employee_id
    LEFT JOIN face_scan_logs f ON a.employee_id = f.employee_id AND a.work_date = f.scan_date
    WHERE a.work_date = ?
    ORDER BY a.check_in DESC, e.employee_id ASC
");
$listStmt->execute([$selectedDate]);
$attendanceList = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Executive Dashboard - HRD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-speedometer2 text-primary"></i> Executive Dashboard</h3>
            <p class="text-muted small mb-0">ภาพรวมการลงเวลาปฏิบัติงานบุคลากรโรงพยาบาล</p>
        </div>
        <div>
            <a href="process_attendance.php?auto_all=1" class="btn btn-warning btn-sm me-2"><i class="bi bi-cpu"></i> ประมวลผลเวลาทั้งหมด</a>
            <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
        </div>
    </div>

    <!-- Date Selection Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label fw-bold"><i class="bi bi-calendar3"></i> เลือกวันที่ต้องการดูสรุป:</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> แสดงข้อมูล</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-calendar-event"></i> ดูวันนี้</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body text-center p-3">
                    <div class="display-6 fw-bold"><?= number_format($stats['count_present'] ?? 0) ?></div>
                    <div class="small">มาปฏิบัติงานปกติ</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body text-center p-3">
                    <div class="display-6 fw-bold"><?= number_format($stats['count_late'] ?? 0) ?></div>
                    <div class="small">มาสาย</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm bg-info text-dark">
                <div class="card-body text-center p-3">
                    <div class="display-6 fw-bold"><?= number_format($stats['count_early'] ?? 0) ?></div>
                    <div class="small">ออกก่อนเวลา</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body text-center p-3">
                    <div class="display-6 fw-bold"><?= number_format($stats['count_absent'] ?? 0) ?></div>
                    <div class="small">ขาดงาน</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body text-center p-3">
                    <div class="display-6 fw-bold"><?= number_format($stats['count_leave'] ?? 0) ?></div>
                    <div class="small">ลา / ไปราชการ</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm bg-secondary text-white">
                <div class="card-body text-center p-3">
                    <div class="display-6 fw-bold"><?= number_format($stats['count_weekend'] ?? 0) ?></div>
                    <div class="small">วันหยุด</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Attendance List -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">รายการการลงเวลาปฏิบัติงานประจำวันที่ <?= htmlspecialchars($selectedDate) ?></h5>
            <a href="attendance_report.php?start_date=<?= $selectedDate ?>&end_date=<?= $selectedDate ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-text"></i> ดูรายงานแบบละเอียด
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>รหัส</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>แผนก / กลุ่มงาน</th>
                            <th>เวลาเข้า (Check In)</th>
                            <th>เวลาออก (Check Out)</th>
                            <th>เวลารวม</th>
                            <th>มาสาย (นาที)</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendanceList) > 0): ?>
                            <?php foreach ($attendanceList as $row): ?>
                                <tr>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars($row['employee_id']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><small><?= htmlspecialchars($row['department'] ?? '-') ?></small></td>
                                    <td>
                                        <?php if (!empty($row['check_in']) && $row['check_in'] !== '00:00:00'): ?>
                                            <span class="text-success fw-bold"><?= substr($row['check_in'], 0, 5) ?> น.</span>
                                        <?php else: ?>
                                            <span class="text-danger small">ไม่มีสแกนหน้าเข้า</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['check_out']) && $row['check_out'] !== '00:00:00'): ?>
                                            <span class="text-primary fw-bold"><?= substr($row['check_out'], 0, 5) ?> น.</span>
                                        <?php else: ?>
                                            <span class="text-danger small">ไม่มีสแกนหน้าออก</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['total_hours'] ?? '00:00') ?></span></td>
                                    <td>
                                        <?php if ($row['late_minutes'] > 0): ?>
                                            <span class="badge bg-danger">+<?= $row['late_minutes'] ?> นาที</span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        switch ($row['status']) {
                                            case 'PRESENT': echo '<span class="badge bg-success">ปกติ</span>'; break;
                                            case 'LATE': echo '<span class="badge bg-warning text-dark">มาสาย</span>'; break;
                                            case 'EARLY_LEAVE': echo '<span class="badge bg-info text-dark">ออกก่อน</span>'; break;
                                            case 'ABSENT': echo '<span class="badge bg-danger">ขาดงาน</span>'; break;
                                            case 'WEEKEND': echo '<span class="badge bg-secondary">วันหยุด</span>'; break;
                                            case 'LEAVE': echo '<span class="badge bg-secondary">ลา</span>'; break;
                                            case 'HOLIDAY': echo '<span class="badge bg-primary">ไปราชการ</span>'; break;
                                            default: echo '<span class="badge bg-light text-dark">'.$row['status'].'</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    ยังไม่มีข้อมูลสรุปเวลาปฏิบัติงานประจำวันที่ <?= htmlspecialchars($selectedDate) ?> 
                                    (หากนำเข้าไฟล์แล้ว กรุณากดปุ่ม "ประมวลผลเวลาทั้งหมด")
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>