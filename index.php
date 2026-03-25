<?php
session_start();
$host = 'localhost';
$user = 'root';
$pass = ''; // Mật khẩu mặc định thường để trống
$db   = 'phone_store';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);
mysqli_set_charset($conn, 'utf8');

// --- LOGIC NGHIỆP VỤ ---

// 1. Công thức giá nhập bình quân
// $P_{new} = \frac{(Q_{old} \times P_{old}) + (Q_{import} \times P_{import})}{Q_{old} + Q_{import}}$
function updateStock($conn, $id, $qty, $price) {
    $res = $conn->query("SELECT gia_nhap, ton_kho FROM products WHERE id = $id");
    $p = $res->fetch_assoc();
    $new_price = (($p['ton_kho'] * $p['gia_nhap']) + ($qty * $price)) / ($p['ton_kho'] + $qty);
    $new_qty = $p['ton_kho'] + $qty;
    $conn->query("UPDATE products SET gia_nhap = $new_price, ton_kho = $new_qty WHERE id = $id");
}

// 2. Xử lý Đăng nhập / Đăng ký / Giỏ hàng
$action = $_GET['action'] ?? '';

if ($action == 'login' && $_POST) {
    $u = $_POST['user']; $p = $_POST['pass'];
    $res = $conn->query("SELECT * FROM users WHERE username='$u' AND password='$p'");
    if ($user_data = $res->fetch_assoc()) $_SESSION['user'] = $user_data;
}

if ($action == 'logout') session_destroy(); header("Location: index.php");

if ($action == 'add_cart' && isset($_SESSION['user'])) {
    $id = $_GET['id'];
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
    header("Location: index.php?view=cart");
}

