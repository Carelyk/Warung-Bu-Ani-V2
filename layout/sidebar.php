<?php
$app_user = $app_user ?? getActiveUser();
$role     = $app_user['role'] ?? 'user';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-logo" aria-hidden="true">
            <svg viewBox="0 0 48 48" width="40" height="40" role="img">
                <defs>
                    <linearGradient id="baGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#6366f1"/>
                        <stop offset="100%" stop-color="#8b5cf6"/>
                    </linearGradient>
                </defs>
                <rect x="2" y="2" width="44" height="44" rx="13" fill="url(#baGrad)"/>
                <path d="M14 33V15h9.2c3.6 0 5.8 1.7 5.8 4.6 0 2-1.1 3.4-3 4 2.4.5 3.9 2.1 3.9 4.4 0 3.2-2.4 5-6.3 5H14zm4.2-10.5h4.2c1.6 0 2.5-.8 2.5-2.1s-.9-2-2.5-2h-4.2v4.1zm0 7.2h4.8c1.8 0 2.8-.9 2.8-2.4s-1-2.4-2.8-2.4h-4.8v4.8z" fill="#fff"/>
            </svg>
        </span>
        <span class="brand-text">
            <strong>Bu Ani Shop</strong>
            <small>Inventory &amp; Finance</small>
        </span>
        <button class="icon-btn sidebar-collapse" id="sidebarCollapse" aria-label="Collapse sidebar">
            <span class="material-symbols-rounded">chevron_left</span>
        </button>
    </div>

    <nav class="sidebar-nav">
        <p class="nav-section">Overview</p>
        <a href="<?= BASE_URL ?>/modules/dashboard/" class="nav-item <?= checkActive('dashboard') ?>">
            <span class="material-symbols-rounded">dashboard</span>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/inventori/inventori.php" class="nav-item <?= checkActive('inventori') ?>">
            <span class="material-symbols-rounded">inventory_2</span>
            <span class="nav-label">Inventory</span>
        </a>

        <?php if ($role === 'admin'): ?>
        <p class="nav-section">Management</p>
        <a href="<?= BASE_URL ?>/modules/keuangan/" class="nav-item <?= checkActive('keuangan') ?>">
            <span class="material-symbols-rounded">account_balance_wallet</span>
            <span class="nav-label">Finance</span>
        </a>
        <a href="<?= BASE_URL ?>/modules/user_management/" class="nav-item <?= checkActive('user_management') ?>">
            <span class="material-symbols-rounded">group</span>
            <span class="nav-label">User Management</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
        <button class="nav-action" id="sidebarTheme">
            <span class="material-symbols-rounded nav-icon-dark">light_mode</span>
            <span class="material-symbols-rounded nav-icon-light">dark_mode</span>
            <span class="nav-label">Theme</span>
        </button>
        <?php // Sign-out is a POST so it cannot be triggered by a third-party GET. ?>
        <form method="POST" action="<?= BASE_URL ?>/modules/auth/logout.php" class="logout-form">
            <?= csrfField() ?>
            <button type="submit" class="nav-action danger">
                <span class="material-symbols-rounded">logout</span>
                <span class="nav-label">Sign out</span>
            </button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>