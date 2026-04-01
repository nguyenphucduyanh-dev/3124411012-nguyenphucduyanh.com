<?php
// ============================================================
//  admin/inventory/report.php  –  Báo cáo tồn kho
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title  = 'Báo cáo tồn kho';
$active_menu = 'inventory';
$pdo         = get_db();

// ── Tham số bộ lọc ───────────────────────────────────────────
$date_from   = $_GET['from']       ?? date('Y-m-01');          // Đầu tháng
$date_to     = $_GET['to']         ?? date('Y-m-d');
$low_n       = max(0, (int)($_GET['low_n'] ?? 5));
$history_pid = (int)($_GET['history_pid'] ?? 0);
$history_dt  = $_GET['history_dt']  ?? '';
$q           = trim($_GET['q'] ?? '');

// ============================================================
//  QUERY 1: Nhập – Xuất – Tồn trong khoảng thời gian
//  Logic: Tồn = Tồn đầu kỳ + Nhập trong kỳ - Xuất trong kỳ
// ============================================================
$inv_sql = "
SELECT
    p.id,
    p.name,
    c.name                                                       AS category_name,
    p.import_price,
    ROUND(p.import_price * (1 + p.profit_rate))                 AS selling_price,

    /* Tồn đầu kỳ (trước from) */
    COALESCE((
        SELECT SUM(il_pre.quantity_change)
        FROM inventory_log il_pre
        WHERE il_pre.product_id = p.id
          AND il_pre.created_at < ?
    ), 0)                                                        AS opening_stock,

    /* Nhập trong kỳ */
    COALESCE((
        SELECT SUM(il_in.quantity_change)
        FROM inventory_log il_in
        WHERE il_in.product_id = p.id
          AND il_in.change_type = 'import'
          AND DATE(il_in.created_at) BETWEEN ? AND ?
    ), 0)                                                        AS imported_qty,

    /* Xuất trong kỳ */
    COALESCE((
        SELECT ABS(SUM(il_out.quantity_change))
        FROM inventory_log il_out
        WHERE il_out.product_id = p.id
          AND il_out.change_type = 'export'
          AND DATE(il_out.created_at) BETWEEN ? AND ?
    ), 0)                                                        AS exported_qty,

    /* Tồn cuối kỳ (từ bảng products là tồn hiện tại) */
    p.stock_quantity                                             AS current_stock

FROM products p
JOIN categories c ON c.id = p.category_id
WHERE p.status = 1
" . ($q ? " AND p.name LIKE ?" : "") . "
ORDER BY c.name, p.name
";

$inv_params = [$date_from, $date_from, $date_to, $date_from, $date_to];
if ($q) $inv_params[] = "%{$q}%";

$inv_stmt = $pdo->prepare($inv_sql);
$inv_stmt->execute($inv_params);
$inventory = $inv_stmt->fetchAll();

