<?php
// leave_summary_report.php - รายงานสรุปการลา ราชการ และการมาสาย (ดึงปีงบประมาณไทยอัตโนมัติ + กดที่ตัวเลขดูรายละเอียด)
require_once 'auth_check.php';
require_once 'config.php';

// -----------------------------------------------------------------
// คำนวณช่วงวันที่เริ่มต้น - สิ้นสุด ตามปีงบประมาณไทย (อัตโนมัติ)
// -----------------------------------------------------------------
$today        = date('Y-m-d');
$currentYear  = intval(date('Y'));
$currentMonth = intval(date('n'));

if ($currentMonth >= 10) {
    // พ้น 30 กันยายนแล้ว (เดือน ต.ค. - ธ.ค.) -> สลับเข้าสู่ปีงบประมาณใหม่ทันที
    // วันเริ่มต้น: 1 ตุลาคม ของปีนี้ / วันสิ้นสุด: วันปัจจุบัน
    $defaultStartDate = "{$currentYear}-10-01";
    $defaultEndDate   = $today;
} else {
    // ยังอยู่ในปีงบประมาณปัจจุบัน (เดือน ม.ค. - ก.ย.)
    // วันเริ่มต้น: 1 ตุลาคม ของปีก่อนหน้า / วันสิ้นสุด: วันปัจจุบัน
    $fiscalStartYear  = $currentYear - 1;
    $defaultStartDate = "{$fiscalStartYear}-10-01";
    $defaultEndDate   = $today;
}

// รับค่าจาก Filter (หากไม่มีการเลือก ให้ใช้ค่า Default ปีงบประมาณ)
$startDate  = $_GET['start_date'] ?? $defaultStartDate;
$endDate    = $_GET['end_date'] ?? $defaultEndDate;
$search     = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? '');

// ดึงรายชื่อแผนกเฉพาะคนที่ Active
$deptStmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE app_status = 'เปิดใช้งาน' AND department IS NOT NULL AND department != '' ORDER BY department ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// เงื่อนไข Filter พนักงาน
$where  = ["e.app_status = 'เปิดใช้งาน'"];
$params = [];

if ($search !== '') {
    $where[] = "(e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $st = "%{$search}%";
    $params = array_merge($params, [$st, $st, $st]);
}

if ($filterDept !== '') {
    $where[] = "e.department = ?";
    $params[] = $filterDept;
}

$whereClause = implode(" AND ", $where);

// ดึงข้อมูลพนักงาน
$empSql = "SELECT e.employee_id, e.first_name, e.last_name, e.department, e.position 
           FROM employees e 
           WHERE {$whereClause} 
           ORDER BY e.department ASC, e.employee_id ASC";
$empStmt = $pdo->prepare($empSql);
$empStmt->execute($params);
$employees = $empStmt->fetchAll();

// -----------------------------------------------------------------
// ดึงข้อมูลสถิติการลา และการมาสาย ของพนักงานทุกคนในช่วงวันที่เลือก
// -----------------------------------------------------------------

// 1. ดึงประวัติการลา/ราชการ
$leaveSql = "SELECT l.*, 
             CASE 
                WHEN l.leave_period IN ('MORNING', 'AFTERNOON') THEN 0.5 
                ELSE (DATEDIFF(l.end_date, l.start_date) + 1) 
             END as total_days
             FROM leave_requests l
             WHERE l.status = 'APPROVED'
               AND l.start_date <= ? AND l.end_date >= ?";
$leaveStmt = $pdo->prepare($leaveSql);
$leaveStmt->execute([$endDate, $startDate]);
$leaveRaw = $leaveStmt->fetchAll();

