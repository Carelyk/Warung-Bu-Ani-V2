<?php
/**
 * =====================================================================
 *  Bu Ani Shop - Database Setup & Migration
 *  ---------------------------------------------------------------------
 *  Script ini:
 *   1. Menambah kolom baru pada tabel `barang` dan `keuangan` (migrasi).
 *   2. Mengisi ulang data inventori (ATK, Snacks, Drinks, Household).
 *   3. Mengisi data keuangan yang sudah berkategori (tahun 2026).
 *
 *  Jalankan sekali:  http://localhost/warung-bu-ani/setup_database.php
 *  atau via CLI:     php setup_database.php
 * =====================================================================
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

function out($msg) { echo $msg . PHP_EOL; }

function columnExists(PDO $db, $table, $column) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $s->execute([$table, $column]);
    return (int) $s->fetchColumn() > 0;
}

/* ---------------------------------------------------------------------
 * 1. MIGRASI STRUKTUR
 * ------------------------------------------------------------------- */
out('== Migrating schema ==');

// barang: kolom baru
if (!columnExists($db, 'barang', 'kategori')) {
    $db->exec("ALTER TABLE barang ADD COLUMN kategori VARCHAR(30) NOT NULL DEFAULT 'ATK' AFTER nama_barang");
    out('  + barang.kategori');
}
if (!columnExists($db, 'barang', 'sub_kategori')) {
    $db->exec("ALTER TABLE barang ADD COLUMN sub_kategori VARCHAR(40) NOT NULL DEFAULT 'General' AFTER kategori");
    out('  + barang.sub_kategori');
}
if (!columnExists($db, 'barang', 'harga_beli')) {
    $db->exec("ALTER TABLE barang ADD COLUMN harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER harga");
    out('  + barang.harga_beli');
}
if (!columnExists($db, 'barang', 'barcode')) {
    $db->exec("ALTER TABLE barang ADD COLUMN barcode VARCHAR(30) NULL AFTER min_stock");
    out('  + barang.barcode');
}
if (!columnExists($db, 'barang', 'lokasi_rak')) {
    $db->exec("ALTER TABLE barang ADD COLUMN lokasi_rak VARCHAR(20) NULL AFTER barcode");
    out('  + barang.lokasi_rak');
}
// satuan_type: perlebar dari enum menjadi varchar
$colType = $db->query("SELECT DATA_TYPE FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name='barang' AND column_name='satuan_type'")->fetchColumn();
if ($colType === 'enum') {
    $db->exec("ALTER TABLE barang MODIFY COLUMN satuan_type VARCHAR(20) NOT NULL DEFAULT 'pcs'");
    out('  ~ barang.satuan_type -> VARCHAR(20)');
}

// keuangan: kolom baru
if (!columnExists($db, 'keuangan', 'kategori')) {
    $db->exec("ALTER TABLE keuangan ADD COLUMN kategori VARCHAR(40) NOT NULL DEFAULT 'Other' AFTER keterangan");
    out('  + keuangan.kategori');
}
if (!columnExists($db, 'keuangan', 'metode')) {
    $db->exec("ALTER TABLE keuangan ADD COLUMN metode VARCHAR(20) NOT NULL DEFAULT 'cash' AFTER jenis");
    out('  + keuangan.metode');
}

/* ---------------------------------------------------------------------
 * 2. SEED INVENTORY
 * ------------------------------------------------------------------- */
out('== Seeding inventory ==');

$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("TRUNCATE TABLE keuangan");
$db->exec("TRUNCATE TABLE barang");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");

