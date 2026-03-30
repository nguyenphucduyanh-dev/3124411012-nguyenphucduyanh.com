<?php
/**
 * Các hàm xử lý nghiệp vụ chính
 * Tác giả: nguyenphucduyanh-dev
 */

require_once __DIR__ . '/../config/db.php';

// =============================================
// HÀM NHẬP HÀNG VÀO KHO
// Công thức bình quân:
// Giá mới = (Tồn * Giá_BQ_cũ + SL_nhập * Giá_nhập_mới) / (Tồn + SL_nhập)
// =============================================

/**
 * Nhập hàng vào kho và cập nhật giá nhập bình quân
 *
 * @param int    $productId        ID sản phẩm
 * @param int    $quantityImported Số lượng nhập mới (> 0)
 * @param float  $unitImportPrice  Đơn giá nhập lần này (> 0)
 * @param string $supplier         Nhà cung cấp (tuỳ chọn)
 * @param string $note             Ghi chú (tuỳ chọn)
 * @param int|null $createdBy      ID admin thực hiện (tuỳ chọn)
 * @return array ['success' => bool, 'message' => string, 'data' => array|null]
 */
function importInventory(
    int    $productId,
    int    $quantityImported,
    float  $unitImportPrice,
    string $supplier = '',
    string $note = '',
    ?int   $createdBy = null
): array {
    // --- Validate đầu vào ---
    if ($quantityImported <= 0) {
        return [
            'success' => false,
            'message' => 'Số lượng nhập phải lớn hơn 0.',
            'data'    => null
        ];
    }

    if ($unitImportPrice <= 0) {
        return [
            'success' => false,
            'message' => 'Đơn giá nhập phải lớn hơn 0.',
            'data'    => null
        ];
    }

    $pdo = getDBConnection();

    try {
        // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
        $pdo->beginTransaction();

        // 1) Lấy thông tin sản phẩm hiện tại (khóa dòng để tránh race condition)
        $stmt = $pdo->prepare("
            SELECT product_id, product_name, import_price, stock_quantity
            FROM products
            WHERE product_id = :pid
            FOR UPDATE
        ");
        $stmt->execute([':pid' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => "Không tìm thấy sản phẩm có ID = {$productId}.",
                'data'    => null
            ];
        }

        $quantityBefore    = (int)$product['stock_quantity'];
        $importPriceBefore = (float)$product['import_price'];

        // 2) Tính giá nhập bình quân mới
        //    Trường hợp tồn kho = 0 (lần nhập đầu tiên hoặc đã hết hàng):
        //    → Giá BQ mới = Đơn giá nhập lần này
        if ($quantityBefore === 0) {
            $importPriceAfter = $unitImportPrice;
        } else {
            // Công thức bình quân gia quyền
            $importPriceAfter = (
                ($quantityBefore * $importPriceBefore) +
                ($quantityImported * $unitImportPrice)
            ) / ($quantityBefore + $quantityImported);
        }

        // Làm tròn 2 chữ số thập phân
        $importPriceAfter = round($importPriceAfter, 2);
        $quantityAfter    = $quantityBefore + $quantityImported;

        // 3) Cập nhật bảng products
        $stmtUpdate = $pdo->prepare("
            UPDATE products
            SET import_price   = :new_price,
                stock_quantity = :new_qty,
                updated_at     = NOW()
            WHERE product_id = :pid
        ");
        $stmtUpdate->execute([
            ':new_price' => $importPriceAfter,
            ':new_qty'   => $quantityAfter,
            ':pid'       => $productId
        ]);

        // 4) Ghi log vào inventory_log
        $stmtLog = $pdo->prepare("
            INSERT INTO inventory_log
                (product_id, quantity_before, import_price_before,
                 quantity_imported, unit_import_price,
                 import_price_after, quantity_after,
                 supplier, note, created_by)
            VALUES
                (:pid, :qty_before, :price_before,
                 :qty_imported, :unit_price,
                 :price_after, :qty_after,
                 :supplier, :note, :created_by)
        ");
        $stmtLog->execute([
            ':pid'          => $productId,
            ':qty_before'   => $quantityBefore,
            ':price_before' => $importPriceBefore,
            ':qty_imported' => $quantityImported,
            ':unit_price'   => $unitImportPrice,
            ':price_after'  => $importPriceAfter,
            ':qty_after'    => $quantityAfter,
            ':supplier'     => $supplier,
            ':note'         => $note,
            ':created_by'   => $createdBy
        ]);

        $logId = $pdo->lastInsertId();

        // Commit transaction
        $pdo->commit();

        return [
            'success' => true,
            'message' => "Nhập hàng thành công cho sản phẩm \"{$product['product_name']}\".",
            'data'    => [
                'log_id'              => (int)$logId,
                'product_id'          => $productId,
                'product_name'        => $product['product_name'],
                'quantity_before'     => $quantityBefore,
                'import_price_before' => $importPriceBefore,
                'quantity_imported'   => $quantityImported,
                'unit_import_price'   => $unitImportPrice,
                'import_price_after'  => $importPriceAfter,
                'quantity_after'      => $quantityAfter
            ]
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Lỗi CSDL: ' . $e->getMessage(),
            'data'    => null
        ];
    }
}

// =============================================
// HÀM LẤY GIÁ BÁN (SELLING PRICE)
// Công thức: selling_price = import_price * (1 + profit_rate)
// =============================================

/**
 * Lấy giá bán của một sản phẩm
 *
 * @param int $productId ID sản phẩm
 * @return float|null     Giá bán hoặc null nếu không tìm thấy
 */
function getSellingPrice(int $productId): ?float
{
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT import_price, profit_rate
        FROM products
        WHERE product_id = :pid AND is_active = 1
    ");
    $stmt->execute([':pid' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return null;
    }

    $importPrice = (float)$product['import_price'];
    $profitRate  = (float)$product['profit_rate'];

    return round($importPrice * (1 + $profitRate), 2);
}

/**
 * Lấy thông tin sản phẩm kèm giá bán (dùng cho hiển thị)
 *
 * @param int $productId
 * @return array|null
 */
function getProductWithSellingPrice(int $productId): ?array
{
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name,
               ROUND(p.import_price * (1 + p.profit_rate), 2) AS selling_price
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_id = :pid AND p.is_active = 1
    ");
    $stmt->execute([':pid' => $productId]);
    $product = $stmt->fetch();

    return $product ?: null;
}

// =============================================
// HÀM TÌM KIẾM SẢN PHẨM (cho AJAX)
// Tìm theo: tên (LIKE), category_id, khoảng giá bán
// Có phân trang: 12 sản phẩm/trang
// =============================================

/**
 * Tìm kiếm sản phẩm nâng cao
 *
 * @param string   $keyword     Từ khoá tên sản phẩm
 * @param int|null $categoryId  ID danh mục (null = tất cả)
 * @param float|null $priceMin  Giá bán tối thiểu
 * @param float|null $priceMax  Giá bán tối đa
 * @param int      $page        Trang hiện tại (>= 1)
 * @param int      $perPage     Số sản phẩm mỗi trang
 * @return array   ['products' => [], 'total' => int, 'total_pages' => int, 'current_page' => int]
 */
function searchProducts(
    string $keyword = '',
    ?int   $categoryId = null,
    ?float $priceMin = null,
    ?float $priceMax = null,
    int    $page = 1,
    int    $perPage = 12
): array {
    $pdo = getDBConnection();

    // Xây dựng câu truy vấn động
    $where  = "WHERE p.is_active = 1";
    $params = [];

    // Tìm theo tên sản phẩm
    if (!empty($keyword)) {
        $where .= " AND p.product_name LIKE :keyword";
        $params[':keyword'] = '%' . $keyword . '%';
    }

    // Lọc theo danh mục
    if ($categoryId !== null && $categoryId > 0) {
        $where .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    // Lọc theo khoảng giá BÁN (selling_price = import_price * (1 + profit_rate))
    // Dùng HAVING vì selling_price là cột tính toán
    $having = "";
    $havingParts = [];

    if ($priceMin !== null && $priceMin > 0) {
        $havingParts[] = "selling_price >= :price_min";
        $params[':price_min'] = $priceMin;
    }

    if ($priceMax !== null && $priceMax > 0) {
        $havingParts[] = "selling_price <= :price_max";
        $params[':price_max'] = $priceMax;
    }

    if (!empty($havingParts)) {
        $having = "HAVING " . implode(' AND ', $havingParts);
    }

    // Đếm tổng số kết quả
    $sqlCount = "
        SELECT COUNT(*) as total FROM (
            SELECT ROUND(p.import_price * (1 + p.profit_rate), 2) AS selling_price
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            {$where}
            {$having}
        ) AS counted
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Tính phân trang
    $page       = max(1, $page);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Truy vấn lấy sản phẩm
    $sql = "
        SELECT p.*, c.category_name,
               ROUND(p.import_price * (1 + p.profit_rate), 2) AS selling_price
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        {$where}
        {$having}
        ORDER BY p.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    return [
        'products'     => $products,
        'total'        => $total,
        'total_pages'  => $totalPages,
        'current_page' => $page,
        'per_page'     => $perPage
    ];
}

// =============================================
// HÀM GIỎ HÀNG (SESSION)
// =============================================

/**
 * Thêm sản phẩm vào giỏ hàng
 */
function addToCart(int $productId, int $quantity = 1): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($quantity <= 0) {
        return ['success' => false, 'message' => 'Số lượng phải lớn hơn 0.'];
    }

    // Kiểm tra sản phẩm tồn tại và còn hàng
    $product = getProductWithSellingPrice($productId);
    if (!$product) {
        return ['success' => false, 'message' => 'Sản phẩm không tồn tại.'];
    }

    if ($product['stock_quantity'] <= 0) {
        return ['success' => false, 'message' => 'Sản phẩm đã hết hàng.'];
    }

    // Khởi tạo giỏ hàng nếu chưa có
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Cộng dồn nếu sản phẩm đã có trong giỏ
    if (isset($_SESSION['cart'][$productId])) {
        $newQty = $_SESSION['cart'][$productId]['quantity'] + $quantity;

        // Kiểm tra không vượt quá tồn kho
        if ($newQty > $product['stock_quantity']) {
            return [
                'success' => false,
                'message' => "Chỉ còn {$product['stock_quantity']} sản phẩm trong kho."
            ];
        }

        $_SESSION['cart'][$productId]['quantity'] = $newQty;
    } else {
        if ($quantity > $product['stock_quantity']) {
            return [
                'success' => false,
                'message' => "Chỉ còn {$product['stock_quantity']} sản phẩm trong kho."
            ];
        }

        $_SESSION['cart'][$productId] = [
            'product_id'   => $productId,
            'product_name' => $product['product_name'],
            'image'        => $product['image'],
            'selling_price'=> (float)$product['selling_price'],
            'quantity'     => $quantity,
            'stock'        => (int)$product['stock_quantity']
        ];
    }

    return [
        'success'    => true,
        'message'    => "Đã thêm \"{$product['product_name']}\" vào giỏ hàng.",
        'cart_count' => getCartTotalItems()
    ];
}

/**
 * Cập nhật số lượng sản phẩm trong giỏ
 */
function updateCartItem(int $productId, int $quantity): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['cart'][$productId])) {
        return ['success' => false, 'message' => 'Sản phẩm không có trong giỏ hàng.'];
    }

    if ($quantity <= 0) {
        return removeFromCart($productId);
    }

    // Kiểm tra tồn kho
    $product = getProductWithSellingPrice($productId);
    if ($quantity > $product['stock_quantity']) {
        return [
            'success' => false,
            'message' => "Chỉ còn {$product['stock_quantity']} sản phẩm trong kho."
        ];
    }

    $_SESSION['cart'][$productId]['quantity']      = $quantity;
    $_SESSION['cart'][$productId]['selling_price']  = (float)$product['selling_price'];

    return ['success' => true, 'message' => 'Đã cập nhật giỏ hàng.', 'cart_count' => getCartTotalItems()];
}

/**
 * Xóa sản phẩm khỏi giỏ hàng
 */
function removeFromCart(int $productId): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }

    return ['success' => true, 'message' => 'Đã xóa khỏi giỏ hàng.', 'cart_count' => getCartTotalItems()];
}

