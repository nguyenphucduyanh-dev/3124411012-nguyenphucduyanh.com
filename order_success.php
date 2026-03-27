<?php
require 'db.php';
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
?>

<h2>Đặt hàng thành công!</h2>
<p>Mã đơn hàng: #<?= $order['id'] ?></p>
<p>Tổng tiền: <?= number_format($order['total_price']) ?>đ</p>
<p>Phương thức: <?= $order['payment_method'] ?></p>
<p>Địa chỉ: <?= $order['shipping_address'] ?></p>
<a href="history.php">Xem lịch sử mua hàng</a>
