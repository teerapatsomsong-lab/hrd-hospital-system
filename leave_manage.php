<?php
// leave_manage.php - ระบบจัดการข้อมูลการลา และการไปประชุม/ราชการ (รองรับ Filter วันที่ + คำนวณวันลา 0.5/1 วัน)
require_once 'auth_check.php';
require_once 'config.php';

$message = '';

// 1. บันทึก/แก้ไข ข้อมูลการลา / ราชการ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action      = $_POST['action'];
    $empId       = trim($_POST['employee_id'] ?? '');
    $reqType     = trim($_POST['request_type'] ?? 'LEAVE');
    $subType     = trim($_POST['sub_type'] ?? '');
    $leavePeriod = trim($_POST['leave_period'] ?? 'FULL');
    $startDate   = trim($_POST['start_date'] ?? '');
    $endDate     = trim($_POST['end_date'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');

    if ($startDate > $endDate) {
        $message = '<div class="alert alert-danger">วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด</div>';
    } else if ($empId && $startDate && $endDate) {
        
        $chkEmp = $pdo->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $chkEmp->execute([$empId]);
        
        if ($chkEmp->fetch()) {
            
            // ตรวจสอบการลาทับซ้อน (Overlap Check)
            $overlapSql = "SELECT * FROM leave_requests 
                           WHERE employee_id = ? 
                             AND start_date <= ? AND end_date >= ?
                             AND (
                                 leave_period = 'FULL' OR ? = 'FULL'
                                 OR leave_period = ?
                             )";
            
            if ($action === 'edit_leave') {
                $leaveId = intval($_POST['leave_id'] ?? 0);
                $overlapSql .= " AND id != " . intval($leaveId);
            }

            $chkOverlap = $pdo->prepare($overlapSql);
            $chkOverlap->execute([$empId, $endDate, $startDate, $leavePeriod, $leavePeriod]);
            $existing = $chkOverlap->fetch();

            if ($existing) {
                $periodText = ($existing['leave_period'] === 'MORNING') ? 'ครึ่งวันเช้า' : (($existing['leave_period'] === 'AFTERNOON') ? 'ครึ่งวันบ่าย' : 'เต็มวัน');
                $message = "<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-triangle-fill\"></i> ไม่สามารถบันทึกได้! บุคลากรท่านนี้มีรายการลา/ราชการ ทับซ้อนช่วงวันที่ {$existing['start_date']} ถึง {$existing['end_date']} ({$periodText}) แล้ว</div>";
            } else {
                if ($action === 'add_leave') {
                    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, request_type, sub_type, leave_period, start_date, end_date, reason, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'APPROVED', ?)");
                    $stmt->execute([$empId, $reqType, $subType, $leavePeriod, $startDate, $endDate, $reason, $_SESSION['user_id']]);

                    logAudit($pdo, $_SESSION['user_id'], 'ADD_LEAVE_REQUEST', 'leave_requests', $empId, $_POST);
                    $message = '<div class="alert alert-success">บันทึกข้อมูลการลาเรียบร้อยแล้ว!</div>';
                } else if ($action === 'edit_leave') {
                    checkRole(['Super Admin', 'HR Admin']);
                    $leaveId = intval($_POST['leave_id'] ?? 0);

                    if ($leaveId > 0) {
                        $stmt = $pdo->prepare("UPDATE leave_requests SET employee_id = ?, request_type = ?, sub_type = ?, leave_period = ?, start_date = ?, end_date = ?, reason = ? WHERE id = ?");
                        $stmt->execute([$empId, $reqType, $subType, $leavePeriod, $startDate, $endDate, $reason, $leaveId]);

                        logAudit($pdo, $_SESSION['user_id'], 'EDIT_LEAVE_REQUEST', 'leave_requests', $leaveId, $_POST);
                        $message = '<div class="alert alert-success">อัปเดตข้อมูลการลาเรียบร้อยแล้ว!</div>';
                    }
                }
            }

        } else {
            $message = '<div class="alert alert-danger">กรุณาเลือกพนักงานจากรายการที่ค้นหา</div>';
        }
    }
}

// 2. ลบ รายการ
if (isset($_GET['delete_id'])) {
    checkRole(['Super Admin', 'HR Admin']);
    $delId = intval($_GET['delete_id']);
    $stmtDel = $pdo->prepare("DELETE FROM leave_requests WHERE id = ?");
    $stmtDel->execute([$delId]);
    logAudit($pdo, $_SESSION['user_id'], 'DELETE_LEAVE_REQUEST', 'leave_requests', $delId, 'ลบรายการ');
    echo "<script>alert('ลบรายการเรียบร้อย'); window.location.href='leave_manage.php';</script>";
    exit();
}

