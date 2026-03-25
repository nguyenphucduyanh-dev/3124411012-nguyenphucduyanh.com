<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'phone_store');
mysqli_set_charset($conn, 'utf8');

// --- KIỂM TRA ĐĂNG NHẬP ADMIN ---
if (isset($_POST['admin_login'])) {
    if ($_POST['user'] == 'admin' && $_POST['pass'] == '123') { // Tài khoản cứng để demo
        $_SESSION['admin'] = 'Administrator';
    }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); }

if (!isset($_SESSION['admin'])): ?>
    <div style="width: 300px; margin: 100px auto; text-align: center; border: 1px solid #ccc; padding: 20px;">
        <h2>ADMIN LOGIN</h2>
        <form method="POST">
            <input type="text" name="user" placeholder="Admin User" required><br><br>
            <input type="password" name="pass" placeholder="Password" required><br><br>
            <button type="submit" name="admin_login">Đăng nhập hệ thống</button>
        </form>
    </div>
<?php exit; endif; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - Phone Store</title>
    <style>
        body { font-family: Arial; display: flex; margin: 0; }
        .sidebar { width: 250px; background: #2c3e50; color: white; min-height: 100vh; padding: 20px; }
        .sidebar a { color: white; display: block; padding: 10px; text-decoration: none; border-bottom: 1px solid #34495e; }
        .main { flex: 1; padding: 20px; background: #ecf0f1; }
        .card { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .status-pending { color: orange; } .status-shipped { color: green; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>Quản trị: <?= $_SESSION['admin'] ?></h3>
    <a href="admin.php?task=users">Quản lý Người dùng</a>
    <a href="admin.php?task=products">Quản lý Sản phẩm</a>
    <a href="admin.php?task=import">Nhập hàng (Phiếu nhập)</a>
    <a href="admin.php?task=orders">Quản lý Đơn hàng</a>
    <a href="admin.php?task=reports">Tồn kho & Báo cáo</a>
    <a href="admin.php?logout=1" style="color: #e74c3c;">Đăng xuất</a>
</div>

<div class="main">
    <?php
    $task = $_GET['task'] ?? 'dashboard';

    // --- 1. QUẢN LÝ SẢN PHẨM (THÊM/SỬA/XOÁ) ---
    if ($task == 'products'):
        // Xử lý Xoá/Ẩn
        if (isset($_GET['delete_id'])) {
            $id = $_GET['delete_id'];
            $check = $conn->query("SELECT id FROM import_details WHERE product_id=$id")->num_rows;
            if ($check > 0) {
                $conn->query("UPDATE products SET status='hide' WHERE id=$id"); // Có lịch sử nhập -> Ẩn
                echo "<script>alert('Sản phẩm đã có giao dịch, chuyển sang trạng thái ẨN');</script>";
            } else {
                $conn->query("DELETE FROM products WHERE id=$id"); // Chưa có -> Xoá hẳn
            }
        }
    ?>
        <div class="card">
            <h2>Danh sách sản phẩm</h2>
            <button onclick="document.getElementById('addForm').style.display='block'">+ Thêm sản phẩm mới</button>
            <table border="1" width="100%" style="margin-top:10px">
                <tr><th>ID</th><th>Tên</th><th>Loại</th><th>Giá vốn</th><th>Lợi nhuận</th><th>Giá bán</th><th>Trạng thái</th><th>Thao tác</th></tr>
                <?php
                $res = $conn->query("SELECT p.*, c.name as cat_name FROM products p JOIN categories c ON p.category_id=c.id");
                while($r = $res->fetch_assoc()):
                    $gia_ban = $r['gia_nhap'] * (1 + $r['loi_nhuan']);
                ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= $r['name'] ?></td>
                    <td><?= $r['cat_name'] ?></td>
                    <td><?= number_format($r['gia_nhap']) ?></td>
                    <td><?= $r['loi_nhuan']*100 ?>%</td>
                    <td><b><?= number_format($gia_ban) ?></b></td>
                    <td><?= $r['status'] == 'show' ? 'Đang bán' : 'Ẩn' ?></td>
                    <td>
                        <a href="#">Sửa</a> | 
                        <a href="admin.php?task=products&delete_id=<?= $r['id'] ?>" onclick="return confirm('Xoá/Ẩn sản phẩm này?')">Xoá</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    <?php 
    // --- 2. QUẢN LÝ NHẬP HÀNG ---
    elseif ($task == 'import'): 
        if (isset($_POST['create_import'])) {
            $conn->query("INSERT INTO import_orders (status) VALUES ('draft')");
            $import_id = $conn->insert_id;
        }
    ?>
        <div class="card">
            <h2>Quản lý Nhập hàng</h2>
            <form method="POST"><button name="create_import">Tạo phiếu nhập mới</button></form>
            
            <h3>Các phiếu nhập hiện có</h3>
            <table border="1" width="100%">
                <tr><th>Mã phiếu</th><th>Ngày tạo</th><th>Trạng thái</th><th>Thao tác</th></tr>
                <?php
                $imports = $conn->query("SELECT * FROM import_orders ORDER BY id DESC");
                while($im = $imports->fetch_assoc()):
                ?>
                <tr>
                    <td>#<?= $im['id'] ?></td>
                    <td><?= $im['created_at'] ?></td>
                    <td><?= $im['status'] ?></td>
                    <td>
                        <?php if($im['status'] == 'draft'): ?>
                            <a href="admin.php?task=import_detail&id=<?= $im['id'] ?>">Sửa/Thêm hàng</a>
                        <?php else: ?>
                            <a href="admin.php?task=import_detail&id=<?= $im['id'] ?>">Xem chi tiết</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    <?php 
    // --- 3. BÁO CÁO TỒN KHO & CẢNH BÁO ---
    elseif ($task == 'reports'): 
        $threshold = $_GET['threshold'] ?? 5;
    ?>
        <div class="card">
            <h2>Cảnh báo sắp hết hàng (Tồn < <?= $threshold ?>)</h2>
            <form>
                <input type="hidden" name="task" value="reports">
                Chỉ định mức cảnh báo: <input type="number" name="threshold" value="<?= $threshold ?>">
                <button>Cập nhật mức</button>
            </form>
            <table border="1" width="100%" style="margin-top:10px; color: red;">
                <tr><th>Tên sản phẩm</th><th>Số lượng tồn hiện tại</th></tr>
                <?php
                $low_stock = $conn->query("SELECT name, ton_kho FROM products WHERE ton_kho <= $threshold");
                while($ls = $low_stock->fetch_assoc()):
                ?>
                <tr><td><?= $ls['name'] ?></td><td><?= $ls['ton_kho'] ?></td></tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h2>Thống kê Nhập - Xuất</h2>
            <form method="GET">
                <input type="hidden" name="task" value="reports">
                Từ: <input type="date" name="from"> Đến: <input type="date" name="to">
                <button type="submit">Xem báo cáo</button>
            </form>
            <p><i>(Dữ liệu tổng hợp từ các phiếu nhập đã hoàn thành và đơn hàng đã giao thành công)</i></p>
        </div>

    <?php 
    // --- 4. QUẢN LÝ ĐƠN HÀNG (LỌC THEO PHƯỜNG / THỜI GIAN) ---
    elseif ($task == 'orders'): 
        $status_filter = $_GET['st'] ?? '';
        $sql = "SELECT * FROM orders WHERE 1=1";
        if($status_filter) $sql .= " AND status='$status_filter'";
        $sql .= " ORDER BY ward ASC"; // Sắp xếp theo phường
    ?>
        <div class="card">
            <h2>Quản lý đơn hàng</h2>
            <div style="margin-bottom: 10px;">
                Lọc trạng thái: 
                <a href="admin.php?task=orders">Tất cả</a> | 
                <a href="admin.php?task=orders&st=pending">Chưa xử lý</a> | 
                <a href="admin.php?task=orders&st=shipped">Đã giao</a>
            </div>
            <table border="1" width="100%">
                <tr><th>Mã ĐH</th><th>Thời gian</th><th>Phường (Địa chỉ)</th><th>Tổng tiền</th><th>Trạng thái</th><th>Chi tiết</th></tr>
                <?php
                $orders = $conn->query($sql);
                while($o = $orders->fetch_assoc()):
                ?>
                <tr>
                    <td>#<?= $o['id'] ?></td>
                    <td><?= $o['created_at'] ?></td>
                    <td><?= $o['ward'] ?></td>
                    <td><?= number_format($o['total_amount']) ?>đ</td>
                    <td class="status-<?= $o['status'] ?>"><?= $o['status'] ?></td>
                    <td><a href="#">Xem chi tiết</a></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
