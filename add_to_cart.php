<?php
session_start();
if (!isset($_SESSION['user'])) {
    die("Bạn cần <a href='login.php'>đăng nhập</a> để mua hàng!");
}

$id = $_GET['id'];
// Khởi tạo giỏ hàng nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Nếu sp đã có thì tăng số lượng, chưa có thì set là 1
if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]++;
} else {
    $_SESSION['cart'][$id] = 1;
}

header("Location: cart.php");
