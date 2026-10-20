<?php
/**
 * =====================================================================
 *  Bu Ani Shop - Helpers
 *  ---------------------------------------------------------------------
 *  Kumpulan fungsi bantu: session, format uang/tanggal, kategori,
 *  badge, dan utilitas tampilan. Semua label dalam Bahasa Inggris.
 * =====================================================================
 */

if (session_status() == PHP_SESSION_NONE) {
    // Harden the session cookie before the session starts.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // not readable from JavaScript
        'samesite' => 'Lax',  // blocks cross-site POSTs from other origins
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['SERVER_PORT'] ?? null) == 443
                      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
    session_start();
}

if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/warung-bu-ani');
if (!defined('APP_NAME')) define('APP_NAME', 'Bu Ani Shop');

/* ---------------------------------------------------------------------
 * Sanitasi & Navigasi
 * ------------------------------------------------------------------- */

/**
 * Trim and strip slashes from user input.
 *
 * This only normalises a value. It does NOT make it safe for HTML; escaping
 * happens at the point of output, with e().
 */
function clean($data) { return trim(stripslashes((string) $data)); }

/**
 * Escape a value for safe output in HTML.
 *
 * Every place that prints data coming from a request or the database should
 * wrap it in e(). The behaviour matches htmlspecialchars() with UTF-8 and
 * ENT_QUOTES, which is what the templates already assume.
 */
function e($value) { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function redirect($path) {
    $url = BASE_URL . '/' . ltrim($path, '/');
    header("Location: " . $url);
    exit;
}

/* ---------------------------------------------------------------------
 * CSRF protection
 *
 * Every state-changing request (POST, and destructive GET such as logout)
 * must carry a token. Without it, another site could submit the shop's own
 * forms on behalf of a signed-in user.
 * ------------------------------------------------------------------- */

/** Return the per-session CSRF token, generating it on first use. */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden input carrying the CSRF token. */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Abort the request unless it carries a valid CSRF token.
 *
 * Sends the user back to the referring page with a flash message, so a stale
 * form gives a clear explanation instead of a bare error page.
 */
function requireCsrf() {
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrfToken(), $sent)) {
        setFlash('error', 'Sesi kedaluwarsa atau permintaan tidak valid. Silakan coba lagi.');
        $back = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        header('Location: ' . $back);
        exit;
    }
}

/* ---------------------------------------------------------------------
 * Session helpers
 * ------------------------------------------------------------------- */
function isLoggedIn()       { return isset($_SESSION['admin']) || isset($_SESSION['user']); }
function isAdminLoggedIn()  { return isset($_SESSION['admin']); }
function isUserLoggedIn()   { return isset($_SESSION['user']); }

/** Regenerate the session ID after a privilege change, then drop the old file. */
function refreshSession() { session_regenerate_id(true); }

function getActiveUser() {
    return $_SESSION['admin'] ?? ($_SESSION['user'] ?? null);
}

function currentRole() {
    $u = getActiveUser();
    return $u['role'] ?? 'guest';
}

