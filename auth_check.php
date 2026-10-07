<?php
// auth_check.php - ตรวจสอบ Session และ สิทธิ์การใช้งาน (RBAC)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

function checkRole($allowedRoles = []) {
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'); window.location.href='main.php';</script>";
        exit();
    }
}
?>