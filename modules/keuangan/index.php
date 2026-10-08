<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdminLoggedIn()) {
    setFlash('error', 'You do not have permission to access this page.');
    redirect('modules/dashboard/');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = $_POST['id'] ?? $_GET['id'] ?? '';

$income_categories  = ['Product Sales', 'Other Income'];
$expense_categories = ['Stock Purchase', 'Salary', 'Rent', 'Utilities', 'Transport', 'Marketing', 'Other Expense'];
$all_categories     = array_merge($income_categories, $expense_categories);
$methods            = ['cash' => 'Cash', 'transfer' => 'Bank Transfer', 'qris' => 'QRIS'];

/* ---------------------------------------------------------------------
 * Add transaction
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $tanggal    = clean($_POST['tanggal'] ?? date('Y-m-d'));
    $keterangan = clean($_POST['keterangan'] ?? '');
    $jenis      = clean($_POST['jenis'] ?? 'masuk');
    $kategori   = clean($_POST['kategori'] ?? 'Product Sales');
    $metode     = clean($_POST['metode'] ?? 'cash');
    $jumlah     = preg_replace('/\D/', '', $_POST['jumlah'] ?? '0');
    if ($jumlah === '') $jumlah = '0';
    $jumlah     = (string) min(1000000000, (int) $jumlah);
    $user_id    = getActiveUser()['id'] ?? null;

    try {
        $db->prepare("INSERT INTO keuangan (tanggal, keterangan, kategori, jenis, metode, jumlah, user_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$tanggal, $keterangan, $kategori, $jenis, $metode, $jumlah, $user_id]);
        setFlash('success', 'Transaction added successfully.');
    } catch (PDOException $e) {
        setFlash('error', 'Could not add transaction: ' . $e->getMessage());
    }
    redirect('modules/keuangan/index.php');
}

/* ---------------------------------------------------------------------
 * Edit transaction
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id         = clean($_POST['id'] ?? '');
    $tanggal    = clean($_POST['tanggal'] ?? date('Y-m-d'));
    $keterangan = clean($_POST['keterangan'] ?? '');
    $jenis      = clean($_POST['jenis'] ?? 'masuk');
    $kategori   = clean($_POST['kategori'] ?? 'Product Sales');
    $metode     = clean($_POST['metode'] ?? 'cash');
    $jumlah     = preg_replace('/\D/', '', $_POST['jumlah'] ?? '0');
    if ($jumlah === '') $jumlah = '0';
    $jumlah     = (string) min(1000000000, (int) $jumlah);

    try {
        $db->prepare("UPDATE keuangan SET tanggal = ?, keterangan = ?, kategori = ?, jenis = ?, metode = ?, jumlah = ? WHERE id = ?")
           ->execute([$tanggal, $keterangan, $kategori, $jenis, $metode, $jumlah, $id]);
        setFlash('success', 'Transaction updated successfully.');
    } catch (PDOException $e) {
        setFlash('error', 'Failed to update transaction: ' . $e->getMessage());
    }
    redirect('modules/keuangan/index.php');
}

/* ---------------------------------------------------------------------
 * Delete transaction
 * ------------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    try {
        $db->prepare("DELETE FROM keuangan WHERE id = ?")->execute([$id]);
        setFlash('success', 'Transaction deleted.');
    } catch (PDOException $e) {
        setFlash('error', 'Failed to delete transaction: ' . $e->getMessage());
    }
    redirect('modules/keuangan/index.php');
}

/* ---------------------------------------------------------------------
 * Filters + pagination
 * ------------------------------------------------------------------- */
$q        = trim($_GET['q'] ?? '');
$jenis    = in_array($_GET['jenis'] ?? 'all', ['all', 'masuk', 'keluar'], true) ? ($_GET['jenis'] ?? 'all') : 'all';
$kategori = $_GET['kategori'] ?? 'all';
$metode   = $_GET['metode'] ?? 'all';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$per      = in_array((int) ($_GET['per'] ?? 25), [25, 50, 100], true) ? (int) ($_GET['per'] ?? 25) : 25;

$where = []; $params = [];
if ($q !== '') {
    $where[] = "(k.keterangan LIKE ? OR k.kategori LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%";
}
if ($jenis !== 'all') { $where[] = "k.jenis = ?"; $params[] = $jenis; }
if ($kategori !== 'all' && in_array($kategori, $all_categories, true)) { $where[] = "k.kategori = ?"; $params[] = $kategori; }
if ($metode !== 'all' && isset($methods[$metode])) { $where[] = "k.metode = ?"; $params[] = $metode; }
$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM keuangan k $ws");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$stmt = $db->prepare("SELECT k.*, u.nama_lengkap FROM keuangan k
                      LEFT JOIN users u ON k.user_id = u.id
                      $ws ORDER BY k.tanggal DESC, k.id DESC LIMIT $per OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* Global totals */
$tot_income  = (float) $db->query("SELECT COALESCE(SUM(jumlah),0) FROM keuangan WHERE jenis='masuk'")->fetchColumn();
$tot_expense = (float) $db->query("SELECT COALESCE(SUM(jumlah),0) FROM keuangan WHERE jenis='keluar'")->fetchColumn();
$balanace    = $tot_income - $tot_expense;
$tot_count   = (int) $db->query("SELECT COUNT(*) FROM keuangan")->fetchColumn();

$queryString = function (array $overrides = []) {
    $base = ['q' => $_GET['q'] ?? '', 'jenis' => $_GET['jenis'] ?? 'all', 'kategori' => $_GET['kategori'] ?? 'all', 'metode' => $_GET['metode'] ?? 'all', 'per' => $_GET['per'] ?? 25, 'page' => $_GET['page'] ?? 1];
    return http_build_query(array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null));
};
$pageUrl = fn(int $p): string => BASE_URL . '/modules/keuangan/index.php?' . $queryString(['page' => $p]);

