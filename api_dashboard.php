<?php
// api_dashboard.php - API สรุปตัวเลข Dashboard
require_once 'auth_check.php';
require_once 'config.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$dept = $_GET['department'] ?? '';

$where = " WHERE 1=1 ";
$params = [];

if ($dept) {
    $where .= " AND e.department = ? ";
    $params[] = $dept;
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM employees e $where");
$stmtTotal->execute($params);
$totalEmp = $stmtTotal->fetchColumn();

$attSql = "SELECT a.status, COUNT(*) as cnt 
           FROM attendance_records a 
           JOIN employees e ON a.employee_id = e.employee_id 
           $where AND a.work_date = ? 
           GROUP BY a.status";

$stmtAtt = $pdo->prepare($attSql);
$stmtAtt->execute(array_merge($params, [$date]));
$attResults = $stmtAtt->fetchAll(PDO::FETCH_KEY_PAIR);

$present = ($attResults['PRESENT'] ?? 0) + ($attResults['LATE'] ?? 0) + ($attResults['EARLY_LEAVE'] ?? 0);
$late    = $attResults['LATE'] ?? 0;
$absent  = $attResults['ABSENT'] ?? 0;
$leave   = $attResults['LEAVE'] ?? 0;
$rate    = $totalEmp > 0 ? round(($present / $totalEmp) * 100, 1) : 0;

echo json_encode([
    'total'   => $totalEmp,
    'present' => $present,
    'late'    => $late,
    'absent'  => $absent,
    'leave'   => $leave,
    'rate'    => $rate
]);
?>