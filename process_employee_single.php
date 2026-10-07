<?php
// process_employee_single.php - บันทึกพนักงานทีละคน
require_once 'auth_check.php';
require_once 'config.php';
checkRole(['Super Admin', 'HR Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId = trim($_POST['employee_id'] ?? '');
    if (!$empId) die("กรุณาระบุรหัสพนักงาน");

    $sql = "INSERT INTO employees (employee_id, first_name, last_name, card_number, department, position, employee_type, hire_date, gender, email, app_status, area) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            first_name=VALUES(first_name), last_name=VALUES(last_name), card_number=VALUES(card_number),
            department=VALUES(department), position=VALUES(position), employee_type=VALUES(employee_type),
            hire_date=VALUES(hire_date), gender=VALUES(gender), email=VALUES(email),
            app_status=VALUES(app_status), area=VALUES(area)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $empId, $_POST['first_name']??'', $_POST['last_name']??'', $_POST['card_number']??'',
        $_POST['department']??'', $_POST['position']??'', $_POST['employee_type']??'ประจำ',
        !empty($_POST['hire_date']) ? $_POST['hire_date'] : null,
        $_POST['gender']??'ชาย', $_POST['email']??'', $_POST['app_status']??'เปิดใช้งาน', $_POST['area']??''
    ]);

    logAudit($pdo, $_SESSION['user_id'], 'SAVE_EMPLOYEE_SINGLE', 'employees', $empId, $_POST);
    echo "<script>alert('บันทึกสำเร็จ!'); window.location.href='employee_manage.php';</script>";
}
?>