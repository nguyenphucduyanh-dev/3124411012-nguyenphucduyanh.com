<?php
/**
 * order_history.php - Lịch sử đơn hàng
 * Tác giả: nguyenphucduyanh-dev
 */

session_start();
require_once __DIR__ . '/includes/functions.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=order_history.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$orders = getOrdersByUser($userId);

// -----------------------------------------------
// AJAX: Lấy chi tiết đơn hàng
// -----------------------------------------------
if (isset($_GET['ajax_detail']) && isset($_GET['order_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $orderId = (int)$_GET['order_id'];
    $detail  = getOrderDetail($orderId, $userId);

    if ($detail) {
        echo json_encode(['success' => true, 'order' => $detail], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử đơn hàng - Website Bán Điện Thoại</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 960px; margin: 0 auto; padding: 20px; }
        .page-header { background: #1a73e8; color: #fff; padding: 20px; text-align: center; margin-bottom: 20px; border-radius: 8px; }

        .orders-list { display: flex; flex-direction: column; gap: 16px; }

        .order-card {
            background: #fff; border-radius: 8px; overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .order-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 20px; background: #fafafa; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 10px;
        }
        .order-code { font-weight: 700; font-size: 16px; }
        .order-date { font-size: 13px; color: #888; }
        .order-body { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; flex-wrap: wrap; gap: 10px; }
        .order-total { font-size: 18px; font-weight: 700; color: #e53935; }

        .status-badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .status-pending   { background: #fff3e0; color: #e65100; }
        .status-confirmed { background: #e3f2fd; color: #1565c0; }
        .status-shipping  { background: #e8f5e9; color: #2e7d32; }
        .status-delivered  { background: #e8f5e9; color: #1b5e20; }
        .status-cancelled  { background: #ffebee; color: #c62828; }

        .btn-detail {
            padding: 8px 16px; background: #1a73e8; color: #fff; border: none;
            border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;
            transition: background 0.2s;
        }
        .btn-detail:hover { background: #1557b0; }

        .empty-orders { text-align: center; padding: 60px 20px; color: #999; }

        /* Modal chi tiết */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #fff; border-radius: 12px; max-width: 700px; width: 100%;
            max-height: 80vh; overflow-y: auto; padding: 0;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 24px; border-bottom: 1px solid #eee; position: sticky; top: 0; background: #fff;
            border-radius: 12px 12px 0 0;
        }
        .modal-header h2 { font-size: 18px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 24px; }

        .detail-info { margin-bottom: 20px; }
        .detail-info p { padding: 6px 0; font-size: 14px; }
        .detail-info strong { display: inline-block; min-width: 140px; color: #555; }

        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th { background: #f5f5f5; padding: 10px 12px; text-align: left; font-size: 13px; }
        .detail-table td { padding: 10px 12px; border-top: 1px solid #eee; font-size: 14px; }
        .detail-table .price { color: #e53935; font-weight: 600; white-space: nowrap; }
        .detail-total { text-align: right; font-size: 18px; font-weight: 700; color: #e53935; margin-top: 16px; }

        @media (max-width: 600px) {
            .order-header, .order-body { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h1>📋 Lịch sử đơn hàng</h1>
    </div>

    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <p style="font-size: 48px;">📭</p>
            <p style="font-size: 18px; margin-top: 10px;">Bạn chưa có đơn hàng nào.</p>
            <p style="margin-top: 10px;"><a href="search.php">Mua sắm ngay →</a></p>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
                <?php
                    $statusClass = 'status-' . $order['status'];
                    $statusText  = translateOrderStatus($order['status']);
                    $date        = date('d/m/Y H:i', strtotime($order['created_at']));
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <span class="order-code">🧾 <?= htmlspecialchars($order['order_code']) ?></span>
                            <span class="order-date"> — <?= $date ?></span>
                        </div>
                        <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                    </div>
                    <div class="order-body">
                        <div>
                            <span>Tổng tiền: </span>
                            <span class="order-total"><?= formatVND($order['total_amount']) ?></span>
                        </div>
                        <button class="btn-detail" onclick="viewDetail(<?= $order['order_id'] ?>)">
                            👁 Xem chi tiết
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal chi tiết đơn hàng -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Chi tiết đơn hàng</h2>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" id="modalBody">
            <p>Đang tải...</p>
        </div>
    </div>
</div>

<script>
/**
 * JavaScript Order History
 */

function viewDetail(orderId) {
    const modal = document.getElementById('detailModal');
    const body  = document.getElementById('modalBody');
    const title = document.getElementById('modalTitle');

    modal.classList.add('active');
    body.innerHTML = '<p style="text-align:center; padding:40px;">⏳ Đang tải chi tiết...</p>';

    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'order_history.php?ajax_detail=1&order_id=' + orderId, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        renderOrderDetail(data.order);
                    } else {
                        body.innerHTML = '<p style="color:red;">❌ ' + data.message + '</p>';
                    }
                } catch(e) {
                    body.innerHTML = '<p style="color:red;">❌ Lỗi xử lý dữ liệu.</p>';
                }
            }
        }
    };
    xhr.send();
}

function renderOrderDetail(order) {
    const title = document.getElementById('modalTitle');
    const body  = document.getElementById('modalBody');

    title.textContent = '🧾 Đơn hàng: ' + order.order_code;

    const statusMap = {
        'pending': 'Chờ xác nhận',
        'confirmed': 'Đã xác nhận',
        'shipping': 'Đang giao hàng',
        'delivered': 'Đã giao',
        'cancelled': 'Đã hủy'
    };

    const paymentMap = {
        'cash': '💵 Tiền mặt (COD)',
        'bank_transfer': '🏦 Chuyển khoản',
        'online': '📲 Online'
    };

    let html = '<div class="detail-info">';
    html += '<p><strong>Ngày đặt:</strong> ' + formatDate(order.created_at) + '</p>';
    html += '<p><strong>Người nhận:</strong> ' + escapeHtml(order.receiver_name) + '</p>';
    html += '<p><strong>Điện thoại:</strong> ' + escapeHtml(order.receiver_phone) + '</p>';
    html += '<p><strong>Địa chỉ:</strong> ' + escapeHtml(order.shipping_address) + '</p>';
    html += '<p><strong>Thanh toán:</strong> ' + (paymentMap[order.payment_method] || order.payment_method) + '</p>';
    html += '<p><strong>Trạng thái:</strong> ' + (statusMap[order.status] || order.status) + '</p>';
    if (order.note) {
        html += '<p><strong>Ghi chú:</strong> ' + escapeHtml(order.note) + '</p>';
    }
    html += '</div>';

    html += '<table class="detail-table">';
    html += '<thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>';
    html += '<tbody>';

    if (order.items && order.items.length > 0) {
        order.items.forEach(function(item) {
            html += '<tr>';
            html += '<td>' + escapeHtml(item.product_name) + '</td>';
            html += '<td class="price">' + formatCurrency(item.unit_price) + '</td>';
            html += '<td>' + item.quantity + '</td>';
            html += '<td class="price">' + formatCurrency(item.subtotal) + '</td>';
            html += '</tr>';
        });
    }

    html += '</tbody></table>';
    html += '<div class="detail-total">TỔNG: ' + formatCurrency(order.total_amount) + '</div>';

    body.innerHTML = html;
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

// Đóng modal khi click ngoài
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Đóng modal bằng Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'});
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>

</body>
</html>
