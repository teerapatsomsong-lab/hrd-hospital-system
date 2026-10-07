<?php
// process_employee_import.php - นำเข้าไฟล์บุคลากร
require_once 'auth_check.php';
require_once 'config.php';
checkRole(['Super Admin', 'HR Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_import'])) {
    $file = $_FILES['file_import'];
    $tmpName = $file['tmp_name'];

    if (($handle = fopen($tmpName, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        if (isset($headers[0])) $headers[0] = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $headers[0]);

        $importer = new DataImporter($pdo);
        $rules = $importer->getMappingRules('EMPLOYEE');
        
        $batchId = 'EMP_' . date('Ymd_His');
        $success = 0; $failed = 0; $errorLogs = []; $rowNum = 1;

        $pdo->beginTransaction();
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNum++;
            if (count($headers) !== count($data)) continue;
            $row = array_combine($headers, $data);

            try {
                $m = $importer->mapRowData($row, $rules);
                $stmt = $pdo->prepare("INSERT INTO employees 
                    (employee_id, first_name, last_name, card_number, department, position, employee_type, hire_date, gender, email, app_status, area) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    first_name=VALUES(first_name), last_name=VALUES(last_name), card_number=VALUES(card_number),
                    department=VALUES(department), position=VALUES(position), employee_type=VALUES(employee_type),
                    hire_date=VALUES(hire_date), gender=VALUES(gender), email=VALUES(email), app_status=VALUES(app_status), area=VALUES(area)");

                $stmt->execute([
                    $m['employee_id'], $m['first_name'], $m['last_name'], $m['card_number'],
                    $m['department'], $m['position'], $m['employee_type'],
                    !empty($m['hire_date']) ? $m['hire_date'] : null,
                    $m['gender'], $m['email'], $m['app_status'], $m['area']
                ]);
                $success++;
            } catch (Exception $e) {
                $failed++;
                $errorLogs[] = ['line' => $rowNum, 'employee_id' => $row['รหัสพนักงาน']??'N/A', 'reason' => $e->getMessage()];
            }
        }
        fclose($handle);

        $hist = $pdo->prepare("INSERT INTO import_history (batch_id, import_type, file_name, total_records, success_count, failed_count, error_log, imported_by) VALUES (?, 'EMPLOYEE', ?, ?, ?, ?, ?, ?)");
        $hist->execute([$batchId, $file['name'], ($success + $failed), $success, $failed, json_encode($errorLogs, JSON_UNESCAPED_UNICODE), $_SESSION['user_id']]);

        $pdo->commit();
        logAudit($pdo, $_SESSION['user_id'], 'IMPORT_EMPLOYEE', 'employees', $batchId, ['success' => $success, 'failed' => $failed]);

        echo "<script>alert('นำเข้าสำเร็จ: $success รายการ, ล้มเหลว: $failed รายการ'); window.location.href='employee_manage.php';</script>";
    }
}
?>