<?php
// ============================================================
//  admin/orders/index.php  –  Quản lý đơn hàng
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title  = 'Quản lý đơn hàng';
$active_menu = 'orders';
$pdo         = get_db();

// ── Cập nhật trạng thái ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id  = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'] ?? '';
    $allowed    = ['pending','confirmed','shipping','completed','cancelled'];
    if (in_array($new_status, $allowed)) {
        $pdo->prepare(
            "UPDATE orders SET status=?, updated_at=NOW() WHERE id=?"
        )->execute([$new_status, $order_id]);
        $_SESSION['flash_success'] = 'Đã cập nhật trạng thái đơn hàng.';
    }
    header('Location: /admin/orders/index.php?' . http_build_query($_GET));
    exit;
}

// ── Lọc & Sắp xếp ────────────────────────────────────────────
$status_filter = $_GET['status']   ?? '';
$date_from     = $_GET['from']     ?? '';
$date_to       = $_GET['to']       ?? '';
$sort          = $_GET['sort']     ?? 'newest';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if (in_array($status_filter, ['pending','confirmed','shipping','completed','cancelled'])) {
    $where[]  = 'o.status = ?';
    $params[] = $status_filter;
}
if ($date_from) { $where[] = 'DATE(o.created_at) >= ?'; $params[] = $date_from; }
if ($date_to)   { $where[] = 'DATE(o.created_at) <= ?'; $params[] = $date_to; }

$where_sql = implode(' AND ', $where);

$order_sql = match($sort) {
    'ward'    => 'o.ward, o.district, o.province',
    'amount'  => 'o.total_amount DESC',
    'oldest'  => 'o.created_at ASC',
    default   => 'o.created_at DESC',
};

// Đếm tổng
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE {$where_sql}");
$cnt_stmt->execute($params);
$total = (int)$cnt_stmt->fetchColumn();
$pages = (int)ceil($total / $per_page);

// Lấy đơn hàng
$list_stmt = $pdo->prepare(
    "SELECT o.id, o.order_code, o.status, o.total_amount, o.created_at,
            o.recipient_name, o.recipient_phone,
            o.ward, o.district, o.province,
            o.payment_method, o.payment_status,
            u.username
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE {$where_sql}
     ORDER BY {$order_sql}
     LIMIT {$per_page} OFFSET {$offset}"
);
$list_stmt->execute($params);
$orders = $list_stmt->fetchAll();

// Chi tiết 1 đơn (cho modal)
$detail_order_id = (int)($_GET['detail'] ?? 0);
$detail_items    = [];
$detail_order    = null;
if ($detail_order_id > 0) {
    $det = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $det->execute([$detail_order_id]);
    $detail_order = $det->fetch();

    $dit = $pdo->prepare(
        "SELECT od.quantity, od.unit_price, od.subtotal,
                p.name AS product_name, p.image
         FROM order_details od
         JOIN products p ON p.id = od.product_id
         WHERE od.order_id = ?"
    );
    $dit->execute([$detail_order_id]);
    $detail_items = $dit->fetchAll();
}

