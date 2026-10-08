<?php
require_once __DIR__ . '/../includes/functions.php';

$public_pages = ['login.php', 'register.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!isLoggedIn() && !in_array($current_page, $public_pages)) {
    redirect('modules/auth/login.php');
}

$app_user     = getActiveUser();
$page_title   = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';

// Low stock count for the notification bell
$low_stock_count = 0;
try {
    $low_stock_count = (int) $GLOBALS['db']->query("SELECT COUNT(*) FROM barang WHERE jumlah <= min_stock")->fetchColumn();
} catch (Exception $e) { $low_stock_count = 0; }
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> &middot; <?= APP_NAME ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-50..200">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: {
                            50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',
                            500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
    <script>
        (function () {
            const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body class="app-body">
<?php if (isLoggedIn()): ?>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="app-shell">
        <main class="app-main">
            <header class="topbar">
                <button class="icon-btn topbar-menu" id="topbarMenu" aria-label="Menu">
                    <span class="material-symbols-rounded">menu</span>
                </button>

                <div class="topbar-title">
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                    <?php if ($page_subtitle): ?><p><?= htmlspecialchars($page_subtitle) ?></p><?php endif; ?>
                </div>

                <div class="topbar-actions">
                    <div class="topbar-search">
                        <span class="material-symbols-rounded">search</span>
                        <input type="text" id="globalSearch" placeholder="Search products, transactions...">
                    </div>

                    <button class="icon-btn" id="themeToggle" title="Toggle theme" aria-label="Toggle theme">
                        <span class="material-symbols-rounded theme-icon-dark">light_mode</span>
                        <span class="material-symbols-rounded theme-icon-light">dark_mode</span>
                    </button>

                    <a href="<?= BASE_URL ?>/modules/inventori/inventori.php?filter=low" class="icon-btn" title="Low stock alerts" aria-label="Alerts">
                        <span class="material-symbols-rounded">notifications</span>
                        <?php if ($low_stock_count > 0): ?><span class="nav-dot"><?= $low_stock_count > 99 ? '99+' : $low_stock_count ?></span><?php endif; ?>
                    </a>

                    <div class="user-chip" id="userChip">
                        <span class="avatar"><?= initials($app_user['nama_lengkap'] ?? 'User') ?></span>
                        <span class="user-chip-info">
                            <strong><?= htmlspecialchars($app_user['nama_lengkap'] ?? 'User') ?></strong>
                            <small><?= htmlspecialchars(ucfirst($app_user['role'] ?? 'user')) ?></small>
                        </span>
                        <span class="material-symbols-rounded">expand_more</span>
                        <div class="user-menu">
                            <div class="user-menu-head">
                                <span class="avatar avatar-lg"><?= initials($app_user['nama_lengkap'] ?? 'User') ?></span>
                                <div>
                                    <strong><?= htmlspecialchars($app_user['nama_lengkap'] ?? 'User') ?></strong>
                                    <small>@<?= htmlspecialchars($app_user['username'] ?? 'user') ?></small>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="user-menu-item danger">
                                <span class="material-symbols-rounded">logout</span> Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-content">
<?php else: ?>
            <div class="page-content page-content-bare">
<?php endif; ?>