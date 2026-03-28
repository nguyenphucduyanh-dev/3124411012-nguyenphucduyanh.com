<?php
session_start();

// Kiểm tra nếu chưa đăng nhập hoặc không có session admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
