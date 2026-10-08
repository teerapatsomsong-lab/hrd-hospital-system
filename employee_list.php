<?php
// employee_list.php - รายชื่อบุคลากร (ฉบับสมบูรณ์: รูปโปรไฟล์, Pagination, Modal ละเอียด + work_mode)
require_once 'auth_check.php';
require_once 'config.php';

// 1. จัดการการอัปเดตข้อมูลพนักงาน (Update Employee Data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
    checkRole(['Super Admin', 'HR Admin']);
    
    $empId = trim($_POST['employee_id'] ?? '');
    
    if ($empId !== '') {
        $updateSql = "UPDATE employees SET 
                        first_name = ?, 
                        last_name = ?, 
                        card_number = ?, 
                        department = ?, 
                        position = ?, 
                        employee_type = ?, 
                        hire_date = ?, 
                        gender = ?, 
                        email = ?, 
                        app_status = ?, 
                        work_start_time = ?, 
                        work_end_time = ?, 
                        work_mode = ?, 
                        area = ? 
                      WHERE employee_id = ?";
                      
        $stmtUpdate = $pdo->prepare($updateSql);
        $stmtUpdate->execute([
            $_POST['first_name'] ?? '',
            $_POST['last_name'] ?? '',
            $_POST['card_number'] ?? '',
            $_POST['department'] ?? '',
            $_POST['position'] ?? '',
            $_POST['employee_type'] ?? 'ประจำ',
            !empty($_POST['hire_date']) ? $_POST['hire_date'] : null,
            $_POST['gender'] ?? 'ชาย',
            $_POST['email'] ?? '',
            $_POST['app_status'] ?? 'เปิดใช้งาน',
            $_POST['work_start_time'] ?? '08:00:00',
            $_POST['work_end_time'] ?? '16:00:00',
            $_POST['work_mode'] ?? 'FIXED',
            $_POST['area'] ?? '',
            $empId
        ]);

        logAudit($pdo, $_SESSION['user_id'], 'UPDATE_EMPLOYEE', 'employees', $empId, $_POST);
        echo "<script>alert('แก้ไขข้อมูลพนักงานสำเร็จ!'); window.location.href='employee_list.php';</script>";
        exit();
    }
}

// 2. จัดการการอัปโหลดรูปภาพโปรไฟล์ (Upload Avatar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && isset($_POST['emp_id'])) {
    checkRole(['Super Admin', 'HR Admin']);
    $empId = $_POST['emp_id'];
    $file = $_FILES['profile_image'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowedExts)) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = 'emp_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $empId) . '_' . time() . '.' . $fileExt;
            $targetFilePath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                $updateStmt = $pdo->prepare("UPDATE employees SET profile_image = ? WHERE employee_id = ?");
                $updateStmt->execute([$targetFilePath, $empId]);

                logAudit($pdo, $_SESSION['user_id'], 'UPLOAD_AVATAR', 'employees', $empId, 'อัปโหลดรูปโปรไฟล์');
                echo "<script>alert('อัปโหลดรูปภาพสำเร็จ!'); window.location.href='employee_list.php';</script>";
                exit();
            }
        } else {
            echo "<script>alert('รองรับเฉพาะไฟล์รูปภาพ .jpg, .jpeg, .png, .webp เท่านั้น');</script>";
        }
    }
}

// ดึงรายการแผนกทั้งหมดสำหรับ Dropdown Filter
$deptStmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// รับค่าการค้นหาและ Filter
$search     = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? '');
$filterMode = trim($_GET['work_mode'] ?? '');
$page       = max(1, intval($_GET['page'] ?? 1));
$limit      = 20; // แสดงหน้าละ 20 คน
$offset     = ($page - 1) * $limit;

// เงื่อนไข Search
$where  = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(employee_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR card_number LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filterDept !== '') {
    $where[] = "department = ?";
    $params[] = $filterDept;
}

if ($filterMode !== '') {
    $where[] = "work_mode = ?";
    $params[] = $filterMode;
}

$whereClause = implode(" AND ", $where);

// นับจำนวนทั้งหมด
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE {$whereClause}");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages   = ceil($totalRecords / $limit);