/**
 * Lấy tổng số lượng sản phẩm trong giỏ
 */
function getCartTotalItems(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $total = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['quantity'];
        }
    }
    return $total;
}

/**
 * Lấy tổng tiền giỏ hàng
 */
function getCartTotalAmount(): float
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $total = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['selling_price'] * $item['quantity'];
        }
    }
    return round($total, 2);
}

/**
 * Lấy toàn bộ giỏ hàng
 */
function getCart(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['cart'] ?? [];
}

/**
 * Xóa toàn bộ giỏ hàng
 */
function clearCart(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['cart'] = [];
}

// =============================================
// HÀM ĐẶT HÀNG (CHECKOUT)
// =============================================

/**
 * Tạo đơn hàng từ giỏ hàng
 */
function createOrder(
    int    $userId,
    string $receiverName,
    string $receiverPhone,
    string $shippingAddress,
    string $paymentMethod = 'cash',
    string $note = ''
): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $cart = getCart();
    if (empty($cart)) {
        return ['success' => false, 'message' => 'Giỏ hàng trống.'];
    }

    $pdo = getDBConnection();

    try {
        $pdo->beginTransaction();

        // Tạo mã đơn hàng
        $orderCode = 'DH' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Tính tổng tiền và kiểm tra tồn kho
        $totalAmount  = 0;
        $orderItems   = [];

        foreach ($cart as $item) {
            // Lấy lại giá bán mới nhất từ DB (tránh sai lệch)
            $stmtProduct = $pdo->prepare("
                SELECT product_id, product_name, stock_quantity,
                       ROUND(import_price * (1 + profit_rate), 2) AS selling_price
                FROM products
                WHERE product_id = :pid AND is_active = 1
                FOR UPDATE
            ");
            $stmtProduct->execute([':pid' => $item['product_id']]);
            $product = $stmtProduct->fetch();

            if (!$product) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "Sản phẩm \"{$item['product_name']}\" không còn tồn tại."
                ];
            }

            if ($product['stock_quantity'] < $item['quantity']) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "Sản phẩm \"{$product['product_name']}\" chỉ còn {$product['stock_quantity']} trong kho."
                ];
            }

            $subtotal = round($product['selling_price'] * $item['quantity'], 2);
            $totalAmount += $subtotal;

            $orderItems[] = [
                'product_id'   => $product['product_id'],
                'product_name' => $product['product_name'],
                'quantity'     => $item['quantity'],
                'unit_price'   => $product['selling_price'],
                'subtotal'     => $subtotal
            ];

            // Trừ tồn kho
            $stmtStock = $pdo->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity - :qty, updated_at = NOW()
                WHERE product_id = :pid
            ");
            $stmtStock->execute([
                ':qty' => $item['quantity'],
                ':pid' => $product['product_id']
            ]);
        }

        // Tạo đơn hàng
        $stmtOrder = $pdo->prepare("
            INSERT INTO orders
                (order_code, user_id, receiver_name, receiver_phone,
                 shipping_address, payment_method, total_amount, status, note)
            VALUES
                (:code, :uid, :name, :phone, :address, :payment, :total, 'pending', :note)
        ");
        $stmtOrder->execute([
            ':code'    => $orderCode,
            ':uid'     => $userId,
            ':name'    => $receiverName,
            ':phone'   => $receiverPhone,
            ':address' => $shippingAddress,
            ':payment' => $paymentMethod,
            ':total'   => $totalAmount,
            ':note'    => $note
        ]);

        $orderId = (int)$pdo->lastInsertId();

        // Tạo chi tiết đơn hàng
        $stmtDetail = $pdo->prepare("
            INSERT INTO order_details
                (order_id, product_id, product_name, quantity, unit_price, subtotal)
            VALUES
                (:oid, :pid, :pname, :qty, :price, :subtotal)
        ");

        foreach ($orderItems as $oi) {
            $stmtDetail->execute([
                ':oid'      => $orderId,
                ':pid'      => $oi['product_id'],
                ':pname'    => $oi['product_name'],
                ':qty'      => $oi['quantity'],
                ':price'    => $oi['unit_price'],
                ':subtotal' => $oi['subtotal']
            ]);
        }

        $pdo->commit();

        // Xóa giỏ hàng
        clearCart();

        return [
            'success'    => true,
            'message'    => 'Đặt hàng thành công!',
            'order_id'   => $orderId,
            'order_code' => $orderCode,
            'total'      => $totalAmount
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Lỗi CSDL: ' . $e->getMessage()];
    }
}

// =============================================
// HÀM LỊCH SỬ ĐƠN HÀNG
// =============================================

/**
 * Lấy danh sách đơn hàng theo user (mới nhất trước)
 */
function getOrdersByUser(int $userId): array
{
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT order_id, order_code, total_amount, status, payment_method, created_at
        FROM orders
        WHERE user_id = :uid
        ORDER BY created_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Lấy chi tiết một đơn hàng
 */
function getOrderDetail(int $orderId, int $userId): ?array
{
    $pdo = getDBConnection();

    // Lấy thông tin đơn hàng (kiểm tra quyền sở hữu)
    $stmt = $pdo->prepare("
        SELECT * FROM orders
        WHERE order_id = :oid AND user_id = :uid
    ");
    $stmt->execute([':oid' => $orderId, ':uid' => $userId]);
    $order = $stmt->fetch();

    if (!$order) {
        return null;
    }

    // Lấy chi tiết sản phẩm
    $stmtDetail = $pdo->prepare("
        SELECT * FROM order_details
        WHERE order_id = :oid
    ");
    $stmtDetail->execute([':oid' => $orderId]);
    $order['items'] = $stmtDetail->fetchAll();

    return $order;
}

/**
 * Format tiền VND
 */
function formatVND(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Dịch trạng thái đơn hàng sang tiếng Việt
 */
function translateOrderStatus(string $status): string
{
    $map = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping'  => 'Đang giao hàng',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy'
    ];
    return $map[$status] ?? $status;
}
