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
 * Konfigurasi Database
 *
 * Nilai bisa di-override lewat environment variable, sehingga kode yang sama
 * berjalan di XAMPP lokal maupun di hosting tanpa perlu mengedit file ini.
 */
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'sistem_inventori';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

try {
    $db = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // prepared statement asli
    ]);
} catch (PDOException $e) {
    // Pesan mentah dari driver bisa memuat nama host, user, dan detail server,
    // jadi hanya dicatat ke log. Pengguna menerima pesan yang bisa ditindaklanjuti.
    error_log('Database connection failed: ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit(
        "Koneksi database gagal.\n\n" .
        "Periksa konfigurasi berikut di config/database.php:\n" .
        "  host     : {$db_host}\n" .
        "  database : {$db_name}\n" .
        "  user     : {$db_user}\n\n" .
        "Pastikan MySQL sudah berjalan dan database sudah dibuat " .
        "(jalankan setup_database.php). Detail teknis tersimpan di error log server."
    );
}
?>
