<?php
// cart.php  —  Giỏ hàng dựa trên PHP Session
// Hỗ trợ cả HTML view và JSON API (khi ?action=...)

session_start();
require_once __DIR__ . '/config/db.php';

// ── Helper: phản hồi JSON ──────────────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Helper: lấy toàn bộ giỏ hàng từ session ──────────────────────────────
function &getCart(): array
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

// ── Helper: tổng tiền giỏ hàng ────────────────────────────────────────────
function cartTotal(): float
{
    $total = 0.0;
    foreach ($_SESSION['cart'] ?? [] as $item) {
        $total += $item['selling_price'] * $item['qty'];
    }
    return $total;
}

// ── Xử lý action (AJAX) ───────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action === 'add') {
    // Chỉ người đã đăng nhập mới được thêm
    if (empty($_SESSION['user'])) {
        jsonResponse(['success' => false, 'redirect' => 'login.php?ref=cart']);
    }

    $productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    if (!$productId) {
        jsonResponse(['success' => false, 'message' => 'Sản phẩm không hợp lệ.'], 400);
    }

    // Lấy thông tin sản phẩm
    $stmt = getDB()->prepare(
        'SELECT id, name, image, selling_price, stock_quantity
           FROM products WHERE id = :id AND is_active = 1'
    );
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        jsonResponse(['success' => false, 'message' => 'Sản phẩm không tồn tại.'], 404);
    }
    if ($product['stock_quantity'] < 1) {
        jsonResponse(['success' => false, 'message' => 'Sản phẩm đã hết hàng.']);
    }

    $cart = &getCart();
    $key  = 'p_' . $productId;

    if (isset($cart[$key])) {
        // Kiểm tra không vượt tồn kho
        if ($cart[$key]['qty'] >= $product['stock_quantity']) {
            jsonResponse(['success' => false, 'message' => 'Không đủ hàng trong kho.']);
        }
        $cart[$key]['qty']++;
    } else {
        $cart[$key] = [
            'product_id'    => $product['id'],
            'name'          => $product['name'],
            'image'         => $product['image'],
            'selling_price' => (float) $product['selling_price'],
            'qty'           => 1,
        ];
    }

    jsonResponse([
        'success'    => true,
        'message'    => 'Đã thêm vào giỏ hàng.',
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'qty')),
    ]);
}

if ($action === 'update') {
    // Cập nhật số lượng: ?action=update&product_id=X&qty=Y
    if (empty($_SESSION['user'])) {
        jsonResponse(['success' => false, 'redirect' => 'login.php']);
    }
    $productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    $qty       = filter_input(INPUT_GET, 'qty',        FILTER_VALIDATE_INT);
    $key       = 'p_' . $productId;
    $cart      = &getCart();

    if ($qty <= 0) {
        unset($cart[$key]);
    } else {
        if (isset($cart[$key])) $cart[$key]['qty'] = $qty;
    }
    jsonResponse(['success' => true, 'total' => cartTotal()]);
}

if ($action === 'remove') {
    // Xoá sản phẩm khỏi giỏ: ?action=remove&product_id=X
    $productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    unset($_SESSION['cart']['p_' . $productId]);
    jsonResponse(['success' => true, 'total' => cartTotal()]);
}

