<?php
session_start();
require_once __DIR__.'/../../includes/functions.php';

// Pastikan role yang ingin logout sudah didefinisikan dengan benar
$role_to_logout = $_GET['role'] ?? null;

if ($role_to_logout === 'admin' && isset($_SESSION['admin'])) {
    unset($_SESSION['admin']);
} elseif ($role_to_logout === 'user' && isset($_SESSION['user'])) {
    unset($_SESSION['user']);
} else {
    // Jika tidak ada role spesifik, hancurkan semua sesi
    session_destroy();
}

// Jika masih ada sesi lain yang aktif, kembali ke dashboard. Jika tidak, ke halaman login.
if (isLoggedIn()) {
    redirect('modules/dashboard/');
} else {
    redirect('modules/auth/login.php');
}
?>