$status_labels = [
    'pending'   => ['Chờ duyệt',   'badge-pending'],
    'confirmed' => ['Đã xác nhận', 'badge-confirmed'],
    'shipping'  => ['Đang giao',   'badge-shipping'],
    'completed' => ['Thành công',  'badge-completed'],
    'cancelled' => ['Đã hủy',      'badge-cancelled'],
];
$status_next = [
    'pending'   => ['confirmed' => 'Xác nhận', 'cancelled' => 'Hủy đơn'],
    'confirmed' => ['shipping'  => 'Giao hàng', 'cancelled' => 'Hủy đơn'],
    'shipping'  => ['completed' => 'Hoàn thành'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0 fw-700"><?= e($page_title) ?></h5>
  <span class="text-muted" style="font-size:.85rem">Tổng: <?= $total ?> đơn</span>
</div>

<!-- ── Bộ lọc ── -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label form-label-sm">Từ ngày</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($date_from) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label form-label-sm">Đến ngày</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($date_to) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label form-label-sm">Trạng thái</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          <?php foreach ($status_labels as $s => [$label, $_]): ?>
          <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label form-label-sm">Sắp xếp theo</label>
        <select name="sort" class="form-select form-select-sm">
          <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Mới nhất</option>
          <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Cũ nhất</option>
          <option value="ward"   <?= $sort==='ward'  ?'selected':'' ?>>Phường/Xã</option>
          <option value="amount" <?= $sort==='amount'?'selected':'' ?>>Tiền cao nhất</option>
        </select>
      </div>
      <div class="col-md-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
        <a href="/admin/orders/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- ── Bảng đơn hàng ── -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Khách hàng</th>
          <th>Địa chỉ giao</th>
          <th>Ngày đặt</th>
          <th>Tổng tiền</th>
          <th>Thanh toán</th>
          <th>Trạng thái</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <?php [$label, $badge] = $status_labels[$o['status']] ?? ['?','']; ?>
        <tr>
          <td>
            <a href="?<?= http_build_query(array_merge($_GET, ['detail'=>$o['id']])) ?>"
               class="mono fw-600 text-decoration-none text-primary">
              <?= e($o['order_code']) ?>
            </a>
          </td>
          <td>
            <div style="font-size:.85rem;font-weight:500"><?= e($o['recipient_name']) ?></div>
            <small class="text-muted"><?= e($o['username']) ?></small>
          </td>
          <td style="font-size:.8rem;max-width:200px">
            <?php if ($o['ward']): ?>
              <span class="fw-500"><?= e($o['ward']) ?></span>,
            <?php endif; ?>
            <?= e($o['district'] ?? '') ?>,
            <?= e($o['province'] ?? '') ?>
          </td>
          <td style="font-size:.8rem;color:#64748b">
            <?= date('d/m/Y H:i', strtotime($o['created_at'])) ?>
          </td>
          <td class="mono fw-600"><?= vnd((float)$o['total_amount']) ?></td>
          <td style="font-size:.78rem">
            <?= match($o['payment_method']) {
              'cash'     => '<i class="bi bi-cash text-success"></i> Tiền mặt',
              'transfer' => '<i class="bi bi-bank text-info"></i> CK ngân hàng',
              'online'   => '<i class="bi bi-credit-card text-primary"></i> Online',
              default    => $o['payment_method']
            } ?>
          </td>
          <td><span class="badge-status <?= $badge ?>"><?= $label ?></span></td>
          <td>
            <a href="?<?= http_build_query(array_merge($_GET,['detail'=>$o['id']])) ?>"
               class="btn btn-sm btn-outline-secondary py-0 px-2" title="Xem chi tiết">
              <i class="bi bi-eye"></i>
            </a>
            <?php if (isset($status_next[$o['status']])): ?>
            <div class="btn-group ms-1">
              <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 dropdown-toggle"
                      data-bs-toggle="dropdown" style="font-size:.72rem">
                Cập nhật
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($status_next[$o['status']] as $ns => $nlabel): ?>
                <li>
                  <form method="POST">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="order_id"  value="<?= $o['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $ns ?>">
                    <button type="submit" class="dropdown-item <?= $ns==='cancelled'?'text-danger':'' ?>">
                      <?= $nlabel ?>
                    </button>
                  </form>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">Không có đơn hàng nào.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Phân trang -->
  <?php if ($pages > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">Trang <?= $page ?> / <?= $pages ?></small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- ── Modal chi tiết đơn hàng ── -->
<?php if ($detail_order): ?>
<div class="modal fade show" id="detailModal" tabindex="-1"
     style="display:block;background:rgba(0,0,0,.5)">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="bi bi-bag-check me-2"></i>
          Chi tiết đơn hàng: <span class="mono"><?= e($detail_order['order_code']) ?></span>
        </h6>
        <a href="?<?= http_build_query(array_diff_key($_GET, ['detail'=>''])) ?>"
           class="btn-close"></a>
      </div>
      <div class="modal-body">
        <div class="row mb-3 g-3">
          <div class="col-md-6">
            <div class="p-3 rounded" style="background:#f8fafc;font-size:.85rem">
              <div class="fw-600 mb-2">Thông tin giao hàng</div>
              <div><?= e($detail_order['recipient_name']) ?> — <?= e($detail_order['recipient_phone']) ?></div>
              <div class="text-muted"><?= e($detail_order['address']) ?></div>
              <?php if ($detail_order['ward']): ?>
              <div class="text-muted">
                <?= e($detail_order['ward']) ?>, <?= e($detail_order['district']) ?>,
                <?= e($detail_order['province']) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 rounded" style="background:#f8fafc;font-size:.85rem">
              <div class="fw-600 mb-2">Thông tin đơn hàng</div>
              <div>Ngày đặt: <strong><?= date('d/m/Y H:i', strtotime($detail_order['created_at'])) ?></strong></div>
              <div>Thanh toán:
                <strong><?= match($detail_order['payment_method']) {
                  'cash' => 'Tiền mặt', 'transfer' => 'Chuyển khoản', 'online' => 'Online', default => '?'
                } ?></strong>
              </div>
              <?php [$sl,$sb] = $status_labels[$detail_order['status']] ?? ['?','']; ?>
              <div>Trạng thái: <span class="badge-status <?= $sb ?>"><?= $sl ?></span></div>
              <?php if ($detail_order['note']): ?>
              <div class="text-muted mt-1">Ghi chú: <?= e($detail_order['note']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <table class="table table-sm">
          <thead>
            <tr>
              <th>Sản phẩm</th>
              <th class="text-center">SL</th>
              <th class="text-end">Đơn giá</th>
              <th class="text-end">Thành tiền</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($detail_items as $item): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if ($item['image']): ?>
                <img src="/uploads/products/<?= e($item['image']) ?>"
                     style="width:36px;height:36px;object-fit:cover;border-radius:5px">
                <?php endif; ?>
                <span style="font-size:.85rem;font-weight:500"><?= e($item['product_name']) ?></span>
              </div>
            </td>
            <td class="text-center"><?= $item['quantity'] ?></td>
            <td class="text-end mono"><?= vnd((float)$item['unit_price']) ?></td>
            <td class="text-end mono fw-600"><?= vnd((float)$item['subtotal']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-700 table-light">
              <td colspan="3" class="text-end">Tổng cộng:</td>
              <td class="text-end mono text-primary">
                <?= vnd((float)$detail_order['total_amount']) ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer">
        <a href="?<?= http_build_query(array_diff_key($_GET, ['detail'=>''])) ?>"
           class="btn btn-sm btn-secondary">Đóng</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
