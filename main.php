<?php
// main.php - หน้าหลักของระบบ HRD Hospital System (แสดงชื่อ-นามสกุลผู้ใช้งานใน Header)
require_once 'auth_check.php';
require_once 'config.php';

// ดึงเวลาอัปเดตข้อมูลการลงเวลาล่าสุด
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

// สถิติตัวเลขเบื้องต้นสำหรับแสดงการ์ดสรุป
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE app_status = 'เปิดใช้งาน'")->fetchColumn();
$todayLeaves     = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'APPROVED' AND CURRENT_DATE() BETWEEN start_date AND end_date")->fetchColumn();

// ดึงชื่อ-นามสกุลผู้ใช้งานจาก Session (หากไม่มีให้ดึง Username แทน)
$displayName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้งานระบบ';
$displayRole = $_SESSION['role'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หน้าหลัก - ระบบบริหารจัดการทรัพยากรบุคคล (HRD System)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .menu-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            border-radius: 12px;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
        }
        .icon-box {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
    </style>
</head>
<body class="bg-light py-4">

<div class="container px-4">
    <!-- Navbar Header (ปรับแต่งแสดงชื่อ-นามสกุลจริงผู้ใช้งาน) -->
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-box bg-primary text-white">
                <i class="bi bi-hospital"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold">HRD Hospital System</h4>
                <small class="text-muted">ระบบบริหารจัดการเวลาปฏิบัติงาน บุคลากร และการลา</small>
            </div>
        </div>
        <div class="text-end">
            <div class="d-flex align-items-center gap-2">
                <div class="text-end me-1">
                    <div class="fw-bold text-dark"><i class="bi bi-person-circle text-primary"></i> <?= htmlspecialchars($displayName) ?></div>
                    <small class="text-muted">(<?= htmlspecialchars($_SESSION['username'] ?? '') ?>)</small>
                </div>
                <span class="badge bg-primary fs-6 p-2"><i class="bi bi-shield-check"></i> <?= htmlspecialchars($displayRole) ?></span>
            </div>
            <div class="mt-1">
                <a href="logout.php" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('ยืนยันการออกจากระบบ?')">
                    <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="alert alert-info shadow-sm d-flex justify-content-between align-items-center mb-4" role="alert">
        <div>
            <i class="bi bi-info-circle-fill me-2"></i>
            สถานะระบบ: ข้อมูลการลงเวลาล่าสุดเมื่อ <strong><?= formatThaiDateTime($lastUpdate) ?></strong>
        </div>
        <div>
            <a href="process_attendance.php?auto_all=1" class="btn btn-warning btn-sm text-dark fw-bold">
                <i class="bi bi-cpu"></i> ประมวลผลเวลาทั้งหมด
            </a>
        </div>
    </div>

    <!-- Summary Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-white p-3 d-flex flex-row align-items-center">
                <div class="icon-box bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-0">บุคลากรเปิดใช้งาน</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($totalEmployees) ?> <small class="fs-6 text-muted">คน</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-white p-3 d-flex flex-row align-items-center">
                <div class="icon-box bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-person-dash-fill"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-0">ผู้ลา/ไปราชการวันนี้</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($todayLeaves) ?> <small class="fs-6 text-muted">คน</small></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Menu Cards -->
    <h5 class="fw-bold mb-3 text-secondary"><i class="bi bi-grid-fill me-1"></i> เมนูการใช้งานหลัก</h5>
    <div class="row g-4 mb-4">
        
        <!-- 1. รายงานตารางการลงเวลา -->
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3">
                <div class="card-body text-center">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h5 class="fw-bold">รายงานตารางลงเวลา</h5>
                    <p class="text-muted small">ดูประวัติลงเวลา สแกนหน้า เข้า-ออก มาสาย ออกก่อน ขาดงาน และส่งออกรายงาน Excel/PDF</p>
                    <a href="attendance_report.php" class="btn btn-primary w-100"><i class="bi bi-arrow-right-circle"></i> เข้าสู่รายงานตารางลงเวลา</a>
                </div>
            </div>
        </div>

        <!-- 2. สรุปสถิติการลา/สาย (Drill-down) -->
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3 border-start border-4 border-info">
                <div class="card-body text-center">
                    <div class="icon-box bg-info bg-opacity-10 text-info mx-auto mb-3">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                    </div>
                    <h5 class="fw-bold text-info">สรุปสถิติการลา/สาย</h5>
                    <p class="text-muted small">สรุปจำนวนวันลา ประเภทวันลา และจำนวนครั้งการสาย กดที่ตัวเลขเพื่อดูลายละเอียดวันเวลาจริงได้</p>
                    <a href="leave_summary_report.php" class="btn btn-info text-dark w-100 fw-bold"><i class="bi bi-search"></i> ดูสรุปสถิติรายละเอียด</a>
                </div>
            </div>
        </div>

        <!-- 3. บันทึกการลา และราชการ -->
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3">
                <div class="card-body text-center">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning mx-auto mb-3">
                        <i class="bi bi-calendar2-week"></i>
                    </div>
                    <h5 class="fw-bold">บันทึกการลา / ราชการ</h5>
                    <p class="text-muted small">บันทึกใบลา ลาป่วย ลากิจ ลาครึ่งวันเช้า-บ่าย ไปประชุม/ราชการ และตรวจสอบวันลาซ้ำ</p>
                    <a href="leave_manage.php" class="btn btn-warning text-dark w-100 fw-bold"><i class="bi bi-arrow-right-circle"></i> จัดการข้อมูลการลา</a>
                </div>
            </div>
        </div>

        <!-- 4. จัดการตารางเวรรายเดือน -->
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3">
                <div class="card-body text-center">
                    <div class="icon-box bg-secondary bg-opacity-10 text-secondary mx-auto mb-3">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <h5 class="fw-bold">จัดการตารางเวรรายเดือน</h5>
                    <p class="text-muted small">กำหนดตารางกะปฏิบัติงานรายวัน/รายเดือน สำหรับบุคลากรรูปแบบการทำงานแบบ SHIFT</p>
                    <a href="work_schedule_manage.php" class="btn btn-secondary text-white w-100"><i class="bi bi-arrow-right-circle"></i> จัดการตารางเวร</a>
                </div>
            </div>
        </div>

        <!-- 5. ข้อมูลบุคลากร -->
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3">
                <div class="card-body text-center">
                    <div class="icon-box bg-success bg-opacity-10 text-success mx-auto mb-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5 class="fw-bold">ข้อมูลบุคลากร</h5>
                    <p class="text-muted small">จัดการรายชื่อบุคลากร ตั้งเวลาเข้างานรายบุคคล กำหนดรูปแบบงาน (FIXED, FLEXIBLE, SHIFT)</p>
                    <a href="employee_list.php" class="btn btn-success w-100"><i class="bi bi-arrow-right-circle"></i> จัดการบุคลากร</a>
                </div>
            </div>
        </div>

        <!-- 6. จัดการผู้ใช้งานระบบ (เฉพาะ Super Admin) -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin'): ?>
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3 border-start border-4 border-danger">
                <div class="card-body text-center">
                    <div class="icon-box bg-danger bg-opacity-10 text-danger mx-auto mb-3">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h5 class="fw-bold text-danger">จัดการผู้ใช้งานระบบ</h5>
                    <p class="text-muted small">กำหนดสิทธิ์การเข้าใช้งาน ผูกบัญชีกับพนักงาน สร้าง Username/Password (สิทธิ์ Super Admin)</p>
                    <a href="user_manage.php" class="btn btn-danger w-100 fw-bold"><i class="bi bi-gear"></i> จัดการ User</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 7. นำเข้าไฟล์สแกนหน้า CSV (เฉพาะ Super Admin และ HR Admin) -->
        <?php if (in_array($_SESSION['role'] ?? '', ['Super Admin', 'HR Admin'])): ?>
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3">
                <div class="card-body text-center">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                    </div>
                    <h5 class="fw-bold">นำเข้าไฟล์สแกนหน้า</h5>
                    <p class="text-muted small">อัปโหลดไฟล์ Log สแกนใบหน้า (CSV/Excel) จากเครื่องสแกนเพื่อเข้าสู่ระบบประมวลผล</p>
                    <a href="face_scan_import.php" class="btn btn-outline-primary w-100"><i class="bi bi-upload"></i> นำเข้าไฟล์ Log</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 8. นำเข้าบุคลากร Batch Import (เฉพาะ Super Admin และ HR Admin) -->
        <?php if (in_array($_SESSION['role'] ?? '', ['Super Admin', 'HR Admin'])): ?>
        <div class="col-md-4">
            <div class="card shadow-sm menu-card h-100 p-3">
                <div class="card-body text-center">
                    <div class="icon-box bg-secondary bg-opacity-10 text-secondary mx-auto mb-3">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <h5 class="fw-bold">นำเข้าบุคลากร (Batch Import)</h5>
                    <p class="text-muted small">นำเข้าหรืออัปเดตข้อมูลบุคลากรจำนวนมากผ่านไฟล์ Excel/CSV</p>
                    <a href="employee_manage.php" class="btn btn-outline-secondary w-100"><i class="bi bi-gear"></i> นำเข้าข้อมูลพนักงาน</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>