function setFlash($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function renderFlash() {
    if (!isset($_SESSION['flash_message'])) return '';
    $f = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    $type = in_array($f['type'], ['success', 'error', 'warning', 'info']) ? $f['type'] : 'info';
    return '<script>window.__FLASH__ = ' . json_encode(['type' => $type, 'message' => $f['message']]) . ';</script>';
}

/* ---------------------------------------------------------------------
 * Formatting
 * ------------------------------------------------------------------- */
function formatCurrency($amount) {
    return 'Rp ' . number_format((float) ($amount ?? 0), 0, '.', ',');
}

function formatCurrencyShort($amount) {
    $amount = (float) $amount;
    $abs = abs($amount);
    if ($abs >= 1000000000) return 'Rp ' . rtrim(rtrim(number_format($amount / 1000000000, 1, '.', ','), '0'), '.') . ' Miliar';
    if ($abs >= 1000000)    return 'Rp ' . rtrim(rtrim(number_format($amount / 1000000, 1, '.', ','), '0'), '.') . ' Jt';
    if ($abs >= 1000)       return 'Rp ' . rtrim(rtrim(number_format($amount / 1000, 1, '.', ','), '0'), '.') . ' Rb';
    return 'Rp ' . number_format($amount, 0, '.', ',');
}

function formatDate($date) {
    if (empty($date) || $date === '0000-00-00' || strpos((string) $date, '0000-00-00') === 0) return 'N/A';
    try {
        return (new DateTime($date))->format('d M Y');
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}

function formatDateTime($date) {
    if (empty($date) || strpos((string) $date, '0000-00-00') === 0) return 'N/A';
    try {
        return (new DateTime($date))->format('d M Y H:i');
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}

function formatCurrencyShortAxis($amount) {
    $amount = (float) $amount;
    $abs = abs($amount);
    if ($abs >= 1000000000) return number_format($amount / 1000000000, 1, '.', ',') . ' Miliar';
    if ($abs >= 1000000)    return number_format($amount / 1000000, 1, '.', ',') . ' Jt';
    if ($abs >= 1000)       return number_format($amount / 1000, 0, '.', ',') . ' Rb';
    return (string) $amount;
}

/* ---------------------------------------------------------------------
 * Domain helpers
 * ------------------------------------------------------------------- */
/**
 * Warehouse rack locations, grouped by aisle.
 * This is the single source of truth for the rack dropdown and validation.
 * To add/remove racks, just edit this map.
 */
function rackLocations() {
    return [
        'Aisle A' => ['A1', 'A2', 'A3', 'A4', 'A5'],
        'Aisle B' => ['B1', 'B2', 'B3', 'B4', 'B5'],
        'Aisle C' => ['C1', 'C2', 'C3', 'C4', 'C5'],
        'Aisle D' => ['D1', 'D2', 'D3', 'D4', 'D5'],
        'Aisle E' => ['E1', 'E2', 'E3', 'E4', 'E5', 'E6'],
        'Aisle F' => ['F1', 'F2', 'F3', 'F4', 'F5'],
        'Aisle G' => ['G1', 'G2', 'G3', 'G4', 'G5'],
        'Aisle H' => ['H1', 'H2', 'H3', 'H4', 'H5'],
        'Storage' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8'],
    ];
}

/** Flat list of every valid rack code. */
function rackLocationList() {
    $all = [];
    foreach (rackLocations() as $group) {
        $all = array_merge($all, $group);
    }
    return $all;
}

/** True when the rack is empty (allowed) or one of the known codes. */
function rackIsValid($rack) {
    return $rack === '' || $rack === null || in_array($rack, rackLocationList(), true);
}

/**
 * Sub-categories grouped by main category.
 * Single source of truth for the dependent sub-category dropdown.
 */
function subCategories() {
    return [
        'ATK' => ['Writing', 'Notebooks', 'Paper', 'Filing', 'Adhesives', 'Office Tools', 'Printer Supplies', 'IT Accessories', 'Envelopes', 'Batteries'],
        'Snacks' => ['Chips', 'Biscuits', 'Candy', 'Chocolate', 'Wafers', 'Nuts', 'Bakery', 'Instant Food'],
        'Drinks' => ['Coffee', 'Tea', 'Milk', 'Juice', 'Soda', 'Water', 'Energy', 'Isotonic', 'Powder'],
        'Household' => ['Cleaning', 'Toiletries', 'Grocery'],
    ];
}

/** First valid sub-category for a category (used as a safe fallback). */
function defaultSubCategory($kategori) {
    $map = subCategories();
    return $map[$kategori][0] ?? 'General';
}

/** True when $sub is a valid sub-category of $cat. */
function subCategoryIsValid($cat, $sub) {
    $map = subCategories();
    return isset($map[$cat]) && in_array($sub, $map[$cat], true);
}

/**
 * Generate a unique 13-digit internal barcode (prefix 899 = internal use).
 * Pass a PDO connection to guarantee uniqueness against existing rows.
 */
function generateBarcode($db = null) {
    do {
        $code = '899' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        if ($db === null) return $code;
        $stmt = $db->prepare("SELECT COUNT(*) FROM barang WHERE barcode = ?");
        $stmt->execute([$code]);
    } while ((int) $stmt->fetchColumn() > 0);
    return $code;
}

function stockStatus($item) {
    $qty = (int) ($item['jumlah'] ?? 0);
    $min = (int) ($item['min_stock'] ?? 10);
    if ($qty <= 0) {
        return ['key' => 'out', 'label' => 'Out of Stock', 'class' => 'badge-danger'];
    } elseif ($qty <= $min) {
        return ['label' => 'Low Stock', 'class' => 'badge-warning'];
    }
    return ['label' => 'In Stock', 'class' => 'badge-success'];
}

function categoryClass($kategori) {
    $map = [
        'ATK'       => 'cat-atk',
        'Snacks'    => 'snack',
        'Drinks'    => 'drink',
        'Household' => 'home',
    ];
    return $map[$kategori] ?? 'default';
}

function categoryLabel($kategori) {
    $labels = [
        'ATK'       => 'ATK / Stationery',
        'Snacks'    => 'Snacks',
        'Drinks'    => 'Drinks',
        'Household' => 'Household',
    ];
    return $labels[$kategori] ?? $kategori;
}

function transactionLabel($jenis) {
    return $jenis === 'masuk' ? 'Income' : 'Expense';
}

function methodLabel($method) {
    $map = ['cash' => 'Cash', 'transfer' => 'Transfer', 'qris' => 'QRIS'];
    return $map[$method] ?? ucfirst((string) $method);
}

function statusLabel($status) {
    $map = ['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive'];
    return $map[$status] ?? ucfirst((string) $status);
}

function initials($name) {
    $name = trim((string) $name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return strtoupper($first . $last);
}

/** Next item code for a category prefix, e.g. BRG0007. Uses a bound parameter. */
function generateKodeBarang($kategori = 'ATK') {
    global $db;
    $map = ['ATK' => 'BRG', 'Snacks' => 'SNK', 'Drinks' => 'DRK', 'Household' => 'HHD'];
    $prefix = $map[$kategori] ?? 'BRG';
    $stmt = $db->prepare(
        "SELECT MAX(CAST(SUBSTRING(kode_barang, ?) AS UNSIGNED)) AS max_id
           FROM barang WHERE kode_barang LIKE ?"
    );
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    $kode = (int) ($stmt->fetch()['max_id'] ?? 0);
    return $prefix . sprintf("%04d", $kode + 1);
}

function checkActive($path) {
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    return ($current_dir == $path) ? 'active' : '';
}