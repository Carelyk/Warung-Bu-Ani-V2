<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    setFlash('error', 'Please sign in to access the inventory.');
    redirect('modules/auth/login.php');
}

// Every write in this module arrives by POST, so validate the CSRF token once
// here instead of in each handler below.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = $_POST['id'] ?? $_GET['id'] ?? '';

/* ---------------------------------------------------------------------
 * Add product
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $kode_barang = clean($_POST['kode_barang'] ?? '');
    $nama_barang = clean($_POST['nama_barang'] ?? '');
    $kategori    = clean($_POST['kategori'] ?? 'ATK');
    $sub_kategori = clean($_POST['sub_kategori'] ?? '');
    $jumlah      = (int) preg_replace('/\D/', '', $_POST['jumlah'] ?? '0');
    $satuan_type = clean($_POST['satuan_type'] ?? 'pcs');
    $harga_beli  = preg_replace('/\D/', '', $_POST['harga_beli'] ?? '0');
    $harga       = preg_replace('/\D/', '', $_POST['harga'] ?? '0');
    if ($harga_beli === '') $harga_beli = '0';
    if ($harga === '') $harga = '0';
    $min_stock   = (int) preg_replace('/\D/', '', $_POST['min_stock'] ?? '10');
    $barcode     = clean($_POST['barcode'] ?? '');
    $lokasi_rak  = clean($_POST['lokasi_rak'] ?? '');
    $user_id     = getActiveUser()['id'] ?? null;

    // --- Validation / normalisation ---
    $nama_barang = trim($nama_barang);
    $name_len = function_exists('mb_strlen') ? mb_strlen($nama_barang) : strlen($nama_barang);
    if ($name_len < 2) {
        setFlash('error', 'Product name must be at least 2 characters.');
        redirect('modules/inventori/inventori.php');
    }
    $nama_barang = limitText($nama_barang, 80);
    $kategori    = oneOf($kategori, productCategories(), productCategories()[0]);
    $satuan_type = oneOf($satuan_type, productUnits(), 'pcs');
    $kode_barang = limitText($kode_barang, 20);
    $barcode     = limitText($barcode, 30);
    $jumlah     = min(1000, max(0, $jumlah));
    $min_stock  = min(1000, max(0, $min_stock));
    $harga      = max(0, min(9999999, (int) $harga));
    $harga_beli = max(0, min(9999999, (int) $harga_beli));
    if (!rackIsValid($lokasi_rak)) $lokasi_rak = '';
    if ($sub_kategori === '' || !subCategoryIsValid($kategori, $sub_kategori)) $sub_kategori = defaultSubCategory($kategori);
    if ($barcode === '') $barcode = generateBarcode($db);

    if ($kode_barang === '') $kode_barang = generateKodeBarang($kategori);

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM barang WHERE kode_barang = ?");
        $stmt->execute([$kode_barang]);
        if ($stmt->fetchColumn() > 0) {
            setFlash('error', 'A product with that SKU already exists.');
        } else {
            $stmt = $db->prepare("INSERT INTO barang
                (kode_barang, nama_barang, kategori, sub_kategori, jumlah, satuan_type, harga_beli, harga, min_stock, barcode, lokasi_rak, tanggal_input, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$kode_barang, $nama_barang, $kategori, $sub_kategori, $jumlah, $satuan_type, $harga_beli, $harga, $min_stock, $barcode, $lokasi_rak, $user_id]);
            setFlash('success', 'Product added successfully.');
        }
    } catch (PDOException $e) {
        error_log('Add product failed: ' . $e->getMessage());
        setFlash('error', 'Could not add the product. Please try again.');
    }
    redirect('modules/inventori/inventori.php');
}

/* ---------------------------------------------------------------------
 * Edit product
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id          = clean($_POST['id'] ?? '');
    $kode_barang = clean($_POST['kode_barang'] ?? '');
    $nama_barang = clean($_POST['nama_barang'] ?? '');
    $kategori    = clean($_POST['kategori'] ?? 'ATK');
    $sub_kategori = clean($_POST['sub_kategori'] ?? '');
    $jumlah      = (int) preg_replace('/\D/', '', $_POST['jumlah'] ?? '0');
    $satuan_type = clean($_POST['satuan_type'] ?? 'pcs');
    $harga_beli  = preg_replace('/\D/', '', $_POST['harga_beli'] ?? '0');
    $harga       = preg_replace('/\D/', '', $_POST['harga'] ?? '0');
    if ($harga_beli === '') $harga_beli = '0';
    if ($harga === '') $harga = '0';
    $min_stock   = (int) preg_replace('/\D/', '', $_POST['min_stock'] ?? '10');
    $barcode     = clean($_POST['barcode'] ?? '');
    $lokasi_rak  = clean($_POST['lokasi_rak'] ?? '');

    // --- Validation / normalisation ---
    $nama_barang = trim($nama_barang);
    $name_len = function_exists('mb_strlen') ? mb_strlen($nama_barang) : strlen($nama_barang);
    if ($name_len < 2) {
        setFlash('error', 'Product name must be at least 2 characters.');
        redirect('modules/inventori/inventori.php');
    }
    $nama_barang = limitText($nama_barang, 80);
    $kategori    = oneOf($kategori, productCategories(), productCategories()[0]);
    $satuan_type = oneOf($satuan_type, productUnits(), 'pcs');
    $kode_barang = limitText($kode_barang, 20);
    $barcode     = limitText($barcode, 30);
    $jumlah     = min(1000, max(0, $jumlah));
    $min_stock  = min(1000, max(0, $min_stock));
    $harga      = max(0, min(9999999, (int) $harga));
    $harga_beli = max(0, min(9999999, (int) $harga_beli));
    if (!rackIsValid($lokasi_rak)) $lokasi_rak = '';
    if ($sub_kategori === '' || !subCategoryIsValid($kategori, $sub_kategori)) $sub_kategori = defaultSubCategory($kategori);
    if ($barcode === '') $barcode = generateBarcode($db);

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM barang WHERE kode_barang = ? AND id != ?");
        $stmt->execute([$kode_barang, $id]);
        if ($stmt->fetchColumn() > 0) {
            setFlash('error', 'That SKU is already used by another product.');
        } else {
            $stmt = $db->prepare("UPDATE barang SET
                kode_barang = ?, nama_barang = ?, kategori = ?, sub_kategori = ?, jumlah = ?,
                satuan_type = ?, harga_beli = ?, harga = ?, min_stock = ?, barcode = ?, lokasi_rak = ?
                WHERE id = ?");
            $stmt->execute([$kode_barang, $nama_barang, $kategori, $sub_kategori, $jumlah,
                $satuan_type, $harga_beli, $harga, $min_stock, $barcode, $lokasi_rak, $id]);
            setFlash('success', 'Product updated successfully.');
        }
    } catch (PDOException $e) {
        error_log('Update product failed: ' . $e->getMessage());
        setFlash('error', 'Could not update the product. Please try again.');
    }
    redirect('modules/inventori/inventori.php');
}

/* ---------------------------------------------------------------------
 * Stock out (record a sale)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'stock_out') {
    $id       = clean($_POST['id'] ?? '');
    $quantity = (int) preg_replace('/\D/', '', $_POST['quantity'] ?? '0');
    $price    = (float) preg_replace('/[^0-9.]/', '', $_POST['price_at_transaction'] ?? '0');
    $date     = normalizeDate($_POST['date'] ?? '', date('Y-m-d'));
    $note     = clean($_POST['note'] ?? '');
    $nama_barang = clean($_POST['nama_barang'] ?? '');
    $user_id  = getActiveUser()['id'] ?? null;

    if (!$user_id) {
        setFlash('error', 'You must sign in to record a sale.');
        redirect('modules/inventori/inventori.php');
    }

    if ($quantity <= 0) {
        setFlash('error', 'Quantity must be greater than zero.');
        redirect('modules/inventori/inventori.php');
    }

    $total = $quantity * $price;
    $keterangan = limitText('Sale: ' . $nama_barang . ($note ? ' (' . $note . ')' : ''), 1000);

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT jumlah FROM barang WHERE id = ?");
        $stmt->execute([$id]);
        $current_stock = $stmt->fetchColumn();

        if ($current_stock === false) {
            throw new Exception('The product no longer exists.');
        }
        if ($quantity > $current_stock) {
            throw new Exception("Not enough stock. Available: $current_stock.");
        }

        $db->prepare("UPDATE barang SET jumlah = jumlah - ? WHERE id = ?")->execute([$quantity, $id]);
        $db->prepare("INSERT INTO keuangan (tanggal, keterangan, kategori, jenis, metode, jumlah, user_id, barang_id)
                      VALUES (?, ?, 'Product Sales', 'masuk', 'cash', ?, ?, ?)")
           ->execute([$date, $keterangan, $total, $user_id, $id]);

        $db->commit();
        setFlash('success', 'Sale recorded. Stock and finance were updated.');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        // Business-rule messages (stock, missing product) are safe to show;
        // unexpected database errors are not, so they go to the log instead.
        $message = $e instanceof PDOException
            ? 'Could not record the sale due to a database error.'
            : $e->getMessage();
        if ($e instanceof PDOException) error_log('Stock out failed: ' . $e->getMessage());
        setFlash('error', $message);
    }
    redirect('modules/inventori/inventori.php');
}

/* ---------------------------------------------------------------------
 * Delete product
 * ------------------------------------------------------------------- */
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->prepare("DELETE FROM barang WHERE id = ?")->execute([$id]);
        setFlash('success', 'Product deleted.');
    } catch (PDOException $e) {
        // The row may still be referenced by a finance entry (FK constraint).
        error_log('Delete product failed: ' . $e->getMessage());
        setFlash('error', 'Could not delete the product. It may still be linked to a transaction.');
    }
    redirect('modules/inventori/inventori.php');
}