// 3. ดึงข้อมูลรายการลา/ราชการ + ระบบ Filter
$search          = trim($_GET['search'] ?? '');
$filterType      = trim($_GET['type'] ?? '');
$filterStartDate = trim($_GET['filter_start_date'] ?? '');
$filterEndDate   = trim($_GET['filter_end_date'] ?? '');

$where  = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(l.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $st = "%{$search}%";
    $params = array_merge($params, [$st, $st, $st]);
}

if ($filterType !== '') {$where[] = "l.request_type = ?";
    $params[] =$filterType;
}

// Filter ค้นหาตามช่วงวันที่ลา
if ($filterStartDate !== '' && $filterEndDate !== '') {$where[] = "(l.start_date <= ? AND l.end_date >= ?)";
    $params[] =$filterEndDate;
    $params[] =$filterStartDate;
} else if ($filterStartDate !== '') {$where[] = "l.end_date >= ?";
    $params[] =$filterStartDate;
} else if ($filterEndDate !== '') {$where[] = "l.start_date <= ?";
    $params[] =$filterEndDate;
}

$whereClause = implode(" AND ", $where);

$sql = "SELECT l.*, e.first_name, e.last_name, e.department 
        FROM leave_requests l 
        JOIN employees e ON l.employee_id = e.employee_id 
        WHERE {$whereClause} 
        ORDER BY l.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leaveList =$stmt->fetchAll();

// ฟังก์ชันคำนวณจำนวนวันลา (1 วัน / 0.5 วัน)
function calculateLeaveDays($startDate, $endDate,$period) {
    if ($period === 'MORNING' || $period === 'AFTERNOON') {
        return 0.5;
    }$d1 = new DateTime($startDate);$d2 = new DateTime($endDate);$diff = $d1->diff($d2)->days + 1;
    return $diff;
}

// คำนวณยอดรวมวันลาทั้งหมดจากรายการที่ค้นหา
$totalLeaveDaysSum = 0;
foreach ($leaveList as$item) {
    $totalLeaveDaysSum += calculateLeaveDays($item['start_date'], $item['end_date'],$item['leave_period'] ?? 'FULL');
}
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
                            <select name="request_type" class="form-select" id="reqTypeSelect" onchange="updateSubTypes('reqTypeSelect', 'subTypeSelect')" required>
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

                        <div class="mb-3">
                            <label class="form-label fw-bold">ช่วงเวลาการลา *</label>
                            <select name="leave_period" class="form-select" required>
                                <option value="FULL">เต็มวัน (Full Day)</option>
                                <option value="MORNING">ครึ่งวันเช้า (Morning - เข้า 13:00 น.)</option>
                                <option value="AFTERNOON">ครึ่งวันบ่าย (Afternoon - ออก 12:00 น.)</option>
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

        <!-- Table แสดงรายการ + Filter -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body py-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">ค้นหา (รหัส/ชื่อ)</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="รหัส / ชื่อ..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">ประเภท</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="LEAVE" <?= $filterType === 'LEAVE' ? 'selected' : '' ?>>การลา</option>
                                <option value="OFFICIAL_BUSINESS" <?= $filterType === 'OFFICIAL_BUSINESS' ? 'selected' : '' ?>>ไปประชุม/ราชการ</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">ลาตั้งแต่วันที่</label>
                            <input type="date" name="filter_start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterStartDate) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">ถึงวันที่</label>
                            <input type="date" name="filter_end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterEndDate) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> ค้นหา</button>
                            <a href="leave_manage.php" class="btn btn-outline-secondary btn-sm" title="รีเซ็ต"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list-task text-primary"></i> รายการประวัติการลา (พบ <?= count($leaveList) ?> รายการ)</h6>
                    <span class="badge bg-primary fs-6">รวมวันลาทั้งหมด: <?= $totalLeaveDaysSum ?> วัน</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ประเภท</th>
                                    <th>รหัส/ชื่อ-นามสกุล</th>
                                    <th>ประเภทย่อย</th>
                                    <th>ช่วงเวลา</th>
                                    <th>ช่วงวันที่</th>
                                    <th class="text-center">จำนวนวัน</th>
                                    <th>เหตุผล</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($leaveList) > 0): ?>
                                    <?php foreach ($leaveList as$item): 
                                        $days = calculateLeaveDays($item['start_date'], $item['end_date'],$item['leave_period'] ?? 'FULL');
                                    ?>
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
                                                <?php
                                                    $p =$item['leave_period'] ?? 'FULL';
                                                    if ($p === 'MORNING') echo '<span class="badge bg-light text-primary border">ครึ่งวันเช้า</span>';
                                                    else if ($p === 'AFTERNOON') echo '<span class="badge bg-light text-primary border">ครึ่งวันบ่าย</span>';
                                                    else echo '<span class="badge bg-light text-secondary border">เต็มวัน</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <small><?= $item['start_date'] ?> ถึง <?= $item['end_date'] ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success fs-6"><?= $days ?> วัน</span>
                                            </td>
                                            <td><small class="text-muted"><?= htmlspecialchars($item['reason'] ?? '-') ?></small></td>
                                            <td>
                                                <?php if (in_array($_SESSION['role'], ['Super Admin', 'HR Admin'])): ?>
                                                    <button type="button" class="btn btn-outline-warning btn-sm me-1" onclick='openEditModal(<?= json_encode($item) ?>)'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <a href="?delete_id=<?= $item['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันการลบรายการนี้?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">ไม่พบประวัติการลาหรือไปราชการตามเงื่อนไข</td>
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

