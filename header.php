<?php
// admin/includes/header.php
// Nhận biến $page_title từ trang gọi
$page_title = $page_title ?? 'Admin Panel';
$admin      = current_admin();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> — PhoneShop Admin</title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Google Font: DM Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --sidebar-w: 260px;
      --sidebar-bg: #0f1117;
      --sidebar-border: #1e2130;
      --accent: #4f8ef7;
      --accent-soft: rgba(79,142,247,.12);
      --accent-hover: #3a7ae0;
      --success: #22c55e;
      --warning: #f59e0b;
      --danger: #ef4444;
      --text-primary: #f1f5f9;
      --text-muted: #64748b;
      --body-bg: #f8fafc;
      --card-bg: #ffffff;
      --border: #e2e8f0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--body-bg);
      color: #1e293b;
      min-height: 100vh;
    }

    /* ── SIDEBAR ─────────────────────────────── */
    #sidebar {
      position: fixed; top: 0; left: 0;
      width: var(--sidebar-w); height: 100vh;
      background: var(--sidebar-bg);
      border-right: 1px solid var(--sidebar-border);
      display: flex; flex-direction: column;
      z-index: 1000; overflow-y: auto;
      transition: transform .25s ease;
    }

    .sidebar-logo {
      padding: 22px 24px 16px;
      border-bottom: 1px solid var(--sidebar-border);
    }
    .sidebar-logo .brand {
      font-size: 1.1rem; font-weight: 700;
      color: var(--text-primary); letter-spacing: -.3px;
      display: flex; align-items: center; gap: 10px;
    }
    .sidebar-logo .brand .dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--accent); display: inline-block;
    }
    .sidebar-logo small {
      color: var(--text-muted); font-size: .72rem;
      display: block; margin-top: 3px; padding-left: 18px;
    }

    .sidebar-nav { flex: 1; padding: 12px 0; }

    .nav-label {
      font-size: .65rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-muted);
      padding: 16px 24px 6px;
    }

    .sidebar-link {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 24px; color: #94a3b8;
      text-decoration: none; font-size: .875rem; font-weight: 500;
      border-radius: 0; transition: all .15s ease;
      position: relative;
    }
    .sidebar-link:hover {
      color: var(--text-primary);
      background: rgba(255,255,255,.04);
    }
    .sidebar-link.active {
      color: var(--accent);
      background: var(--accent-soft);
    }
    .sidebar-link.active::before {
      content: ''; position: absolute; left: 0; top: 0;
      bottom: 0; width: 3px; background: var(--accent);
      border-radius: 0 3px 3px 0;
    }
    .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }

    .sidebar-footer {
      padding: 16px 24px;
      border-top: 1px solid var(--sidebar-border);
    }
    .admin-info {
      display: flex; align-items: center; gap: 10px;
    }
    .admin-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: var(--accent); display: flex; align-items: center;
      justify-content: center; font-size: .8rem; font-weight: 700;
      color: #fff; flex-shrink: 0;
    }
    .admin-name { font-size: .82rem; font-weight: 600; color: var(--text-primary); }
    .admin-role { font-size: .7rem; color: var(--text-muted); }
    .logout-btn {
      margin-left: auto; color: var(--text-muted); font-size: 1rem;
      text-decoration: none; transition: color .15s;
    }
    .logout-btn:hover { color: var(--danger); }

    /* ── MAIN ─────────────────────────────────── */
    #main-wrap {
      margin-left: var(--sidebar-w);
      min-height: 100vh;
      display: flex; flex-direction: column;
    }

    #topbar {
      background: var(--card-bg);
      border-bottom: 1px solid var(--border);
      padding: 0 28px;
      height: 60px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 900;
    }
    .page-title-bar {
      font-size: 1rem; font-weight: 600; color: #1e293b;
    }
    .topbar-right { display: flex; align-items: center; gap: 14px; }
    .topbar-badge {
      display: flex; align-items: center; gap: 6px;
      background: var(--accent-soft); color: var(--accent);
      padding: 5px 12px; border-radius: 20px;
      font-size: .78rem; font-weight: 600;
    }

    #page-content { padding: 28px; flex: 1; }

    /* ── CARDS ───────────────────────────────── */
    .card {
      border: 1px solid var(--border);
      border-radius: 12px; background: var(--card-bg);
      box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .card-header {
      background: transparent;
      border-bottom: 1px solid var(--border);
      padding: 16px 20px;
      font-weight: 600; font-size: .9rem; color: #1e293b;
    }

    /* ── STAT CARDS ──────────────────────────── */
    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px 22px;
      display: flex; align-items: center; gap: 16px;
      transition: box-shadow .2s;
    }
    .stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
    .stat-icon {
      width: 46px; height: 46px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; flex-shrink: 0;
    }
    .stat-icon.blue  { background: rgba(79,142,247,.1);  color: var(--accent); }
    .stat-icon.green { background: rgba(34,197,94,.1);   color: var(--success); }
    .stat-icon.amber { background: rgba(245,158,11,.1);  color: var(--warning); }
    .stat-icon.red   { background: rgba(239,68,68,.1);   color: var(--danger); }
    .stat-label { font-size: .78rem; color: var(--text-muted); margin-bottom: 3px; }
    .stat-value { font-size: 1.4rem; font-weight: 700; color: #1e293b; }

    /* ── TABLE ───────────────────────────────── */
    .table th {
      font-size: .75rem; font-weight: 600; text-transform: uppercase;
      letter-spacing: .05em; color: var(--text-muted);
      border-bottom: 2px solid var(--border); white-space: nowrap;
    }
    .table td { vertical-align: middle; font-size: .875rem; }
    .table-hover tbody tr:hover { background: #f8fafc; }

    /* ── BADGES ──────────────────────────────── */
    .badge-status {
      padding: 4px 10px; border-radius: 20px;
      font-size: .72rem; font-weight: 600;
    }
    .badge-pending    { background:#fef3c7; color:#92400e; }
    .badge-confirmed  { background:#dbeafe; color:#1e40af; }
    .badge-shipping   { background:#e0e7ff; color:#3730a3; }
    .badge-completed  { background:#dcfce7; color:#166534; }
    .badge-cancelled  { background:#fee2e2; color:#991b1b; }
    .badge-draft      { background:#f1f5f9; color:#475569; }

    /* ── BUTTONS ─────────────────────────────── */
    .btn-primary   { background: var(--accent); border-color: var(--accent); }
    .btn-primary:hover { background: var(--accent-hover); border-color: var(--accent-hover); }

    /* ── MONO ────────────────────────────────── */
    .mono { font-family: 'JetBrains Mono', monospace; font-size: .82rem; }

    /* ── FLASH ───────────────────────────────── */
    .flash-container { position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 280px; }

    /* ── RESPONSIVE ──────────────────────────── */
    @media (max-width: 768px) {
      #sidebar { transform: translateX(-100%); }
      #sidebar.open { transform: translateX(0); }
      #main-wrap { margin-left: 0; }
    }
  </style>
</head>
<body>

<!-- ── FLASH MESSAGES ───────────────────────── -->
<div class="flash-container" id="flashContainer">
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?= e($_SESSION['flash_success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= e($_SESSION['flash_error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php unset($_SESSION['flash_error']); endif; ?>
</div>

<!-- ── SIDEBAR ───────────────────────────────── -->
<nav id="sidebar">
  <div class="sidebar-logo">
    <div class="brand"><span class="dot"></span> PhoneShop</div>
    <small>Administration Panel</small>
  </div>

  <div class="sidebar-nav">
    <div class="nav-label">Tổng quan</div>
    <a href="/admin/dashboard.php" class="sidebar-link <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="nav-label">Danh mục</div>
    <a href="/admin/products/index.php" class="sidebar-link <?= $active_menu === 'products' ? 'active' : '' ?>">
      <i class="bi bi-phone"></i> Sản phẩm
    </a>
    <a href="/admin/purchase_orders/index.php" class="sidebar-link <?= $active_menu === 'purchase_orders' ? 'active' : '' ?>">
      <i class="bi bi-box-arrow-in-down"></i> Nhập hàng
    </a>
    <a href="/admin/pricing/index.php" class="sidebar-link <?= $active_menu === 'pricing' ? 'active' : '' ?>">
      <i class="bi bi-tag"></i> Quản lý giá
    </a>

    <div class="nav-label">Kinh doanh</div>
    <a href="/admin/orders/index.php" class="sidebar-link <?= $active_menu === 'orders' ? 'active' : '' ?>">
      <i class="bi bi-bag-check"></i> Đơn hàng
    </a>
    <a href="/admin/inventory/report.php" class="sidebar-link <?= $active_menu === 'inventory' ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-line"></i> Tồn kho
    </a>

    <div class="nav-label">Hệ thống</div>
    <a href="/admin/users/index.php" class="sidebar-link <?= $active_menu === 'users' ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Quản lý User
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($admin['username'], 0, 1)) ?></div>
      <div>
        <div class="admin-name"><?= e($admin['username']) ?></div>
        <div class="admin-role">Administrator</div>
      </div>
      <a href="/admin/logout.php" class="logout-btn" title="Đăng xuất">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- ── MAIN CONTENT ──────────────────────────── -->
<div id="main-wrap">
  <header id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="sidebarToggle">
        <i class="bi bi-list"></i>
      </button>
      <span class="page-title-bar"><?= e($page_title) ?></span>
    </div>
    <div class="topbar-right">
      <span class="topbar-badge">
        <i class="bi bi-person-fill"></i>
        <?= e($admin['username']) ?>
      </span>
    </div>
  </header>

  <main id="page-content">
