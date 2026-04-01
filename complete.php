<?php
// ============================================================
//  admin/purchase_orders/complete.php
//  Hoàn thành phiếu nhập → cập nhật tồn kho + giá vốn bình quân
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo   = get_db();
$po_id = (int)($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: /admin/purchase_orders/index.php'); exit;
}

// Lấy phiếu nhập và kiểm tra trạng thái
$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id=? AND status='draft'");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

if (!$po) {
    $_SESSION['flash_error'] = 'Phiếu không tồn tại hoặc không ở trạng thái Nháp.';
    header('Location: /admin/purchase_orders/index.php'); exit;
}

// Lấy chi tiết phiếu
$details_stmt = $pdo->prepare(
    "SELECT * FROM purchase_order_details WHERE purchase_order_id = ?"
);
$details_stmt->execute([$po_id]);
$details = $details_stmt->fetchAll();

if (empty($details)) {
    $_SESSION['flash_error'] = 'Phiếu không có sản phẩm nào.';
    header("Location: /admin/purchase_orders/create.php?id={$po_id}"); exit;
}

try {
    $pdo->beginTransaction();

    // Duyệt từng dòng chi tiết → cập nhật tồn kho + giá vốn bình quân
    foreach ($details as $d) {
        process_stock_import(
            $pdo,
            (int)  $d['product_id'],
            (int)  $d['quantity'],
            (float)$d['import_price'],
            $po_id
        );
    }

    // Cập nhật trạng thái phiếu → completed
    $pdo->prepare(
        "UPDATE purchase_orders
         SET status='completed', completed_at=NOW()
         WHERE id=?"
    )->execute([$po_id]);

    $pdo->commit();
    $_SESSION['flash_success'] =
        "Phiếu {$po['code']} đã hoàn thành. Tồn kho và giá vốn đã được cập nhật.";
    header('Location: /admin/purchase_orders/index.php'); exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Lỗi khi hoàn thành phiếu: ' . $e->getMessage();
    header("Location: /admin/purchase_orders/create.php?id={$po_id}"); exit;
}
