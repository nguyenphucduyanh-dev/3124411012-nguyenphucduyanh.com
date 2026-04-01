<?php
// ============================================================
//  admin/includes/auth.php  –  Kiểm tra quyền Admin
//  Include file này ở đầu MỌI trang trong /admin/
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_admin_logged_in(): bool {
    return isset($_SESSION['admin_id'])
        && isset($_SESSION['admin_role'])
        && $_SESSION['admin_role'] === 'admin';
}

function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php');
        header("Location: /admin/login.php?redirect={$redirect}");
        exit;
    }
}

function current_admin(): array {
    return [
        'id'       => $_SESSION['admin_id']       ?? 0,
        'username' => $_SESSION['admin_username']  ?? '',
        'email'    => $_SESSION['admin_email']     ?? '',
    ];
}

// Gọi ngay để bảo vệ trang
require_admin_login();
