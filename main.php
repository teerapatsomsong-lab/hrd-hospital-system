<?php
// main.php - หน้าเมนูหลัก (Portal)
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หน้าหลัก - ระบบ HRD โรงพยาบาล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="main.php"><i class="bi bi-hospital me-2"></i>HRD System</a>
        <div class="text-white d-flex align-items-center">
            <span class="me-3"><i class="bi bi-person-circle"></i> <?= $_SESSION['full_name'] ?> (<b><?= $_SESSION['role'] ?></b>)</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">ออกจากระบบ</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <h4 class="mb-4">เมนูหลัก (Main Menu)</h4>
    <div class="row g-4">
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-primary mb-2"><i class="bi bi-speedometer2"></i></div>
                <h5>Dashboard</h5>
                <p class="text-muted small">สรุปเวลาปฏิบัติงานภาพรวม</p>
                <a href="dashboard.php" class="btn btn-primary w-100 mt-auto">เข้าสู่ Dashboard</a>
            </div>
        </div>

        <?php if(in_array($_SESSION['role'], ['Super Admin', 'HR Admin'])): ?>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-success mb-2"><i class="bi bi-people-fill"></i></div>
                <h5>จัดการบุคลากร</h5>
                <p class="text-muted small">เพิ่มเดี่ยว/นำเข้าไฟล์พนักงาน</p>
                <a href="employee_manage.php" class="btn btn-success w-100 mt-auto">จัดการพนักงาน</a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-warning mb-2"><i class="bi bi-qr-code-scan"></i></div>
                <h5>นำเข้า Face Scan</h5>
                <p class="text-muted small">นำเข้า CSV/Excel สแกนหน้า</p>
                <a href="face_scan_import.php" class="btn btn-warning w-100 mt-auto">นำเข้าเวลาสแกน</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-info mb-2"><i class="bi bi-calendar-check-fill"></i></div>
                <h5>ประมวลผลเวลา</h5>
                <p class="text-muted small">คำนวณ ขาด ลา สาย OT</p>
                <a href="process_attendance.php" class="btn btn-info text-white w-100 mt-auto">ประมวลผลเวลา</a>
            </div>
        </div>

        <!-- แทรกในส่วน <div class="row g-4"> ของ main.php -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-primary mb-2"><i class="bi bi-person-lines-fill"></i></div>
                <h5>ดูข้อมูลบุคลากร</h5>
                <p class="text-muted small">ค้นหาและดูทำเนียบรายชื่อพนักงาน</p>
                <a href="employee_list.php" class="btn btn-primary w-100 mt-auto">ดูรายชื่อพนักงาน</a>
            </div>
        </div>

        <!-- แทรกในส่วน <div class="row g-4"> ในไฟล์ main.php -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-warning mb-2"><i class="bi bi-calendar2-week"></i></div>
                <h5>การลา / ไปราชการ</h5>
                <p class="text-muted small">บันทึกวันลา ประชุม อบรม สัมมนา</p>
                <a href="leave_manage.php" class="btn btn-warning text-dark w-100 mt-auto">จัดการการลา/ราชการ</a>
            </div>
        </div>

        <!-- แทรกในส่วน <div class="row g-4"> ของ main.php -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="display-5 text-dark mb-2"><i class="bi bi-clock-history"></i></div>
                <h5>ตารางการลงเวลา</h5>
                <p class="text-muted small">ดูประวัติเข้า-ออกงานรายบุคคลย้อนหลัง</p>
                <a href="attendance_report.php" class="btn btn-dark w-100 mt-auto">ดูตารางการลงเวลา</a>
            </div>
        </div>

    </div>
</div>
</body>
</html>