// ============================================================
//  QUERY 2: Tồn tại thời điểm cụ thể trong quá khứ
// ============================================================
$history_result = null;
if ($history_pid > 0 && $history_dt !== '') {
    $hstmt = $pdo->prepare("
        SELECT p.name,
               COALESCE(SUM(il.quantity_change), 0) AS stock_at_time
        FROM products p
        LEFT JOIN inventory_log il ON il.product_id = p.id
                                   AND il.created_at <= ?
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $hstmt->execute([$history_dt . ' 23:59:59', $history_pid]);
    $history_result = $hstmt->fetch();
}

// ============================================================
//  QUERY 3: Sản phẩm sắp hết hàng (stock_quantity <= N)
// ============================================================
$low_stock_stmt = $pdo->prepare("
    SELECT p.id, p.name, p.stock_quantity,
           c.name AS category_name,
           ROUND(p.import_price * (1 + p.profit_rate)) AS selling_price
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.status = 1 AND p.stock_quantity <= ?
    ORDER BY p.stock_quantity ASC, p.name
");
$low_stock_stmt->execute([$low_n]);
$low_stock = $low_stock_stmt->fetchAll();

// Tổng hợp
$total_imported = array_sum(array_column($inventory, 'imported_qty'));
$total_exported = array_sum(array_column($inventory, 'exported_qty'));
$total_stock    = array_sum(array_column($inventory, 'current_stock'));

$products_list = $pdo->query(
    "SELECT id, name FROM products WHERE status=1 ORDER BY name"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0 fw-700"><?= e($page_title) ?></h5>
</div>

<!-- ── Bộ lọc kỳ báo cáo ── -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label form-label-sm">Từ ngày</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($date_from) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label form-label-sm">Đến ngày</label>
        <input type="date" name="to"   class="form-control form-control-sm" value="<?= e($date_to) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label form-label-sm">Tìm sản phẩm</label>
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Nhập tên..." value="<?= e($q) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label form-label-sm">Cảnh báo hết hàng (N ≤)</label>
        <input type="number" name="low_n" class="form-control form-control-sm"
               value="<?= $low_n ?>" min="0" style="width:80px">
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary">
          <i class="bi bi-bar-chart me-1"></i>Xem báo cáo
        </button>
        <a href="/admin/inventory/report.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- ── Stat tổng hợp ── -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-box-arrow-in-down"></i></div>
      <div>
        <div class="stat-label">Tổng nhập kỳ này</div>
        <div class="stat-value"><?= number_format($total_imported) ?> <small class="fw-400 fs-6">máy</small></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-box-arrow-up"></i></div>
      <div>
        <div class="stat-label">Tổng xuất kỳ này</div>
        <div class="stat-value"><?= number_format($total_exported) ?> <small class="fw-400 fs-6">máy</small></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-archive"></i></div>
      <div>
        <div class="stat-label">Tổng tồn kho hiện tại</div>
        <div class="stat-value"><?= number_format($total_stock) ?> <small class="fw-400 fs-6">máy</small></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- ── Bảng Nhập/Xuất/Tồn ── -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-table me-2"></i>
        Nhập – Xuất – Tồn
        <small class="text-muted fw-400 ms-2">
          (<?= date('d/m/Y', strtotime($date_from)) ?> → <?= date('d/m/Y', strtotime($date_to)) ?>)
        </small>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead>
            <tr>
              <th>Sản phẩm</th>
              <th>Danh mục</th>
              <th class="text-center">Tồn đầu kỳ</th>
              <th class="text-center text-success">Nhập</th>
              <th class="text-center text-danger">Xuất</th>
              <th class="text-center">Tồn cuối</th>
              <th>Giá vốn</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($inventory as $inv): ?>
            <?php
              $closing = (int)$inv['opening_stock'] + (int)$inv['imported_qty'] - (int)$inv['exported_qty'];
            ?>
            <tr>
              <td style="font-size:.83rem;font-weight:500"><?= e($inv['name']) ?></td>
              <td style="font-size:.75rem;color:#64748b"><?= e($inv['category_name']) ?></td>
              <td class="text-center"><?= $inv['opening_stock'] ?></td>
              <td class="text-center text-success fw-600">
                <?= $inv['imported_qty'] > 0 ? '+' . $inv['imported_qty'] : '—' ?>
              </td>
              <td class="text-center text-danger fw-600">
                <?= $inv['exported_qty'] > 0 ? '-' . $inv['exported_qty'] : '—' ?>
              </td>
              <td class="text-center">
                <span class="badge <?= $inv['current_stock']<=5 ? 'bg-danger' : 'bg-light text-dark border' ?>">
                  <?= $inv['current_stock'] ?>
                </span>
              </td>
              <td class="mono" style="font-size:.78rem"><?= vnd((float)$inv['import_price']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($inventory)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Không có dữ liệu.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- ── Tra cứu tồn tại thời điểm quá khứ ── -->
    <div class="card mb-3">
      <div class="card-header">
        <i class="bi bi-search me-2"></i>Tồn kho tại thời điểm quá khứ
      </div>
      <div class="p-3">
        <form method="GET">
          <input type="hidden" name="from"   value="<?= e($date_from) ?>">
          <input type="hidden" name="to"     value="<?= e($date_to) ?>">
          <input type="hidden" name="low_n"  value="<?= $low_n ?>">
          <div class="mb-2">
            <label class="form-label form-label-sm">Chọn sản phẩm</label>
            <select name="history_pid" class="form-select form-select-sm">
              <option value="">— Chọn sản phẩm —</option>
              <?php foreach ($products_list as $pl): ?>
              <option value="<?= $pl['id'] ?>" <?= $history_pid==$pl['id']?'selected':'' ?>>
                <?= e($pl['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label form-label-sm">Thời điểm tra cứu</label>
            <input type="date" name="history_dt" class="form-control form-control-sm"
                   value="<?= e($history_dt) ?>" max="<?= date('Y-m-d') ?>">
          </div>
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">
            <i class="bi bi-search me-1"></i>Tra cứu
          </button>
        </form>

        <?php if ($history_result): ?>
        <div class="mt-3 p-3 rounded text-center"
             style="background:#f0f9ff;border:1px solid #bae6fd">
          <div style="font-size:.8rem;color:#0369a1">
            <?= e($history_result['name']) ?> vào ngày <?= date('d/m/Y', strtotime($history_dt)) ?>
          </div>
          <div class="fw-700 mt-1" style="font-size:1.8rem;color:#0c4a6e">
            <?= number_format($history_result['stock_at_time']) ?>
          </div>
          <div style="font-size:.75rem;color:#0369a1">máy tồn kho</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Cảnh báo sắp hết hàng ── -->
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle text-warning"></i>
        Sắp hết hàng
        <span class="badge bg-warning text-dark ms-auto"><?= count($low_stock) ?> sản phẩm</span>
      </div>
      <div class="p-3" style="max-height:340px;overflow-y:auto">
        <?php if (empty($low_stock)): ?>
          <p class="text-center text-muted py-3 mb-0">
            Không có sản phẩm nào có tồn ≤ <?= $low_n ?>
          </p>
        <?php else: ?>
          <?php foreach ($low_stock as $ls): ?>
          <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
            <div>
              <div style="font-size:.83rem;font-weight:500"><?= e($ls['name']) ?></div>
              <small class="text-muted"><?= e($ls['category_name']) ?></small>
            </div>
            <div class="text-end">
              <span class="badge <?= $ls['stock_quantity']==0?'bg-danger':($ls['stock_quantity']<=2?'bg-orange':'bg-warning text-dark') ?>">
                <?= $ls['stock_quantity'] ?> máy
              </span>
              <?php if ($ls['stock_quantity'] == 0): ?>
                <div style="font-size:.7rem;color:#ef4444">HẾT HÀNG</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="p-3 border-top">
        <a href="/admin/purchase_orders/create.php" class="btn btn-sm btn-outline-primary w-100">
          <i class="bi bi-plus me-1"></i>Lập phiếu nhập hàng
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