// จัดกลุ่มข้อมูลการลาตาม employee_id
$leaveSummary = [];
foreach ($leaveRaw as $l) {
    $empId =$l['employee_id'];
    if (!isset($leaveSummary[$empId])) {
        $leaveSummary[$empId] = [
            'sick_count' => 0, 'sick_days' => 0,
            'personal_count' => 0, 'personal_days' => 0,
            'vacation_count' => 0, 'vacation_days' => 0,
            'other_leave_count' => 0, 'other_leave_days' => 0,
            'official_count' => 0, 'official_days' => 0,
            'total_leave_count' => 0, 'total_leave_days' => 0,
        ];
    }

    $days = floatval($l['total_days']);
    $sub  =$l['sub_type'];
    $req  =$l['request_type'];

    if ($req === 'OFFICIAL_BUSINESS') {$leaveSummary[$empId]['official_count']++;$leaveSummary[$empId]['official_days'] +=$days;
    } else {
        $leaveSummary[$empId]['total_leave_count']++;$leaveSummary[$empId]['total_leave_days'] +=$days;

        if ($sub === 'ลาป่วย') {
            $leaveSummary[$empId]['sick_count']++;$leaveSummary[$empId]['sick_days'] +=$days;
        } else if ($sub === 'ลากิจส่วนตัว') {
            $leaveSummary[$empId]['personal_count']++;$leaveSummary[$empId]['personal_days'] +=$days;
        } else if ($sub === 'ลาพักผ่อน') {
            $leaveSummary[$empId]['vacation_count']++;$leaveSummary[$empId]['vacation_days'] +=$days;
        } else {
            $leaveSummary[$empId]['other_leave_count']++;$leaveSummary[$empId]['other_leave_days'] +=$days;
        }
    }
}

// 2. ดึงประวัติการมาสายจาก attendance_records
$lateSql = "SELECT employee_id, COUNT(*) as late_count, SUM(late_minutes) as total_late_minutes 
            FROM attendance_records 
            WHERE work_date BETWEEN ? AND ? AND late_minutes > 0 
            GROUP BY employee_id";
$lateStmt =$pdo->prepare($lateSql);$lateStmt->execute([$startDate,$endDate]);
$lateRaw =$lateStmt->fetchAll();

