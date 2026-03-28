-- Bảng dành riêng cho Admin và Nhân viên
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100),
    role ENUM('admin', 'staff') DEFAULT 'staff',
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- Thêm cột trạng thái cho bảng users (khách hàng)
ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1;

-- Chèn một tài khoản admin mẫu (mật khẩu: 123456)
INSERT INTO admins (username, password, fullname, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên', 'admin');
