<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdminLoggedIn()) {
    setFlash('error', 'You do not have permission to access this page.');
    redirect('modules/dashboard/');
}

$action = $_GET['action'] ?? '';
$id     = $_GET['id'] ?? '';
$me     = $_SESSION['admin']['id'] ?? null;

/* Block self status/role changes */
if ($id && $me && $id == $me) {
    setFlash('error', 'You cannot change your own status or role here.');
    redirect('modules/user_management/index.php');
}

/* Activate / deactivate */
if (($action === 'activate' || $action === 'deactivate') && $id) {
    try {
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (($stmt->fetch()['role'] ?? '') === 'admin') {
            setFlash('error', 'Administrator status cannot be changed.');
        } else {
            $new_status = $action === 'activate' ? 'active' : 'inactive';
            $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $id]);
            setFlash('success', 'User status updated.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Failed to update user status.');
    }
    redirect('modules/user_management/index.php');
}

/* Delete */
if ($action === 'delete' && $id) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'User deleted.');
        } else {
            setFlash('error', 'The administrator account cannot be deleted.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Failed to delete user.');
    }
    redirect('modules/user_management/index.php');
}

/* Update user */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $id = $_POST['id'];
    if ($me && $id == $me) {
        setFlash('error', 'You cannot change your own status or role here.');
        redirect('modules/user_management/index.php');
    }
    $nama_lengkap = clean($_POST['nama_lengkap'] ?? '');
    $username     = clean($_POST['username'] ?? '');
    $role         = clean($_POST['role'] ?? 'user');
    $status       = clean($_POST['status'] ?? 'pending');

    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            setFlash('error', 'That username is already taken.');
        } else {
            if ($role !== 'admin') {
                $admin_count = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                $current_role = $db->prepare("SELECT role FROM users WHERE id = ?");
                $current_role->execute([$id]);
                if ($admin_count <= 1 && $current_role->fetchColumn() === 'admin') {
                    setFlash('error', 'The last administrator cannot be demoted.');
                    redirect('modules/user_management/index.php');
                }
            }
            $db->prepare("UPDATE users SET nama_lengkap = ?, username = ?, role = ?, status = ? WHERE id = ?")
               ->execute([$nama_lengkap, $username, $role, $status, $id]);
            setFlash('success', 'User updated successfully.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Failed to update user.');
    }
    redirect('modules/user_management/index.php');
}

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$count_admin = 0; $count_active = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') $count_admin++;
    if ($u['status'] === 'active') $count_active++;
}

$page_title = 'User Management';
$page_subtitle = 'Accounts, roles and access';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Administration</span>
        <h2>User Management</h2>
        <p><?= count($users) ?> accounts &middot; <?= $count_admin ?> admins &middot; <?= $count_active ?> active</p>
    </div>
</div>

<div class="grid grid-3" style="margin-bottom:18px">
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total Users</span><span class="kpi-icon indigo"><span class="material-symbols-rounded">group</span></span></div>
        <div class="kpi-value"><?= count($users) ?></div>
        <div class="kpi-sub">Registered accounts</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Administrators</span><span class="kpi-icon amber"><span class="material-symbols-rounded">shield_person</span></span></div>
        <div class="kpi-value"><?= $count_admin ?></div>
        <div class="kpi-sub">Full access accounts</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Active Users</span><span class="kpi-icon green"><span class="material-symbols-rounded">how_to_reg</span></span></div>
        <div class="kpi-value"><?= $count_active ?></div>
        <div class="kpi-sub">Can sign in right now</div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>All Users</h3><p>Manage roles and access</p></div>
    </div>
    <div style="overflow-x:auto">
        <div class="list" style="min-width:820px">
            <div class="list-head" style="grid-template-columns: 2fr 1.2fr 0.9fr 0.9fr 1fr 150px;">
                <span>User</span><span>Username</span><span>Role</span><span>Status</span><span>Registered</span><span class="cell-right">Actions</span>
            </div>
            <?php foreach ($users as $user): ?>
            <div class="list-row" style="grid-template-columns: 2fr 1.2fr 0.9fr 0.9fr 1fr 150px;">
                <span style="display:flex;align-items:center;gap:10px">
                    <span class="avatar"><?= initials($user['nama_lengkap']) ?></span>
                    <span>
                        <span class="cell-strong" style="display:block"><?= htmlspecialchars($user['nama_lengkap']) ?><?= ($me && $user['id'] == $me) ? ' <span class="badge badge-muted" style="font-size:10px">You</span>' : '' ?></span>
                        <span class="cell-muted" style="font-size:12px"><?= htmlspecialchars($user['email'] ?? '') ?></span>
                    </span>
                </span>
                <span class="cell-muted">@<?= htmlspecialchars($user['username']) ?></span>
                <span><span class="badge <?= $user['role'] === 'admin' ? 'badge-info' : 'badge-muted' ?>"><?= ucfirst($user['role']) ?></span></span>
                <span>
                    <?php $sc = $user['status'] === 'active' ? 'badge-success' : ($user['status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>
                    <span class="badge <?= $sc ?>"><?= ucfirst($user['status']) ?></span>
                </span>
                <span class="cell-muted"><?= formatDate($user['created_at']) ?></span>
                <span class="cell-actions">
                    <button class="icon-btn btn-icon" title="Edit" onclick='openModal("editUserModal", <?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                    <?php if ($user['role'] !== 'admin'): ?>
                        <?php if ($user['status'] === 'active'): ?>
                            <button class="icon-btn btn-icon" title="Deactivate" onclick="confirmAction('<?= BASE_URL ?>/modules/user_management/index.php?action=deactivate&id=<?= (int) $user['id'] ?>', 'Deactivate this user?')">
                                <span class="material-symbols-rounded">toggle_on</span>
                            </button>
                        <?php else: ?>
                            <button class="icon-btn btn-icon" title="Activate" onclick="confirmAction('<?= BASE_URL ?>/modules/user_management/index.php?action=activate&id=<?= (int) $user['id'] ?>', 'Activate this user?')">
                                <span class="material-symbols-rounded">toggle_off</span>
                            </button>
                        <?php endif; ?>
                        <button class="icon-btn btn-icon" title="Delete" style="color:var(--danger)" onclick="confirmDelete('<?= BASE_URL ?>/modules/user_management/index.php?action=delete&id=<?= (int) $user['id'] ?>', 'Delete this user?')">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                    <?php else: ?>
                        <span class="badge badge-muted"><span class="material-symbols-rounded" style="font-size:14px">lock</span> Protected</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Edit user modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-card">
        <form action="<?= BASE_URL ?>/modules/user_management/index.php" method="POST">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id">
            <div class="modal-head">
                <h3>Edit User</h3>
                <button type="button" class="icon-btn" data-close-modal="editUserModal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label>Full name *</label><input type="text" name="nama_lengkap" class="input" required></div>
                    <div class="field"><label>Username *</label><input type="text" name="username" class="input" required></div>
                </div>
                <div class="form-grid cols-2" style="margin-top:14px">
                    <div class="field">
                        <label>Role *</label>
                        <select name="role" class="input" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Status *</label>
                        <select name="status" class="input" required>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close-modal="editUserModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>