$lateSummary = [];
foreach ($lateRaw as$lt) {
    $lateSummary[$lt['employee_id']] = [
        'count' => $lt['late_count'],
        'minutes' => $lt['total_late_minutes']
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปรายงานการลาและสาย - HRD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .clickable-badge {
            cursor: pointer;
            transition: transform 0.15s ease-in-out;
        }
        .clickable-badge:hover {
            transform: scale(1.1);
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-light py-4">
<?php include 'header.php'; ?> <!-- 2. ดึง Header มาแสดง -->
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="mb-1"><i class="bi bi-file-earmark-bar-graph text-primary"></i> สรุปรายงานการลา ไปราชการ และการมาสาย</h3>
            <p class="text-muted small mb-0">คลิกที่ตัวเลขเพื่อดูตารางรายละเอียดประวัติของบุคลากรแต่ละคน</p>
        </div>
        <div>
            <a href="main.php" class="btn btn-secondary btn-sm"><i class="bi bi-house-door"></i> กลับหน้าหลัก</a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">ตั้งแต่วันที่ (เริ่มต้นปีงบ)</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">ค้นหาพนักงาน</label>
                    <input type="text" name="search" class="form-control" placeholder="พิมพ์ชื่อ / รหัส..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">แผนก/กลุ่มงาน</label>
                    <select name="department" class="form-select">
                        <option value="">-- ทุกแผนก --</option>
                        <?php foreach ($departments as$d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary me-1"><i class="bi bi-search"></i> แสดงรายงาน</button>
                    <a href="leave_summary_report.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-counterclockwise"></i> รีเซ็ตค่าเริ่มต้น</a>
                    <button type="button" onclick="exportExcel()" class="btn btn-success me-1"><i class="bi bi-file-earmark-excel"></i> ส่งออก Excel</button>
                    <button type="button" onclick="window.print()" class="btn btn-danger"><i class="bi bi-printer"></i> พิมพ์ PDF</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">ตารางสรุปสถิติ (ประจำวันที่ <?= $startDate ?> ถึง <?= $endDate ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle text-center" id="summaryTable">
                    <thead class="table-light align-middle">
                        <tr>
                            <th rowspan="2" class="text-start">รหัส / ชื่อ-นามสกุล</th>
                            <th rowspan="2" class="text-start">แผนก/กลุ่มงาน</th>
                            <th colspan="2" class="table-warning">ลาป่วย</th>
                            <th colspan="2" class="table-warning">ลากิจส่วนตัว</th>
                            <th colspan="2" class="table-warning">ลาพักผ่อน</th>
                            <th colspan="2" class="table-warning">ลาอื่นๆ</th>
                            <th colspan="2" class="table-info">ไปราชการ/ประชุม</th>
                            <th colspan="2" class="table-danger">มาสาย</th>
                        </tr>
                        <tr>
                            <th class="table-warning"><small>ครั้ง</small></th>
                            <th class="table-warning"><small>วัน</small></th>
                            <th class="table-warning"><small>ครั้ง</small></th>
                            <th class="table-warning"><small>วัน</small></th>
                            <th class="table-warning"><small>ครั้ง</small></th>
                            <th class="table-warning"><small>วัน</small></th>
                            <th class="table-warning"><small>ครั้ง</small></th>
                            <th class="table-warning"><small>วัน</small></th>
                            <th class="table-info"><small>ครั้ง</small></th>
                            <th class="table-info"><small>วัน</small></th>
                            <th class="table-danger"><small>ครั้ง</small></th>
                            <th class="table-danger"><small>นาทีรวม</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as$emp): 
                                $id   =$emp['employee_id'];
                                $ls   =$leaveSummary[$id] ?? [];$lt   = $lateSummary[$id] ?? ['count' => 0, 'minutes' => 0];

                                $sCount =$ls['sick_count'] ?? 0;
                                $sDays  =$ls['sick_days'] ?? 0;

                                $pCount =$ls['personal_count'] ?? 0;
                                $pDays  =$ls['personal_days'] ?? 0;

                                $vCount =$ls['vacation_count'] ?? 0;
                                $vDays  =$ls['vacation_days'] ?? 0;

                                $oCount =$ls['other_leave_count'] ?? 0;
                                $oDays  =$ls['other_leave_days'] ?? 0;

                                $offCount =$ls['official_count'] ?? 0;
                                $offDays  =$ls['official_days'] ?? 0;

                                $lateCount =$lt['count'];
                                $lateMin   =$lt['minutes'];
                            ?>
                                <tr>
                                    <td class="text-start fw-bold">
                                        <small class="text-muted d-block"><?= htmlspecialchars($id) ?></small>
                                        <?= htmlspecialchars($emp['first_name'] . ' ' .$emp['last_name']) ?>
                                    </td>
                                    <td class="text-start"><small><?= htmlspecialchars($emp['department'] ?? '-') ?></small></td>
                                    
                                    <!-- ลาป่วย -->
                                    <td><?= renderBadge($sCount,$id, 'LEAVE', 'ลาป่วย', $emp['first_name'].' '.$emp['last_name']) ?></td>
                                    <td class="fw-bold text-secondary"><?= $sDays ?></td>

                                    <!-- ลากิจ -->
                                    <td><?= renderBadge($pCount,$id, 'LEAVE', 'ลากิจส่วนตัว', $emp['first_name'].' '.$emp['last_name']) ?></td>
                                    <td class="fw-bold text-secondary"><?= $pDays ?></td>

                                    <!-- ลาพักผ่อน -->
                                    <td><?= renderBadge($vCount,$id, 'LEAVE', 'ลาพักผ่อน', $emp['first_name'].' '.$emp['last_name']) ?></td>
                                    <td class="fw-bold text-secondary"><?= $vDays ?></td>

                                    <!-- ลาอื่นๆ -->
                                    <td><?= renderBadge($oCount,$id, 'LEAVE', 'OTHER', $emp['first_name'].' '.$emp['last_name']) ?></td>
                                    <td class="fw-bold text-secondary"><?= $oDays ?></td>

                                    <!-- ไปราชการ -->
                                    <td><?= renderBadge($offCount,$id, 'OFFICIAL', 'ALL', $emp['first_name'].' '.$emp['last_name']) ?></td>
                                    <td class="fw-bold text-info"><?= $offDays ?></td>

                                    <!-- มาสาย -->
                                    <td><?= renderBadge($lateCount,$id, 'LATE', 'ALL', $emp['first_name'].' '.$emp['last_name'], 'danger') ?></td>
                                    <td class="fw-bold text-danger"><?= number_format($lateMin) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14" class="py-4 text-muted">ไม่พบข้อมูลตามเงื่อนไขที่ระบุ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// ฟังก์ชันสร้าง Badge ปุ่มกดดูรายละเอียด
function renderBadge($count,$empId, $type,$subType, $empName,$color = 'primary') {
    if ($count == 0) return '<span class="text-muted small">0</span>';
    return "<span class=\"badge bg-{$color} clickable-badge fs-6\" onclick=\"showDetail('{$empId}', '{$type}', '{$subType}', '{$empName}')\">{$count}</span>";
}
?>

<!-- Modal แสดงรายละเอียดประวัติเมื่อคลิกตัวเลข -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalDetailTitle"><i class="bi bi-clock-history"></i> รายละเอียดประวัติ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 alert alert-light border">
                    <strong>บุคลากร:</strong> <span id="modalEmpName" class="text-primary fw-bold"></span> | 
                    <strong>ช่วงวันที่:</strong> <?= $startDate ?> ถึง <?= $endDate ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="table-light" id="modalTableHead">
                            <!-- JS จะเจนหัวตารางให้ตามประเภท -->
                        </thead>
                        <tbody id="modalTableBody">
                            <!-- JS จะดึงข้อมูลมาแสดงที่นี่ -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
const startDate = "<?= $startDate ?>";
const endDate   = "<?= $endDate ?>";

// ฟังก์ชันดึงรายละเอียดด้วย AJAX เมื่อกดตัวเลข
function showDetail(empId, type, subType, empName) {
    $('#modalEmpName').text(empName);
    $('#modalTableBody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-primary" role="status"></div> กำลังโหลดข้อมูล...</td></tr>');
    
    var modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();

    if (type === 'LATE') {
        $('#modalDetailTitle').html('<i class="bi bi-alarm text-danger"></i> ประวัติการมาสาย');
        $('#modalTableHead').html('<tr><th>วันที่</th><th>สแกนเข้าจริง</th><th>สาย (นาที)</th><th>สถานะ</th></tr>');
    } else if (type === 'OFFICIAL') {
        $('#modalDetailTitle').html('<i class="bi bi-briefcase text-info"></i> ประวัติการไปประชุม / ราชการ');
        $('#modalTableHead').html('<tr><th>ประเภท</th><th>ช่วงวันที่</th><th>ช่วงเวลา</th><th>จำนวนวัน</th><th>เหตุผล / สถานที่</th></tr>');
    } else {
        $('#modalDetailTitle').html('<i class="bi bi-calendar-event text-warning"></i> ประวัติการลา (' + subType + ')');
        $('#modalTableHead').html('<tr><th>ประเภทย่อย</th><th>ช่วงวันที่</th><th>ช่วงเวลา</th><th>จำนวนวัน</th><th>เหตุผล</th></tr>');
    }

    // เรียก API ดึงข้อมูล
    $.getJSON('api_get_leave_detail.php', {
        emp_id: empId,
        type: type,
        sub_type: subType,
        start_date: startDate,
        end_date: endDate
    }, function(data) {
        var html = '';
        if (data.length > 0) {
            $.each(data, function(i, row) {
                if (type === 'LATE') {
                    html += `<tr>
                        <td><strong>${row.work_date}</strong></td>
                        <td class="text-danger fw-bold">${row.check_in ? row.check_in.substring(0,5) + ' น.' : '-'}</td>
                        <td><span class="badge bg-danger">+${row.late_minutes} นาที</span></td>
                        <td><small class="text-muted">${row.status}</small></td>
                    </tr>`;
                } else {
                    var periodText = row.leave_period === 'MORNING' ? 'ครึ่งวันเช้า' : (row.leave_period === 'AFTERNOON' ? 'ครึ่งวันบ่าย' : 'เต็มวัน');
                    html += `<tr>
                        <td><strong>${row.sub_type}</strong></td>
                        <td>${row.start_date} ถึง ${row.end_date}</td>
                        <td><span class="badge bg-light text-dark border">${periodText}</span></td>
                        <td><span class="badge bg-success">${row.total_days} วัน</span></td>
                        <td><small class="text-muted">${row.reason || '-'}</small></td>
                    </tr>`;
                }
            });
        } else {
            html = '<tr><td colspan="5" class="text-center py-3 text-muted">ไม่พบประวัติรายการในช่วงเวลานี้</td></tr>';
        }
        $('#modalTableBody').html(html);
    });
}

function exportExcel() {
    var table = document.getElementById("summaryTable");
    var wb = XLSX.utils.table_to_book(table, { sheet: "Leave_Summary" });
    XLSX.writeFile(wb, "Leave_Summary_Report_" + startDate + "_to_" + endDate + ".xlsx");
}
</script>
</body>
</html>