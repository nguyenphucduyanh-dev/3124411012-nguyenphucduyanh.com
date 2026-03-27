<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];
$cart = $_SESSION['cart'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Xác định địa chỉ giao hàng
    $address = ($_POST['address_type'] == 'default') ? $user['address'] : $_POST['new_address'];
    $payment_method = $_POST['payment_method'];
    
    // 2. Tính tổng tiền và chuẩn bị dữ liệu chi tiết
    $total_bill = 0;
    $order_items = [];
    
    foreach ($cart as $id => $qty) {
        $stmt = $pdo->prepare("SELECT name, gia_nhap, ty_le_loi_nhuan, so_luong_ton FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        
        $gia_ban = $p['gia_nhap'] * (1 + $p['ty_le_loi_nhuan'] / 100);
        $total_bill += $gia_ban * $qty;
        
        $order_items[] = [
            'id' => $id,
            'qty' => $qty,
            'price' => $gia_ban
        ];
    }

    // 3. Lưu vào bảng orders
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_price, shipping_address, payment_method) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $total_bill, $address, $payment_method]);
    $order_id = $pdo->lastInsertId();

    // 4. Lưu vào order_details và cập nhật tồn kho
    foreach ($order_items as $item) {
        // Lưu chi tiết
        $stmt = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
        $stmt->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
        
        // Trừ tồn kho
        $stmt = $pdo->prepare("UPDATE products SET so_luong_ton = so_luong_ton - ? WHERE id = ?");
        $stmt->execute([$item['qty'], $item['id']]);
    }

    // Xóa giỏ hàng và chuyển hướng
    unset($_SESSION['cart']);
    header("Location: order_success.php?id=" . $order_id);
    exit();
}
?>

<form method="POST">
    <h2>Thông tin thanh toán</h2>
    
    <div>
        <h4>Địa chỉ giao hàng:</h4>
        <input type="radio" name="address_type" value="default" checked> Dùng địa chỉ mặc định: <?= $user['address'] ?><br>
        <input type="radio" name="address_type" value="new"> Nhập địa chỉ mới: <br>
        <textarea name="new_address" placeholder="Địa chỉ cụ thể..."></textarea>
    </div>

    <div>
        <h4>Phương thức thanh toán:</h4>
        <input type="radio" name="payment_method" value="Tiền mặt" checked> Tiền mặt khi nhận hàng<br>
        <input type="radio" name="payment_method" value="Chuyển khoản"> Chuyển khoản ngân hàng<br>
        <div id="bank_info" style="display:none; border: 1px dashed #ccc; padding: 10px;">
            STK: 123456789 - Ngân hàng: ABC - Chủ TK: NGUYEN PHUC DUY ANH
        </div>
        <input type="radio" name="payment_method" value="Online"> Thanh toán trực tuyến (VNPAY/Momo)<br>
    </div>

    <button type="submit">Xác nhận đặt hàng</button>
</form>

<script>
// Hiển thị STK khi chọn chuyển khoản
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('bank_info').style.display = (this.value == 'Chuyển khoản') ? 'block' : 'none';
    });
});
</script>
