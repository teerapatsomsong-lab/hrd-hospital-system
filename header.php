<?php
// header.php - แถบ Header แสดงข้อมูลผู้ใช้ และปุ่มออกจากระบบ
$displayName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้งานระบบ';
$displayRole = $_SESSION['role'] ?? 'User';
?>
<!-- Navbar Header Standard -->
<div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
    <div class="d-flex align-items-center gap-3">
        <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
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