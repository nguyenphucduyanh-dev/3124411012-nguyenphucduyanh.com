<?php
// ============================================================
//  admin/products/edit.php  –  Sửa sản phẩm (dùng chung Create)
//  Nếu ?id= thì sửa, không có thì tạo mới
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo        = get_db();
$is_edit    = isset($_GET['id']) || isset($_POST['id']);
$product_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors     = [];

// Tải dữ liệu cũ nếu sửa
$product = null;
if ($is_edit && $product_id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if (!$product) {
        $_SESSION['flash_error'] = 'Không tìm thấy sản phẩm.';
        header('Location: /admin/products/index.php'); exit;
    }
}

$page_title  = $is_edit ? 'Sửa sản phẩm' : 'Thêm sản phẩm mới';
$active_menu = 'products';

// ── Xử lý POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $profit_rate = (float)str_replace('%', '', $_POST['profit_rate'] ?? '15') / 100;
    $status      = (int)($_POST['status'] ?? 1);
    $slug_base   = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));

    if ($name === '')       $errors[] = 'Tên sản phẩm không được để trống.';
    if ($category_id <= 0) $errors[] = 'Vui lòng chọn danh mục.';
    if ($profit_rate < 0)  $errors[] = 'Tỷ lệ lợi nhuận không hợp lệ.';

    // Upload ảnh
    $image_name = $product['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Định dạng ảnh không hợp lệ (jpg/jpeg/png/webp).';
        } elseif ($_FILES['image']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Ảnh không được vượt quá 3MB.';
        } else {
            $image_name = uniqid('img_', true) . '.' . $ext;
            $upload_dir = __DIR__ . '/../../uploads/products/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
                $errors[] = 'Upload ảnh thất bại.';
                $image_name = $product['image'] ?? null;
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $pdo->prepare(
                    "UPDATE products
                     SET name=?, category_id=?, description=?, profit_rate=?,
                         image=?, status=?, updated_at=NOW()
                     WHERE id=?"
                )->execute([$name, $category_id, $description, $profit_rate,
                            $image_name, $status, $product_id]);
                $_SESSION['flash_success'] = "Cập nhật sản phẩm thành công.";
            } else {
                // Tạo slug duy nhất
                $slug = $slug_base;
                $i    = 1;
                while ((int)$pdo->prepare("SELECT COUNT(*) FROM products WHERE slug=?")
                                ->execute([$slug]) &&
                       (int)$pdo->query("SELECT COUNT(*) FROM products WHERE slug='{$slug}'")->fetchColumn() > 0) {
                    $slug = $slug_base . '-' . $i++;
                }
                // Kiểm tra slug trùng đúng cách
                $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug=?");
                $chk->execute([$slug]);
                while ((int)$chk->fetchColumn() > 0) {
                    $slug = $slug_base . '-' . (++$i);
                    $chk->execute([$slug]);
                }

                $pdo->prepare(
                    "INSERT INTO products
                     (category_id, name, slug, description, import_price, profit_rate,
                      stock_quantity, image, status)
                     VALUES (?,?,?,?,0,?,0,?,?)"
                )->execute([$category_id, $name, $slug, $description,
                            $profit_rate, $image_name, $status]);
                $_SESSION['flash_success'] = "Thêm sản phẩm thành công.";
            }
            header('Location: /admin/products/index.php'); exit;
        } catch (PDOException $e) {
            $errors[] = 'Lỗi CSDL: ' . $e->getMessage();
        }
    }
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/admin/products/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-700"><?= e($page_title) ?></h5>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <?php if ($is_edit): ?>
    <input type="hidden" name="id" value="<?= $product_id ?>">
  <?php endif; ?>

  <div class="row g-3">
    <!-- ── Cột trái ── -->
    <div class="col-lg-8">
      <div class="card p-4">
        <div class="mb-3">
          <label class="form-label fw-500">Tên sản phẩm <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control"
                 value="<?= e($product['name'] ?? $_POST['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-500">Mô tả</label>
          <textarea name="description" class="form-control" rows="5"
          ><?= e($product['description'] ?? $_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-500">Danh mục <span class="text-danger">*</span></label>
            <select name="category_id" class="form-select" required>
              <option value="">— Chọn danh mục —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"
                <?= ($product['category_id'] ?? $_POST['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-500">
              Tỷ lệ lợi nhuận (%)
              <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip"
                 title="Giá bán = Giá vốn × (1 + tỷ lệ LN)"></i>
            </label>
            <div class="input-group">
              <input type="number" name="profit_rate" id="profitRate" class="form-control"
                     step="0.1" min="0" max="500"
                     value="<?= number_format(($product['profit_rate'] ?? 0.15) * 100, 1) ?>">
              <span class="input-group-text">%</span>
            </div>
            <small class="text-muted">
              Giá bán dự kiến:
              <strong class="text-primary" id="sellingPreview">—</strong>
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Cột phải ── -->
    <div class="col-lg-4">
      <!-- Ảnh sản phẩm -->
      <div class="card p-4 mb-3">
        <label class="form-label fw-500">Hình ảnh sản phẩm</label>
        <div id="imagePreviewWrap" class="mb-2 text-center">
          <?php if (!empty($product['image'])): ?>
            <img id="imagePreview" src="/uploads/products/<?= e($product['image']) ?>"
                 class="img-fluid rounded" style="max-height:180px">
          <?php else: ?>
            <div id="imagePreview" style="width:100%;height:140px;background:#f8fafc;
                 border:2px dashed #e2e8f0;border-radius:8px;display:flex;
                 align-items:center;justify-content:center;color:#94a3b8">
              <i class="bi bi-image fs-2"></i>
            </div>
          <?php endif; ?>
        </div>
        <input type="file" name="image" id="imageInput"
               class="form-control form-control-sm" accept="image/*">
        <small class="text-muted">JPG/PNG/WebP, tối đa 3MB</small>
      </div>

      <!-- Trạng thái & Thông tin giá vốn -->
      <div class="card p-4">
        <div class="mb-3">
          <label class="form-label fw-500">Trạng thái</label>
          <select name="status" class="form-select form-select-sm">
            <option value="1" <?= ($product['status'] ?? 1) == 1 ? 'selected' : '' ?>>Hiển thị</option>
            <option value="0" <?= ($product['status'] ?? 1) == 0 ? 'selected' : '' ?>>Ẩn</option>
          </select>
        </div>
        <?php if ($is_edit): ?>
        <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
          <div class="d-flex justify-content-between mb-1" style="font-size:.82rem">
            <span class="text-muted">Giá vốn hiện tại:</span>
            <strong class="mono"><?= vnd((float)($product['import_price'] ?? 0)) ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-1" style="font-size:.82rem">
            <span class="text-muted">Tồn kho:</span>
            <strong><?= (int)($product['stock_quantity'] ?? 0) ?> máy</strong>
          </div>
          <small class="text-muted">* Giá vốn cập nhật qua phiếu nhập hàng</small>
        </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-3">
        <i class="bi bi-check-lg me-1"></i>
        <?= $is_edit ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm' ?>
      </button>
    </div>
  </div>
</form>

<script>
// Preview ảnh khi chọn file
document.getElementById('imageInput').addEventListener('change', function () {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const wrap = document.getElementById('imagePreviewWrap');
    wrap.innerHTML = `<img id="imagePreview" src="${e.target.result}"
                      class="img-fluid rounded" style="max-height:180px">`;
  };
  reader.readAsDataURL(file);
});

// Tính giá bán preview theo profit_rate
const importPrice = <?= (float)($product['import_price'] ?? 0) ?>;
function updatePreview() {
  const rate  = parseFloat(document.getElementById('profitRate').value) / 100 || 0;
  const price = Math.round(importPrice * (1 + rate));
  document.getElementById('sellingPreview').textContent =
    price > 0 ? price.toLocaleString('vi-VN') + ' ₫' : '(nhập hàng trước)';
}
document.getElementById('profitRate').addEventListener('input', updatePreview);
updatePreview();

// Tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
  new bootstrap.Tooltip(el);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