// ── Hiển thị trang giỏ hàng ───────────────────────────────────────────────
if (!empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
} else {
    $user = null;
}
$cart  = $_SESSION['cart'] ?? [];
$total = cartTotal();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giỏ hàng — Phone Shop</title>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#f5f6fa;color:#333}
  .container{max-width:960px;margin:0 auto;padding:0 16px}
  header{background:#1a1a2e;color:#fff;padding:16px 0;margin-bottom:28px}
  header h1{font-size:1.3rem}
  .cart-table{width:100%;border-collapse:collapse;background:#fff;
    border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07)}
  .cart-table th{background:#f0f2ff;padding:12px 16px;text-align:left;
    font-size:.85rem;color:#555;border-bottom:1px solid #eee}
  .cart-table td{padding:12px 16px;border-bottom:1px solid #f0f0f0;
    vertical-align:middle;font-size:.9rem}
  .cart-table img{width:60px;height:60px;object-fit:cover;border-radius:6px}
  .qty-input{width:60px;padding:6px;border:1px solid #ddd;border-radius:4px;
    text-align:center;font-size:.9rem}
  .btn-remove{background:none;border:none;color:#e63946;cursor:pointer;
    font-size:1.2rem;padding:4px}
  .summary-box{background:#fff;border-radius:10px;padding:24px;margin-top:20px;
    box-shadow:0 2px 8px rgba(0,0,0,.07);display:flex;
    justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
  .total-label{font-size:1rem;color:#666}
  .total-price{font-size:1.5rem;font-weight:700;color:#e63946}
  .btn-checkout{padding:12px 36px;background:#4361ee;color:#fff;border:none;
    border-radius:8px;font-size:1rem;cursor:pointer;text-decoration:none;
    display:inline-block;transition:background .2s}
  .btn-checkout:hover{background:#3451d1}
  .empty-cart{text-align:center;padding:80px 0;color:#aaa;font-size:1.1rem}
  .login-note{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;
    padding:16px 20px;margin-bottom:20px;color:#856404;font-size:.9rem}
</style>
</head>
<body>
<header>
  <div class="container">
    <h1>🛒 Giỏ hàng của bạn</h1>
  </div>
</header>
<div class="container">

  <?php if (!$user): ?>
  <div class="login-note">
    ⚠️ Bạn chưa đăng nhập.
    <a href="login.php?ref=cart" style="color:#4361ee;font-weight:600">Đăng nhập</a>
    để tiếp tục mua hàng.
  </div>
  <?php endif; ?>

  <?php if (empty($cart)): ?>
    <div class="empty-cart">
      <p>🛒 Giỏ hàng của bạn đang trống.</p>
      <a href="products.php" style="color:#4361ee;display:block;margin-top:12px">
        ← Tiếp tục mua sắm
      </a>
    </div>
  <?php else: ?>

  <table class="cart-table">
    <thead>
      <tr>
        <th>Ảnh</th>
        <th>Tên sản phẩm</th>
        <th>Đơn giá</th>
        <th>Số lượng</th>
        <th>Thành tiền</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="cart-body">
    <?php foreach ($cart as $key => $item):
          $subtotal = $item['selling_price'] * $item['qty'];
          $pid      = $item['product_id'];
    ?>
      <tr id="row-<?= $pid ?>">
        <td>
          <?php if ($item['image']): ?>
            <img src="<?= htmlspecialchars($item['image']) ?>"
                 alt="<?= htmlspecialchars($item['name']) ?>">
          <?php else: ?>
            <div style="width:60px;height:60px;background:#eee;border-radius:6px;
                        display:flex;align-items:center;justify-content:center">📱</div>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td><?= number_format($item['selling_price'], 0, ',', '.') ?>₫</td>
        <td>
          <input class="qty-input" type="number" min="1" value="<?= $item['qty'] ?>"
                 onchange="updateQty(<?= $pid ?>, this.value)"
                 id="qty-<?= $pid ?>">
        </td>
        <td id="sub-<?= $pid ?>" style="font-weight:600;color:#333">
          <?= number_format($subtotal, 0, ',', '.') ?>₫
        </td>
        <td>
          <button class="btn-remove" onclick="removeItem(<?= $pid ?>)"
                  title="Xoá">🗑</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="summary-box">
    <div>
      <p class="total-label">Tổng cộng:</p>
      <p class="total-price" id="cart-total">
        <?= number_format($total, 0, ',', '.') ?>₫
      </p>
    </div>
    <?php if ($user): ?>
      <a href="checkout.php" class="btn-checkout">Thanh toán →</a>
    <?php else: ?>
      <a href="login.php?ref=cart" class="btn-checkout">Đăng nhập để thanh toán</a>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</div><!-- /container -->

<script>
function updateQty(productId, qty) {
    qty = parseInt(qty);
    if (qty < 1) qty = 1;
    document.getElementById('qty-' + productId).value = qty;

    fetch(`cart.php?action=update&product_id=${productId}&qty=${qty}`)
        .then(r => r.json())
        .then(j => {
            if (j.success) {
                // Cập nhật thành tiền dòng này
                const priceText = document.querySelector(`#row-${productId} td:nth-child(3)`).textContent;
                const price = parseFloat(priceText.replace(/[^0-9]/g, ''));
                document.getElementById('sub-' + productId).textContent =
                    formatMoney(price * qty) + '₫';
                // Cập nhật tổng
                document.getElementById('cart-total').textContent =
                    formatMoney(j.total) + '₫';
            }
        });
}

function removeItem(productId) {
    if (!confirm('Bỏ sản phẩm này khỏi giỏ?')) return;
    fetch(`cart.php?action=remove&product_id=${productId}`)
        .then(r => r.json())
        .then(j => {
            if (j.success) {
                const row = document.getElementById('row-' + productId);
                row.remove();
                document.getElementById('cart-total').textContent =
                    formatMoney(j.total) + '₫';
                // Kiểm tra giỏ rỗng
                if (document.querySelectorAll('#cart-body tr').length === 0) {
                    location.reload();
                }
            }
        });
}

function formatMoney(n) {
    return Math.round(n).toLocaleString('vi-VN');
}
</script>
</body>
</html>