// A destructive action that arrived by GET is refused explicitly, so the user
// gets clear feedback instead of a page that silently ignores the click.
if ($action === 'delete' && $id) {
    setFlash('error', 'Delete must be submitted through the page buttons.');
    redirect('modules/inventori/inventori.php');
}

/* ---------------------------------------------------------------------
 * Data
 * ------------------------------------------------------------------- */
$barang = $db->query("SELECT * FROM barang ORDER BY nama_barang ASC")->fetchAll();

$total_products  = count($barang);
$inventory_value = 0.0;
$low_count = 0; $out_count = 0;
foreach ($barang as $b) {
    $inventory_value += (float) $b['jumlah'] * (float) $b['harga'];
    if ((int) $b['jumlah'] <= 0) $out_count++;
    if ((int) $b['jumlah'] <= (int) $b['min_stock']) $low_count++;
}

$categories = ['ATK', 'Snacks', 'Drinks', 'Household'];
$units = ['pcs', 'pack', 'box', 'pair', 'ream', 'bottle', 'can', 'sachet', 'unit', 'lusin'];

$preselect_low = (($_GET['filter'] ?? '') === 'low');

$page_title = 'Inventory';
$page_subtitle = 'Manage products, stock levels and pricing';
require_once __DIR__ . '/../../layout/header.php';

