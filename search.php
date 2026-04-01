<?php
// search.php
// Tìm kiếm sản phẩm qua AJAX — trả về JSON
// Hỗ trợ: tên, danh mục, khoảng giá bán, phân trang 12 SP/trang

require_once __DIR__ . '/config/db.php';

// Chỉ chấp nhận GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Đọc & làm sạch tham số ────────────────────────────────────────────────
$keyword    = trim($_GET['keyword']    ?? '');
$categoryId = isset($_GET['category_id']) && ctype_digit($_GET['category_id'])
              ? (int) $_GET['category_id'] : null;
$minPrice   = isset($_GET['min_price']) && is_numeric($_GET['min_price'])
              ? (float) $_GET['min_price'] : null;
$maxPrice   = isset($_GET['max_price']) && is_numeric($_GET['max_price'])
              ? (float) $_GET['max_price'] : null;
$page       = isset($_GET['page']) && ctype_digit($_GET['page']) && (int)$_GET['page'] >= 1
              ? (int) $_GET['page'] : 1;

define('PER_PAGE', 12);
$offset = ($page - 1) * PER_PAGE;

// ── Xây dựng WHERE động ──────────────────────────────────────────────────
$where  = ['p.is_active = 1'];
$params = [];

if ($keyword !== '') {
    $where[]            = 'p.name LIKE :keyword';
    $params[':keyword'] = '%' . $keyword . '%';
}
if ($categoryId !== null) {
    $where[]              = 'p.category_id = :cat_id';
    $params[':cat_id']    = $categoryId;
}
// Khoảng giá so sánh với selling_price (generated column)
if ($minPrice !== null) {
    $where[]              = 'p.selling_price >= :min_price';
    $params[':min_price'] = $minPrice;
}
if ($maxPrice !== null) {
    $where[]              = 'p.selling_price <= :max_price';
    $params[':max_price'] = $maxPrice;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

try {
    $pdo = getDB();

    // ── Đếm tổng bản ghi (cho phân trang) ────────────────────────────────
    $countSql  = "SELECT COUNT(*) AS total
                    FROM products p
                   {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total     = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / PER_PAGE);

    // ── Truy vấn dữ liệu trang hiện tại ──────────────────────────────────
    $dataSql = "SELECT p.id,
                       p.name,
                       p.slug,
                       p.image,
                       p.import_price,
                       p.profit_rate,
                       p.selling_price,
                       p.stock_quantity,
                       c.id   AS category_id,
                       c.name AS category_name
                  FROM products  p
                  JOIN categories c ON c.id = p.category_id
                {$whereSql}
                 ORDER BY p.created_at DESC
                 LIMIT :limit OFFSET :offset";

    $dataStmt = $pdo->prepare($dataSql);
    // Bind tham số WHERE
    foreach ($params as $key => $val) {
        $dataStmt->bindValue($key, $val);
    }
    // Bind LIMIT / OFFSET riêng để tránh lỗi kiểu dữ liệu
    $dataStmt->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $dataStmt->execute();
    $products = $dataStmt->fetchAll();

    echo json_encode([
        'success'     => true,
        'data'        => $products,
        'pagination'  => [
            'current_page' => $page,
            'per_page'     => PER_PAGE,
            'total_items'  => $total,
            'total_pages'  => $totalPages,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[search.php Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống.']);
}