// --- GIAO DIỆN ---
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phone Store - Dynamic Website</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background: #f4f4f4; }
        .nav { background: #333; color: #fff; padding: 10px; margin-bottom: 20px; }
        .nav a { color: #fff; margin-right: 15px; text-decoration: none; }
        .product-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .price { color: #e74c3c; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    </style>
</head>
<body>

<div class="nav">
    <a href="index.php">Trang chủ</a>
    <?php if(isset($_SESSION['user'])): ?>
        Chào, <b><?= $_SESSION['user']['fullname'] ?></b> | 
        <a href="index.php?view=cart">Giỏ hàng (<?= count($_SESSION['cart'] ?? []) ?>)</a> |
        <a href="index.php?view=history">Lịch sử</a> |
        <a href="index.php?action=logout">Đăng xuất</a>
    <?php else: ?>
        <a href="index.php?view=login">Đăng nhập / Đăng ký</a>
    <?php endif; ?>
</div>

<?php
$view = $_GET['view'] ?? 'home';

// --- VIEW: TRANG CHỦ & TÌM KIẾM ---
if ($view == 'home'): 
    $search = $_GET['search'] ?? '';
    $cat = $_GET['cat'] ?? '';
    $min = $_GET['min'] ?? 0;
    $max = $_GET['max'] ?? 999999999;
    
    // Câu lệnh SQL tìm kiếm nâng cao
    $where = "WHERE 1=1";
    if($search) $where .= " AND name LIKE '%$search%'";
    if($cat) $where .= " AND category_id = $cat";
    
    // Phân trang
    $limit = 6;
    $page = $_GET['page'] ?? 1;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT *, (gia_nhap * (1 + loi_nhuan)) as gia_ban FROM products $where 
            HAVING gia_ban BETWEEN $min AND $max LIMIT $limit OFFSET $offset";
    $products = $conn->query($sql);
?>
    <form method="GET" style="margin-bottom: 20px; background: #eee; padding: 15px;">
        <input type="text" name="search" placeholder="Tên sản phẩm..." value="<?= $search ?>">
        <select name="cat">
            <option value="">-- Tất cả danh mục --</option>
            <option value="1">iPhone</option>
            <option value="2">iPad</option>
        </select>
        <input type="number" name="min" placeholder="Giá từ" value="<?= $min ?>">
        <input type="number" name="max" placeholder="đến" value="<?= $max ?>">
        <button type="submit">Tìm nâng cao</button>
    </form>

    <div class="product-grid">
        <?php while($row = $products->fetch_assoc()): ?>
            <div class="card">
                <h3><?= $row['name'] ?></h3>
                <p class="price">Giá: <?= number_format($row['gia_ban']) ?>đ</p>
                <p>Tồn kho: <?= $row['ton_kho'] ?></p>
                <a href="index.php?view=detail&id=<?= $row['id'] ?>">Xem chi tiết</a> |
                <a href="index.php?action=add_cart&id=<?= $row['id'] ?>">Thêm vào giỏ</a>
            </div>
        <?php endwhile; ?>
    </div>

<?php 
// --- VIEW: GIỎ HÀNG ---
elseif ($view == 'cart'): 
    if(!isset($_SESSION['user'])) die("Vui lòng đăng nhập!");
?>
    <h2>Giỏ hàng của bạn</h2>
    <table>
        <tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr>
        <?php 
        $total = 0;
        foreach(($_SESSION['cart'] ?? []) as $id => $qty): 
            $p = $conn->query("SELECT name, (gia_nhap * (1 + loi_nhuan)) as gia_ban FROM products WHERE id=$id")->fetch_assoc();
            $sub = $p['gia_ban'] * $qty;
            $total += $sub;
        ?>
        <tr>
            <td><?= $p['name'] ?></td>
            <td><?= number_format($p['gia_ban']) ?>đ</td>
            <td><?= $qty ?></td>
            <td><?= number_format($sub) ?>đ</td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="3"><b>Tổng cộng</b></td><td><b><?= number_format($total) ?>đ</b></td></tr>
    </table>
    
    <div style="margin-top: 20px; background: #fff; padding: 20px;">
        <h3>Thông tin thanh toán</h3>
        <form method="POST" action="index.php?action=checkout">
            <p>Địa chỉ nhận hàng: <br>
               <input type="radio" name="addr_type" value="default" checked> Dùng địa chỉ mặc định: <?= $_SESSION['user']['address'] ?><br>
               <input type="radio" name="addr_type" value="new"> Nhập địa chỉ mới: <input type="text" name="new_addr">
            </p>
            <p>Phương thức: 
                <select name="pay_method" id="pay_method" onchange="checkOnline(this.value)">
                    <option value="Tiền mặt">Tiền mặt</option>
                    <option value="Chuyển khoản">Chuyển khoản (STK: 0123456789)</option>
                    <option value="Trực tuyến">Trực tuyến (Momo/ZaloPay)</option>
                </select>
            </p>
            <button type="submit">Xác nhận đặt hàng</button>
        </form>
    </div>

<?php 
// --- VIEW: LỊCH SỬ ---
elseif ($view == 'history'): 
    $uid = $_SESSION['user']['id'];
    $orders = $conn->query("SELECT * FROM orders WHERE user_id=$uid ORDER BY created_at DESC");
?>
    <h2>Lịch sử mua hàng</h2>
    <table>
        <tr><th>Mã ĐH</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Thanh toán</th><th>Địa chỉ</th></tr>
        <?php while($o = $orders->fetch_assoc()): ?>
        <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= $o['created_at'] ?></td>
            <td><?= number_format($o['total_amount']) ?>đ</td>
            <td><?= $o['payment_method'] ?></td>
            <td><?= $o['address'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

<?php elseif ($view == 'login'): ?>
    <form method="POST" action="index.php?action=login">
        <h2>Đăng nhập</h2>
        User: <input type="text" name="user" required><br><br>
        Pass: <input type="password" name="pass" required><br><br>
        <button type="submit">Vào hệ thống</button>
    </form>
<?php endif; ?>

<script>
function checkOnline(val) {
    if(val === 'Trực tuyến') {
        alert("Chức năng thanh toán trực tuyến đang được bảo trì!");
    }
}
</script>
</body>
</html>
