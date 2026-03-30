<?php
/**
 * checkout.php - Trang thanh toán
 * Tác giả: nguyenphucduyanh-dev
 */

session_start();
require_once __DIR__ . '/includes/functions.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo    = getDBConnection();

// Lấy thông tin user
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid");
$stmtUser->execute([':uid' => $userId]);
$user = $stmtUser->fetch();

$cart      = getCart();
$cartTotal = getCartTotalAmount();
$error     = '';
$success   = false;
$orderData = null;

// -----------------------------------------------
// Xử lý form đặt hàng
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $receiverName  = trim($_POST['receiver_name'] ?? '');
    $receiverPhone = trim($_POST['receiver_phone'] ?? '');
    $addressType   = $_POST['address_type'] ?? 'default';
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $note          = trim($_POST['note'] ?? '');

    // Xác định địa chỉ giao hàng
    if ($addressType === 'default') {
        $shippingAddress = $user['address'] ?? '';
    } else {
        $shippingAddress = trim($_POST['new_address'] ?? '');
    }

    // Validate
    if (empty($receiverName)) {
        $error = 'Vui lòng nhập tên người nhận.';
    } elseif (empty($receiverPhone)) {
        $error = 'Vui lòng nhập số điện thoại.';
    } elseif (empty($shippingAddress)) {
        $error = 'Vui lòng cung cấp địa chỉ giao hàng.';
    } elseif (empty($cart)) {
        $error = 'Giỏ hàng trống.';
    } else {
        $result = createOrder($userId, $receiverName, $receiverPhone, $shippingAddress, $paymentMethod, $note);

        if ($result['success']) {
            $success   = true;
            $orderData = $result;
        } else {
            $error = $result['message'];
        }
    }
}

