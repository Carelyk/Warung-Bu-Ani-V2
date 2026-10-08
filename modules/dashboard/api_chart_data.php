<?php
/**
 * Dashboard chart data API
 *   ?period=3d|7d|1m|3m|1y
 *   &type=cashflow|expense|income|category
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$period = strtolower($_GET['period'] ?? '7d');
$type   = strtolower($_GET['type'] ?? 'cashflow');

$days = ['3d' => 3, '7d' => 7, '1m' => 30, '3m' => 90, '1y' => 365][$period] ?? 7;

$end   = new DateTime('today');
$start = (new DateTime('today'))->sub(new DateInterval("P{$days}D"));

$startStr = $start->format('Y-m-d');
$endStr   = $end->format('Y-m-d');

/* Build a full label range */
$labels = [];
$cursor = clone $start;
while ($cursor <= $end) {
    $labels[] = $cursor->format('Y-m-d');
    $cursor->add(new DateInterval('P1D'));
}
$displayLabels = array_map(fn($d) => (new DateTime($d))->format('d M'), $labels);

if ($type === 'expense' || $type === 'category') {
    $stmt = $db->prepare("SELECT kategori, SUM(jumlah) AS total
                          FROM keuangan
                          WHERE jenis = 'keluar' AND tanggal BETWEEN ? AND ?
                          GROUP BY kategori ORDER BY total DESC");
    $stmt->execute([$startStr, $endStr]);
    $rows = $stmt->fetchAll();
    echo json_encode([
        'labels' => array_map(fn($r) => $r['kategori'], $rows),
        'data'   => array_map(fn($r) => (float) $r['total'], $rows),
    ]);
    exit;
}

if ($type === 'income') {
    $stmt = $db->prepare("SELECT
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, 'Daily sales - ', -1), ':', 1)) AS cat,
            SUM(jumlah) AS total
        FROM keuangan
        WHERE jenis = 'masuk' AND keterangan LIKE 'Daily sales - %' AND tanggal BETWEEN ? AND ?
        GROUP BY cat ORDER BY total DESC");
    $stmt->execute([$startStr, $endStr]);
    $rows = $stmt->fetchAll();
    echo json_encode([
        'labels' => array_map(fn($r) => $r['cat'], $rows),
        'data'   => array_map(fn($r) => (float) $r['total'], $rows),
    ]);
    exit;
}

/* Default: cashflow */
$incomeStmt = $db->prepare("SELECT DATE(tanggal) d, SUM(jumlah) total FROM keuangan
                            WHERE jenis = 'masuk' AND tanggal BETWEEN ? AND ? GROUP BY DATE(tanggal)");
$incomeStmt->execute([$startStr, $endStr]);
$income = $incomeStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$expenseStmt = $db->prepare("SELECT DATE(tanggal) d, SUM(jumlah) total FROM keuangan
                             WHERE jenis = 'keluar' AND tanggal BETWEEN ? AND ? GROUP BY DATE(tanggal)");
$expenseStmt->execute([$startStr, $endStr]);
$expense = $expenseStmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo json_encode([
    'labels'  => $displayLabels,
    'income'  => array_map(fn($d) => (float) ($income[$d] ?? 0), $labels),
    'expense' => array_map(fn($d) => (float) ($expense[$d] ?? 0), $labels),
]);