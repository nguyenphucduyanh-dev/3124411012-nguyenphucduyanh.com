<?php
require 'auth.php';
require '../db.php';

// Lấy các con số thống kê nhanh
$total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Chưa xử lý'")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE so_luong_ton < 5 AND is_deleted = 0")->fetchColumn();
$revenue_today = $pdo->query("SELECT SUM(total_price) FROM orders WHERE DATE(order_date) = CURDATE() AND status = 'Đã giao thành công'")->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Phone Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { display: flex; min-height: 100vh; background: #f8f9fa; }
        .sidebar { width: 250px; background: #343a40; color: white; padding: 20px; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 10px; border-radius: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #495057; color: white; }
        .main-content { flex: 1; padding: 30px; }
        .card-stat { border: none; border-radius: 10px; transition: 0.3s; }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>ADMIN PANEL</h4>
    <hr>
    <p class="small text-uppercase fw-bold text-muted">Chính</p>
    <a href="index.php" class="active">🏠 Dashboard</a>
    
    <p class="small text-uppercase fw-bold text-muted mt-4">Quản lý</p>
    <a href="manage_categories.php">📁 Danh mục</a>
    <a href="product_list.php">📱 Sản phẩm</a>
    <a href="admin_orders.php">🛒 Đơn hàng (<?= $pending_orders ?>)</a>
    <a href="create_import.php">📦 Nhập hàng kho</a>
    
    <p class="small text-uppercase fw-bold text-muted mt-4">Hệ thống</p>
    <a href="manage_users.php">👥 Người dùng</a>
    <a href="admin_reports.php">📊 Thống kê báo cáo</a>
    <hr>
    <a href="logout.php" class="text-danger">🚪 Đăng xuất</a>
</div>

<div class="main-content">
    <header class="d-flex justify-content-between align-items-center mb-4">
        <h2>Tổng quan hệ thống</h2>
        <div class="user-info">Xin chào, <strong><?= $_SESSION['admin_user'] ?></strong></div>
    </header>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="card card-stat bg-primary text-white p-3">
                <h5>Tổng sản phẩm</h5>
                <h2><?= $total_products ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-warning text-dark p-3">
                <h5>Đơn chờ xử lý</h5>
                <h2><?= $pending_orders ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-danger text-white p-3">
                <h5>Sắp hết hàng (< 5)</h5>
                <h2><?= $low_stock ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-success text-white p-3">
                <h5>Doanh thu hôm nay</h5>
                <h4><?= number_format($revenue_today) ?>đ</h4>
            </div>
        </div>
    </div>

    <div class="mt-5 card p-4 shadow-sm">
        <h4>Đơn hàng mới nhất</h4>
        <table class="table table-hover mt-3">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recent = $pdo->query("SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 5");
                while($row = $recent->fetch()):
                ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><?= $row['fullname'] ?></td>
                    <td><?= number_format($row['total_price']) ?>đ</td>
                    <td><span class="badge bg-secondary"><?= $row['status'] ?></span></td>
                    <td><a href="admin_order_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Xem</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
