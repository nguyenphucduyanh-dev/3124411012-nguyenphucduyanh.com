<?php
/**
 * pages/order_detail_ajax.php - Trả về HTML chi tiết đơn hàng (AJAX)
 * Tác giả: nguyenphucduyanh-dev
 */

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/function.php';

cartInit();

if (!isLoggedIn()) { http_response_code(403); exit; }

$order_id = (int)($_GET['order_id'] ?? 0);
$user     = currentUser();
$pdo      = getDBConnection();

// Kiểm tra đơn hàng thuộc về user đang đăng nhập
$ord_stmt = $pdo->prepare(
    "SELECT * FROM orders WHERE id = ? AND user_id = ?"
);
$ord_stmt->execute([$order_id, $user['id']]);
$order = $ord_stmt->fetch();

if (!$order) {
    echo '<p class="text-danger">Không tìm thấy đơn hàng.</p>';
    exit;
}

$det_stmt = $pdo->prepare(
    "SELECT od.quantity, od.unit_price, od.subtotal, p.name, p.image
     FROM order_details od
     JOIN products p ON p.id = od.product_id
     WHERE od.order_id = ?"
);
$det_stmt->execute([$order_id]);
$details = $det_stmt->fetchAll();
?>
<table class="table table-sm table-bordered mb-0">
    <thead class="table-secondary">
        <tr>
            <th>Sản phẩm</th>
            <th width="130">Giá bán</th>
            <th width="80">SL</th>
            <th width="150">Thành tiền</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($details as $d): ?>
    <tr>
        <td>
            <div class="d-flex align-items-center gap-2">
                <img src="<?= e($d['image'] ?: '../assets/images/no-image.png') ?>" width="36">
                <?= e($d['name']) ?>
            </div>
        </td>
        <td><?= formatVND($d['unit_price']) ?></td>
        <td><?= $d['quantity'] ?></td>
        <td class="fw-bold"><?= formatVND($d['subtotal']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-end fw-bold">Tổng cộng đơn #<?= $order_id ?>:</td>
            <td class="fw-bold text-danger"><?= formatVND($order['total_amount']) ?></td>
        </tr>
    </tfoot>
</table>
