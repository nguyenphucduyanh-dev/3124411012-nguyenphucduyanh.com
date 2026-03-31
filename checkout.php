<?php
/**
 * checkout.php - Thanh toán
 * Tác giả: nguyenphucduyanh-dev
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/function.php';

cartInit();

// Bắt buộc đăng nhập
if (!isLoggedIn()) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

if (empty(cartItems())) {
    header('Location: cart.php');
    exit;
}

$user   = currentUser();
$pdo    = getDBConnection();
$errors = [];
$order_result = null;

// ====== POST: Đặt hàng ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address_type    = $_POST['address_type']    ?? 'default';
    $new_address     = trim($_POST['new_address'] ?? '');
    $payment_method  = $_POST['payment_method']  ?? 'cash';
    $note            = trim($_POST['note']        ?? '');

    if ($address_type === 'default') {
        $shipping_address = $user['address'] ?? '';
    } else {
        $shipping_address = $new_address;
    }

    if (empty($shipping_address)) {
        $errors[] = 'Vui lòng nhập địa chỉ giao hàng.';
    }
    if (!in_array($payment_method, ['cash', 'transfer', 'online'])) {
        $errors[] = 'Phương thức thanh toán không hợp lệ.';
    }

    if (empty($errors)) {
        $order_result = placeOrder((int)$user['id'], $shipping_address, $payment_method, $note);
        if (!$order_result['success']) {
            $errors[] = $order_result['message'];
        }
    }
}

$items = cartItems(); // đã được clear nếu đặt thành công
$total = cartTotal();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thanh toán</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4" style="max-width:860px">

<?php if ($order_result && $order_result['success']): ?>
<!-- ====== TRANG TÓM TẮT ĐƠN HÀNG ====== -->
<div class="text-center mb-4">
    <div style="font-size:4rem">✅</div>
    <h2 class="text-success">Đặt hàng thành công!</h2>
    <p class="text-muted">Mã đơn hàng: <strong>#<?= $order_result['order_id'] ?></strong></p>
</div>

<?php
// Lấy chi tiết đơn vừa đặt để hiển thị summary
$ord_stmt = $pdo->prepare(
    "SELECT o.*, u.full_name, u.email, u.phone
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ?"
);
$ord_stmt->execute([$order_result['order_id']]);
$order = $ord_stmt->fetch();

$det_stmt = $pdo->prepare(
    "SELECT od.*, p.name, p.image
     FROM order_details od JOIN products p ON p.id = od.product_id
     WHERE od.order_id = ?"
);
$det_stmt->execute([$order_result['order_id']]);
$details = $det_stmt->fetchAll();
?>

<div class="card mb-3">
  <div class="card-header"><strong>📦 Thông tin đơn hàng</strong></div>
  <div class="card-body row">
    <div class="col-md-6">
        <p><strong>Khách hàng:</strong> <?= e($order['full_name']) ?></p>
        <p><strong>Email:</strong> <?= e($order['email']) ?></p>
        <p><strong>SĐT:</strong> <?= e($order['phone'] ?? '—') ?></p>
    </div>
    <div class="col-md-6">
        <p><strong>Địa chỉ giao:</strong> <?= e($order['shipping_address']) ?></p>
        <p><strong>Thanh toán:</strong>
            <?= ['cash'=>'Tiền mặt','transfer'=>'Chuyển khoản','online'=>'Thanh toán Online'][$order['payment_method']] ?>
        </p>
        <p><strong>Trạng thái:</strong> <span class="badge bg-warning">Chờ xác nhận</span></p>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><strong>🛍 Sản phẩm đã đặt</strong></div>
  <div class="card-body p-0">
    <table class="table mb-0">
        <thead class="table-light">
            <tr><th>Sản phẩm</th><th>Giá</th><th>SL</th><th>Thành tiền</th></tr>
        </thead>
        <tbody>
        <?php foreach ($details as $d): ?>
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= e($d['image'] ?: 'assets/images/no-image.png') ?>" width="40">
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
                <td colspan="3" class="text-end fw-bold">Tổng cộng:</td>
                <td class="fw-bold text-danger fs-5"><?= formatVND($order['total_amount']) ?></td>
            </tr>
        </tfoot>
    </table>
  </div>
</div>

<div class="text-center">
    <a href="order_history.php" class="btn btn-outline-primary me-2">Xem lịch sử đơn hàng</a>
    <a href="search.php" class="btn btn-danger">Tiếp tục mua sắm</a>
</div>

<?php else: ?>
<!-- ====== FORM CHECKOUT ====== -->
<h2 class="mb-4">💳 Thanh toán</h2>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="POST" id="checkoutForm">
<div class="row g-4">

  <!-- Cột trái: Địa chỉ + Thanh toán -->
  <div class="col-md-7">

    <!-- ĐỊA CHỈ GIAO HÀNG -->
    <div class="card mb-3">
      <div class="card-header"><strong>📍 Địa chỉ giao hàng</strong></div>
      <div class="card-body">
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="address_type"
                 id="addr_default" value="default" checked>
          <label class="form-check-label" for="addr_default">
            Dùng địa chỉ hồ sơ: <em><?= e($user['address'] ?? 'Chưa có địa chỉ') ?></em>
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="address_type"
                 id="addr_new" value="new">
          <label class="form-check-label" for="addr_new">Nhập địa chỉ mới</label>
        </div>
        <div id="newAddressWrap" class="mt-3" style="display:none">
          <textarea name="new_address" class="form-control" rows="3"
                    placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành..."></textarea>
        </div>
      </div>
    </div>

    <!-- PHƯƠNG THỨC THANH TOÁN -->
    <div class="card mb-3">
      <div class="card-header"><strong>💰 Phương thức thanh toán</strong></div>
      <div class="card-body">

        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="payment_method"
                 id="pay_cash" value="cash" checked>
          <label class="form-check-label" for="pay_cash">💵 Tiền mặt khi nhận hàng (COD)</label>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="payment_method"
                 id="pay_transfer" value="transfer">
          <label class="form-check-label" for="pay_transfer">🏦 Chuyển khoản ngân hàng</label>
        </div>
        <div id="transferInfo" class="alert alert-info ms-4 mt-2" style="display:none">
          <strong>Thông tin chuyển khoản:</strong><br>
          Ngân hàng: <strong>Vietcombank</strong><br>
          Số tài khoản: <strong>1234567890</strong><br>
          Chủ TK: <strong>NGUYEN PHUC DUY ANH</strong><br>
          Nội dung CK: <code>DH + Mã đơn hàng</code>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="radio" name="payment_method"
                 id="pay_online" value="online">
          <label class="form-check-label" for="pay_online">💳 Thanh toán Online</label>
        </div>
        <div id="onlineInfo" class="ms-4 mt-2" style="display:none">
          <div class="d-flex gap-3 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>VNPAY</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>MoMo</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>ZaloPay</button>
          </div>
          <small class="text-muted">Tính năng đang phát triển.</small>
        </div>

      </div>
    </div>

    <!-- GHI CHÚ -->
    <div class="card">
      <div class="card-body">
        <label class="form-label fw-bold">Ghi chú đơn hàng</label>
        <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú..."></textarea>
      </div>
    </div>

  </div>

  <!-- Cột phải: Tóm tắt giỏ -->
  <div class="col-md-5">
    <div class="card sticky-top" style="top:20px">
      <div class="card-header"><strong>🛍 Đơn hàng của bạn</strong></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
        <?php foreach ($items as $item): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <img src="<?= e($item['image'] ?: 'assets/images/no-image.png') ?>" width="40">
            <span class="small"><?= e($item['name']) ?> × <?= $item['qty'] ?></span>
          </div>
          <strong><?= formatVND($item['price'] * $item['qty']) ?></strong>
        </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between fs-5">
          <strong>Tổng cộng</strong>
          <strong class="text-danger"><?= formatVND($total) ?></strong>
        </li>
        </ul>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-danger w-100 btn-lg">✅ Xác nhận đặt hàng</button>
        <a href="cart.php" class="btn btn-outline-secondary w-100 mt-2">← Quay lại giỏ</a>
      </div>
    </div>
  </div>

</div>
</form>

<script>
// Toggle địa chỉ mới
document.querySelectorAll('input[name="address_type"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('newAddressWrap').style.display =
            this.value === 'new' ? 'block' : 'none';
    });
});

// Toggle thông tin thanh toán
document.querySelectorAll('input[name="payment_method"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('transferInfo').style.display =
            this.value === 'transfer' ? 'block' : 'none';
        document.getElementById('onlineInfo').style.display =
            this.value === 'online' ? 'block' : 'none';
    });
});
</script>

<?php endif; ?>
</div>
</body>
</html>
