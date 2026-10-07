<?php
// config.php - ไฟล์เชื่อมต่อฐานข้อมูลและฟังก์ชันกลาง
$host = 'localhost';
$db   = 'hrd';
$user = 'root'; // ปรับตามเครื่องของคุณ
$pass = '';     // ปรับตามเครื่องของคุณ
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Function สำหรับบันทึก Audit Log
function logAudit($pdo, $userId, $action, $tableName, $recordId, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $tableName, $recordId, is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details, $ip]);
}

// Class สำหรับ Dynamic Mapping Layer
class DataImporter {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getMappingRules($type) {
        $stmt = $this->pdo->prepare("SELECT source_column_name, target_field_name, is_required FROM import_column_mappings WHERE mapping_type = ?");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public function mapRowData($row, $mappingRules) {
        $mappedData = [];
        foreach ($mappingRules as $rule) {
            $srcCol = trim($rule['source_column_name']);
            $targetField = $rule['target_field_name'];
            $val = isset($row[$srcCol]) ? trim($row[$srcCol]) : null;
            if ($rule['is_required'] && ($val === null || $val === '')) {
                throw new Exception("ขาดข้อมูลจำเป็น: {$srcCol}");
            }
            $mappedData[$targetField] = $val;
        }
        return $mappedData;
    }
}
?>