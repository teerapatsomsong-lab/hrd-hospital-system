<?php
// api_get_leave_detail.php - ดึงข้อมูลประวัติการลา/สาย แบบละเอียดส่งกลับให้ Modal
require_once 'auth_check.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$empId     = trim($_GET['emp_id'] ?? '');
$type      = trim($_GET['type'] ?? '');
$subType   = trim($_GET['sub_type'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate   = trim($_GET['end_date'] ?? '');

if (!$empId || !$type) {
    echo json_encode([]);
    exit();
}

if ($type === 'LATE') {
    // ดึงรายการมาสาย
    $stmt = $pdo->prepare("SELECT work_date, check_in, late_minutes, status 
                           FROM attendance_records 
                           WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND late_minutes > 0 
                           ORDER BY work_date DESC");
    $stmt->execute([$empId, $startDate, $endDate]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    // ดึงรายการลา หรือ ไปราชการ
    $sql = "SELECT sub_type, leave_period, start_date, end_date, reason,
            CASE 
                WHEN leave_period IN ('MORNING', 'AFTERNOON') THEN 0.5 
                ELSE (DATEDIFF(end_date, start_date) + 1) 
            END as total_days
            FROM leave_requests 
            WHERE employee_id = ? AND status = 'APPROVED'
              AND start_date <= ? AND end_date >= ?";

    $params = [$empId, $endDate, $startDate];

    if ($type === 'OFFICIAL') {
        $sql .= " AND request_type = 'OFFICIAL_BUSINESS'";
    } else {
        $sql .= " AND request_type = 'LEAVE'";
        if ($subType !== 'OTHER' && $subType !== 'ALL') {
            $sql .= " AND sub_type = ?";
            $params[] = $subType;
        } else if ($subType === 'OTHER') {
            $sql .= " AND sub_type NOT IN ('ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน')";
        }
    }

    $sql .= " ORDER BY start_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>