<?php
// logout.php
session_start();
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    logAudit($pdo, $_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], 'ออกจากระบบ');
}
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>