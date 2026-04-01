-- ============================================================
--  ADMIN SCHEMA UPDATE - Phone Store Management System
--  Chạy file này để bổ sung các bảng cho hệ thống Admin
-- ============================================================

-- Thêm cột status và role vào bảng users (nếu chưa có)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS address TEXT NULL,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL;

-- Thêm cột status vào bảng products (nếu chưa có)
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=hiển thị, 0=ẩn';

-- ============================================================
--  BẢNG: purchase_orders (Phiếu nhập hàng)
-- ============================================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE COMMENT 'Mã phiếu: PN-YYYYMMDD-XXXX',
    import_date DATE NOT NULL,
    supplier    VARCHAR(255) NULL COMMENT 'Tên nhà cung cấp',
    note        TEXT NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status      ENUM('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
    created_by  INT UNSIGNED NOT NULL COMMENT 'Admin tạo phiếu',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT fk_po_admin FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BẢNG: purchase_order_details (Chi tiết phiếu nhập)
-- ============================================================
CREATE TABLE IF NOT EXISTS purchase_order_details (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id   INT UNSIGNED NOT NULL,
    product_id          INT UNSIGNED NOT NULL,
    quantity            INT UNSIGNED NOT NULL,
    import_price        DECIMAL(15,2) NOT NULL COMMENT 'Giá nhập lô này',
    subtotal            DECIMAL(15,2) GENERATED ALWAYS AS (quantity * import_price) STORED,
    CONSTRAINT fk_pod_po      FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_pod_product FOREIGN KEY (product_id)        REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BẢNG: inventory_log (Lịch sử nhập/xuất kho)
-- ============================================================
CREATE TABLE IF NOT EXISTS inventory_log (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id          INT UNSIGNED NOT NULL,
    change_type         ENUM('import','export','adjustment') NOT NULL,
    quantity_change     INT NOT NULL COMMENT 'Dương=nhập, Âm=xuất',
    import_price        DECIMAL(15,2) NULL COMMENT 'Giá nhập lúc giao dịch (chỉ cho import)',
    avg_price_after     DECIMAL(15,2) NULL COMMENT 'Giá bình quân sau giao dịch',
    stock_after         INT NOT NULL COMMENT 'Tồn kho sau giao dịch',
    reference_type      VARCHAR(50) NULL COMMENT 'purchase_order | order',
    reference_id        INT UNSIGNED NULL,
    note                VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_il_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BẢNG: categories (nếu chưa tồn tại - đầy đủ)
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    status      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BẢNG: products (nếu chưa tồn tại - đầy đủ)
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(280) NOT NULL UNIQUE,
    description     TEXT NULL,
    image           VARCHAR(255) NULL,
    import_price    DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Giá nhập bình quân hiện tại',
    profit_rate     DECIMAL(5,4) NOT NULL DEFAULT 0.1500 COMMENT 'Tỷ lệ LN, vd: 0.15 = 15%',
    stock_quantity  INT NOT NULL DEFAULT 0,
    status          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_p_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BẢNG: orders & order_details (nếu chưa tồn tại)
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    order_code      VARCHAR(30) NOT NULL UNIQUE,
    recipient_name  VARCHAR(150) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    address         TEXT NOT NULL,
    ward            VARCHAR(100) NULL COMMENT 'Phường/Xã - dùng để sort',
    district        VARCHAR(100) NULL,
    province        VARCHAR(100) NULL,
    payment_method  ENUM('cash','transfer','online') NOT NULL DEFAULT 'cash',
    payment_status  ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    status          ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending',
    total_amount    DECIMAL(15,2) NOT NULL DEFAULT 0,
    note            TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_o_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_details (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    quantity        INT UNSIGNED NOT NULL,
    unit_price      DECIMAL(15,2) NOT NULL COMMENT 'Giá bán tại thời điểm đặt',
    subtotal        DECIMAL(15,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    CONSTRAINT fk_od_order   FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_od_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Tài khoản Admin mặc định: admin / Admin@123
--  Password hash: password_hash('Admin@123', PASSWORD_BCRYPT)
-- ============================================================
INSERT IGNORE INTO users (id, username, email, password, role, status, created_at)
VALUES (1, 'admin', 'admin@phoneshop.vn',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin', 1, NOW());

-- ============================================================
--  Indexes bổ sung cho hiệu năng
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_products_status       ON products(status);
CREATE INDEX IF NOT EXISTS idx_orders_status         ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_created        ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_orders_ward           ON orders(ward);
CREATE INDEX IF NOT EXISTS idx_inventory_log_product ON inventory_log(product_id, created_at);
CREATE INDEX IF NOT EXISTS idx_po_status             ON purchase_orders(status);
