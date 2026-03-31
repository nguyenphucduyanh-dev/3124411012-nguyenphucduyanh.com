<?php
/**
 * order_history.php - Lịch sử đơn hàng
 * Tác giả: nguyenphucduyanh-dev
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/function.php';

cartInit();

if (!isLoggedIn()) {
    header('Location: login.php?redirect=order_history.php');
    exit;
}

$user = currentUser();
$pdo  = getDBConnection();

$orders_stmt = $pdo->prepare(
    "SELECT id, created_at, total_amount, status, payment_method, shipping_address
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$orders_stmt->execute([$user['id']]);
$orders = $orders_stmt->fetchAll();

$status_map = [
    'pending'   => ['label' => 'Chờ xác nhận', 'class' => 'warning'],
    'confirmed' => ['label' => 'Đã xác nhận',  'class' => 'info'],
    'shipping'  => ['label' => 'Đang giao',    'class' => 'primary'],
    'delivered' => ['label' => 'Đã giao',       'class' => 'success'],
    'cancelled' => ['label' => 'Đã hủy',        'class' => 'danger'],
];
$payment_map = [
    'cash'     => '💵 Tiền mặt',
    'transfer' => '🏦 Chuyển khoản',
    'online'   => '💳 Online',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lịch sử đơn hàng</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
    .order-detail-row { background: #f8f9fa; }
    .order-detail-row td { padding: 16px; }
</style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-1">📋 Lịch sử đơn hàng</h2>
    <p class="text-muted mb-4">Xin chào, <strong><?= e($user['full_name']) ?></strong></p>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">Bạn chưa có đơn hàng nào. <a href="search.php">Mua ngay!</a></div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-hover align-middle" id="ordersTable">
        <thead class="table-dark">
            <tr>
                <th>Mã đơn</th>
                <th>Ngày đặt</th>
                <th>Tổng tiền</th>
                <th>Thanh toán</th>
                <th>Địa chỉ giao</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order):
            $st = $status_map[$order['status']] ?? ['label'=>$order['status'],'class'=>'secondary'];
        ?>
        <tr class="order-row" data-id="<?= $order['id'] ?>">
            <td><strong>#<?= $order['id'] ?></strong></td>
            <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
            <td class="fw-bold text-danger"><?= formatVND($order['total_amount']) ?></td>
            <td><?= $payment_map[$order['payment_method']] ?? $order['payment_method'] ?></td>
            <td class="text-truncate" style="max-width:180px" title="<?= e($order['shipping_address']) ?>">
                <?= e($order['shipping_address']) ?>
            </td>
            <td><span class="badge bg-<?= $st['class'] ?>"><?= $st['label'] ?></span></td>
            <td>
                <button class="btn btn-outline-primary btn-sm btn-detail"
                        data-id="<?= $order['id'] ?>">
                    Xem chi tiết
                </button>
            </td>
        </tr>
        <!-- Hàng chi tiết (ẩn) -->
        <tr class="order-detail-row d-none" id="detail-<?= $order['id'] ?>">
            <td colspan="7">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Đang tải...
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <a href="search.php" class="btn btn-outline-secondary mt-2">← Tiếp tục mua sắm</a>
</div>

<script>
document.querySelectorAll('.btn-detail').forEach(btn => {
    btn.addEventListener('click', function() {
        const id  = this.dataset.id;
        const row = document.getElementById('detail-' + id);
        const isOpen = !row.classList.contains('d-none');

        // Toggle đóng/mở
        if (isOpen) {
            row.classList.add('d-none');
            this.textContent = 'Xem chi tiết';
            return;
        }

        row.classList.remove('d-none');
        this.textContent = 'Ẩn chi tiết';

        // Nếu đã load rồi thì không load lại
        if (row.dataset.loaded === '1') return;

        fetch(`pages/order_detail_ajax.php?order_id=${id}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(html => {
            row.querySelector('td').innerHTML = html;
            row.dataset.loaded = '1';
        });
    });
});
</script>
</body>
</html>
