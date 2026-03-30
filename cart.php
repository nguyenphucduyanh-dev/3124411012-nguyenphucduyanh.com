<?php
/**
 * cart.php - Quản lý giỏ hàng (Session + AJAX)
 * Tác giả: nguyenphucduyanh-dev
 */

session_start();
require_once __DIR__ . '/includes/functions.php';

// -----------------------------------------------
// Xử lý AJAX POST requests
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action    = $_POST['action'] ?? '';
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity  = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    switch ($action) {
        case 'add':
            $result = addToCart($productId, $quantity);
            break;
        case 'update':
            $result = updateCartItem($productId, $quantity);
            break;
        case 'remove':
            $result = removeFromCart($productId);
            break;
        case 'clear':
            clearCart();
            $result = ['success' => true, 'message' => 'Đã xóa toàn bộ giỏ hàng.', 'cart_count' => 0];
            break;
        default:
            $result = ['success' => false, 'message' => 'Hành động không hợp lệ.'];
    }

    // Kèm theo thông tin giỏ hàng mới
    $result['cart']        = array_values(getCart());
    $result['cart_count']  = getCartTotalItems();
    $result['cart_total']  = getCartTotalAmount();

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------
// AJAX GET: Lấy giỏ hàng hiện tại
// -----------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'cart'       => array_values(getCart()),
        'cart_count' => getCartTotalItems(),
        'cart_total' => getCartTotalAmount()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------
