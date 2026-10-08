<?php
/**
 * ===================================================================
 * Definisi Base URL Dinamis
 * ===================================================================
 * Kode ini secara otomatis akan membuat URL dasar (base URL)
 * untuk proyek Anda, sehingga bisa berjalan di mana saja tanpa
 * perlu mengubah path secara manual.
 */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? null) == 443
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); // HTTPS di belakang proxy/tunnel (Cloudflare, dll.)
$protocol = $https ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$project_root = preg_replace('/\/modules\/.+|\/config|\/includes|\/api/', '', $script_path);
$base_url = rtrim($protocol . $host . $project_root, '/');
define('BASE_URL', $base_url ?: 'http://localhost/warung-bu-ani');

/**
 * Zona waktu aplikasi
 */
date_default_timezone_set('Asia/Jakarta');

/**
 * Konfigurasi Database untuk Lingkungan Lokal (XAMPP)
 */
try {
    $host = 'localhost';
    $dbname = 'sistem_inventori';
    $username = 'root';
    $password = ''; // Standar XAMPP tidak memiliki password

    // Membuat koneksi PDO ke database
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Tampilkan error jika koneksi gagal
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Ambil data sebagai associative array
    ]);
} catch (PDOException $e) {
    // --- DEBUGGING: Hentikan eksekusi dan tampilkan error koneksi yang sebenarnya ---
    die("Database Connection Error: " . $e->getMessage());
}
?>
