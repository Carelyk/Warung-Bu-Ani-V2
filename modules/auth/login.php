<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (isAdminLoggedIn() || isUserLoggedIn()) {
    redirect('modules/dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    // Prevent session fixation: new ID once privileges change.
                    refreshSession();

                    $session = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nama_lengkap' => $user['nama_lengkap'],
                        'role' => $user['role'],
                    ];
                    if ($user['role'] === 'admin') {
                        $_SESSION['admin'] = $session;
                    } else {
                        $_SESSION['user'] = $session;
                    }
                    redirect('modules/dashboard/');
                } else {
                    $error = 'Your account is not active yet. Please contact an administrator.';
                }
            } else {
                $error = 'Incorrect username or password.';
            }
        } catch (PDOException $e) {
            error_log('Login query failed: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in &middot; <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-50..200">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .auth-wrap { display: grid; grid-template-columns: 1.05fr 1fr; min-height: 100vh; }
        .auth-aside {
            position: relative; overflow: hidden; padding: 56px; color: #fff;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #8b5cf6 100%);
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .auth-aside::after {
            content: ''; position: absolute; width: 520px; height: 520px; border-radius: 50%;
            background: rgba(255,255,255,.10); top: -160px; right: -160px;
        }
        .auth-aside::before {
            content: ''; position: absolute; width: 380px; height: 380px; border-radius: 50%;
            background: rgba(255,255,255,.08); bottom: -140px; left: -120px;
        }
        .auth-aside .auth-inner { position: relative; z-index: 2; }
        .auth-stat { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22); border-radius: 16px; padding: 16px 18px; backdrop-filter: blur(4px); }
        .auth-main { display: flex; align-items: center; justify-content: center; padding: 40px 24px; background: var(--bg); }
        .auth-card { width: 100%; max-width: 420px; }
        .pw-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-soft); display: flex; }
        @media (max-width: 900px) { .auth-wrap { grid-template-columns: 1fr; } .auth-aside { display: none; } }
    </style>
</head>
<body class="app-body">
<div class="auth-wrap">
    <aside class="auth-aside">
        <div class="auth-inner" style="display:flex;align-items:center;gap:14px">
            <span style="width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center">
                <span class="material-symbols-rounded" style="font-size:30px">storefront</span>
            </span>
            <div>
                <strong style="font-size:20px;display:block"><?= APP_NAME ?></strong>
                <small style="opacity:.85">Inventory &amp; Finance System</small>
            </div>
        </div>

        <div class="auth-inner">
            <h1 style="font-size:38px;font-weight:800;line-height:1.15;margin:0 0 14px">Run your shop with clarity.</h1>
            <p style="font-size:16px;opacity:.9;max-width:440px;margin:0">Track stock, record sales and follow your cash flow — all in one modern dashboard built for daily warung operations.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:34px;max-width:460px">
                <div class="auth-stat"><span class="material-symbols-rounded" style="font-size:22px">inventory_2</span><div style="font-weight:700;margin-top:6px">Smart Inventory</div><small style="opacity:.85">ATK, snacks &amp; drinks</small></div>
                <div class="auth-stat"><span class="material-symbols-rounded" style="font-size:22px">account_balance_wallet</span><div style="font-weight:700;margin-top:6px">Cash Flow</div><small style="opacity:.85">Income &amp; expenses</small></div>
            </div>
        </div>

        <div class="auth-inner" style="font-size:13px;opacity:.8">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <div style="margin-bottom:26px">
                <h2 style="margin:0;font-size:26px;font-weight:800;color:var(--text)">Welcome back</h2>
                <p style="margin:6px 0 0;color:var(--text-muted)">Sign in to continue to your dashboard.</p>
            </div>

            <?php if ($error): ?>
            <div class="toast error" style="position:static;margin-bottom:18px;min-width:0;max-width:none;animation:none">
                <span class="material-symbols-rounded">error</span>
                <div class="toast-body"><strong>Sign in failed</strong><p><?= htmlspecialchars($error) ?></p></div>
            </div>
            <?php endif; ?>

            <form method="POST" style="display:flex;flex-direction:column;gap:16px">
                <?= csrfField() ?>
                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="prefix"><span class="material-symbols-rounded" style="font-size:20px">person</span></span>
                        <input id="username" name="username" type="text" class="input" required autofocus placeholder="e.g. admin" autocomplete="username">
                    </div>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="prefix"><span class="material-symbols-rounded" style="font-size:20px">lock</span></span>
                        <input id="password" name="password" type="password" class="input" required placeholder="Enter your password" autocomplete="current-password" style="padding-right:44px">
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
                            <span class="material-symbols-rounded" style="font-size:20px">visibility</span>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;margin-top:6px">
                    <span class="material-symbols-rounded">login</span> Sign in
                </button>
            </form>

            <p style="margin-top:22px;text-align:center;color:var(--text-soft);font-size:12.5px">
                Default demo account: <strong style="color:var(--text-muted)">admin / admin123</strong>
            </p>
        </div>
    </main>
</div>

<script>
document.getElementById('pwToggle').addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon = this.querySelector('.material-symbols-rounded');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.textContent = show ? 'visibility_off' : 'visibility';
});
</script>
</body>
</html>