/* Filters without page — used by the "jump to page" control. */
$baseQueryNoPage = http_build_query(array_filter(
    ['q' => $q, 'jenis' => $jenis, 'kategori' => $kategori, 'metode' => $metode, 'per' => $per],
    fn($v) => $v !== '' && $v !== null
));
$paginationBase = BASE_URL . '/modules/keuangan/index.php?' . $baseQueryNoPage;

$page_title = 'Cash Flow';
$page_subtitle = 'Income, expenses and balance';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Finance</span>
        <h2>Cash Flow</h2>
        <p>Track every income and expense of the shop.</p>
    </div>
    <div class="page-head-actions">
        <button class="btn btn-primary" onclick="openModal('addKeuanganModal')">
            <span class="material-symbols-rounded">add</span> Add Transaction
        </button>
    </div>
</div>

<!-- Summary -->
<div class="grid grid-kpi" style="margin-bottom:18px">
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total Income</span><span class="kpi-icon green"><span class="material-symbols-rounded">trending_up</span></span></div>
        <div class="kpi-value"><?= formatCurrency($tot_income) ?></div>
        <div class="kpi-sub"><span class="material-symbols-rounded" style="font-size:16px">north_east</span> All recorded income</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total Expense</span><span class="kpi-icon red"><span class="material-symbols-rounded">trending_down</span></span></div>
        <div class="kpi-value"><?= formatCurrency($tot_expense) ?></div>
        <div class="kpi-sub"><span class="material-symbols-rounded" style="font-size:16px">south_east</span> All recorded spending</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Net Balance</span><span class="kpi-icon <?= $balanace >= 0 ? 'green' : 'red' ?>"><span class="material-symbols-rounded">account_balance</span></span></div>
        <div class="kpi-value" style="color: <?= $balanace >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($balanace) ?></div>
        <div class="kpi-sub">Income minus expense</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Transactions</span><span class="kpi-icon indigo"><span class="material-symbols-rounded">receipt_long</span></span></div>
        <div class="kpi-value"><?= number_format($tot_count) ?></div>
        <div class="kpi-sub">Total entries recorded</div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/modules/keuangan/index.php" class="toolbar">
    <input type="hidden" name="per" value="<?= $per ?>">
    <div class="toolbar-search">
        <span class="material-symbols-rounded">search</span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search description or category...">
    </div>
    <div class="segmented">
        <button type="submit" name="jenis" value="all"    class="<?= $jenis === 'all' ? 'active' : '' ?>" formnovalidate>All</button>
        <button type="submit" name="jenis" value="masuk"  class="<?= $jenis === 'masuk' ? 'active' : '' ?>" formnovalidate>Income</button>
        <button type="submit" name="jenis" value="keluar" class="<?= $jenis === 'keluar' ? 'active' : '' ?>" formnovalidate>Expense</button>
    </div>
    <select class="input" name="kategori" onchange="this.form.submit()" style="min-width:170px">
        <option value="all">All categories</option>
        <optgroup label="Income">
            <?php foreach ($income_categories as $c): ?><option value="<?= $c ?>" <?= $kategori === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
        </optgroup>
        <optgroup label="Expense">
            <?php foreach ($expense_categories as $c): ?><option value="<?= $c ?>" <?= $kategori === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
        </optgroup>
    </select>
    <select class="input" name="metode" onchange="this.form.submit()" style="min-width:150px">
        <option value="all">All methods</option>
        <?php foreach ($methods as $k => $label): ?><option value="<?= $k ?>" <?= $metode === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost"><span class="material-symbols-rounded">filter_alt</span> Apply</button>
    <?php if ($q !== '' || $jenis !== 'all' || $kategori !== 'all' || $metode !== 'all'): ?>
        <a href="<?= BASE_URL ?>/modules/keuangan/index.php" class="btn btn-outline">Reset</a>
    <?php endif; ?>