// ดึงข้อมูลพนักงาน
$sql = "SELECT * FROM employees WHERE {$whereClause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อบุคลากร - ระบบ HRD โรงพยาบาล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .clickable-row { cursor: pointer; }
        .avatar-lg { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid #0d6efd; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .avatar-sm { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
    </style>
</head>
<body class="bg-light py-4">

<div class="container-fluid px-4">
    <?php include 'header.php'; ?> <!-- 2. ดึง Header มาแสดง -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-people text-primary"></i> ข้อมูลบุคลากร (Employee Directory)</h3>
        <div>
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Super Admin', 'HR Admin'])): ?>
                <a href="employee_manage.php" class="btn btn-success btn-sm me-2"><i class="bi bi-person-plus-fill"></i> จัดการ/เพิ่มข้อมูล</a>
            <?php endif; ?>
            <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
        </div>
    </div>

    <!-- Filter & Search Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">ค้นหา (รหัส / ชื่อ / บัตร)</label>
                    <input type="text" name="search" class="form-control" placeholder="พิมพ์คำค้นหา..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">แผนก/กลุ่มงาน</label>
                    <select name="department" class="form-select">
                        <option value="">-- แสดงทุกแผนก --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">รูปแบบการทำงาน</label>
                    <select name="work_mode" class="form-select">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="FIXED" <?= $filterMode === 'FIXED' ? 'selected' : '' ?>>พนักงานประจำ (เวลาปกติ)</option>
                        <option value="FLEXIBLE" <?= $filterMode === 'FLEXIBLE' ? 'selected' : '' ?>>ยืดหยุ่น / Part-time</option>
                        <option value="SHIFT" <?= $filterMode === 'SHIFT' ? 'selected' : '' ?>>ตามตารางเวร</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> ค้นหา</button>
                    <a href="employee_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">รายการบุคลากรทั้งหมด (<?= number_format($totalRecords) ?> คน)</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> คลิกที่แถวเพื่อดู/แก้ไขรายละเอียด</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">รูป</th>
                            <th>รหัสพนักงาน</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>แผนก / กลุ่มงาน</th>
                            <th>ตำแหน่ง</th>
                            <th>เวลาเข้างาน</th>
                            <th>รูปแบบงาน</th>
                            <th>สถานะการใช้งาน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $emp): 
                                $avatarSrc = !empty($emp['profile_image']) && file_exists($emp['profile_image']) 
                                    ? $emp['profile_image'] 
                                    : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                                $startTime = !empty($emp['work_start_time']) ? substr($emp['work_start_time'], 0, 5) : '08:00';
                            ?>
                                <tr class="clickable-row" onclick='openEmpDetail(<?= json_encode($emp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <td><img src="<?= $avatarSrc ?>" class="avatar-sm" alt="profile"></td>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars($emp['employee_id']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($emp['position'] ?? '-') ?></td>
                                    <td><span class="badge bg-primary"><?= $startTime ?> น.</span></td>
                                    <td>
                                        <?php
                                        $wm = $emp['work_mode'] ?? 'FIXED';
                                        if ($wm === 'FLEXIBLE') {
                                            echo '<span class="badge bg-warning text-dark">ยืดหยุ่น / Part-time</span>';
                                        } else if ($wm === 'SHIFT') {
                                            echo '<span class="badge bg-info text-dark">ตามตารางเวร</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">พนักงานประจำ</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (($emp['app_status'] ?? '') === 'เปิดใช้งาน'): ?>
                                            <span class="badge bg-success">เปิดใช้งาน</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= htmlspecialchars($emp['app_status'] ?? 'ปิดการใช้งาน') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลบุคลากรตามเงื่อนไขที่ค้นหา</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <div class="small text-muted">
                    แสดง <?= $offset + 1 ?> ถึง <?= min($offset + $limit, $totalRecords) ?> จากทั้งหมด <?= $totalRecords ?> รายการ
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">ก่อนหน้า</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">ถัดไป</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal แสดงรายละเอียด/แก้ไขข้อมูลพนักงาน -->
<div class="modal fade" id="empDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> ข้อมูลรายละเอียดบุคลากร</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <!-- Section 1: Avatar & Upload Form -->
                <div class="text-center mb-4">
                    <img id="modalAvatar" src="https://cdn-icons-png.flaticon.com/512/149/149071.png" class="avatar-lg bg-light mb-2" alt="Avatar">
                    
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Super Admin', 'HR Admin'])): ?>
                    <form method="POST" enctype="multipart/form-data" class="mt-2">
                        <input type="hidden" name="emp_id" id="uploadEmpId">
                        <div class="input-group input-group-sm w-50 mx-auto">
                            <input type="file" name="profile_image" class="form-control" accept="image/*" required>
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-upload"></i> เปลี่ยนรูป</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Section 2: View Mode -->
                <div id="viewMode">
                    <div class="text-center mb-3">
                        <h4 id="modalName" class="fw-bold mb-1"></h4>
                        <p id="modalPosition" class="text-muted mb-0"></p>
                    </div>
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><strong>รหัสพนักงาน:</strong> <span id="modalEmpId"></span></div>
                                <div class="col-md-4"><strong>เลขบัตร:</strong> <span id="modalCard"></span></div>
                                <div class="col-md-4"><strong>ประเภท:</strong> <span id="modalType"></span></div>
                                <div class="col-md-6"><strong>แผนก/กลุ่มงาน:</strong> <span id="modalDept"></span></div>
                                <div class="col-md-6"><strong>ตำแหน่ง:</strong> <span id="modalPosText"></span></div>
                                <div class="col-md-4"><strong class="text-primary">เวลาเข้างาน:</strong> <span id="modalWorkStart" class="fw-bold text-primary"></span> น.</div>
                                <div class="col-md-4"><strong class="text-primary">เวลาออกงาน:</strong> <span id="modalWorkEnd" class="fw-bold text-primary"></span> น.</div>
                                <div class="col-md-4"><strong class="text-primary">รูปแบบงาน:</strong> <span id="modalWorkMode" class="fw-bold"></span></div>
                                <div class="col-md-4"><strong>เพศ:</strong> <span id="modalGender"></span></div>
                                <div class="col-md-4"><strong>วันที่จ้าง:</strong> <span id="modalHireDate"></span></div>
                                <div class="col-md-4"><strong>สถานะการใช้งาน:</strong> <span id="modalStatus"></span></div>
                                <div class="col-md-6"><strong>อีเมล:</strong> <span id="modalEmail"></span></div>
                                <div class="col-md-6"><strong>พื้นที่/ศูนย์:</strong> <span id="modalArea"></span></div>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Super Admin', 'HR Admin'])): ?>
                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-warning" onclick="toggleEditMode(true)"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลพนักงาน</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Section 3: Edit Mode -->
                <div id="editMode" style="display: none;">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_employee">
                        <input type="hidden" name="employee_id" id="editEmpIdHidden">

                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-pencil-fill me-1"></i> กำลังแก้ไขข้อมูลรหัสพนักงาน: <strong id="editEmpIdDisplay"></strong>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">นามสกุล</label>
                                <input type="text" name="last_name" id="editLastName" class="form-control">
                            </div>

                            <div class="col-md-4 bg-warning bg-opacity-10 p-2 rounded">
                                <label class="form-label fw-bold text-dark">เวลาเข้างานปกติ *</label>
                                <input type="time" name="work_start_time" id="editWorkStart" class="form-control" required>
                            </div>
                            <div class="col-md-4 bg-warning bg-opacity-10 p-2 rounded">
                                <label class="form-label fw-bold text-dark">เวลาออกงานปกติ *</label>
                                <input type="time" name="work_end_time" id="editWorkEnd" class="form-control" required>
                            </div>
                            <div class="col-md-4 bg-warning bg-opacity-10 p-2 rounded">
                                <label class="form-label fw-bold text-dark">รูปแบบการทำงาน *</label>
                                <select name="work_mode" id="editWorkMode" class="form-select" required>
                                    <option value="FIXED">ประจำ (FIXED)</option>
                                    <option value="FLEXIBLE">ยืดหยุ่น / Part-time</option>
                                    <option value="SHIFT">ตามตารางเวร (SHIFT)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">เลขบัตร/คีย์การ์ด</label>
                                <input type="text" name="card_number" id="editCardNumber" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ประเภทพนักงาน</label>
                                <select name="employee_type" id="editEmployeeType" class="form-select">
                                    <option value="ประจำ">ประจำ</option>
                                    <option value="ชั่วคราว">ชั่วคราว</option>
                                    <option value="พนักงานราชการ">พนักงานราชการ</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">แผนก/กลุ่มงาน</label>
                                <input type="text" name="department" id="editDepartment" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ตำแหน่ง</label>
                                <input type="text" name="position" id="editPosition" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เพศ</label>
                                <select name="gender" id="editGender" class="form-select">
                                    <option value="ชาย">ชาย</option>
                                    <option value="หญิง">หญิง</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่จ้าง</label>
                                <input type="date" name="hire_date" id="editHireDate" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">สถานะแอปพลิเคชัน</label>
                                <select name="app_status" id="editAppStatus" class="form-select">
                                    <option value="เปิดใช้งาน">เปิดใช้งาน</option>
                                    <option value="ปิดการใช้งาน">ปิดการใช้งาน</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">อีเมล</label>
                                <input type="email" name="email" id="editEmail" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">พื้นที่/ศูนย์</label>
                                <input type="text" name="area" id="editArea" class="form-control">
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-secondary me-2" onclick="toggleEditMode(false)">ยกเลิก</button>
                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEmpDetail(emp) {
    toggleEditMode(false);

    const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    document.getElementById('modalAvatar').src = emp.profile_image ? emp.profile_image : defaultAvatar;
    document.getElementById('uploadEmpId').value = emp.employee_id;

    // View Mode Display
    document.getElementById('modalName').innerText = (emp.first_name || '') + ' ' + (emp.last_name || '');
    document.getElementById('modalPosition').innerText = emp.position || '-';
    document.getElementById('modalEmpId').innerText = emp.employee_id || '-';
    document.getElementById('modalCard').innerText = emp.card_number || '-';
    document.getElementById('modalType').innerText = emp.employee_type || '-';
    document.getElementById('modalDept').innerText = emp.department || '-';
    document.getElementById('modalPosText').innerText = emp.position || '-';
    document.getElementById('modalWorkStart').innerText = emp.work_start_time ? emp.work_start_time.substring(0,5) : '08:00';
    document.getElementById('modalWorkEnd').innerText = emp.work_end_time ? emp.work_end_time.substring(0,5) : '16:00';
    
    var modeText = 'พนักงานประจำ';
    if (emp.work_mode === 'FLEXIBLE') modeText = 'ยืดหยุ่น / Part-time';
    else if (emp.work_mode === 'SHIFT') modeText = 'ตามตารางเวร';
    document.getElementById('modalWorkMode').innerText = modeText;

    document.getElementById('modalGender').innerText = emp.gender || '-';
    document.getElementById('modalHireDate').innerText = emp.hire_date || '-';
    document.getElementById('modalEmail').innerText = emp.email || '-';
    document.getElementById('modalStatus').innerHTML = emp.app_status === 'เปิดใช้งาน' 
        ? '<span class="badge bg-success">เปิดใช้งาน</span>' 
        : '<span class="badge bg-danger">' + (emp.app_status || 'ปิดการใช้งาน') + '</span>';
    document.getElementById('modalArea').innerText = emp.area || '-';

    // Edit Mode Form Values
    document.getElementById('editEmpIdHidden').value = emp.employee_id;
    document.getElementById('editEmpIdDisplay').innerText = emp.employee_id;
    document.getElementById('editFirstName').value = emp.first_name || '';
    document.getElementById('editLastName').value = emp.last_name || '';
    document.getElementById('editWorkStart').value = emp.work_start_time || '08:00:00';
    document.getElementById('editWorkEnd').value = emp.work_end_time || '16:00:00';
    document.getElementById('editWorkMode').value = emp.work_mode || 'FIXED';
    document.getElementById('editCardNumber').value = emp.card_number || '';
    document.getElementById('editEmployeeType').value = emp.employee_type || 'ประจำ';
    document.getElementById('editDepartment').value = emp.department || '';
    document.getElementById('editPosition').value = emp.position || '';
    document.getElementById('editGender').value = emp.gender || 'ชาย';
    document.getElementById('editHireDate').value = emp.hire_date || '';
    document.getElementById('editAppStatus').value = emp.app_status || 'เปิดใช้งาน';
    document.getElementById('editEmail').value = emp.email || '';
    document.getElementById('editArea').value = emp.area || '';

    var modal = new bootstrap.Modal(document.getElementById('empDetailModal'));
    modal.show();
}

function toggleEditMode(isEdit) {
    document.getElementById('viewMode').style.display = isEdit ? 'none' : 'block';
    document.getElementById('editMode').style.display = isEdit ? 'block' : 'none';
}
</script>
</body>
</html>