<?php
// employee_manage.php - หน้าจัดการบุคลากร (รองรับ 2 วิธี)
require_once 'auth_check.php';
require_once 'config.php';
checkRole(['Super Admin', 'HR Admin']);

$history = $pdo->query("SELECT * FROM import_history WHERE import_type = 'EMPLOYEE' ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลบุคลากร - HRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>จัดการข้อมูลบุคลากร (Employee Management)</h3>
        <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
    </div>

    <ul class="nav nav-tabs nav-fill mb-4" id="empTab">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="single-tab" data-bs-toggle="tab" data-bs-target="#single">1. เพิ่มทีละคน (Single Add)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="batch-tab" data-bs-toggle="tab" data-bs-target="#batch">2. นำเข้าด้วยไฟล์ (Batch CSV/Excel)</button>
        </li>
    </ul>

    <div class="tab-content" id="empTabContent">
        <!-- 1. เพิ่มทีละคน -->
        <div class="tab-pane fade show active" id="single">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="process_employee_single.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">รหัสพนักงาน *</label><input type="text" name="employee_id" class="form-control" required placeholder="101"></div>
                            <div class="col-md-3"><label class="form-label">ชื่อ *</label><input type="text" name="first_name" class="form-control" required></div>
                            <div class="col-md-3"><label class="form-label">นามสกุล</label><input type="text" name="last_name" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">บัตร/คีย์การ์ด</label><input type="text" name="card_number" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">แผนก/กลุ่มงาน</label><input type="text" name="department" class="form-control" placeholder="1. กลุ่มงานบริหารทั่วไป"></div>
                            <div class="col-md-4"><label class="form-label">ตำแหน่ง</label><input type="text" name="position" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">ประเภทพนักงาน</label>
                                <select name="employee_type" class="form-select"><option value="ประจำ">ประจำ</option><option value="ชั่วคราว">ชั่วคราว</option></select>
                            </div>
                            <div class="col-md-3"><label class="form-label">วันที่จ้าง</label><input type="date" name="hire_date" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">เพศ</label>
                                <select name="gender" class="form-select"><option value="ชาย">ชาย</option><option value="หญิง">หญิง</option></select>
                            </div>
                            <div class="col-md-3"><label class="form-label">อีเมล์</label><input type="email" name="email" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">สถานะแอปพลิเคชั่น</label>
                                <select name="app_status" class="form-select"><option value="เปิดใช้งาน">เปิดใช้งาน</option><option value="ปิดการใช้งาน">ปิดการใช้งาน</option></select>
                            </div>
                            <div class="col-md-12"><label class="form-label">พื้นที่/ศูนย์</label><input type="text" name="area" class="form-control" placeholder="ศูนย์การแพทย์นนทบุรี"></div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> บันทึกบุคลากร</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. นำเข้าไฟล์ -->
        <div class="tab-pane fade" id="batch">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form action="process_employee_import.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">เลือกไฟล์ CSV หรือ Excel</label>
                            <input type="file" name="file_import" class="form-control" accept=".csv, .xlsx" required>
                            <div class="form-text">* หาก Employee ID มีในระบบแล้ว ระบบจะ Update ข้อมูลเดิมอัตโนมัติ</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload & Import ข้อมูล</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">ประวัติการนำเข้าไฟล์ล่าสุด</div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr><th>Batch ID</th><th>ไฟล์</th><th>ทั้งหมด</th><th>สำเร็จ</th><th>ล้มเหลว</th><th>วันที่</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $h): ?>
                            <tr>
                                <td><code><?= $h['batch_id'] ?></code></td>
                                <td><?= $h['file_name'] ?></td>
                                <td><?= $h['total_records'] ?></td>
                                <td><span class="badge bg-success"><?= $h['success_count'] ?></span></td>
                                <td><span class="badge bg-danger"><?= $h['failed_count'] ?></span></td>
                                <td><?= $h['created_at'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>