// Nếu đặt hàng thành công → hiển thị trang tóm tắt
if ($success && $orderData):
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công!</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .success-box {
            background: #fff; border-radius: 12px; padding: 40px; text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .success-icon { font-size: 64px; margin-bottom: 16px; }
        .success-box h1 { color: #2e7d32; margin-bottom: 10px; }
        .order-info { margin-top: 24px; text-align: left; background: #f9f9f9; padding: 20px; border-radius: 8px; }
        .order-info p { padding: 8px 0; border-bottom: 1px solid #eee; }
        .order-info strong { display: inline-block; min-width: 160px; }
        .total-highlight { font-size: 20px; color: #e53935; font-weight: 700; }
        .actions { margin-top: 24px; display: flex; gap: 12px; justify-content: center; }
        .btn {
            padding: 12px 24px; border-radius: 6px; border: none; cursor: pointer;
            font-size: 15px; font-weight: 600; text-decoration: none; display: inline-block;
        }
        .btn-primary { background: #1a73e8; color: #fff; }
        .btn-outline { background: #fff; color: #1a73e8; border: 2px solid #1a73e8; }
    </style>
</head>
<body>
<div class="container">
    <div class="success-box">
        <div class="success-icon">🎉</div>
        <h1>Đặt hàng thành công!</h1>
        <p>Cảm ơn bạn đã mua hàng tại Website Bán Điện Thoại.</p>

        <div class="order-info">
            <p><strong>Mã đơn hàng:</strong> <?= htmlspecialchars($orderData['order_code']) ?></p>
            <p><strong>Tổng tiền:</strong> <span class="total-highlight"><?= formatVND($orderData['total']) ?></span></p>
            <p><strong>Trạng thái:</strong> Chờ xác nhận</p>
            <p><strong>Thời gian:</strong> <?= date('d/m/Y H:i:s') ?></p>
        </div>

        <div class="actions">
            <a href="order_history.php" class="btn btn-primary">📋 Xem lịch sử đơn hàng</a>
            <a href="search.php" class="btn btn-outline">🛒 Tiếp tục mua sắm</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
exit;
endif;

// -----------------------------------------------
// Hiển thị form thanh toán
// -----------------------------------------------
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - Website Bán Điện Thoại</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .page-header { background: #1a73e8; color: #fff; padding: 20px; text-align: center; margin-bottom: 20px; border-radius: 8px; }

        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }

        .section { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { font-size: 18px; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #eee; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: #1a73e8; outline: none; }

        .radio-group { display: flex; flex-direction: column; gap: 10px; }
        .radio-item {
            display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #ddd;
            border-radius: 6px; cursor: pointer; transition: border-color 0.2s;
        }
        .radio-item:hover { border-color: #1a73e8; }
        .radio-item input[type="radio"] { margin-top: 3px; }
        .radio-item .radio-label { font-weight: 600; }
        .radio-item .radio-desc { font-size: 13px; color: #666; margin-top: 2px; }

        .new-address-form { display: none; margin-top: 12px; padding: 16px; background: #f9f9f9; border-radius: 6px; }

        .bank-info { display: none; margin-top: 12px; padding: 16px; background: #e3f2fd; border-radius: 6px; font-size: 14px; }
        .bank-info p { margin-bottom: 6px; }

        .online-info { display: none; margin-top: 12px; padding: 16px; background: #f3e5f5; border-radius: 6px; font-size: 14px; }

        .order-summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .order-summary-item:last-child { border-bottom: none; }
        .order-summary-total { display: flex; justify-content: space-between; padding: 14px 0; font-size: 18px; font-weight: 700; color: #e53935; border-top: 2px solid #333; margin-top: 10px; }

        .error-msg { background: #ffebee; color: #c62828; padding: 12px; border-radius: 6px; margin-bottom: 16px; }

        .btn-checkout {
            display: block; width: 100%; padding: 14px; background: #2e7d32; color: #fff; border: none;
            border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-checkout:hover { background: #1b5e20; }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h1>💳 Thanh toán</h1>
    </div>

    <?php if ($error): ?>
        <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($cart)): ?>
        <div class="section" style="text-align:center;">
            <p>Giỏ hàng trống. <a href="search.php">Tiếp tục mua sắm</a></p>
        </div>
    <?php else: ?>

    <form method="POST" action="checkout.php">
        <div class="checkout-grid">
            <!-- Cột trái: Thông tin giao hàng -->
            <div>
                <div class="section">
                    <h2>📦 Thông tin giao hàng</h2>

                    <div class="form-group">
                        <label for="receiver_name">Tên người nhận *</label>
                        <input type="text" name="receiver_name" id="receiver_name"
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="receiver_phone">Số điện thoại *</label>
                        <input type="tel" name="receiver_phone" id="receiver_phone"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Địa chỉ giao hàng *</label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="address_type" value="default" checked
                                       onchange="toggleAddressForm()">
                                <div>
                                    <div class="radio-label">📍 Sử dụng địa chỉ mặc định</div>
                                    <div class="radio-desc">
                                        <?= htmlspecialchars($user['address'] ?? 'Chưa có địa chỉ mặc định') ?>
                                    </div>
                                </div>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="address_type" value="new"
                                       onchange="toggleAddressForm()">
                                <div>
                                    <div class="radio-label">✏️ Nhập địa chỉ mới</div>
                                    <div class="radio-desc">Giao hàng đến địa chỉ khác</div>
                                </div>
                            </label>
                        </div>

                        <div class="new-address-form" id="newAddressForm">
                            <textarea name="new_address" id="new_address" rows="3"
                                      placeholder="Nhập địa chỉ giao hàng mới..."></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Phương thức thanh toán *</label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="payment_method" value="cash" checked
                                       onchange="togglePaymentInfo()">
                                <div>
                                    <div class="radio-label">💵 Thanh toán khi nhận hàng (COD)</div>
                                    <div class="radio-desc">Thanh toán bằng tiền mặt khi shipper giao hàng</div>
                                </div>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="payment_method" value="bank_transfer"
                                       onchange="togglePaymentInfo()">
                                <div>
                                    <div class="radio-label">🏦 Chuyển khoản ngân hàng</div>
                                    <div class="radio-desc">Chuyển khoản trước, xác nhận sau</div>
                                </div>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="payment_method" value="online"
                                       onchange="togglePaymentInfo()">
                                <div>
                                    <div class="radio-label">📲 Thanh toán Online (MoMo, ZaloPay, VNPay)</div>
                                    <div class="radio-desc">Thanh toán qua ví điện tử / cổng thanh toán</div>
                                </div>
                            </label>
                        </div>

                        <div class="bank-info" id="bankInfo">
                            <p><strong>Thông tin chuyển khoản:</strong></p>
                            <p>🏦 Ngân hàng: <strong>Vietcombank</strong></p>
                            <p>📄 Số tài khoản: <strong>1234 5678 9012</strong></p>
                            <p>👤 Chủ tài khoản: <strong>NGUYEN PHUC DUY ANH</strong></p>
                            <p>📝 Nội dung CK: <strong>[Mã đơn hàng] - [SĐT]</strong></p>
                        </div>

                        <div class="online-info" id="onlineInfo">
                            <p>📲 <strong>Thanh toán Online</strong></p>
                            <p>Sau khi đặt hàng, bạn sẽ được chuyển đến cổng thanh toán để hoàn tất.</p>
                            <p style="color: #7b1fa2; font-style: italic;">(Tính năng đang phát triển — hiện chỉ hiển thị giao diện)</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="note">Ghi chú đơn hàng</label>
                        <textarea name="note" id="note" rows="3" placeholder="VD: Giao giờ hành chính, gọi trước khi giao..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Tóm tắt đơn hàng -->
            <div>
                <div class="section">
                    <h2>📋 Đơn hàng của bạn</h2>

                    <?php foreach ($cart as $item): ?>
                        <?php $subtotal = $item['selling_price'] * $item['quantity']; ?>
                        <div class="order-summary-item">
                            <div>
                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                <div style="font-size: 13px; color: #666;">
                                    <?= formatVND($item['selling_price']) ?> × <?= $item['quantity'] ?>
                                </div>
                            </div>
                            <div style="font-weight: 600; color: #e53935; white-space: nowrap;">
                                <?= formatVND($subtotal) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="order-summary-total">
                        <span>TỔNG CỘNG</span>
                        <span><?= formatVND($cartTotal) ?></span>
                    </div>

                    <button type="submit" name="place_order" class="btn-checkout" style="margin-top: 20px;">
                        ✅ Đặt hàng (<?= formatVND($cartTotal) ?>)
                    </button>
                </div>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
function toggleAddressForm() {
    const addressType = document.querySelector('input[name="address_type"]:checked').value;
    const form = document.getElementById('newAddressForm');
    form.style.display = (addressType === 'new') ? 'block' : 'none';

    if (addressType === 'new') {
        document.getElementById('new_address').focus();
    }
}

function togglePaymentInfo() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;

    document.getElementById('bankInfo').style.display   = (method === 'bank_transfer') ? 'block' : 'none';
    document.getElementById('onlineInfo').style.display  = (method === 'online') ? 'block' : 'none';
}
</script>

</body>
</html>
