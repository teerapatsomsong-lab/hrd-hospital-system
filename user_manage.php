<?php
// user_manage.php - หน้าจัดการผู้ใช้งานระบบ (เฉพาะ Super Admin เท่านั้น + ค้นหาพนักงานแบบ Autocomplete)
require_once 'auth_check.php';
require_once 'config.php';

// 1. เช็กสิทธิ์การเข้าถึง (จำกัดเฉพาะ Super Admin เท่านั้น)
checkRole(['Super Admin']);

$message = '';

// 2. จัดการการเพิ่ม / แก้ไข / ลบ ผู้ใช้งาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action   =$_POST['action'];
    $userId   = intval($_POST['user_id'] ?? 0);
    $empId    = trim($_POST['employee_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $dept     = trim($_POST['department'] ?? '');
    $role     = trim($_POST['role'] ?? 'User');
    $status   = trim($_POST['status'] ?? 'ACTIVE');

    if ($action === 'add') {
        if ($username &&$password) {
            // เช็ก username ซ้ำ
            $chk =$pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {$message = '<div class="alert alert-danger">Username นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น</div>';
            } else {
                $empIdVal = ($empId !== '') ?$empId : NULL;

                $stmt =$pdo->prepare("INSERT INTO users (employee_id, username, password, full_name, department, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$empIdVal, $username,$password, $fullName,$dept, $role,$status]);

                logAudit($pdo, $_SESSION['user_id'], 'ADD_USER', 'users',$username, $_POST);$message = '<div class="alert alert-success">สร้างผู้ใช้งานใหม่เรียบร้อยแล้ว!</div>';
            }
        }
    } else if ($action === 'edit' && $userId > 0) {$empIdVal = ($empId !== '') ?$empId : NULL;

        if (!empty($password)) {
            // ถ้ามีการกรอกรหัสผ่านใหม่ ให้ทำการอัปเดตด้วย
            $stmt =$pdo->prepare("UPDATE users SET employee_id = ?, full_name = ?, department = ?, role = ?, status = ?, password = ? WHERE id = ?");
            $stmt->execute([$empIdVal, $fullName,$dept, $role,$status, $password,$userId]);
        } else {
            // ถ้ารหัสผ่านว่างไว้ ให้อัปเดตเฉพาะข้อมูลอื่น
            $stmt =$pdo->prepare("UPDATE users SET employee_id = ?, full_name = ?, department = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$empIdVal,$fullName, $dept,$role, $status,$userId]);
        }

        logAudit($pdo, $_SESSION['user_id'], 'EDIT_USER', 'users',$userId, $_POST);$message = '<div class="alert alert-success">อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว!</div>';
    }
}

// ลบผู้ใช้งาน
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    // ป้องกันการลบตัวเอง
    if ($delId === intval($_SESSION['user_id'] ?? 0)) {
        echo "<script>alert('ไม่สามารถลบบัญชีผู้ใช้ที่กำลังใช้งานอยู่ได้'); window.location.href='user_manage.php';</script>";
        exit();
    }
    $stmtDel =$pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmtDel->execute([$delId]);
    logAudit($pdo, $_SESSION['user_id'], 'DELETE_USER', 'users',$delId, 'ลบผู้ใช้');
    echo "<script>alert('ลบผู้ใช้งานเรียบร้อย'); window.location.href='user_manage.php';</script>";
    exit();
}

// 3. ดึงรายการ User ทั้งหมด
$userSql = "SELECT u.*, e.first_name, e.last_name, e.department as emp_department 
            FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.employee_id 
            ORDER BY u.id DESC";
$users = $pdo->query($userSql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งานระบบ - HRD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .autocomplete-suggestions {
            position: absolute;
            z-index: 1050;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 0 0 0.375rem 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
        }
        .autocomplete-item:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body class="bg-light py-4">

<div class="container-fluid px-4">
    <?php include 'header.php'; ?> <!-- 2. ดึง Header มาแสดง -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-shield-lock text-danger"></i> จัดการผู้ใช้งานระบบ (Super Admin Only)</h3>
        <div>
            <button type="button" class="btn btn-primary me-2" onclick="openAddModal()">
                <i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้ใหม่
            </button>
            <a href="main.php" class="btn btn-secondary"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
        </div>
    </div>

    <?= $message ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">รายชื่อผู้ใช้งานทั้งหมด (<?= count($users) ?> บัญชี)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>ชื่อ - นามสกุล (Display)</th>
                            <th>ผูกกับรหัสพนักงาน</th>
                            <th>แผนก/กลุ่มงาน</th>
                            <th>สิทธิ์การใช้งาน (Role)</th>
                            <th>สถานะ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $index =>$u): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong class="text-primary"><?= htmlspecialchars($u['username']) ?></strong></td>
                                    <td class="fw-bold"><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($u['employee_id'])): ?>
                                            <span class="badge bg-dark me-1"><?= htmlspecialchars($u['employee_id']) ?></span>
                                            <small><?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted small">- บัญชีส่วนกลาง -</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($u['department'] ?? $u['emp_department'] ?? '-') ?></small></td>
                                    <td>
                                        <?php
                                        if ($u['role'] === 'Super Admin') echo '<span class="badge bg-danger">Super Admin</span>';
                                        else if ($u['role'] === 'HR Admin') echo '<span class="badge bg-warning text-dark">HR Admin</span>';
                                        else echo '<span class="badge bg-info text-dark">User</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (strtoupper($u['status']) === 'ACTIVE'): ?>
                                            <span class="badge bg-success">ACTIVE</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-warning btn-sm me-1" onclick='openEditModal(<?= json_encode($u) ?>)'>
                                            <i class="bi bi-pencil"></i> แก้ไข
                                        </button>
                                        <?php if ($u['id'] !== intval($_SESSION['user_id'] ?? 0)): ?>
                                            <a href="?delete_id=<?= $u['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันการลบผู้ใช้ <?= htmlspecialchars($u['username']) ?> ?')">
                                                <i class="bi bi-trash"></i> ลบ
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลผู้ใช้งาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่ม / แก้ไข ผู้ใช้งาน -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" id="modal_action" value="add">
                <input type="hidden" name="user_id" id="modal_user_id">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle"><i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้งานใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- ช่องค้นหาพนักงานแบบ Autocomplete -->
                        <div class="col-md-12 bg-primary bg-opacity-10 p-3 rounded position-relative">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-search"></i> ค้นหาบุคลากรเพื่อดึงข้อมูลอัตโนมัติ</label>
                            <div class="input-group">
                                <input type="text" id="empSearchInput" class="form-control" placeholder="พิมพ์ชื่อ, นามสกุล หรือรหัสพนักงาน..." autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary bg-white" onclick="clearSelectedEmployee()"><i class="bi bi-x-circle"></i> ล้าง</button>
                            </div>
                            <input type="hidden" name="employee_id" id="selectedEmpId">
                            <div id="autocompleteList" class="autocomplete-suggestions" style="display: none;"></div>
                            <div id="selectedEmpBadge" class="form-text text-success fw-bold mt-1"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Username *</label>
                            <input type="text" name="username" id="user_username" class="form-control" required placeholder="เช่น admin_01">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" id="passwordLabel">Password *</label>
                            <input type="text" name="password" id="user_password" class="form-control" placeholder="ตั้งรหัสผ่าน...">
                            <small class="text-muted d-none" id="passwordNote">* เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่านเดิม</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">ชื่อ - นามสกุลแสดงผล (Full Name) *</label>
                            <input type="text" name="full_name" id="user_full_name" class="form-control" required placeholder="เช่น เจ้าหน้าที่ HR">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">แผนก / กลุ่มงาน</label>
                            <input type="text" name="department" id="user_department" class="form-control" placeholder="เช่น กลุ่มงานบริหารทั่วไป">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">สิทธิ์การใช้งาน (Role) *</label>
                            <select name="role" id="user_role" class="form-select" required>
                                <option value="User">User (ดูรายงานทั่วไป)</option>
                                <option value="HR Admin">HR Admin (จัดการการลา/ลงเวลา/พนักงาน)</option>
                                <option value="Super Admin">Super Admin (สิทธิ์สูงสุดสิทธิเต็ม)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">สถานะบัญชี *</label>
                            <select name="status" id="user_status" class="form-select" required>
                                <option value="ACTIVE">ACTIVE (ใช้งานปกติ)</option>
                                <option value="INACTIVE">INACTIVE (ระงับใช้งาน)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ระบบค้นหาพนักงาน Autocomplete
const searchInput = document.getElementById('empSearchInput');
const hiddenEmpId = document.getElementById('selectedEmpId');
const autocompleteList = document.getElementById('autocompleteList');
const selectedBadge = document.getElementById('selectedEmpBadge');

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    if (query.length < 1) {
        autocompleteList.style.display = 'none';
        return;
    }

    fetch('api_search_employee.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            autocompleteList.innerHTML = '';
            if (data.length > 0) {
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = `<strong>${item.first_name} ${item.last_name}</strong> <small class="text-muted">(รหัส: ${item.employee_id} - ${item.department || '-'})</small>`;
                    div.onclick = function() {
                        const fullName = `${item.first_name} ${item.last_name}`;
                        searchInput.value = fullName;
                        hiddenEmpId.value = item.employee_id;
                        selectedBadge.innerText = `✓ ผูกกับรหัสพนักงาน: ${item.employee_id}`;
                        
                        // เติมข้อมูลลงช่อง Full Name และ Department อัตโนมัติ
                        document.getElementById('user_full_name').value = fullName;
                        if (item.department) {
                            document.getElementById('user_department').value = item.department;
                        }
                        
                        autocompleteList.style.display = 'none';
                    };
                    autocompleteList.appendChild(div);
                });
                autocompleteList.style.display = 'block';
            } else {
                autocompleteList.innerHTML = '<div class="p-2 text-muted small">ไม่พบข้อมูลพนักงาน</div>';
                autocompleteList.style.display = 'block';
            }
        });
});

