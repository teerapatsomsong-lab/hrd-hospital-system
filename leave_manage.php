<?php
// leave_manage.php - ระบบจัดการข้อมูลการลา และการไปประชุม/ราชการ (ค้นหาพนักงานด้วย Autocomplete)
require_once 'auth_check.php';
require_once 'config.php';

$message = '';

// 1. บันทึกข้อมูลการลา / ราชการ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'add_leave') {
    $empId     = trim($_POST['employee_id'] ?? '');
    $reqType   = trim($_POST['request_type'] ?? 'LEAVE');
    $subType   = trim($_POST['sub_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');

    if ($empId && $startDate &&$endDate) {
        $chkEmp =$pdo->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $chkEmp->execute([$empId]);
        
        if ($chkEmp->fetch()) {
            $stmt =$pdo->prepare("INSERT INTO leave_requests (employee_id, request_type, sub_type, start_date, end_date, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $reqType,$subType, $startDate,$endDate, $reason,$_SESSION['user_id']]);

            logAudit($pdo, $_SESSION['user_id'], 'ADD_LEAVE_REQUEST', 'leave_requests',$empId, $_POST);$message = '<div class="alert alert-success">บันทึกข้อมูลสำเร็จ!</div>';
        } else {
            $message = '<div class="alert alert-danger">กรุณาเลือกพนักงานจากรายการที่ค้นหา</div>';
        }
    }
}

// 2. ลบ/ยกเลิก รายการ
if (isset($_GET['delete_id'])) {
    checkRole(['Super Admin', 'HR Admin']);
    $delId = intval($_GET['delete_id']);
    $stmtDel =$pdo->prepare("DELETE FROM leave_requests WHERE id = ?");
    $stmtDel->execute([$delId]);
    logAudit($pdo, $_SESSION['user_id'], 'DELETE_LEAVE_REQUEST', 'leave_requests',$delId, 'ลบรายการ');
    echo "<script>alert('ลบรายการเรียบร้อย'); window.location.href='leave_manage.php';</script>";
    exit();
}

// ดึงข้อมูลรายการลา/ราชการ ทั้งหมด
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');

$where = ["1=1"];
$params = [];

if ($search !== '') {$where[] = "(l.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $st = "\%{$search}%";
    $params = array_merge($params, [$st, $st,$st]);
}

if ($filterType !== '') {$where[] = "l.request_type = ?";
    $params[] =$filterType;
}

$whereClause = implode(" AND ", $where);

$sql = "SELECT l.*, e.first_name, e.last_name, e.department 
        FROM leave_requests l 
        JOIN employees e ON l.employee_id = e.employee_id 
        WHERE {$whereClause} 
        ORDER BY l.id DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leaveList =$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการการลาและราชการ - HRD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .autocomplete-suggestions {
            position: absolute;
            z-index: 1000;
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-calendar2-week text-primary"></i> บันทึกการลา และไปประชุม/ราชการ</h3>
        <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
    </div>

    <?= $message ?>

    <div class="row g-4">
        <!-- Form บันทึกข้อมูล -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-plus-circle"></i> แบบฟอร์มลงบันทึก
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_leave">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภทรายการ *</label>
                            <select name="request_type" class="form-select" id="reqTypeSelect" onchange="updateSubTypes()" required>
                                <option value="LEAVE">1. การลา</option>
                                <option value="OFFICIAL_BUSINESS">2. ไปประชุม / ราชการ</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภทย่อย *</label>
                            <select name="sub_type" id="subTypeSelect" class="form-select" required>
                                <!-- JS สร้างตัวเลือก -->
                            </select>
                        </div>

                        <!-- ค้นหาชื่อพนักงานแบบ Autocomplete -->
                        <div class="mb-3 position-relative">
                            <label class="form-label fw-bold">ค้นหาชื่อบุคลากร *</label>
                            <input type="text" id="empSearchInput" class="form-control" placeholder="พิมพ์ชื่อ, นามสกุล หรือรหัสพนักงาน..." autocomplete="off" required>
                            <input type="hidden" name="employee_id" id="selectedEmpId" required>
                            <div id="autocompleteList" class="autocomplete-suggestions" style="display: none;"></div>
                            <div id="selectedEmpBadge" class="form-text text-success fw-bold mt-1"></div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">ตั้งแต่วันที่ *</label>
                                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">ถึงวันที่ *</label>
                                <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">เหตุผล / รายละเอียด / สถานที่</label>
                            <textarea name="reason" class="form-control" rows="2" placeholder="ระบุเหตุผล หรือสถานที่ไปประชุม"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table แสดงรายการ -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="ค้นหารหัส / ชื่อพนักงาน..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="type" class="form-select form-select-sm">
                                <option value="">-- แสดงทั้งหมด --</option>
                                <option value="LEAVE" <?= $filterType === 'LEAVE' ? 'selected' : '' ?>>การลา</option>
                                <option value="OFFICIAL_BUSINESS" <?= $filterType === 'OFFICIAL_BUSINESS' ? 'selected' : '' ?>>ไปประชุม/ราชการ</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100">ค้นหา</button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ประเภท</th>
                                    <th>รหัส/ชื่อ-นามสกุล</th>
                                    <th>ประเภทย่อย</th>
                                    <th>ช่วงวันที่</th>
                                    <th>เหตุผล</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($leaveList) > 0): ?>
                                    <?php foreach ($leaveList as$item): ?>
                                        <tr>
                                            <td>
                                                <?php if ($item['request_type'] === 'LEAVE'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-person-dash"></i> การลา</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark"><i class="bi bi-briefcase"></i> ไปราชการ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($item['employee_id']) ?></small><br>
                                                <strong><?= htmlspecialchars($item['first_name'] . ' ' .$item['last_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($item['sub_type']) ?></td>
                                            <td>
                                                <small><?= $item['start_date'] ?> ถึง <?= $item['end_date'] ?></small>
                                            </td>
                                            <td><small class="text-muted"><?= htmlspecialchars($item['reason'] ?? '-') ?></small></td>
                                            <td>
                                                <?php if (in_array($_SESSION['role'], ['Super Admin', 'HR Admin'])): ?>
                                                    <a href="?delete_id=<?= $item['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันการลบรายการนี้?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">ไม่พบประวัติการลาหรือไปราชการ</td>
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

<script>
// 1. อัปเดตประเภทย่อย
function updateSubTypes() {
    const mainType = document.getElementById('reqTypeSelect').value;
    const subSelect = document.getElementById('subTypeSelect');
    subSelect.innerHTML = '';

    if (mainType === 'LEAVE') {
        const leaveOptions = ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน', 'ลาคลอดบุตร', 'ลาอุปสมบท', 'ลาอื่นๆ'];
        leaveOptions.forEach(opt => subSelect.add(new Option(opt, opt)));
    } else {
        const businessOptions = ['ไปประชุมวิชาการ', 'ไปอบรม / สัมมนา', 'ปฏิบัติราชการนอกสถานที่', 'นิเทศงาน / ตรวจการ'];
        businessOptions.forEach(opt => subSelect.add(new Option(opt, opt)));
    }
}

// 2. ระบบค้นหาชื่อพนักงาน Autocomplete
const searchInput = document.getElementById('empSearchInput');
const hiddenEmpId = document.getElementById('selectedEmpId');
const autocompleteList = document.getElementById('autocompleteList');
const selectedBadge = document.getElementById('selectedEmpBadge');

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    hiddenEmpId.value = '';
    selectedBadge.innerText = '';

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
                        searchInput.value = `${item.first_name} ${item.last_name}`;
                        hiddenEmpId.value = item.employee_id;
                        selectedBadge.innerText = `✓ เลือกรหัสพนักงาน: ${item.employee_id} (${item.department || ''})`;
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

// ปิด Dropdown เมื่อคลิกนอกพื้นที่
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !autocompleteList.contains(e.target)) {
        autocompleteList.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', updateSubTypes);
</script>
</body>
</html>