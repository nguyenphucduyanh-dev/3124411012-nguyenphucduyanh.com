<?php
// checkout.php  —  Trang thanh toán
// Yêu cầu đăng nhập; hỗ trợ địa chỉ mặc định / nhập mới + 3 PTTT

session_start();
require_once __DIR__ . '/config/db.php';

// ── Guard: phải đăng nhập ─────────────────────────────────────────────────
if (empty($_SESSION['user'])) {
    header('Location: login.php?ref=checkout');
    exit;
}
// ── Guard: giỏ hàng không được rỗng ──────────────────────────────────────
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$user = $_SESSION['user'];
$cart = $_SESSION['cart'];

// Lấy thông tin user từ DB (lấy default_address mới nhất)
$userStmt = getDB()->prepare(
    'SELECT id, full_name, email, phone, default_address FROM users WHERE id = :id'
);
$userStmt->execute([':id' => $user['id']]);
$userInfo = $userStmt->fetch();

// ── Xử lý POST (đặt hàng) ────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addressType     = $_POST['address_type']     ?? 'default';
    $newAddress      = trim($_POST['new_address']  ?? '');
    $paymentMethod   = $_POST['payment_method']   ?? 'COD';
    $note            = trim($_POST['note']          ?? '');

    // Xác định địa chỉ giao hàng
    if ($addressType === 'default') {
        $shippingAddress = $userInfo['default_address'] ?? '';
        if (empty($shippingAddress)) {
            $errors[] = 'Bạn chưa có địa chỉ mặc định. Vui lòng nhập địa chỉ mới.';
        }
    } else {
        $shippingAddress = $newAddress;
        if (strlen($shippingAddress) < 10) {
            $errors[] = 'Vui lòng nhập địa chỉ giao hàng đầy đủ (ít nhất 10 ký tự).';
        }
    }

    // Validate phương thức thanh toán
    $allowedMethods = ['COD', 'BANK_TRANSFER', 'ONLINE'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Phương thức thanh toán không hợp lệ.';
    }

    if (empty($errors)) {
        $pdo = getDB();
        try {
            $pdo->beginTransaction();

            // Tính tổng tiền + kiểm tra tồn kho lần cuối
            $totalAmount = 0.0;
            $cartItems   = [];

            foreach ($cart as $item) {
                $pid  = $item['product_id'];
                $qty  = $item['qty'];

                $pStmt = $pdo->prepare(
                    'SELECT id, name, selling_price, stock_quantity
                       FROM products WHERE id = :id AND is_active = 1 FOR UPDATE'
                );
                $pStmt->execute([':id' => $pid]);
                $p = $pStmt->fetch();

                if (!$p || $p['stock_quantity'] < $qty) {
                    $pdo->rollBack();
                    $errors[] = "Sản phẩm \"{$item['name']}\" không đủ hàng trong kho.";
                    goto renderPage;
                }
                $cartItems[]  = ['product' => $p, 'qty' => $qty];
                $totalAmount += $p['selling_price'] * $qty;
            }

            // Tạo đơn hàng
            $orderStmt = $pdo->prepare(
                'INSERT INTO orders
                    (user_id, shipping_address, payment_method, total_amount, note)
                 VALUES
                    (:user_id, :address, :payment, :total, :note)'
            );
            $orderStmt->execute([
                ':user_id' => $user['id'],
                ':address' => $shippingAddress,
                ':payment' => $paymentMethod,
                ':total'   => $totalAmount,
                ':note'    => $note,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // Thêm chi tiết đơn hàng + trừ tồn kho
            $detailStmt = $pdo->prepare(
                'INSERT INTO order_details (order_id, product_id, unit_price, quantity)
                 VALUES (:oid, :pid, :price, :qty)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id'
            );
            foreach ($cartItems as $ci) {
                $detailStmt->execute([
                    ':oid'   => $orderId,
                    ':pid'   => $ci['product']['id'],
                    ':price' => $ci['product']['selling_price'],
                    ':qty'   => $ci['qty'],
                ]);
                $stockStmt->execute([
                    ':qty' => $ci['qty'],
                    ':id'  => $ci['product']['id'],
                ]);
            }

            $pdo->commit();

            // Xoá giỏ hàng
            $_SESSION['cart'] = [];

            // Redirect đến trang tóm tắt
            header('Location: order_summary.php?order_id=' . $orderId);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[checkout.php Error] ' . $e->getMessage());
            $errors[] = 'Lỗi hệ thống. Vui lòng thử lại.';
        }
    }
}

renderPage:
// Tính tổng hiển thị
$displayTotal = 0.0;
foreach ($cart as $item) {
    $displayTotal += $item['selling_price'] * $item['qty'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thanh toán — Phone Shop</title>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#f5f6fa;color:#333}
  .container{max-width:860px;margin:0 auto;padding:0 16px 40px}
  header{background:#1a1a2e;color:#fff;padding:16px 0;margin-bottom:28px}
  header h1{font-size:1.3rem}

  /* Bước */
  .steps{display:flex;gap:0;margin-bottom:28px}
  .step{flex:1;text-align:center;padding:10px 0;font-size:.8rem;color:#aaa;
    border-bottom:3px solid #e0e0e0;font-weight:600;letter-spacing:.5px}
  .step.done{color:#4361ee;border-color:#4361ee}
  .step.active{color:#4361ee;border-color:#4361ee;font-weight:700}

  /* Card */
  .card{background:#fff;border-radius:10px;padding:24px 28px;margin-bottom:20px;
    box-shadow:0 2px 8px rgba(0,0,0,.07)}
  .card h2{font-size:1rem;font-weight:700;margin-bottom:16px;color:#222;
    padding-bottom:10px;border-bottom:1px solid #f0f0f0}

  /* Form */
  .form-group{margin-bottom:14px}
  label{display:block;font-size:.8rem;color:#666;margin-bottom:5px;font-weight:600}
  input[type=text],input[type=tel],textarea{
    width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;
    font-size:.9rem;font-family:inherit}
  input:focus,textarea:focus{outline:none;border-color:#4361ee;
    box-shadow:0 0 0 3px rgba(67,97,238,.15)}
  .radio-group{display:flex;flex-direction:column;gap:10px}
  .radio-option{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
    border:1px solid #ddd;border-radius:8px;cursor:pointer;transition:border .2s}
  .radio-option:has(input:checked){border-color:#4361ee;background:#f0f2ff}
  .radio-option input[type=radio]{margin-top:2px;accent-color:#4361ee}
  .radio-label{font-size:.9rem;font-weight:600}
  .radio-desc{font-size:.78rem;color:#888;margin-top:2px}

  /* Bank info */
  .bank-info{background:#f8f9ff;border:1px dashed #4361ee;border-radius:8px;
    padding:14px 16px;margin-top:12px;font-size:.85rem;display:none}
  .bank-info.show{display:block}
  .bank-info strong{color:#4361ee}

  /* Online placeholder */
  .online-info{background:#fff9f0;border:1px dashed #f4a261;border-radius:8px;
    padding:14px 16px;margin-top:12px;font-size:.85rem;color:#e76f51;display:none}
  .online-info.show{display:block}

  /* Order summary */
  .order-items{list-style:none}
  .order-items li{display:flex;justify-content:space-between;padding:7px 0;
    border-bottom:1px solid #f5f5f5;font-size:.88rem}
  .order-items li:last-child{border:none}
  .total-row{display:flex;justify-content:space-between;font-size:1.1rem;
    font-weight:700;padding-top:12px;border-top:2px solid #eee;
    margin-top:8px;color:#e63946}

  /* Errors */
  .error-box{background:#fff5f5;border:1px solid #f8d7da;border-radius:8px;
    padding:14px 16px;margin-bottom:18px;color:#842029;font-size:.88rem}
  .error-box ul{padding-left:18px;margin-top:6px}

  /* Submit */
  .btn-place-order{width:100%;padding:14px;background:#4361ee;color:#fff;
    border:none;border-radius:8px;font-size:1.05rem;font-weight:700;
    cursor:pointer;transition:background .2s;letter-spacing:.5px}
  .btn-place-order:hover{background:#3451d1}

  #new-address-section{display:none;margin-top:12px}
</style>
</head>
<body>
<header>
  <div class="container">
    <h1>🛒 Thanh toán đơn hàng</h1>
  </div>
</header>
<div class="container">

  <div class="steps">
    <div class="step done">1. Giỏ hàng</div>
    <div class="step active">2. Thanh toán</div>
    <div class="step">3. Xác nhận</div>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="error-box">
    ⚠️ Vui lòng kiểm tra lại:
    <ul>
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="POST" id="checkout-form">

    <!-- ── 1. Địa chỉ giao hàng ── -->
    <div class="card">
      <h2>📍 Địa chỉ giao hàng</h2>
      <div class="radio-group">

        <!-- Địa chỉ mặc định -->
        <label class="radio-option">
          <input type="radio" name="address_type" value="default"
                 <?= empty($userInfo['default_address']) ? 'disabled' : 'checked' ?>
                 onchange="toggleAddressForm(this)">
          <div>
            <p class="radio-label">Dùng địa chỉ trong hồ sơ</p>
            <p class="radio-desc">
              <?php if ($userInfo['default_address']): ?>
                📌 <?= htmlspecialchars($userInfo['default_address']) ?>
              <?php else: ?>
                <em style="color:#ccc">(Chưa có địa chỉ mặc định)</em>
              <?php endif; ?>
            </p>
          </div>
        </label>

        <!-- Địa chỉ mới -->
        <label class="radio-option">
          <input type="radio" name="address_type" value="new"
                 <?= empty($userInfo['default_address']) ? 'checked' : '' ?>
                 onchange="toggleAddressForm(this)">
          <div>
            <p class="radio-label">Nhập địa chỉ giao hàng mới</p>
            <p class="radio-desc">Nhập địa chỉ khác với hồ sơ của bạn</p>
          </div>
        </label>

      </div>

      <div id="new-address-section">
        <div class="form-group" style="margin-top:14px">
          <label for="new_address">Địa chỉ mới <span style="color:#e63946">*</span></label>
          <input type="text" id="new_address" name="new_address"
                 placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố"
                 value="<?= htmlspecialchars($_POST['new_address'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- ── 2. Phương thức thanh toán ── -->
    <div class="card">
      <h2>💳 Phương thức thanh toán</h2>
      <div class="radio-group">

        <!-- COD -->
        <label class="radio-option">
          <input type="radio" name="payment_method" value="COD" checked
                 onchange="showPaymentInfo(this)">
          <div>
            <p class="radio-label">💵 Tiền mặt khi nhận hàng (COD)</p>
            <p class="radio-desc">Thanh toán khi shipper giao hàng đến tay bạn</p>
          </div>
        </label>

        <!-- Chuyển khoản -->
        <label class="radio-option">
          <input type="radio" name="payment_method" value="BANK_TRANSFER"
                 onchange="showPaymentInfo(this)">
          <div>
            <p class="radio-label">🏦 Chuyển khoản ngân hàng</p>
            <p class="radio-desc">Chuyển trước, đơn hàng xử lý sau khi xác nhận</p>
          </div>
        </label>

        <!-- Online -->
        <label class="radio-option">
          <input type="radio" name="payment_method" value="ONLINE"
                 onchange="showPaymentInfo(this)">
          <div>
            <p class="radio-label">📱 Thanh toán Online (MoMo / ZaloPay / VNPay)</p>
            <p class="radio-desc">Tích hợp sắp ra mắt</p>
          </div>
        </label>

      </div>

      <!-- Thông tin STK ngân hàng mẫu -->
      <div class="bank-info" id="bank-info">
        <p>Vui lòng chuyển khoản đến:</p>
        <p style="margin-top:8px"><strong>Ngân hàng:</strong> Vietcombank (VCB)</p>
        <p><strong>Số tài khoản:</strong> <strong>1234567890</strong></p>
        <p><strong>Tên tài khoản:</strong> CONG TY PHONE SHOP</p>
        <p style="margin-top:8px;color:#888">Nội dung CK: <strong>ĐH + Tên + SĐT của bạn</strong></p>
      </div>

      <!-- Placeholder Online -->
      <div class="online-info" id="online-info">
        🚧 Tính năng thanh toán Online đang được phát triển. Vui lòng chọn phương thức khác.
      </div>
    </div>

    <!-- ── 3. Ghi chú ── -->
    <div class="card">
      <h2>📝 Ghi chú đơn hàng (tuỳ chọn)</h2>
      <div class="form-group" style="margin:0">
        <textarea name="note" rows="3"
                  placeholder="Lưu ý cho shipper, thời gian giao hàng..."><?=
          htmlspecialchars($_POST['note'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- ── 4. Tóm tắt đơn hàng ── -->
    <div class="card">
      <h2>🧾 Tóm tắt đơn hàng</h2>
      <ul class="order-items">
        <?php foreach ($cart as $item):
              $sub = $item['selling_price'] * $item['qty'];
        ?>
        <li>
          <span><?= htmlspecialchars($item['name']) ?> × <?= $item['qty'] ?></span>
          <span><?= number_format($sub, 0, ',', '.') ?>₫</span>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="total-row">
        <span>Tổng cộng</span>
        <span><?= number_format($displayTotal, 0, ',', '.') ?>₫</span>
      </div>
    </div>

    <button type="submit" class="btn-place-order">✅ Đặt hàng ngay</button>

  </form>

</div><!-- /container -->

<script>
// Toggle form địa chỉ mới
function toggleAddressForm(radio) {
    const section = document.getElementById('new-address-section');
    section.style.display = radio.value === 'new' ? 'block' : 'none';
}

// Hiện thông tin thanh toán tương ứng
function showPaymentInfo(radio) {
    document.getElementById('bank-info').classList.remove('show');
    document.getElementById('online-info').classList.remove('show');
    if (radio.value === 'BANK_TRANSFER') {
        document.getElementById('bank-info').classList.add('show');
    } else if (radio.value === 'ONLINE') {
        document.getElementById('online-info').classList.add('show');
    }
}

// Khởi tạo đúng trạng thái khi load
(function init() {
    const addrNew = document.querySelector('input[name=address_type][value=new]');
    if (addrNew && addrNew.checked) {
        document.getElementById('new-address-section').style.display = 'block';
    }

    const selectedPM = document.querySelector('input[name=payment_method]:checked');
    if (selectedPM) showPaymentInfo(selectedPM);
})();
</script>
</body>
</html>