function clearSelectedEmployee() {
    searchInput.value = '';
    hiddenEmpId.value = '';
    selectedBadge.innerText = '';
    autocompleteList.style.display = 'none';
}

// ปิด Dropdown เมื่อคลิกนอกพื้นที่
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !autocompleteList.contains(e.target)) {
        autocompleteList.style.display = 'none';
    }
});

function openAddModal() {
    document.getElementById('modal_action').value = 'add';
    document.getElementById('modal_user_id').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้งานใหม่';
    clearSelectedEmployee();
    document.getElementById('user_username').value = '';
    document.getElementById('user_username').readOnly = false;
    document.getElementById('user_password').value = '';
    document.getElementById('user_password').required = true;
    document.getElementById('passwordNote').classList.add('d-none');
    document.getElementById('user_full_name').value = '';
    document.getElementById('user_department').value = 'กลุ่มงานบริหารทั่วไป';
    document.getElementById('user_role').value = 'User';
    document.getElementById('user_status').value = 'ACTIVE';

    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

function openEditModal(user) {
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('modal_user_id').value = user.id;
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil-square"></i> แก้ไขผู้ใช้งาน: ' + user.username;
    
    // ตั้งค่า Autocomplete เมื่อกดแก้ไข
    if (user.employee_id) {
        hiddenEmpId.value = user.employee_id;
        searchInput.value = user.full_name || '';
        selectedBadge.innerText = `✓ ผูกกับรหัสพนักงาน: ${user.employee_id}`;
    } else {
        clearSelectedEmployee();
    }

    document.getElementById('user_username').value = user.username;
    document.getElementById('user_username').readOnly = true;
    document.getElementById('user_password').value = '';
    document.getElementById('user_password').required = false;
    document.getElementById('passwordNote').classList.remove('d-none');
    document.getElementById('user_full_name').value = user.full_name || '';
    document.getElementById('user_department').value = user.department || user.emp_department || '';
    document.getElementById('user_role').value = user.role || 'User';
    document.getElementById('user_status').value = user.status || 'ACTIVE';

    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}
</script>
</body>
</html>