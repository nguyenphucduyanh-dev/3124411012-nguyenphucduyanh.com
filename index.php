<?php
// ============================================================
//  admin/products/index.php  –  Danh sách & Xóa sản phẩm
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title  = 'Quản lý Sản phẩm';
$active_menu = 'products';
$pdo         = get_db();

// ── Xử lý xóa ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    try {
        // Kiểm tra có lịch sử nhập hàng không
        $has_import = (int)$pdo->prepare(
            "SELECT COUNT(*) FROM inventory_log
             WHERE product_id = ? AND change_type = 'import'"
        )->execute([$id]) ? $pdo->query(
            "SELECT COUNT(*) FROM inventory_log
             WHERE product_id = {$id} AND change_type = 'import'"
        )->fetchColumn() : 0;

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM inventory_log
             WHERE product_id = ? AND change_type = 'import'"
        );
        $stmt->execute([$id]);
        $has_import = (int)$stmt->fetchColumn();

        if ($has_import > 0) {
            // Có lịch sử → chỉ ẩn (soft delete)
            $pdo->prepare("UPDATE products SET status=0 WHERE id=?")->execute([$id]);
            $_SESSION['flash_success'] = 'Đã ẩn sản phẩm (có lịch sử nhập hàng, không thể xóa vĩnh viễn).';
        } else {
            // Chưa có lịch sử → xóa thật
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            $_SESSION['flash_success'] = 'Đã xóa sản phẩm thành công.';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = 'Lỗi: ' . $e->getMessage();
    }
    header('Location: /admin/products/index.php');
    exit;
}

// ── Phân trang & Tìm kiếm ────────────────────────────────────
$q          = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;
$offset     = ($page - 1) * $per_page;

$where  = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where   .= ' AND p.name LIKE ?';
    $params[] = "%{$q}%";
}
if ($cat_filter > 0) {
    $where   .= ' AND p.category_id = ?';
    $params[] = $cat_filter;
}

$total = (int)$pdo->prepare(
    "SELECT COUNT(*) FROM products p {$where}"
)->execute($params) ? $pdo->prepare(
    "SELECT COUNT(*) FROM products p {$where}"
)->execute($params) : 0;
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products p {$where}");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$pages = (int)ceil($total / $per_page);

$list_stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.import_price, p.profit_rate, p.stock_quantity,
            p.status, p.image, c.name AS category_name,
            (p.import_price * (1 + p.profit_rate)) AS selling_price
     FROM products p
     JOIN categories c ON c.id = p.category_id
     {$where}
     ORDER BY p.id DESC
     LIMIT {$per_page} OFFSET {$offset}"
);
$list_stmt->execute($params);
$products = $list_stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h5 class="mb-0 fw-700"><?= e($page_title) ?></h5>
    <small class="text-muted">Tổng: <?= $total ?> sản phẩm</small>
  </div>
  <a href="/admin/products/create.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>Thêm sản phẩm
  </a>
</div>

<!-- ── Bộ lọc ── -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-5">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Tìm tên sản phẩm..." value="<?= e($q) ?>">
      </div>
      <div class="col-md-3">
        <select name="cat" class="form-select form-select-sm">
          <option value="">— Tất cả danh mục —</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cat_filter==$c['id']?'selected':'' ?>>
            <?= e($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Tìm</button>
        <a href="/admin/products/index.php" class="btn btn-sm btn-outline-secondary ms-1">Xóa lọc</a>
      </div>
    </form>
  </div>
</div>

<!-- ── Bảng sản phẩm ── -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th width="50">ID</th>
          <th width="60">Ảnh</th>
          <th>Tên sản phẩm</th>
          <th>Danh mục</th>
          <th>Giá vốn</th>
          <th>LN (%)</th>
          <th>Giá bán</th>
          <th>Tồn kho</th>
          <th>Trạng thái</th>
          <th width="130">Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td class="text-muted mono"><?= $p['id'] ?></td>
          <td>
            <?php if ($p['image']): ?>
              <img src="/uploads/products/<?= e($p['image']) ?>"
                   style="width:40px;height:40px;object-fit:cover;border-radius:6px">
            <?php else: ?>
              <div style="width:40px;height:40px;background:#f1f5f9;border-radius:6px;
                          display:flex;align-items:center;justify-content:center;color:#94a3b8">
                <i class="bi bi-phone"></i>
              </div>
            <?php endif; ?>
          </td>
          <td class="fw-500"><?= e($p['name']) ?></td>
          <td class="text-muted" style="font-size:.82rem"><?= e($p['category_name']) ?></td>
          <td class="mono"><?= vnd((float)$p['import_price']) ?></td>
          <td><?= number_format($p['profit_rate']*100, 1) ?>%</td>
          <td class="mono fw-600 text-primary"><?= vnd((float)$p['selling_price']) ?></td>
          <td>
            <span class="badge <?= $p['stock_quantity'] <= 5 ? 'bg-danger' : 'bg-light text-dark border' ?>">
              <?= $p['stock_quantity'] ?>
            </span>
          </td>
          <td>
            <?php if ($p['status'] == 1): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle">Hiển thị</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Đã ẩn</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/admin/products/edit.php?id=<?= $p['id'] ?>"
               class="btn btn-sm btn-outline-primary py-0 px-2">
              <i class="bi bi-pencil"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 ms-1"
                    onclick="confirmDelete(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($products)): ?>
        <tr><td colspan="10" class="text-center text-muted py-5">Không tìm thấy sản phẩm nào.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Phân trang -->
  <?php if ($pages > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">
      Trang <?= $page ?> / <?= $pages ?> (<?= $total ?> sản phẩm)
    </small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?q=<?= urlencode($q) ?>&cat=<?= $cat_filter ?>&page=<?= $i ?>">
            <?= $i ?>
          </a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h6 class="modal-title">Xác nhận xóa</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-0" id="deleteModalBody"></div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <form method="POST" id="deleteForm">
          <input type="hidden" name="delete_id" id="deleteId">
          <button type="submit" class="btn btn-sm btn-danger">Xóa / Ẩn</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDelete(id, name) {
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteModalBody').innerHTML =
    `<p class="mb-0">Bạn muốn xóa sản phẩm <strong>${name}</strong>?</p>
     <small class="text-muted">Nếu đã có lịch sử nhập hàng, sản phẩm sẽ bị <em>ẩn</em> thay vì xóa.</small>`;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
