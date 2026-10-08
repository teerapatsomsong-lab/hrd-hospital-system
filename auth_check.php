<?php
// auth_check.php - ตรวจสอบ Session และ สิทธิ์การใช้งาน (RBAC)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบการล็อกอิน (ถ้ายังไม่ได้ล็อกอิน ให้เด้งไปหน้า login.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. ตรวจสอบและดึงชื่อเต็ม (full_name) มาเก็บใน Session หากยังไม่มี
if (!isset($_SESSION['full_name']) && isset($pdo)) {
    try {
        $stmtName = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $stmtName->execute([$_SESSION['user_id']]);
        $userData = $stmtName->fetch(PDO::FETCH_ASSOC);
        
        if ($userData && !empty($userData['full_name'])) {
            $_SESSION['full_name'] = $userData['full_name'];
        } else {
            $_SESSION['full_name'] = $_SESSION['username'] ?? 'ผู้ใช้งานระบบ';
        }
    } catch (PDOException $e) {
        $_SESSION['full_name'] = $_SESSION['username'] ?? 'ผู้ใช้งานระบบ';
    }
}

// 3. ฟังก์ชันตรวจสอบสิทธิ์ตามระดับผู้ใช้งาน (RBAC)
function checkRole($allowedRoles = []) {
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $allowedRoles)) {
        echo "<script>
            alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); 
            window.location.href = 'main.php';
        </script>";
        exit();
    }
}
?>