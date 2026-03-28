<?php
require 'db.php';

// 1. Lấy dữ liệu lọc từ GET
$status_filter = $_GET['status'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$sort_by_ward = isset($_GET['sort_ward']);

// 2. Xây dựng câu SQL
// Logic tách Phường: Tìm chữ "Phường", lấy phần phía sau nó đến dấu phẩy tiếp theo
$sql = "SELECT *, 
        SUBSTRING_INDEX(SUBSTRING_INDEX(shipping_address, 'Phường ', -1), ',', 1) AS phuong_name
        FROM orders WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}
if ($from_date) {
    $sql .= " AND order_date >= ?";
    $params[] = $from_date . " 00:00:00";
}
if ($to_date) {
    $sql .= " AND order_date <= ?";
    $params[] = $to_date . " 23:59:59";
}

// 3. Sắp xếp
if ($sort_by_ward) {
    $sql .= " ORDER BY phuong_name ASC";
} else {
    $sql .= " ORDER BY order_date DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<h2>Quản lý Đơn hàng</h2>

<form method="GET" style="background: #f4f4f4; padding: 15px; margin-bottom: 20px;">
    Trạng thái: 
    <select name="status">
        <option value="">-- Tất cả --</option>
        <option value="Chưa xử lý" <?= $status_filter == 'Chưa xử lý' ? 'selected' : '' ?>>Chưa xử lý</option>
        <option value="Đã xác nhận" <?= $status_filter == 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
        <option value="Đã giao" <?= $status_filter == 'Đã giao' ? 'selected' : '' ?>>Đã giao</option>
        <option value="Đã hủy" <?= $status_filter == 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
    </select>

    Từ ngày: <input type="date" name="from_date" value="<?= $from_date ?>">
    Đến ngày: <input type="date" name="to_date" value="<?= $to_date ?>">
    
    <label><input type="checkbox" name="sort_ward" <?= $sort_by_ward ? 'checked' : '' ?>> Sắp xếp theo Phường</label>

    <button type="submit">Lọc dữ liệu</button>
    <a href="admin_orders.php">Reset</a>
</form>

<table border="1" width="100%" style="border-collapse: collapse;">
    <tr style="background: #eee;">
        <th>Mã Đơn</th>
        <th>Ngày Đặt</th>
        <th>Địa chỉ (Phường)</th>
        <th>Tổng Tiền</th>
        <th>Trạng Thái</th>
        <th>Thao Tác</th>
    </tr>
    <?php foreach ($orders as $o): ?>
    <tr>
        <td>#<?= $o['id'] ?></td>
        <td><?= date("d/m/Y H:i", strtotime($o['order_date'])) ?></td>
        <td><?= htmlspecialchars($o['shipping_address']) ?> </td>
        <td><strong><?= number_format($o['total_price']) ?>đ</strong></td>
        <td>
            <span class="badge status-<?= $o['status'] ?>"><?= $o['status'] ?></span>
        </td>
        <td>
            <a href="admin_order_detail.php?id=<?= $o['id'] ?>">Chi tiết</a> |
            <div style="display: inline-block;">
                <form action="update_status.php" method="POST" style="margin:0;">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <select name="new_status" onchange="this.form.submit()">
                        <option value="">-- Đổi trạng thái --</option>
                        <option value="Chưa xử lý">Chưa xử lý</option>
                        <option value="Đã xác nhận">Đã xác nhận</option>
                        <option value="Đã giao">Đã giao</option>
                        <option value="Đã hủy">Đã hủy</option>
                    </select>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
