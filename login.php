<?php
// login.php - หน้าเข้าสู่ระบบสำหรับ HRD System
require_once 'config.php';

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            $userStatus = $user['status'] ?? 'ACTIVE';
            
            if ($userStatus === 'ACTIVE') {
                $pwdInDb = $user['password'] ?? $user['password_hash'] ?? '';

                // ตรวจสอบรหัสผ่านผ่าน password_verify() หรือเทียบ Plain Text (สำหรับรหัสผ่านเก่า)
                if (password_verify($password, $pwdInDb) || $password === $pwdInDb) {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['role']      = $user['role'] ?? 'Super Admin';

                    logAudit($pdo, $user['id'], 'LOGIN', 'users', $user['id'], 'เข้าสู่ระบบสำเร็จ');

                    header("Location: main.php");
                    exit();
                } else {
                    $error = 'รหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $error = 'บัญชีผู้ใช้งานนี้ถูกระงับการใช้งาน';
            }
        } else {
            $error = 'ไม่พบชื่อผู้ใช้งานนี้ในระบบ';
        }
    } else {
        $error = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - HRD System โรงพยาบาล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { border: none; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
    </style>
</head>
<body>
<div class="card login-card bg-white p-4">
    <div class="text-center mb-3">
        <div class="display-4 text-primary mb-2"><i class="bi bi-hospital-fill"></i></div>
        <h4 class="fw-bold mb-1">ระบบ HRD โรงพยาบาล</h4>
        <p class="text-muted small">กรุณาเข้าสู่ระบบเพื่อใช้งาน</p>
    </div>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 small text-center mb-3"><i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label fw-bold small"><i class="bi bi-person-fill"></i> ชื่อผู้ใช้งาน (Username)</label>
            <input type="text" name="username" class="form-control" placeholder="กรอกชื่อผู้ใช้งาน" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold small"><i class="bi bi-lock-fill"></i> รหัสผ่าน (Password)</label>
            <input type="password" name="password" class="form-control" placeholder="กรอกรหัสผ่าน" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
    </form>
</div>
</body>
</html>