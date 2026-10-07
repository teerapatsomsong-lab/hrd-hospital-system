<?php
// api_search_employee.php - API สำหรับค้นหาชื่อบุคลากร (Autocomplete)
require_once 'auth_check.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 1) {
    echo json_encode([]);
    exit();
}

$searchTerm = "%{$query}%";
$sql = "SELECT employee_id, first_name, last_name, department, position 
        FROM employees 
        WHERE app_status = 'เปิดใช้งาน' 
          AND (employee_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
        ORDER BY first_name ASC 
        LIMIT 10";

$stmt = $pdo->prepare($sql);
$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results, JSON_UNESCAPED_UNICODE);
?>