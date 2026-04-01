<?php
// ============================================================
//  admin/includes/db.php  –  Kết nối CSDL + Helper functions
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'phoneshop');
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ============================================================
//  Tính giá bán từ giá nhập + tỷ lệ lợi nhuận
// ============================================================
function get_selling_price(float $import_price, float $profit_rate): float {
    return round($import_price * (1 + $profit_rate));
}

// ============================================================
//  Cập nhật giá nhập bình quân khi nhập hàng mới
//  Trả về: ['new_price' => float, 'new_stock' => int]
// ============================================================
function calculate_avg_import_price(
    float $current_price,
    int   $current_stock,
    float $new_price,
    int   $new_quantity
): array {
    if ($current_stock <= 0) {
        // Lần nhập đầu tiên hoặc tồn = 0 → dùng giá nhập mới
        return [
            'new_price' => $new_price,
            'new_stock' => $new_quantity,
        ];
    }
    $total_stock = $current_stock + $new_quantity;
    $avg_price   = ($current_stock * $current_price + $new_quantity * $new_price) / $total_stock;
    return [
        'new_price' => round($avg_price, 2),
        'new_stock' => $total_stock,
    ];
}

// ============================================================
//  Xử lý nhập kho: cập nhật products + ghi inventory_log
//  $pdo phải đang trong transaction khi gọi hàm này
// ============================================================
function process_stock_import(
    PDO   $pdo,
    int   $product_id,
    int   $quantity,
    float $import_price,
    int   $purchase_order_id
): void {
    // Lấy thông tin hiện tại (lock row để tránh race condition)
    $stmt = $pdo->prepare(
        'SELECT import_price, stock_quantity FROM products WHERE id = ? FOR UPDATE'
    );
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new RuntimeException("Sản phẩm ID={$product_id} không tồn tại.");
    }

    $result = calculate_avg_import_price(
        (float) $product['import_price'],
        (int)   $product['stock_quantity'],
        $import_price,
        $quantity
    );

    // Cập nhật products
    $pdo->prepare(
        'UPDATE products
         SET import_price   = ?,
             stock_quantity = ?,
             updated_at     = NOW()
         WHERE id = ?'
    )->execute([$result['new_price'], $result['new_stock'], $product_id]);

    // Ghi inventory_log
    $pdo->prepare(
        'INSERT INTO inventory_log
            (product_id, change_type, quantity_change, import_price, avg_price_after,
             stock_after, reference_type, reference_id)
         VALUES (?, "import", ?, ?, ?, ?, "purchase_order", ?)'
    )->execute([
        $product_id,
        $quantity,
        $import_price,
        $result['new_price'],
        $result['new_stock'],
        $purchase_order_id,
    ]);
}

// ============================================================
//  Format tiền VNĐ
// ============================================================
function vnd(float $amount): string {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// ============================================================
//  Escape HTML output
// ============================================================
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ============================================================
//  Tạo mã phiếu nhập: PN-20240115-0001
// ============================================================
function generate_po_code(PDO $pdo): string {
    $date  = date('Ymd');
    $stmt  = $pdo->prepare(
        "SELECT COUNT(*)+1 AS seq FROM purchase_orders WHERE code LIKE ?"
    );
    $stmt->execute(["PN-{$date}-%"]);
    $seq = (int) $stmt->fetchColumn();
    return sprintf('PN-%s-%04d', $date, $seq);
}

// ============================================================
//  Tạo mã đơn hàng
// ============================================================
function generate_order_code(PDO $pdo): string {
    $date = date('Ymd');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)+1 AS seq FROM orders WHERE order_code LIKE ?"
    );
    $stmt->execute(["DH-{$date}-%"]);
    $seq = (int) $stmt->fetchColumn();
    return sprintf('DH-%s-%04d', $date, $seq);
}