</form>

<!-- List -->
<div class="card">
    <div class="card-head">
        <div><h3>Transactions</h3><p><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></p></div>
        <span class="badge badge-muted">Page <?= $page ?> of <?= $pages ?></span>
    </div>
    <div style="overflow-x:auto">
        <div class="list" style="min-width:900px">
            <div class="list-head" style="grid-template-columns: 120px 130px 2.2fr 160px 110px 1fr 120px;">
                <span>Date</span><span>Type</span><span>Description</span><span>Category</span><span>Method</span><span class="cell-right">Amount</span><span class="cell-right">Actions</span>
            </div>

            <?php if (!$rows): ?>
                <div class="empty-state">
                    <span class="material-symbols-rounded">receipt_long</span>
                    <h4>No transactions found</h4>
                    <p>Try changing your filters.</p>
                </div>
            <?php else: foreach ($rows as $k): ?>
            <div class="list-row" style="grid-template-columns: 120px 130px 2.2fr 160px 110px 1fr 120px;">
                <span class="cell-muted"><?= formatDate($k['tanggal']) ?></span>
                <span>
                    <span class="badge <?= $k['jenis'] === 'masuk' ? 'badge-success' : 'badge-danger' ?>">
                        <span class="material-symbols-rounded" style="font-size:14px"><?= $k['jenis'] === 'masuk' ? 'arrow_downward' : 'arrow_upward' ?></span>
                        <?= $k['jenis'] === 'masuk' ? 'Income' : 'Expense' ?>
                    </span>
                </span>
                <span>
                    <span class="cell-strong" style="display:block"><?= htmlspecialchars($k['keterangan']) ?></span>
                    <span class="cell-muted" style="font-size:12px">by <?= htmlspecialchars($k['nama_lengkap'] ?? 'System') ?></span>
                </span>
                <span><span class="cat-chip"><?= htmlspecialchars($k['kategori'] ?? '—') ?></span></span>
                <span class="cell-muted"><?= htmlspecialchars($methods[$k['metode']] ?? strtoupper($k['metode'] ?? '—')) ?></span>
                <span class="cell-right cell-strong" style="color: <?= $k['jenis'] === 'masuk' ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $k['jenis'] === 'masuk' ? '+' : '−' ?><?= formatCurrency($k['jumlah']) ?>
                </span>
                <span class="cell-actions">
                    <button class="icon-btn btn-icon" title="Edit" onclick='openModal("editKeuanganModal", <?= json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                    <button class="icon-btn btn-icon" title="Delete" style="color:var(--danger)" onclick="confirmDelete('<?= BASE_URL ?>/modules/keuangan/index.php?action=delete&id=<?= (int) $k['id'] ?>', 'Delete this transaction?')">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <?php
        $from = $total === 0 ? 0 : $offset + 1;
        $to   = min($offset + $per, $total);
    ?>
    <div class="pager">
        <div class="pager-info">Showing <strong><?= number_format($from) ?></strong>–<strong><?= number_format($to) ?></strong> of <strong><?= number_format($total) ?></strong> transactions</div>
        <div class="pager-controls">
            <form method="GET" action="<?= BASE_URL ?>/modules/keuangan/index.php" class="pager-size" style="margin:0">
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis) ?>">
                <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
                <input type="hidden" name="metode" value="<?= htmlspecialchars($metode) ?>">
                Show
                <select name="per" onchange="this.form.submit()">
                    <?php foreach ([25, 50, 100] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $per === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select> rows
            </form>
            <div class="pager-nav">
                <a class="btn btn-ghost btn-sm" href="<?= $page <= 1 ? '#' : $pageUrl($page - 1) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>
                    <span class="material-symbols-rounded">chevron_left</span> Prev
                </a>
                <?php
                    $start = max(1, $page - 2);
                    $end   = min($pages, $page + 2);
                    if ($start > 1):
                        ?><a class="pager-page" href="<?= $pageUrl(1) ?>">1</a><?php
                        if ($start > 2): ?><span class="pager-page dots">…</span><?php endif;
                    endif;
                    for ($p = $start; $p <= $end; $p++): ?>
                        <a class="pager-page <?= $p === $page ? 'active' : '' ?>" href="<?= $pageUrl($p) ?>"><?= $p ?></a>
                    <?php endfor;
                    if ($end < $pages):
                        if ($end < $pages - 1): ?><span class="pager-page dots">…</span><?php endif; ?>
                        <a class="btn pager-page" href="<?= $pageUrl($pages) ?>"><?= $pages ?></a>
                    <?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="<?= $page >= $pages ? '#' : $pageUrl($page + 1) ?>" <?= $page >= $pages ? 'aria-disabled="true"' : '' ?>>
                    Next <span class="material-symbols-rounded">chevron_right</span>
                </a>
            </div>
            <div class="pager-jump">
                Go to
                <input type="number" min="1" max="<?= $pages ?>" value="<?= $page ?>" id="finJump" class="input">
                <button type="button" class="btn btn-ghost btn-sm" onclick="var v=parseInt(document.getElementById('finJump').value,10); if(v>=1) location.href='<?= $paginationBase ?>&page='+v;">Go</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ Add modal ============ -->
<div class="modal-overlay" id="addKeuanganModal">
    <div class="modal-card">
        <form action="<?= BASE_URL ?>/modules/keuangan/index.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-head">
                <h3>Add Transaction</h3>
                <button type="button" class="icon-btn" data-close-modal="addKeuanganModal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-grid cols-2">
                    <div class="field"><label>Date *</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="input" required></div>
                    <div class="field">
                        <label>Type *</label>
                        <select name="jenis" class="input js-jenis" required data-target="add">
                            <option value="masuk">Income</option>
                            <option value="keluar">Expense</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="field"><label>Description *</label><textarea name="keterangan" rows="2" class="input" required placeholder="e.g. Daily sales, Electricity bill"></textarea></div>
                </div>
                <div class="form-grid cols-2" style="margin-top:14px">
                    <div class="field">
                        <label>Category *</label>
                        <select name="kategori" class="input"></select>
                    </div>
                    <div class="field">
                        <label>Payment method *</label>
                        <select name="metode" class="input" required>
                            <?php foreach ($methods as $k => $label): ?><option value="<?= $k ?>"><?= $label ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="field"><label>Amount *</label><div class="input-wrap"><span class="prefix">Rp</span><input type="text" name="jumlah" class="input rupiah-input" inputmode="numeric" autocomplete="off" data-min="0" data-max="1000000000" required></div><small class="field-hint">Max Rp 1,000,000,000.</small></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close-modal="addKeuanganModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Edit modal ============ -->
<div class="modal-overlay" id="editKeuanganModal">
    <div class="modal-card">
        <form action="<?= BASE_URL ?>/modules/keuangan/index.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id">
            <div class="modal-head">
                <h3>Edit Transaction</h3>
                <button type="button" class="icon-btn" data-close-modal="editKeuanganModal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-grid cols-2">
                    <div class="field"><label>Date *</label><input type="date" name="tanggal" class="input" required></div>
                    <div class="field">
                        <label>Type *</label>
                        <select name="jenis" class="input js-jenis" required data-target="edit">
                            <option value="masuk">Income</option>
                            <option value="keluar">Expense</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="field"><label>Description *</label><textarea name="keterangan" rows="2" class="input" required></textarea></div>
                </div>
                <div class="form-grid cols-2" style="margin-top:14px">
                    <div class="field"><label>Category *</label><select name="kategori" class="input"></select></div>
                    <div class="field">
                        <label>Payment method *</label>
                        <select name="metode" class="input" required>
                            <?php foreach ($methods as $k => $label): ?><option value="<?= $k ?>"><?= $label ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="field"><label>Amount *</label><div class="input-wrap"><span class="prefix">Rp</span><input type="text" name="jumlah" class="input rupiah-input" inputmode="numeric" autocomplete="off" data-min="0" data-max="1000000000" required></div><small class="field-hint">Max Rp 1,000,000,000.</small></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close-modal="editKeuanganModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const INCOME_CATS  = <?= json_encode($income_categories) ?>;
const EXPENSE_CATS = <?= json_encode($expense_categories) ?>;

function fillCategories(select, type, current) {
    const cats = type === 'masuk' ? INCOME_CATS : EXPENSE_CATS;
    select.innerHTML = cats.map(c => `<option value="${c}">${c}</option>`).join('');
    if (current && cats.includes(current)) select.value = current;
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-jenis').forEach(sel => {
        const modal = sel.closest('.modal-overlay');
        const catSel = modal.querySelector('select[name="kategori"]');
        sel.addEventListener('change', () => fillCategories(catSel, sel.value));
        fillCategories(catSel, sel.value);
    });
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>