// format: [nama, kategori, sub_kategori, satuan, harga_beli, harga_jual, stok, min_stock, rak]
$products = [
    // ---------------- ATK / STATIONERY ----------------
    ['Pilot Ballpoint Pen',        'ATK','Writing','pcs',2000,3000,60,12,'A1'],
    ['Standard Ballpoint Pen',     'ATK','Writing','pcs',1200,2000,55,12,'A1'],
    ['Gel Pen 0.5mm',              'ATK','Writing','pcs',2200,3500,40,10,'A1'],
    ['Pencil 2B',                  'ATK','Writing','pcs',600,1000,100,20,'A2'],
    ['Pencil HB',                  'ATK','Writing','pcs',500,900,90,20,'A2'],
    ['Highlighter',                'ATK','Writing','pcs',4500,7000,35,10,'A2'],
    ['Permanent Marker',           'ATK','Writing','pcs',5000,8000,30,8,'A3'],
    ['Whiteboard Marker',          'ATK','Writing','pcs',5500,8500,28,8,'A3'],
    ['Eraser',                     'ATK','Writing','pcs',350,700,80,15,'A2'],
    ['Correction Tape',            'ATK','Writing','pcs',4000,6500,30,10,'A3'],
    ['Plastic Ruler 30cm',         'ATK','Writing','pcs',1300,2500,30,8,'A4'],
    ['Steel Ruler 30cm',           'ATK','Writing','pcs',3500,6000,18,5,'A4'],
    ['Mini Stapler',               'ATK','Office Tools','pcs',6000,9500,24,6,'B1'],
    ['Heavy Duty Stapler',         'ATK','Office Tools','pcs',12000,18000,12,4,'B1'],
    ['Staples Refill No.10',       'ATK','Office Tools','pack',2000,3500,50,12,'B1'],
    ['Scissors',                   'ATK','Office Tools','pcs',8000,13000,20,6,'B2'],
    ['Cutter L-500',               'ATK','Office Tools','pcs',5000,8500,25,8,'B2'],
    ['Cutter Blade Refill',        'ATK','Office Tools','pack',1500,3000,40,10,'B2'],
    ['Glue Stick',                 'ATK','Adhesives','pcs',3500,6000,40,10,'B3'],
    ['Liquid Glue',                'ATK','Adhesives','pcs',4000,7000,30,8,'B3'],
    ['Double Tape',                'ATK','Adhesives','pcs',2500,4500,35,10,'B3'],
    ['Cellophane Tape',            'ATK','Adhesives','pcs',1500,3000,60,15,'B3'],
    ['HVS Paper A4 70gsm',         'ATK','Paper','rim',42000,52000,25,6,'C1'],
    ['HVS Paper F4 70gsm',         'ATK','Paper','rim',40000,50000,20,6,'C1'],
    ['HVS Paper A3 70gsm',         'ATK','Paper','rim',80000,98000,10,3,'C1'],
    ['Cardstock A4 160gsm',        'ATK','Paper','pack',18000,26000,18,5,'C2'],
    ['Manila Paper A4',            'ATK','Paper','pack',15000,22000,16,5,'C2'],
    ['Notebook Sinar Dunia 58',    'ATK','Notebooks','pcs',3000,4500,80,20,'C3'],
    ['Notebook Big Boss 42',       'ATK','Notebooks','pcs',4000,6000,60,15,'C3'],
    ['Drawing Book A4',            'ATK','Notebooks','pcs',5000,8000,40,10,'C4'],
    ['Drawing Book A3',            'ATK','Notebooks','pcs',8000,12000,25,8,'C4'],
    ['Sticky Notes 3x3',           'ATK','Notebooks','pack',6000,9500,30,8,'C4'],
    ['Clear Holder A4',            'ATK','Filing','pcs',6000,9500,30,8,'D1'],
    ['Paper File A4',              'ATK','Filing','pcs',2500,4500,40,10,'D1'],
    ['Ordner Binder A4',           'ATK','Filing','pcs',18000,26000,15,5,'D1'],
    ['Document Box',               'ATK','Filing','pcs',12000,18000,12,4,'D2'],
    ['Expanding File 12 Pocket',   'ATK','Filing','pcs',22000,32000,10,3,'D2'],
    ['Brown Envelope A4',          'ATK','Envelopes','pack',8000,12000,30,8,'D3'],
    ['White Envelope No.2',        'ATK','Envelopes','pack',6000,9500,30,8,'D3'],
    ['Padded Envelope',            'ATK','Envelopes','pcs',2500,4000,40,10,'D3'],
    ['Ink Cartridge Black',        'ATK','Printer Supplies','pcs',45000,65000,14,4,'E1'],
    ['Ink Cartridge Color',        'ATK','Printer Supplies','pcs',55000,78000,10,3,'E1'],
    ['Toner Cartridge 85A',        'ATK','Printer Supplies','pcs',120000,165000,8,2,'E1'],
    ['Ribbon Cartridge',           'ATK','Printer Supplies','pcs',35000,52000,8,3,'E2'],
    ['USB Cable',                  'ATK','IT Accessories','pcs',9000,15000,25,8,'E2'],
    ['HDMI Cable',                 'ATK','IT Accessories','pcs',25000,38000,15,5,'E2'],
    ['Wireless Mouse',             'ATK','IT Accessories','pcs',35000,52000,12,4,'E3'],
    ['Wireless Keyboard',          'ATK','IT Accessories','pcs',65000,90000,8,3,'E3'],
    ['Mini Speaker',               'ATK','IT Accessories','pcs',45000,65000,9,4,'E3'],
    ['Headphone',                  'ATK','IT Accessories','pcs',30000,45000,14,5,'E4'],
    ['Webcam HD',                  'ATK','IT Accessories','pcs',95000,130000,8,3,'E4'],
    ['Microphone',                 'ATK','IT Accessories','pcs',60000,85000,6,2,'E4'],
    ['Flash Drive 16GB',           'ATK','IT Accessories','pcs',35000,55000,20,6,'E5'],
    ['Flash Drive 32GB',           'ATK','IT Accessories','pcs',50000,75000,15,5,'E5'],
    ['Flash Drive 64GB',           'ATK','IT Accessories','pcs',75000,105000,10,3,'E5'],
    ['AA Battery',                 'ATK','Batteries','pcs',2000,3500,100,25,'E6'],
    ['AAA Battery',                'ATK','Batteries','pcs',1700,3000,90,20,'E6'],
    ['9V Battery',                 'ATK','Batteries','pcs',7000,11000,30,8,'E6'],

    // ---------------- SNACKS ----------------
    ['Chitato Potato Chips 68g',   'Snacks','Chips','pcs',9500,12000,40,12,'S1'],
    ['Chiki Balls 55g',            'Snacks','Chips','pcs',4500,6000,50,15,'S1'],
    ['Taro Net 70g',               'Snacks','Chips','pcs',6800,9000,35,10,'S1'],
    ["Lay's 68g",                  'Snacks','Chips','pcs',9500,12000,30,10,'S1'],
    ['Qtela Cassava 60g',          'Snacks','Chips','pcs',6000,8000,35,10,'S2'],
    ['Cheetos 40g',                'Snacks','Chips','pcs',3500,5000,40,12,'S2'],
    ['Piattos 55g',                'Snacks','Chips','pcs',6500,9000,30,10,'S2'],
    ['Peanuts Garuda 60g',         'Snacks','Nuts','pcs',5000,7000,35,10,'S2'],
    ['Atom Peanuts 50g',           'Snacks','Nuts','pcs',4000,6000,30,10,'S2'],
    ['Oreo Original',              'Snacks','Biscuits','pcs',7000,9000,45,12,'S3'],
    ['Roma Kelapa',                'Snacks','Biscuits','pcs',7000,9000,40,12,'S3'],
    ['Roma Marie Susu',            'Snacks','Biscuits','pcs',7800,10000,35,10,'S3'],
    ['Regal Biscuit',              'Snacks','Biscuits','pcs',7000,9500,30,10,'S3'],
    ['Good Time Cookies',          'Snacks','Biscuits','pcs',2000,3000,60,20,'S3'],
    ['Beng Beng',                  'Snacks','Chocolate','pcs',1300,2000,80,25,'S4'],
    ['Kopiko Candy',              'Snacks','Candy','sachet',900,1500,80,25,'S4'],
    ['Relaxa Mint',                'Snacks','Candy','pcs',500,1000,90,30,'S4'],
    ['Mentos',                     'Snacks','Candy','pcs',2200,3000,50,15,'S4'],
    ['Yupi Gummy',                 'Snacks','Candy','pcs',800,1500,70,20,'S4'],
    ['Wafer Tango',                'Snacks','Wafers','pcs',1500,2000,60,20,'S5'],
    ['Nextar Wafer',               'Snacks','Wafers','pcs',1500,2000,60,20,'S5'],
    ['Better Chocolate 60g',       'Snacks','Chocolate','pcs',7800,10000,25,8,'S5'],
    ['SilverQueen 58g',            'Snacks','Chocolate','pcs',9500,13000,20,6,'S5'],
    ['Cadbury Dairy Milk',         'Snacks','Chocolate','pcs',10000,14000,18,6,'S5'],
    ['Indomie Goreng',             'Snacks','Instant Food','pcs',2800,4000,120,30,'S6'],
    ['Pop Mie Cup',                'Snacks','Instant Food','pcs',5000,7000,40,12,'S6'],
    ['Sari Roti',                  'Snacks','Bakery','pcs',10000,13000,20,6,'S7'],
    ['Roti Sisir',                 'Snacks','Bakery','pcs',8000,11000,15,5,'S7'],

    // ---------------- DRINKS ----------------
    ['Aqua 600ml',                 'Drinks','Water','pcs',3000,4000,72,24,'F1'],
    ['Aqua 1500ml',                'Drinks','Water','pcs',5000,6500,36,12,'F1'],
    ['Le Minerale 600ml',          'Drinks','Water','pcs',3000,4000,60,20,'F1'],
    ['Teh Botol Sosro 350ml',      'Drinks','Tea','pcs',3500,5000,60,20,'F2'],
    ['Teh Pucuk 350ml',            'Drinks','Tea','pcs',3500,5000,60,20,'F2'],
    ['Fruit Tea 500ml',            'Drinks','Tea','pcs',5000,7000,40,12,'F2'],
    ['Freshtea 500ml',             'Drinks','Tea','pcs',4500,6500,35,12,'F2'],
    ['Teh Kotak 200ml',            'Drinks','Tea','pcs',3000,4500,50,15,'F2'],
    ['Floridina 350ml',            'Drinks','Tea','pcs',3500,5000,40,12,'F2'],
    ['Pocari Sweat 500ml',         'Drinks','Isotonic','pcs',7000,9000,30,10,'F3'],
    ['Mizone 500ml',               'Drinks','Isotonic','pcs',5000,7000,30,10,'F3'],
    ['You C1000 140ml',            'Drinks','Juice','pcs',3000,4500,40,12,'F3'],
    ['Buavita 250ml',              'Drinks','Juice','pcs',5500,7500,30,10,'F3'],
    ['Ale-Ale 200ml',              'Drinks','Juice','pcs',1800,3000,50,15,'F3'],
    ['Ultra Milk 250ml',           'Drinks','Milk','pcs',5500,7500,36,12,'F4'],
    ['Indomilk 250ml',             'Drinks','Milk','pcs',5000,7000,36,12,'F4'],
    ['Bear Brand 189ml',           'Drinks','Milk','pcs',9000,11000,24,8,'F4'],
    ['Cimory Fresh Milk',          'Drinks','Milk','pcs',6200,8000,24,8,'F4'],
    ['Yakult',                     'Drinks','Milk','pcs',2200,3000,40,15,'F4'],
    ['Coca-Cola 390ml',            'Drinks','Soda','pcs',5300,7000,40,12,'F5'],
    ['Fanta 390ml',                'Drinks','Soda','pcs',5300,7000,35,12,'F5'],
    ['Sprite 390ml',               'Drinks','Soda','pcs',5300,7000,35,12,'F5'],
    ['Big Cola 390ml',             'Drinks','Soda','pcs',3700,5000,40,12,'F5'],
    ['Kapal Api Coffee Sachet',    'Drinks','Coffee','sachet',1300,2000,150,40,'G1'],
    ['Good Day Coffee Sachet',     'Drinks','Coffee','sachet',1300,2000,120,40,'G1'],
    ['Nescafe Sachet',             'Drinks','Coffee','sachet',1700,2500,100,30,'G1'],
    ['ABC Coffee Sachet',          'Drinks','Coffee','sachet',900,1500,120,40,'G1'],
    ['Milo Sachet',                'Drinks','Powder','sachet',1300,2000,100,30,'G2'],
    ['Nutrisari Sachet',           'Drinks','Powder','sachet',600,1000,150,40,'G2'],
    ['Marimas Sachet',             'Drinks','Powder','sachet',300,500,200,50,'G2'],
    ['Pop Ice Sachet',             'Drinks','Powder','sachet',600,1000,180,50,'G2'],
    ['Extra Joss Sachet',          'Drinks','Energy','sachet',900,1500,100,30,'G3'],
    ['Kuku Bima Sachet',           'Drinks','Energy','sachet',1300,2000,90,30,'G3'],

    // ---------------- HOUSEHOLD ----------------
    ['Lifebuoy Soap',              'Household','Toiletries','pcs',3500,5000,30,10,'H1'],
    ['Shampoo Sachet',             'Household','Toiletries','sachet',700,1000,100,30,'H1'],
    ['Toothpaste Sachet',          'Household','Toiletries','sachet',800,1500,80,25,'H1'],
    ['Detergent Sachet',           'Household','Cleaning','sachet',700,1000,100,30,'H2'],
    ['Dish Soap Refill 200ml',     'Household','Cleaning','pcs',3500,5000,24,8,'H2'],
    ['Tissue Roll',                'Household','Cleaning','pcs',4000,6000,30,10,'H2'],
    ['Cooking Oil Sachet',         'Household','Grocery','sachet',3200,4500,60,20,'H3'],
    ['Sugar 250g',                 'Household','Grocery','pcs',6000,8000,20,8,'H3'],
    ['Instant Coffee Refill',      'Household','Grocery','pcs',12000,16000,15,5,'H3'],
    ['Gas LPG 3kg',                'Household','Grocery','pcs',18000,22000,20,6,'H4'],
    ['Eggs (per kg)',              'Household','Grocery','kg',26000,30000,15,5,'H4'],
];