/** Helpers for the view */
function row_search($b) {
    return strtolower(implode(' ', [
        $b['kode_barang'], $b['nama_barang'], $b['kategori'], $b['sub_kategori'],
        $b['barcode'], $b['lokasi_rak'],
    ]));
}

/** Render the fixed rack-location dropdown (grouped by aisle). */
function renderRackSelect($selected = '') {
    echo '<select name="lokasi_rak" class="input">';
    echo '<option value="">&mdash; Select rack &ndash;</option>';
    foreach (rackLocations() as $group => $racks) {
        echo '<optgroup label="' . htmlspecialchars($group) . '">';
        foreach ($racks as $r) {
            $sel = ($r === $selected) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($r) . '"' . $sel . '>' . htmlspecialchars($r) . '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select>';
}
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Warehouse</span>
        <h2>Inventory</h2>
        <p><?= $total_products ?> products &middot; <?= $low_count ?> low stock &middot; <?= $out_count ?> out of stock</p>
    </div>
    <div class="page-head-actions">
        <a href="<?= BASE_URL ?>/modules/inventori/inventori.php?filter=low" class="btn btn-ghost <?= $preselect_low ? 'active' : '' ?>">
            <span class="material-symbols-rounded">warning</span> Low Stock
        </a>
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <span class="material-symbols-rounded">add</span> Add Product
        </button>
    </div>
</div>

<!-- Mini stats -->
<div class="grid grid-kpi" style="margin-bottom:18px">
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total Products</span><span class="kpi-icon indigo"><span class="material-symbols-rounded">inventory_2</span></span></div>
        <div class="kpi-value"><?= $total_products ?></div>
        <div class="kpi-sub">Across <?= count($categories) ?> categories</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Stock Value</span><span class="kpi-icon green"><span class="material-symbols-rounded">sell</span></span></div>
        <div class="kpi-value"><?= formatCurrency($inventory_value) ?></div>
        <div class="kpi-sub">Retail value on hand</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Low Stock</span><span class="kpi-icon amber"><span class="material-symbols-rounded">notification_important</span></span></div>
        <div class="kpi-value"><?= $low_count ?></div>
        <div class="kpi-sub">Below minimum level</div>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-label">Out of Stock</span><span class="kpi-icon red"><span class="material-symbols-rounded">remove_shopping_cart</span></span></div>
        <div class="kpi-value"><?= $out_count ?></div>
        <div class="kpi-sub">Needs reorder</div>
    </div>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <div class="toolbar-search">
        <span class="material-symbols-rounded">search</span>
        <input type="text" placeholder="Search by name, SKU, barcode or rack..." data-search-scope="inventory">
    </div>

    <div class="segmented" data-cat-scope="inventory">
        <button class="active" data-cat="all">All</button>
        <?php foreach ($categories as $c): ?>
            <button data-cat="<?= $c ?>"><?= $c ?></button>
        <?php endforeach; ?>
    </div>

    <select class="input" id="statusFilter" data-status-scope="inventory" style="min-width:150px">
        <option value="all">All statuses</option>
        <option value="low" <?= $preselect_low ? 'selected' : '' ?>>Low / Out of stock</option>
        <option value="ok">In stock</option>
    </select>

    <div class="segmented" data-view-group>
        <button data-view-toggle="list" class="active" title="List view"><span class="material-symbols-rounded">view_list</span></button>
        <button data-view-toggle="grid" title="Grid view"><span class="material-symbols-rounded">grid_view</span></button>
    </div>
</div>

<!-- List view -->
<div class="card js-view" data-view="list">
    <div style="overflow-x:auto">
        <div class="list" id="invList" data-scope="inventory" style="min-width:960px">
            <div class="list-head" style="grid-template-columns: 2.4fr 1fr 0.9fr 1.1fr 1.1fr 1fr 130px;">
                <span>Product</span><span>Category</span><span>Stock</span><span class="cell-right">Cost</span><span class="cell-right">Sell Price</span><span>Status</span><span class="cell-right">Actions</span>
            </div>

            <?php foreach ($barang as $b):
                $st = stockStatus($b);
                $row_status = ((int) $b['jumlah'] <= (int) $b['min_stock']) ? 'low' : 'ok';
            ?>
            <div class="list-row <?= $row_status === 'low' ? 'row-danger' : '' ?>"
                 data-row data-cat="<?= htmlspecialchars($b['kategori']) ?>" data-status="<?= $row_status ?>"
                 data-search="<?= htmlspecialchars(row_search($b)) ?>"
                 style="grid-template-columns: 2.4fr 1fr 0.9fr 1.1fr 1.1fr 1fr 130px;">
                <span>
                    <span class="cell-strong" style="display:block"><?= htmlspecialchars($b['nama_barang']) ?></span>
                    <span class="cell-muted" style="font-size:12px"><?= htmlspecialchars($b['kode_barang']) ?><?= $b['sub_kategori'] ? ' &middot; ' . htmlspecialchars($b['sub_kategori']) : '' ?></span>
                </span>
                <span><span class="cat-chip cat-<?= htmlspecialchars($b['kategori']) ?>"><?= htmlspecialchars($b['kategori']) ?></span></span>
                <span class="cell-strong"><?= (int) $b['jumlah'] ?> <span class="cell-muted" style="font-size:12px"><?= htmlspecialchars($b['satuan_type']) ?></span></span>
                <span class="cell-right cell-muted"><?= formatCurrency($b['harga_beli']) ?></span>
                <span class="cell-right cell-strong"><?= formatCurrency($b['harga']) ?></span>
                <span><span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span></span>
                <span class="cell-actions">
                    <button class="icon-btn btn-icon" title="Record sale" onclick='openStockOutModal(<?= (int) $b['id'] ?>, <?= json_encode($b['nama_barang']) ?>, <?= (float) $b['harga'] ?>, <?= (int) $b['jumlah'] ?>)'>
                        <span class="material-symbols-rounded">point_of_sale</span>
                    </button>
                    <button class="icon-btn btn-icon" title="Edit" onclick='openModal("editModal", <?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                    <button class="icon-btn btn-icon" title="Delete" style="color:var(--danger)" onclick="confirmDelete('<?= BASE_URL ?>/modules/inventori/inventori.php?action=delete&id=<?= (int) $b['id'] ?>', 'Delete <?= htmlspecialchars(addslashes($b['nama_barang'])) ?>?', '<?= e(csrfToken()) ?>')">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </span>
            </div>
            <?php endforeach; ?>

            <div class="empty-state" data-empty-scope="inventory" style="display:none">
                <span class="material-symbols-rounded">search_off</span>
                <h4>No products found</h4>
                <p>Try a different search or filter.</p>
            </div>
        </div>
    </div>
</div>

<!-- Grid view -->
<div class="card js-view" data-view="grid" style="display:none;padding:20px">
    <div class="product-grid" id="invGrid" data-scope="inventory">
        <?php foreach ($barang as $b):
            $st = stockStatus($b);
            $row_status = ((int) $b['jumlah'] <= (int) $b['min_stock']) ? 'low' : 'ok';
        ?>
        <div class="product-card"
             data-row data-cat="<?= htmlspecialchars($b['kategori']) ?>" data-status="<?= $row_status ?>"
             data-search="<?= htmlspecialchars(row_search($b)) ?>">
            <div class="product-top">
                <div class="product-thumb"><span class="material-symbols-rounded"><?= $b['kategori'] === 'Drinks' ? 'local_drink' : ($b['kategori'] === 'Snacks' ? 'fastfood' : ($b['kategori'] === 'Household' ? 'home' : 'edit_note')) ?></span></div>
                <span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
            </div>
            <div>
                <p class="product-name"><?= htmlspecialchars($b['nama_barang']) ?></p>
                <p class="product-meta"><?= htmlspecialchars($b['kode_barang']) ?><?= $b['sub_kategori'] ? ' &middot; ' . htmlspecialchars($b['sub_kategori']) : '' ?></p>
            </div>
            <div class="row-between">
                <span class="product-price"><?= formatCurrency($b['harga']) ?></span>
                <span class="product-stock"><?= (int) $b['jumlah'] ?> <?= htmlspecialchars($b['satuan_type']) ?></span>
            </div>
            <div class="product-foot">
                <span class="cat-chip cat-<?= htmlspecialchars($b['kategori']) ?>"><?= htmlspecialchars($b['kategori']) ?></span>
                <span class="product-actions">
                    <button class="icon-btn btn-icon" title="Record sale" onclick='openStockOutModal(<?= (int) $b['id'] ?>, <?= json_encode($b['nama_barang']) ?>, <?= (float) $b['harga'] ?>, <?= (int) $b['jumlah'] ?>)'><span class="material-symbols-rounded">point_of_sale</span></button>
                    <button class="icon-btn btn-icon" title="Edit" onclick='openModal("editModal", <?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><span class="material-symbols-rounded">edit</span></button>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="empty-state" data-empty-scope="inventory" style="display:none;grid-column:1/-1">
            <span class="material-symbols-rounded">search_off</span>
            <h4>No products found</h4>
        </div>
    </div>
</div>

<!-- Pagination -->
<div class="card" style="margin-top:18px">
    <div class="pager" data-pager-scope="inventory">
        <div class="pager-info">Showing <strong data-pager-from>0</strong>–<strong data-pager-to>0</strong> of <strong data-pager-total>0</strong> products</div>
        <div class="pager-controls">
            <label class="pager-size">Show
                <select data-pager-size class="input">
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select> rows
            </label>
            <div class="pager-nav">
                <button type="button" class="btn btn-ghost btn-sm" data-pager-prev><span class="material-symbols-rounded">chevron_left</span> Prev</button>
                <span class="pager-pages" data-pager-pages></span>
                <button type="button" class="btn btn-ghost btn-sm" data-pager-next>Next <span class="material-symbols-rounded">chevron_right</span></button>
            </div>
            <div class="pager-jump">Go to <input type="number" min="1" class="input" data-pager-jump> <button type="button" class="btn btn-ghost btn-sm" data-pager-go>Go</button></div>
        </div>
    </div>
</div>

<script>window.SUB_CATEGORIES = <?= json_encode(subCategories()) ?>;</script>

<!-- ============ Add modal ============ -->
<div class="modal-overlay" id="addModal">
    <div class="modal-card modal-lg">
        <form action="<?= BASE_URL ?>/modules/inventori/inventori.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-head">
                <h3>Add Product</h3>
                <button type="button" class="icon-btn" data-close-modal="addModal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-grid cols-2">
                    <div class="field">
                        <label>SKU (auto)</label>
                        <input type="text" name="kode_barang" value="<?= htmlspecialchars(generateKodeBarang()) ?>" class="input" readonly>
                    </div>
                    <div class="field">
                        <label>Product name *</label>
                        <input type="text" name="nama_barang" class="input" required minlength="2" maxlength="80" autocomplete="off" placeholder="e.g. Standard Ballpoint Pen">
                        <small class="field-hint">2&ndash;80 characters.</small>
                    </div>
                    <div class="field">
                        <label>Category *</label>
                        <select name="kategori" class="input" required>
                            <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= categoryLabel($c) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Sub-category *</label>
                        <select name="sub_kategori" class="input" required></select>
                    </div>
                    <div class="field">
                        <label>Quantity *</label>
                        <div class="stepper">
                            <button type="button" class="stepper-btn" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                            <input type="text" name="jumlah" class="input rupiah-input" inputmode="numeric" autocomplete="off" required placeholder="0" data-min="0" data-max="1000">
                            <button type="button" class="stepper-btn" data-step="1" aria-label="Increase quantity">+</button>
                        </div>
                        <small class="field-hint">0 &ndash; 1,000.</small>
                    </div>
                    <div class="field">
                        <label>Unit *</label>
                        <select name="satuan_type" class="input" required>
                            <?php foreach ($units as $u): ?><option value="<?= $u ?>"><?= ucfirst($u) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Cost price</label>
                        <div class="input-wrap"><span class="prefix">Rp</span><input type="text" name="harga_beli" class="input rupiah-input" inputmode="numeric" autocomplete="off" placeholder="0" data-min="0" data-max="9999999"></div>
                        <small class="field-hint">Max Rp 9,999,999.</small>
                    </div>
                    <div class="field">
                        <label>Selling price *</label>
                        <div class="input-wrap"><span class="prefix">Rp</span><input type="text" name="harga" class="input rupiah-input" inputmode="numeric" autocomplete="off" required placeholder="0" data-min="0" data-max="9999999"></div>
                        <small class="field-hint">Max Rp 9,999,999.</small>
                    </div>
                    <div class="field">
                        <label>Minimum stock</label>
                        <div class="stepper">
                            <button type="button" class="stepper-btn" data-step="-1" aria-label="Decrease minimum stock">&minus;</button>
                            <input type="text" name="min_stock" class="input rupiah-input" inputmode="numeric" autocomplete="off" value="10" data-min="0" data-max="1000">
                            <button type="button" class="stepper-btn" data-step="1" aria-label="Increase minimum stock">+</button>
                        </div>
                        <small class="field-hint">0 &ndash; 1,000.</small>
                    </div>
                    <div class="field">
                        <label>Barcode</label>
                        <input type="text" name="barcode" class="input" maxlength="13" inputmode="numeric" autocomplete="off" placeholder="Auto-generate if empty">
                        <small class="field-hint">Scan or type the barcode. Leave empty to auto-generate.</small>
                    </div>
                    <div class="field">
                        <label>Rack location</label>
                        <?php renderRackSelect(''); ?>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close-modal="addModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Edit modal ============ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-card modal-lg">
        <form action="<?= BASE_URL ?>/modules/inventori/inventori.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id">
            <div class="modal-head">
                <h3>Edit Product</h3>
                <button type="button" class="icon-btn" data-close-modal="editModal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-grid cols-2">
                    <div class="field"><label>SKU</label><input type="text" name="kode_barang" class="input" required></div>
                    <div class="field"><label>Product name *</label><input type="text" name="nama_barang" class="input" required minlength="2" maxlength="80" autocomplete="off"></div>
                    <div class="field">
                        <label>Category *</label>
                        <select name="kategori" class="input" required>
                            <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= categoryLabel($c) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Sub-category *</label><select name="sub_kategori" class="input" required></select></div>
                    <div class="field"><label>Quantity *</label><div class="stepper"><button type="button" class="stepper-btn" data-step="-1" aria-label="Decrease quantity">&minus;</button><input type="text" name="jumlah" class="input rupiah-input" inputmode="numeric" autocomplete="off" required data-min="0" data-max="1000"><button type="button" class="stepper-btn" data-step="1" aria-label="Increase quantity">+</button></div></div>
                    <div class="field">
                        <label>Unit *</label>
                        <select name="satuan_type" class="input" required>
                            <?php foreach ($units as $u): ?><option value="<?= $u ?>"><?= ucfirst($u) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Cost price</label><div class="input-wrap"><span class="prefix">Rp</span><input type="text" name="harga_beli" class="input rupiah-input" inputmode="numeric" autocomplete="off" data-min="0" data-max="9999999"></div></div>
                    <div class="field"><label>Selling price *</label><div class="input-wrap"><span class="prefix">Rp</span><input type="text" name="harga" class="input rupiah-input" inputmode="numeric" autocomplete="off" required data-min="0" data-max="9999999"></div></div>
                    <div class="field"><label>Minimum stock</label><div class="stepper"><button type="button" class="stepper-btn" data-step="-1" aria-label="Decrease minimum stock">&minus;</button><input type="text" name="min_stock" class="input rupiah-input" inputmode="numeric" autocomplete="off" data-min="0" data-max="1000"><button type="button" class="stepper-btn" data-step="1" aria-label="Increase minimum stock">+</button></div></div>
                    <div class="field"><label>Barcode</label><input type="text" name="barcode" class="input" maxlength="13" inputmode="numeric" autocomplete="off" placeholder="Auto-generate if empty"></div>
                    <div class="field"><label>Rack location</label><?php renderRackSelect(''); ?></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close-modal="editModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Stock out modal ============ -->
<div class="modal-overlay" id="stockOutModal">
    <div class="modal-card">
        <form action="<?= BASE_URL ?>/modules/inventori/inventori.php" method="POST" id="stockOutForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="stock_out">
            <input type="hidden" name="id" id="stockOutItemId">
            <input type="hidden" name="nama_barang" id="stockOutItemName">
            <input type="hidden" name="price_at_transaction" id="stockOutPrice">
            <div class="modal-head">
                <h3>Record Sale</h3>
                <button type="button" class="icon-btn" data-close-modal="stockOutModal"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="modal-body">
                <div class="card-pad" style="background:var(--surface-2);border-radius:12px;margin-bottom:16px">
                    <div class="row-between">
                        <div>
                            <div class="cell-strong" id="stockOutItemLabel">—</div>
                            <div class="cell-muted" style="font-size:12px">Unit price: <span id="stockOutUnitPrice">—</span></div>
                        </div>
                        <span class="badge badge-muted">Available: <span id="stockOutAvailable">0</span></span>
                    </div>
                </div>
                <div class="form-grid cols-2">
                    <div class="field">
                        <label>Quantity sold *</label>
                        <input type="text" name="quantity" id="stockOutQuantity" class="input rupiah-input" inputmode="numeric" autocomplete="off" required placeholder="0">
                        <small class="field-hint" id="stockOutHint"></small>
                    </div>
                    <div class="field">
                        <label>Total</label>
                        <div class="input-wrap"><span class="prefix">Rp</span><input type="text" id="stockOutTotal" class="input" readonly></div>
                    </div>
                </div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="field">
                        <label>Date &amp; time</label>
                        <input type="datetime-local" name="date" id="stockOutDate" value="<?= date('Y-m-d\TH:i') ?>" class="input" required>
                    </div>
                    <div class="field">
                        <label>Note</label>
                        <textarea name="note" rows="2" class="input" placeholder="Optional note"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close-modal="stockOutModal">Cancel</button>
                <button type="submit" class="btn btn-primary"><span class="material-symbols-rounded">point_of_sale</span> Save Sale</button>
            </div>
        </form>
    </div>
</div>

<script>
/* Stock-out modal helpers */
let soUnitPrice = 0;
let soStock = 0;
function openStockOutModal(id, name, price, stock) {
    soUnitPrice = parseFloat(price) || 0;
    soStock = parseInt(stock, 10) || 0;
    document.getElementById('stockOutItemId').value = id;
    document.getElementById('stockOutItemName').value = name;
    document.getElementById('stockOutPrice').value = soUnitPrice;
    document.getElementById('stockOutItemLabel').textContent = name;
    document.getElementById('stockOutUnitPrice').textContent = 'Rp ' + new Intl.NumberFormat('en-US').format(soUnitPrice);
    document.getElementById('stockOutAvailable').textContent = soStock;
    const qty = document.getElementById('stockOutQuantity');
    qty.value = '';
    const hint = document.getElementById('stockOutHint');
    if (hint) { hint.textContent = 'Maximum ' + soStock + ' in stock.'; hint.classList.remove('field-hint-error'); }
    updateStockOutTotal();
    openModal('stockOutModal');
}
function updateStockOutTotal() {
    const qty = window.cleanNumber ? cleanNumber(document.getElementById('stockOutQuantity').value) : 0;
    document.getElementById('stockOutTotal').value = 'Rp ' + new Intl.NumberFormat('en-US').format(qty * soUnitPrice);
    const hint = document.getElementById('stockOutHint');
    if (hint) {
        if (qty > soStock) {
            hint.textContent = 'Only ' + soStock + ' in stock.';
            hint.classList.add('field-hint-error');
        } else {
            hint.textContent = 'Max ' + soStock + ' available.';
            hint.classList.remove('field-hint-error');
        }
    }
}
document.addEventListener('DOMContentLoaded', function () {
    const qty = document.getElementById('stockOutQuantity');
    if (qty) qty.addEventListener('input', updateStockOutTotal);
    const form = document.getElementById('stockOutForm');
    if (form) form.addEventListener('submit', e => {
        const value = window.cleanNumber ? cleanNumber(qty.value) : 0;
        if (value <= 0) {
            e.preventDefault();
            if (window.toast) toast('warning', 'Enter a quantity greater than zero.');
        } else if (value > soStock) {
            e.preventDefault();
            if (window.toast) toast('error', 'Not enough stock. Available: ' + soStock + '.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>