<!-- Modal สำหรับแก้ไขข้อมูลการลา -->
<div class="modal fade" id="editLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_leave">
                <input type="hidden" name="leave_id" id="edit_leave_id">
                <input type="hidden" name="employee_id" id="edit_employee_id">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลการลา/ราชการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">บุคลากร</label>
                        <input type="text" id="edit_emp_name" class="form-control bg-light" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทรายการ *</label>
                        <select name="request_type" id="edit_request_type" class="form-select" onchange="updateSubTypes('edit_request_type', 'edit_sub_type')" required>
                            <option value="LEAVE">1. การลา</option>
                            <option value="OFFICIAL_BUSINESS">2. ไปประชุม / ราชการ</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทย่อย *</label>
                        <select name="sub_type" id="edit_sub_type" class="form-select" required>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ช่วงเวลาการลา *</label>
                        <select name="leave_period" id="edit_leave_period" class="form-select" required>
                            <option value="FULL">เต็มวัน (Full Day)</option>
                            <option value="MORNING">ครึ่งวันเช้า (Morning - เข้า 13:00 น.)</option>
                            <option value="AFTERNOON">ครึ่งวันบ่าย (Afternoon - ออก 12:00 น.)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">ตั้งแต่วันที่ *</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">ถึงวันที่ *</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เหตุผล / รายละเอียด</label>
                        <textarea name="reason" id="edit_reason" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning fw-bold"><i class="bi bi-check-circle"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 1. อัปเดตประเภทย่อย
function updateSubTypes(reqSelectId = 'reqTypeSelect', subSelectId = 'subTypeSelect', selectedValue = null) {
    const mainType = document.getElementById(reqSelectId).value;
    const subSelect = document.getElementById(subSelectId);
    subSelect.innerHTML = '';

    if (mainType === 'LEAVE') {
        const leaveOptions = ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน', 'ลาคลอดบุตร', 'ลาอุปสมบท', 'ลาอื่นๆ'];
        leaveOptions.forEach(opt => subSelect.add(new Option(opt, opt)));
    } else {
        const businessOptions = ['ไปประชุมวิชาการ', 'ไปอบรม / สัมมนา', 'ปฏิบัติราชการนอกสถานที่', 'นิเทศงาน / ตรวจการ'];
        businessOptions.forEach(opt => subSelect.add(new Option(opt, opt)));
    }

    if (selectedValue) {
        subSelect.value = selectedValue;
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

// 3. ฟังก์ชันเปิด Modal แก้ไขข้อมูล
function openEditModal(item) {
    document.getElementById('edit_leave_id').value = item.id;
    document.getElementById('edit_employee_id').value = item.employee_id;
    document.getElementById('edit_emp_name').value = item.employee_id + ' - ' + item.first_name + ' ' + item.last_name + ' (' + (item.department || '-') + ')';
    
    document.getElementById('edit_request_type').value = item.request_type;
    updateSubTypes('edit_request_type', 'edit_sub_type', item.sub_type);
    
    document.getElementById('edit_leave_period').value = item.leave_period || 'FULL';
    document.getElementById('edit_start_date').value = item.start_date;
    document.getElementById('edit_end_date').value = item.end_date;
    document.getElementById('edit_reason').value = item.reason || '';

    var editModal = new bootstrap.Modal(document.getElementById('editLeaveModal'));
    editModal.show();
}

// ปิด Dropdown เมื่อคลิกนอกพื้นที่
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !autocompleteList.contains(e.target)) {
        autocompleteList.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    updateSubTypes('reqTypeSelect', 'subTypeSelect');
});
</script>
</body>
</html>