$insertItem = $db->prepare("INSERT INTO barang
    (kode_barang, nama_barang, kategori, sub_kategori, jumlah, satuan_type, harga_beli, harga, min_stock, barcode, lokasi_rak)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$prefix = ['ATK' => 'BRG', 'Snacks' => 'SNK', 'Drinks' => 'DRK', 'Household' => 'HHD'];
$seq = ['BRG' => 0, 'SNK' => 0, 'DRK' => 0, 'HHD' => 0];
$count = 0;
$idsByKategori = [];
foreach ($products as $p) {
    [$name, $kategori, $sub, $satuan, $beli, $jual, $stok, $min, $rak] = $p;
    $pfx = $prefix[$kategori];
    $seq[$pfx]++;
    $kode = $pfx . sprintf('%04d', $seq[$pfx]);
    $barcode = '899' . str_pad((string) (1000000 + $count), 10, '0', STR_PAD_LEFT);
    $insertItem->execute([$kode, $name, $kategori, $sub, $stok, $satuan, $beli, $jual, $min, $barcode, $rak]);
    $count++;
}
out("  Inserted {$count} products.");

/* ---------------------------------------------------------------------
 * 3. SEED KEUANGAN (2026) - berkategori, realistis
 * ------------------------------------------------------------------- */
out('== Seeding finance ==');

$itemIds = $db->query("SELECT id, nama_barang, harga_beli, harga FROM barang")->fetchAll();
$byCat = [];
foreach ($db->query("SELECT id, nama_barang, kategori, harga_beli, harga FROM barang")->fetchAll() as $r) {
    $byCat[$r['kategori']][] = $r;
}

mt_srand(20260101);
$userIds = array_column($db->query("SELECT id FROM users")->fetchAll(), 'id');
if (empty($userIds)) { $userIds = [1]; }

$insertTx = $db->prepare("INSERT INTO keuangan (tanggal, keterangan, kategori, jenis, metode, jumlah, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");

$start = new DateTime('2026-01-01');
$end   = new DateTime('2026-10-08');
$methods = ['cash', 'cash', 'cash', 'qris', 'transfer'];

$txCount = 0;
$salesTotal = 0; $expenseTotal = 0;

while ($start <= $end) {
    $date = $start->format('Y-m-d');
    $dow  = (int) $start->format('N'); // 6=Sat,7=Sun
    $weekendBoost = ($dow >= 6) ? 1.25 : 1.0;

    // --- Total penjualan harian warung (Rp 300.000 - 620.000, weekend lebih ramai) ---
    $dailySales = round(mt_rand(300000, 620000) * $weekendBoost, -2);

    // Pecah penjualan harian per kategori
    $split = [
        ['Snacks',    0.35],
        ['Drinks',    0.35],
        ['ATK',       0.20],
        ['Household', 0.10],
    ];
    foreach ($split as [$cat, $weight]) {
        if (empty($byCat[$cat])) { continue; }
        $item = $byCat[$cat][array_rand($byCat[$cat])];
        $amount = round($dailySales * $weight * (0.85 + mt_rand(0, 30) / 100), -2);
        if ($amount < 5000) { continue; }
        $qty = max(1, (int) round($amount / max($item['harga'], 500)));
        $ket = 'Daily sales - ' . $cat . ': ' . $item['nama_barang'] . ' x' . $qty;
        $insertTx->execute([$date, $ket, 'Product Sales', 'masuk', $methods[array_rand($methods)], $amount, $userIds[array_rand($userIds)]]);
        $salesTotal += $amount; $txCount++;
    }

    // --- Restock harian (Stock Purchase) ~ 58-72% dari penjualan ---
    $cogs = round($dailySales * mt_rand(58, 72) / 100, -2);
    $cat = ['Snacks', 'Drinks', 'ATK', 'Household'][array_rand([0, 1, 2, 3])];
    $item = $byCat[$cat][array_rand($byCat[$cat])];
    $qty = max(1, (int) round($cogs / max($item['harga_beli'], 500)));
    $ket = 'Restock: ' . $item['nama_barang'] . ' x' . $qty;
    $insertTx->execute([$date, $ket, 'Stock Purchase', 'keluar', 'transfer', $cogs, $userIds[array_rand($userIds)]]);
    $expenseTotal += $cogs; $txCount++;

    // --- Transport mingguan (tiap Senin) ---
    if ($dow == 1) {
        $amount = mt_rand(60000, 120000);
        $insertTx->execute([$date, 'Supplier transport', 'Transport', 'keluar', 'cash', $amount, $userIds[array_rand($userIds)]]);
        $expenseTotal += $amount; $txCount++;
    }

    // --- Marketing sesekali ---
    if (mt_rand(0, 100) <= 8) {
        $amount = mt_rand(50000, 150000);
        $insertTx->execute([$date, 'Online promotion', 'Marketing', 'keluar', 'qris', $amount, $userIds[array_rand($userIds)]]);
        $expenseTotal += $amount; $txCount++;
    }

    // --- Operasional bulanan ---
    $day = (int) $start->format('j');
    if ($day == 1) {
        $insertTx->execute([$date, 'Monthly shop rent', 'Rent', 'keluar', 'transfer', 500000, $userIds[array_rand($userIds)]]); $expenseTotal += 500000; $txCount++;
        $insertTx->execute([$date, 'Part-time staff salary', 'Salary', 'keluar', 'transfer', 1500000, $userIds[array_rand($userIds)]]); $expenseTotal += 1500000; $txCount++;
    }
    if ($day == 5) {
        $util = mt_rand(280000, 380000);
        $insertTx->execute([$date, 'Electricity & water', 'Utilities', 'keluar', 'transfer', $util, $userIds[array_rand($userIds)]]); $expenseTotal += $util; $txCount++;
    }

    $start->modify('+1 day');
}
out("  Inserted {$txCount} transactions.");
out("  Sales total   : Rp " . number_format($salesTotal, 0, ',', '.'));
out("  Expense total : Rp " . number_format($expenseTotal, 0, ',', '.'));
out("  Net           : Rp " . number_format($salesTotal - $expenseTotal, 0, ',', '.'));

out('');
out('== DONE. You can delete this file if not needed. ==');