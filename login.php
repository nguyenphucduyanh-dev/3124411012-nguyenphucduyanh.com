<?php
// ============================================================
//  admin/login.php  –  Trang đăng nhập Admin
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

// Nếu đã login, redirect thẳng vào dashboard
if (isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: /admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$error    = '';
$redirect = $_GET['redirect'] ?? '/admin/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        try {
            $pdo  = get_db();
            $stmt = $pdo->prepare(
                "SELECT id, username, email, password, role, status
                 FROM users
                 WHERE username = ? AND role = 'admin'
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && $user['status'] == 1 && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email']    = $user['email'];
                $_SESSION['admin_role']     = $user['role'];

                $safe = filter_var($redirect, FILTER_SANITIZE_URL);
                header('Location: ' . ($safe ?: '/admin/dashboard.php'));
                exit;
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
                // Tăng bộ đếm lỗi chống brute-force
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            }
        } catch (PDOException $e) {
            $error = 'Lỗi kết nối CSDL. Vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập — PhoneShop Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'DM Sans', sans-serif;
      background: #0f1117;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .login-card {
      background: #1a1d2e;
      border: 1px solid #252840;
      border-radius: 16px;
      padding: 40px 36px;
      width: 100%; max-width: 420px;
    }
    .brand {
      text-align: center; margin-bottom: 32px;
    }
    .brand-icon {
      width: 52px; height: 52px; border-radius: 14px;
      background: linear-gradient(135deg, #4f8ef7, #7c5bf5);
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 1.5rem; margin-bottom: 14px;
    }
    .brand h1 { color: #f1f5f9; font-size: 1.25rem; font-weight: 700; margin: 0; }
    .brand p  { color: #64748b; font-size: .82rem; margin: 4px 0 0; }

    .form-label { color: #94a3b8; font-size: .82rem; font-weight: 500; margin-bottom: 6px; }
    .form-control {
      background: #0f1117; border: 1px solid #2a2e45;
      color: #f1f5f9; border-radius: 8px; padding: 11px 14px;
      font-family: inherit; font-size: .875rem;
    }
    .form-control:focus {
      background: #0f1117; border-color: #4f8ef7;
      color: #f1f5f9; box-shadow: 0 0 0 3px rgba(79,142,247,.15);
    }
    .form-control::placeholder { color: #475569; }
    .input-group .input-group-text {
      background: #0f1117; border: 1px solid #2a2e45;
      border-right: none; color: #64748b;
    }
    .input-group .form-control { border-left: none; }

    .btn-login {
      width: 100%; padding: 12px;
      background: linear-gradient(135deg, #4f8ef7, #7c5bf5);
      border: none; border-radius: 8px; color: #fff;
      font-weight: 600; font-size: .9rem;
      transition: opacity .2s; cursor: pointer;
    }
    .btn-login:hover { opacity: .9; }

    .error-box {
      background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25);
      border-radius: 8px; padding: 10px 14px;
      color: #fca5a5; font-size: .83rem;
      display: flex; align-items: center; gap: 8px;
    }
    .toggle-pw {
      background: #0f1117; border: 1px solid #2a2e45; border-left: none;
      color: #64748b; cursor: pointer; padding: 0 12px;
      border-radius: 0 8px 8px 0;
    }
    .toggle-pw:hover { color: #94a3b8; }
    .hint {
      text-align: center; margin-top: 20px;
      color: #334155; font-size: .75rem;
    }
  </style>
</head>
<body>
<div class="login-card">
  <div class="brand">
    <div class="brand-icon">📱</div>
    <h1>PhoneShop Admin</h1>
    <p>Hệ thống quản trị nội bộ</p>
  </div>

  <?php if ($error): ?>
  <div class="error-box mb-3">
    <i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

    <div class="mb-3">
      <label class="form-label">Tên đăng nhập</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="username" class="form-control"
               placeholder="admin" autocomplete="username" required
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label">Mật khẩu</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="passwordInput"
               class="form-control" placeholder="••••••••"
               autocomplete="current-password" required>
        <button type="button" class="toggle-pw" onclick="togglePw()">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-login">
      <i class="bi bi-shield-lock me-2"></i>Đăng nhập
    </button>
  </form>

  <div class="hint">PhoneShop &copy; <?= date('Y') ?> — Chỉ dành cho nhân viên</div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('passwordInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
