<?php
require 'db.php';
$id = $_GET['id'];

// 1. Kiểm tra xem sản phẩm đã từng được nhập hàng chưa
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM import_details WHERE product_id = ?");
$checkStmt->execute([$id]);
$has_history = $checkStmt->fetchColumn() > 0;

if ($has_history) {
    // Nếu có lịch sử -> Đánh dấu ẩn (Xóa mềm)
    $stmt = $pdo->prepare("UPDATE products SET is_deleted = 1, status = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "Sản phẩm đã có lịch sử nhập hàng nên chỉ được ẩn đi.";
} else {
    // Nếu chưa có lịch sử -> Xóa hẳn
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "Đã xóa hoàn toàn sản phẩm khỏi hệ thống.";
}

header("Location: product_list.php?msg=" . urlencode($msg));
