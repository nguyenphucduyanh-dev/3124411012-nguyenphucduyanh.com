<?php
/**
 * login.php - Đăng nhập
 * Tác giả: nguyenphucduyanh-dev
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/function.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    header('Location: index.html');
    exit;
}

$redirect = $_GET['redirect'] ?? 'index.html';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'phone'     => $user['phone'],
                'address'   => $user['address'],
                'role'      => $user['role'],
            ];
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:440px; padding-top:80px">
    <div class="card shadow">
        <div class="card-body p-4">
            <h3 class="text-center mb-4">🔐 Đăng nhập</h3>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
            </form>
            <hr>
            <div class="text-center">
                <a href="search.php">← Về trang mua hàng</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
