<?php
/**
 * search.php - Tìm kiếm sản phẩm (AJAX + phân trang)
 * Tác giả: nguyenphucduyanh-dev
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/function.php';

cartInit();

const PER_PAGE = 12;

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// --- Đọc tham số ---
$keyword     = trim($_GET['keyword']    ?? '');
$category_id = (int) ($_GET['category'] ?? 0);
$price_min   = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : null;
$price_max   = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : null;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * PER_PAGE;

// --- Build WHERE ---
$where  = ["p.is_active = 1"];
$params = [];

if ($keyword !== '') {
    $where[]  = "p.name LIKE :keyword";
    $params[':keyword'] = '%' . $keyword . '%';
}
if ($category_id > 0) {
    $where[]  = "p.category_id = :cat";
    $params[':cat'] = $category_id;
}
// Khoảng giá so sánh với selling_price = import_price * (1 + profit_rate)
if ($price_min !== null) {
    $where[]  = "(p.import_price * (1 + p.profit_rate)) >= :pmin";
    $params[':pmin'] = $price_min;
}
if ($price_max !== null) {
    $where[]  = "(p.import_price * (1 + p.profit_rate)) <= :pmax";
    $params[':pmax'] = $price_max;
}

$whereSQL = implode(' AND ', $where);

$pdo = getDBConnection();

// --- Đếm tổng ---
$count_sql  = "SELECT COUNT(*) FROM products p WHERE $whereSQL";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total      = (int) $count_stmt->fetchColumn();
$total_pages = (int) ceil($total / PER_PAGE);

// --- Lấy dữ liệu ---
$sql  = "SELECT p.id, p.name, p.slug, p.image,
                p.import_price, p.profit_rate, p.stock_quantity,
                (p.import_price * (1 + p.profit_rate)) AS selling_price,
                c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE $whereSQL
         ORDER BY p.id DESC
         LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// --- Lấy danh mục cho filter ---
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// ====== AJAX: trả về JSON ======
if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total'       => $total,
        'total_pages' => $total_pages,
        'current_page'=> $page,
        'products'    => $products,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tìm kiếm sản phẩm</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
    .product-card img { height:200px; object-fit:contain; }
    .pagination .page-item.active .page-link { background:#0d6efd; border-color:#0d6efd; }
    #loading { display:none; text-align:center; padding:40px; }
</style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4">🔍 Tìm kiếm điện thoại</h2>

    <!-- Form tìm kiếm -->
    <form id="searchForm" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="keyword" id="keyword" class="form-control"
                   placeholder="Tên sản phẩm..." value="<?= e($keyword) ?>">
        </div>
        <div class="col-md-2">
            <select name="category" id="category" class="form-select">
                <option value="">-- Tất cả danh mục --</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"
                    <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" name="price_min" id="price_min" class="form-control"
                   placeholder="Giá từ (đ)"
                   value="<?= $price_min !== null ? (int)$price_min : '' ?>">
        </div>
        <div class="col-md-2">
            <input type="number" name="price_max" id="price_max" class="form-control"
                   placeholder="Giá đến (đ)"
                   value="<?= $price_max !== null ? (int)$price_max : '' ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
        </div>
    </form>

    <!-- Kết quả -->
    <div id="resultInfo" class="text-muted mb-3">
        Tìm thấy <strong><?= $total ?></strong> sản phẩm
    </div>

    <div id="loading"><div class="spinner-border text-primary"></div></div>

    <div id="productGrid" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($products as $p): ?>
        <?= renderProductCard($p) ?>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <nav id="paginationWrap" class="mt-4 d-flex justify-content-center">
        <?= renderPagination($page, $total_pages) ?>
    </nav>
</div>

<script>
let currentPage = <?= $page ?>;

const form = document.getElementById('searchForm');
const grid = document.getElementById('productGrid');
const info = document.getElementById('resultInfo');
const loading = document.getElementById('loading');
const paginationWrap = document.getElementById('paginationWrap');

function buildParams(page) {
    const data = new FormData(form);
    data.set('page', page);
    return new URLSearchParams(data).toString();
}

function doSearch(page) {
    currentPage = page;
    loading.style.display = 'block';
    grid.style.opacity = '0.4';

    fetch('search.php?' + buildParams(page), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        loading.style.display = 'none';
        grid.style.opacity = '1';
        info.innerHTML = `Tìm thấy <strong>${data.total}</strong> sản phẩm`;
        grid.innerHTML = data.products.map(renderCard).join('');
        paginationWrap.innerHTML = renderPagination(data.current_page, data.total_pages);
        // Cập nhật URL không reload
        history.replaceState(null, '', 'search.php?' + buildParams(page));
    })
    .catch(() => {
        loading.style.display = 'none';
        grid.style.opacity = '1';
    });
}

function renderCard(p) {
    const price = new Intl.NumberFormat('vi-VN').format(Math.round(p.selling_price));
    const img   = p.image ? p.image : 'assets/images/no-image.png';
    return `
    <div class="col">
      <div class="card h-100 shadow-sm">
        <img src="${img}" class="card-img-top p-2" alt="${p.name}" style="height:200px;object-fit:contain">
        <div class="card-body d-flex flex-column">
          <span class="badge bg-secondary mb-1">${p.category_name}</span>
          <h6 class="card-title flex-grow-1">${p.name}</h6>
          <p class="fw-bold text-danger mb-1">${price} đ</p>
          <p class="text-muted small mb-2">Còn: ${p.stock_quantity} máy</p>
          <div class="d-flex gap-2">
            <a href="pages/product_detail.php?id=${p.id}" class="btn btn-outline-primary btn-sm flex-grow-1">Chi tiết</a>
            <button onclick="addToCart(${p.id})" class="btn btn-danger btn-sm flex-grow-1">🛒 Mua</button>
          </div>
        </div>
      </div>
    </div>`;
}

function renderPagination(current, total) {
    if (total <= 1) return '';
    let html = '<ul class="pagination">';
    if (current > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="doSearch(${current-1});return false;">«</a></li>`;
    }
    const start = Math.max(1, current - 2);
    const end   = Math.min(total, current + 2);
    for (let i = start; i <= end; i++) {
        html += `<li class="page-item ${i===current?'active':''}">
                   <a class="page-link" href="#" onclick="doSearch(${i});return false;">${i}</a>
                 </li>`;
    }
    if (current < total) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="doSearch(${current+1});return false;">»</a></li>`;
    }
    html += '</ul>';
    return html;
}

function addToCart(productId) {
    fetch('cart.php?action=add&product_id=' + productId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✅ ' + d.message);
        } else {
            if (d.message.includes('đăng nhập')) {
                window.location.href = 'login.php';
            } else {
                alert('❌ ' + d.message);
            }
        }
    });
}

form.addEventListener('submit', function(e) {
    e.preventDefault();
    doSearch(1);
});

// Debounce tìm kiếm khi gõ
let debounceTimer;
document.getElementById('keyword').addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => doSearch(1), 500);
});
</script>
</body>
</html>
<?php
// ---- Hàm render PHP (dùng cho SSR lần đầu) ----
function renderProductCard(array $p): string
{
    $price = number_format($p['selling_price'], 0, ',', '.');
    $img   = $p['image'] ?: 'assets/images/no-image.png';
    return <<<HTML
    <div class="col">
      <div class="card h-100 shadow-sm">
        <img src="{$img}" class="card-img-top p-2" alt="{$p['name']}" style="height:200px;object-fit:contain">
        <div class="card-body d-flex flex-column">
          <span class="badge bg-secondary mb-1">{$p['category_name']}</span>
          <h6 class="card-title flex-grow-1">{$p['name']}</h6>
          <p class="fw-bold text-danger mb-1">{$price} đ</p>
          <p class="text-muted small mb-2">Còn: {$p['stock_quantity']} máy</p>
          <div class="d-flex gap-2">
            <a href="pages/product_detail.php?id={$p['id']}" class="btn btn-outline-primary btn-sm flex-grow-1">Chi tiết</a>
            <button onclick="addToCart({$p['id']})" class="btn btn-danger btn-sm flex-grow-1">🛒 Mua</button>
          </div>
        </div>
      </div>
    </div>
HTML;
}

function renderPagination(int $current, int $total_pages): string
{
    if ($total_pages <= 1) return '';
    $html = '<ul class="pagination">';
    if ($current > 1) {
        $html .= "<li class='page-item'><a class='page-link' href='?page=" . ($current-1) . "'>«</a></li>";
    }
    $start = max(1, $current - 2);
    $end   = min($total_pages, $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current ? 'active' : '';
        $html  .= "<li class='page-item $active'><a class='page-link' href='?page=$i'>$i</a></li>";
    }
    if ($current < $total_pages) {
        $html .= "<li class='page-item'><a class='page-link' href='?page=" . ($current+1) . "'>»</a></li>";
    }
    $html .= '</ul>';
    return $html;
}