// Hiển thị trang giỏ hàng
// -----------------------------------------------
$cart       = getCart();
$cartTotal  = getCartTotalAmount();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - Website Bán Điện Thoại</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 960px; margin: 0 auto; padding: 20px; }
        .page-header { background: #1a73e8; color: #fff; padding: 20px; text-align: center; margin-bottom: 20px; border-radius: 8px; }

        .cart-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .cart-table th { background: #f0f0f0; padding: 14px; text-align: left; font-size: 14px; }
        .cart-table td { padding: 14px; border-top: 1px solid #eee; vertical-align: middle; }
        .cart-table .product-name { font-weight: 600; }
        .cart-table .price { color: #e53935; font-weight: 600; white-space: nowrap; }

        .qty-control { display: flex; align-items: center; gap: 6px; }
        .qty-control button {
            width: 32px; height: 32px; border: 1px solid #ddd; background: #fff;
            border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: 700;
        }
        .qty-control button:hover { background: #f0f0f0; }
        .qty-control input {
            width: 50px; text-align: center; border: 1px solid #ddd; border-radius: 4px;
            padding: 6px; font-size: 14px;
        }

        .btn-remove { background: none; border: none; color: #e53935; cursor: pointer; font-size: 20px; }
        .btn-remove:hover { color: #b71c1c; }

        .cart-summary {
            margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .cart-summary .total { font-size: 22px; font-weight: 700; color: #e53935; }
        .cart-summary .actions { display: flex; gap: 12px; }
        .btn {
            padding: 12px 24px; border-radius: 6px; border: none; cursor: pointer;
            font-size: 15px; font-weight: 600; transition: background 0.2s;
        }
        .btn-primary { background: #1a73e8; color: #fff; }
        .btn-primary:hover { background: #1557b0; }
        .btn-danger { background: #e53935; color: #fff; }
        .btn-danger:hover { background: #b71c1c; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .empty-cart { text-align: center; padding: 60px 20px; color: #999; font-size: 18px; }

        .login-notice {
            background: #fff3cd; border: 1px solid #ffc107; padding: 14px; border-radius: 6px;
            margin-bottom: 20px; font-size: 14px; color: #856404;
        }

        .toast {
            position: fixed; bottom: 20px; right: 20px; background: #333; color: #fff;
            padding: 14px 24px; border-radius: 8px; font-size: 14px; z-index: 1000;
            opacity: 0; transition: opacity 0.3s; pointer-events: none;
        }
        .toast.show { opacity: 1; }

        @media (max-width: 600px) {
            .cart-table, .cart-table thead, .cart-table tbody, .cart-table th, .cart-table td, .cart-table tr {
                display: block;
            }
            .cart-table thead { display: none; }
            .cart-table td { padding: 8px 14px; text-align: right; position: relative; }
            .cart-table td::before {
                content: attr(data-label);
                position: absolute; left: 14px; font-weight: 600; text-align: left;
            }
            .cart-table tr { border-bottom: 2px solid #eee; margin-bottom: 10px; padding: 10px 0; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h1>🛒 Giỏ hàng của bạn</h1>
    </div>

    <?php if (!$isLoggedIn): ?>
        <div class="login-notice">
            ⚠️ Bạn cần <a href="login.php"><strong>đăng nhập</strong></a> để tiến hành mua hàng.
        </div>
    <?php endif; ?>

    <div id="cartContent">
        <?php if (empty($cart)): ?>
            <div class="empty-cart">
                <p>📭 Giỏ hàng trống</p>
                <p style="margin-top: 10px;"><a href="search.php">Tiếp tục mua sắm →</a></p>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cartBody">
                    <?php foreach ($cart as $item): ?>
                        <?php $subtotal = $item['selling_price'] * $item['quantity']; ?>
                        <tr id="row-<?= $item['product_id'] ?>">
                            <td data-label="Sản phẩm" class="product-name">
                                <?= htmlspecialchars($item['product_name']) ?>
                            </td>
                            <td data-label="Đơn giá" class="price">
                                <?= formatVND($item['selling_price']) ?>
                            </td>
                            <td data-label="Số lượng">
                                <div class="qty-control">
                                    <button onclick="changeQty(<?= $item['product_id'] ?>, -1)">−</button>
                                    <input type="number" id="qty-<?= $item['product_id'] ?>"
                                           value="<?= $item['quantity'] ?>" min="1"
                                           onchange="setQty(<?= $item['product_id'] ?>, this.value)">
                                    <button onclick="changeQty(<?= $item['product_id'] ?>, 1)">+</button>
                                </div>
                            </td>
                            <td data-label="Thành tiền" class="price" id="subtotal-<?= $item['product_id'] ?>">
                                <?= formatVND($subtotal) ?>
                            </td>
                            <td>
                                <button class="btn-remove" onclick="removeItem(<?= $item['product_id'] ?>)" title="Xóa">✕</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-summary">
                <div>
                    Tổng cộng: <span class="total" id="cartTotal"><?= formatVND($cartTotal) ?></span>
                </div>
                <div class="actions">
                    <button class="btn btn-danger" onclick="clearCartAll()">🗑 Xóa tất cả</button>
                    <?php if ($isLoggedIn): ?>
                        <a href="checkout.php" class="btn btn-primary" style="text-decoration:none; color:#fff;">
                            💳 Tiến hành thanh toán
                        </a>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled title="Vui lòng đăng nhập">
                            💳 Tiến hành thanh toán
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
/**
 * JavaScript Cart - Website Bán Điện Thoại
 */

function changeQty(productId, delta) {
    const input  = document.getElementById('qty-' + productId);
    let newQty   = parseInt(input.value) + delta;
    if (newQty < 1) newQty = 1;
    input.value = newQty;
    updateItem(productId, newQty);
}

function setQty(productId, value) {
    let qty = parseInt(value);
    if (isNaN(qty) || qty < 1) qty = 1;
    updateItem(productId, qty);
}

function updateItem(productId, quantity) {
    ajaxPost('cart.php', 'action=update&product_id=' + productId + '&quantity=' + quantity, function(data) {
        if (data.success) {
            location.reload();
        } else {
            showToast(data.message, false);
        }
    });
}

function removeItem(productId) {
    ajaxPost('cart.php', 'action=remove&product_id=' + productId, function(data) {
        if (data.success) {
            const row = document.getElementById('row-' + productId);
            if (row) row.remove();
            if (data.cart_count === 0) {
                location.reload();
            } else {
                document.getElementById('cartTotal').textContent = formatCurrency(data.cart_total);
            }
            showToast(data.message, true);
        }
    });
}

function clearCartAll() {
    if (!confirm('Bạn có chắc muốn xóa toàn bộ giỏ hàng?')) return;
    ajaxPost('cart.php', 'action=clear', function(data) {
        location.reload();
    });
}

function ajaxPost(url, body, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                callback(JSON.parse(xhr.responseText));
            } catch(e) {
                showToast('Lỗi xử lý.', false);
            }
        }
    };
    xhr.send(body);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
}

function showToast(message, success) {
    const toast = document.getElementById('toast');
    toast.textContent = (success ? '✅ ' : '⚠️ ') + message;
    toast.style.background = success ? '#2e7d32' : '#c62828';
    toast.classList.add('show');
    setTimeout(function() { toast.classList.remove('show'); }, 3000);
}
</script>

</body>
</html>
