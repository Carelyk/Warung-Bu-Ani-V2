<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect('modules/auth/login.php'); }

/* ---------------- Headline metrics ---------------- */
$total_products  = (int) $db->query("SELECT COUNT(*) FROM barang")->fetchColumn();
$inventory_value = (float) $db->query("SELECT COALESCE(SUM(jumlah * harga),0) FROM barang")->fetchColumn();
$low_stock_count = (int) $db->query("SELECT COUNT(*) FROM barang WHERE jumlah <= min_stock")->fetchColumn();
$out_count       = (int) $db->query("SELECT COUNT(*) FROM barang WHERE jumlah <= 0")->fetchColumn();
$low_stock       = $db->query("SELECT * FROM barang WHERE jumlah <= min_stock ORDER BY jumlah ASC LIMIT 6")->fetchAll();

/* ---------------- This month vs last month ---------------- */
$monthStart     = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd   = date('Y-m-t', strtotime('last day of last month'));

$month_sales   = (float) $db->query("SELECT COALESCE(SUM(jumlah),0) FROM keuangan WHERE jenis='masuk'  AND tanggal >= '$monthStart'")->fetchColumn();
$month_expense = (float) $db->query("SELECT COALESCE(SUM(jumlah),0) FROM keuangan WHERE jenis='keluar' AND tanggal >= '$monthStart'")->fetchColumn();
$last_sales    = (float) $db->query("SELECT COALESCE(SUM(jumlah),0) FROM keuangan WHERE jenis='masuk' AND tanggal BETWEEN '$lastMonthStart' AND '$lastMonthEnd'")->fetchColumn();
$net_month     = $month_sales - $month_expense;
$sales_change  = $last_sales > 0 ? (($month_sales - $last_sales) / $last_sales) * 100 : 0;

$today_sales = (float) $db->query("SELECT COALESCE(SUM(jumlah),0) FROM keuangan WHERE jenis='masuk' AND DATE(tanggal)=CURDATE()")->fetchColumn();

/* ---------------- 7-day cash flow series ---------------- */
$days = 7;
$labels = [];
for ($i = $days - 1; $i >= 0; $i--) { $labels[] = date('Y-m-d', strtotime("-$i days")); }
$incomeMap  = $db->query("SELECT DATE(tanggal) d, SUM(jumlah) t FROM keuangan WHERE jenis='masuk'  AND tanggal >= '" . $labels[0] . "' GROUP BY DATE(tanggal)")->fetchAll(PDO::FETCH_KEY_PAIR);
$expenseMap = $db->query("SELECT DATE(tanggal) d, SUM(jumlah) t FROM keuangan WHERE jenis='keluar' AND tanggal >= '" . $labels[0] . "' GROUP BY DATE(tanggal)")->fetchAll(PDO::FETCH_KEY_PAIR);
$chart_labels  = array_map(fn($d) => date('d M', strtotime($d)), $labels);
$chart_income  = array_map(fn($d) => (float) ($incomeMap[$d] ?? 0), $labels);
$chart_expense = array_map(fn($d) => (float) ($expenseMap[$d] ?? 0), $labels);

/* ---------------- Breakdowns (last 30 days) ---------------- */
$expenseBreakdown = $db->query("SELECT kategori, SUM(jumlah) t FROM keuangan WHERE jenis='keluar' AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY kategori ORDER BY t DESC")->fetchAll();
$salesByCategory  = $db->query("SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan,'Daily sales - ',-1),':',1) AS cat, SUM(jumlah) t FROM keuangan WHERE jenis='masuk' AND keterangan LIKE 'Daily sales - %' AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY cat ORDER BY t DESC")->fetchAll();
$stockByCategory  = $db->query("SELECT kategori, COUNT(*) n, SUM(jumlah*harga) v FROM barang GROUP BY kategori ORDER BY v DESC")->fetchAll();
$recent = $db->query("SELECT k.*, u.nama_lengkap FROM keuangan k LEFT JOIN users u ON k.user_id=u.id ORDER BY k.tanggal DESC, k.id DESC LIMIT 8")->fetchAll();

/* Sparkline helper */
function spark_path($values, $w = 130, $h = 48) {
    $values = array_values($values);
    $n = count($values);
    if ($n < 2) return '';
    $max = max($values); $min = min($values);
    $range = ($max - $min) ?: 1;
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $i / ($n - 1) * $w;
        $y = $h - (($v - $min) / $range) * ($h - 8) - 4;
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    return implode(' ', $pts);
}

$page_title = 'Dashboard';
$page_subtitle = 'Business overview for ' . APP_NAME;
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Overview</span>
        <h2>Welcome back, <?= htmlspecialchars(explode(' ', getActiveUser()['nama_lengkap'] ?? 'there')[0]) ?> 👋</h2>
        <p>Here is what is happening at your shop today, <?= date('d M Y') ?>.</p>
    </div>
    <div class="page-head-actions">
        <a href="<?= BASE_URL ?>/modules/inventori/inventori.php" class="btn btn-ghost">
            <span class="material-symbols-rounded">inventory_2</span> Manage Inventory
        </a>
        <a href="<?= BASE_URL ?>/modules/keuangan/" class="btn btn-primary">
            <span class="material-symbols-rounded">add</span> New Transaction
        </a>
    </div>
</div>

<!-- KPI row -->
<div class="grid grid-kpi" style="margin-bottom:18px">
    <div class="kpi">
        <div class="kpi-top">
            <span class="kpi-label">Inventory Value</span>
            <span class="kpi-icon indigo"><span class="material-symbols-rounded">sell</span></span>
        </div>
        <div class="kpi-value"><?= formatCurrency($inventory_value) ?></div>
        <div class="kpi-sub"><span class="material-symbols-rounded" style="font-size:16px">inventory</span> <?= $total_products ?> products in stock</div>
    </div>

    <div class="kpi">
        <div class="kpi-top">
            <div>
                <span class="kpi-label">Sales this month</span>
                <div class="kpi-value"><?= formatCurrency($month_sales) ?></div>
                <div class="kpi-sub">
                    <?php if ($sales_change >= 0): ?>
                        <span class="trend up"><span class="material-symbols-rounded" style="font-size:16px">trending_up</span><?= number_format(abs($sales_change), 1) ?>%</span>
                    <?php else: ?>
                        <span class="trend down"><span class="material-symbols-rounded" style="font-size:16px">trending_down</span><?= number_format(abs($sales_change), 1) ?>%</span>
                    <?php endif; ?>
                    <span>vs last month</span>
                </div>
            </div>
            <div class="kpi-icon green"><span class="material-symbols-rounded">trending_up</span></div>
        </div>
        <svg class="kpi-spark" viewBox="0 0 130 48" preserveAspectRatio="none">
            <polyline points="<?= spark_path($chart_income) ?>" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    <div class="kpi">
        <div class="kpi-top">
            <div>
                <span class="kpi-label">Net Cash Flow (Month)</span>
                <div class="kpi-value" style="color: <?= $net_month >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= formatCurrency($net_month) ?>
                </div>
                <div class="kpi-sub">
                    <span class="trend up"><span class="material-symbols-rounded" style="font-size:16px">south_west</span> <?= formatCurrencyShort($month_expense) ?></span> expense
                </div>
            </div>
            <span class="kpi-icon <?= $net_month >= 0 ? 'green' : 'red' ?>">
                <span class="material-symbols-rounded"><?= $net_month >= 0 ? 'trending_up' : 'trending_down' ?></span>
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-top">
            <div>
                <span class="kpi-label">Low Stock Alerts</span>
                <div class="kpi-value"><?= $low_stock_count ?> <span style="font-size:14px;font-weight:600;color:var(--text-muted)">items</span></div>
                <div class="kpi-sub">
                    <?php if ($out_count > 0): ?>
                        <span class="trend down"><span class="material-symbols-rounded" style="font-size:16px">error</span><?= $out_count ?> out of stock</span>
                    <?php else: ?>
                        <span class="trend up"><span class="material-symbols-rounded" style="font-size:16px">check_circle</span>All stocked</span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="kpi-icon amber"><span class="material-symbols-rounded">notification_important</span></span>
        </div>
    </div>
</div>

<!-- Cash flow + expense breakdown -->
<div class="grid grid-2-1" style="margin-bottom:18px">
    <div class="card">
        <div class="card-head">
            <div>
                <h3>Cash Flow</h3>
                <p>Income vs expense</p>
            </div>
            <div class="segmented" id="periodButtons">
                <button data-period="1m">1M</button>
                <button data-period="3m">3M</button>
                <button data-period="1y">1Y</button>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="cashflowChart"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h3>Expense Breakdown</h3><p>Last 30 days</p></div>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="expenseChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Category insight row -->
<div class="grid grid-3" style="margin-bottom:18px">
    <div class="card">
        <div class="card-head"><div><h3>Sales by Category</h3><p>Last 30 days</p></div></div>
        <div class="card-body"><div class="chart-box"><canvas id="salesCatChart"></canvas></div></div>
    </div>

    <div class="card">
        <div class="card-head"><div><h3>Stock Value</h3><p>By category</p></div></div>
        <div class="card-body">
            <?php
            $maxVal = 0; foreach ($stockByCategory as $s) { $maxVal = max($maxVal, (float) $s['v']); }
            foreach ($stockByCategory as $s):
                $pct = $maxVal > 0 ? ((float) $s['v'] / $maxVal) * 100 : 0;
            ?>
            <div style="margin-bottom:14px">
                <div class="row-between" style="margin-bottom:6px">
                    <span class="cat-chip cat-<?= htmlspecialchars($s['kategori']) ?>"><?= htmlspecialchars(categoryLabel($s['kategori'])) ?></span>
                    <strong style="font-size:13px"><?= formatCurrencyShort($s['v']) ?></strong>
                </div>
                <div class="progress"><span style="width: <?= round($pct) ?>%"></span></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= (int) $s['n'] ?> products</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div><h3>Low Stock Alerts</h3><p><?= $low_stock_count ?> items need restock</p></div>
            <a href="<?= BASE_URL ?>/modules/inventori/inventori.php?filter=low" class="btn btn-ghost btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($low_stock)): ?>
                <div class="empty-state" style="padding:34px 20px">
                    <span class="material-symbols-rounded">check_circle</span>
                    <h4>All good!</h4>
                    <p>No low stock items right now.</p>
                </div>
            <?php else: ?>
                <ul class="muted-list" style="padding:6px 20px">
                    <?php foreach ($low_stock as $item): $st = stockStatus($item); ?>
                    <li>
                        <span style="max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($item['nama_barang']) ?></span>
                        <span class="badge <?= $st['class'] ?>"><?= (int) $item['jumlah'] ?> / <?= (int) $item['min_stock'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent transactions -->
<div class="card">
    <div class="card-head">
        <div><h3>Recent Transactions</h3><p>Latest activity across the shop</p></div>
        <a href="<?= BASE_URL ?>/modules/keuangan/" class="btn btn-ghost btn-sm">Open Finance</a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="list">
            <div class="list-head" style="grid-template-columns: 130px 1fr 130px 130px 150px;">
                <span>Date</span><span>Description</span><span>Category</span><span class="cell-right">Amount</span><span>Recorded by</span>
            </div>
            <?php if (empty($recent)): ?>
                <div class="empty-state"><span class="material-symbols-rounded">receipt_long</span><h4>No transactions yet</h4></div>
            <?php else: foreach ($recent as $t): ?>
            <div class="list-row" style="grid-template-columns: 130px 1fr 130px 130px 150px;">
                <span class="cell-muted"><?= formatDate($t['tanggal']) ?></span>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($t['keterangan']) ?></span>
                <span><span class="badge badge-muted"><?= htmlspecialchars($t['kategori']) ?></span></span>
                <span class="cell-right cell-strong" style="color: <?= $t['jenis'] === 'masuk' ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $t['jenis'] === 'masuk' ? '+' : '−' ?> <?= formatCurrency($t['jumlah']) ?>
                </span>
                <span class="cell-muted"><?= htmlspecialchars($t['nama_lengkap'] ?? 'System') ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
const FLOW_DATA = {
    labels: <?= json_encode($chart_labels) ?>,
    income: <?= json_encode($chart_income) ?>,
    expense: <?= json_encode($chart_expense) ?>
};
const EXPENSE_DATA = {
    labels: <?= json_encode(array_map(fn($r) => $r['kategori'], $expenseBreakdown)) ?>,
    data: <?= json_encode(array_map(fn($r) => (float) $r['t'], $expenseBreakdown)) ?>
};
const SALES_CAT_DATA = {
    labels: <?= json_encode(array_map(fn($r) => $r['cat'], $salesByCategory)) ?>,
    data: <?= json_encode(array_map(fn($r) => (float) $r['t'], $salesByCategory)) ?>
};
const API_BASE = <?= json_encode(BASE_URL . '/modules/dashboard/api_chart_data.php') ?>;

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const c = window.chartColors();
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = c.text;
    Chart.defaults.plugins.tooltip.backgroundColor = c.tooltipBg;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };

    const rupiah = v => 'Rp ' + new Intl.NumberFormat('en-US').format(v);

    /* ----- Cash flow bar chart ----- */
    const flowEl = document.getElementById('cashflowChart');
    let flowChart;
    if (flowEl) {
        const gradient = flowEl.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(16,185,129,.9)');
        gradient.addColorStop(1, 'rgba(16,185,129,.25)');
        flowChart = new Chart(flowEl, {
            type: 'bar',
            data: {
                labels: FLOW_DATA.labels,
                datasets: [
                    { label: 'Income', data: FLOW_DATA.income, backgroundColor: '#6366f1', borderRadius: 6, maxBarThickness: 26 },
                    { label: 'Expense', data: FLOW_DATA.expense, backgroundColor: '#f59e0b', borderRadius: 6, maxBarThickness: 26 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + rupiah(ctx.parsed.y) } }
                },
                scales: {
                    y: { beginAtZero: true, border: { display: false }, grid: { color: c.grid }, ticks: { callback: v => formatShort(v) } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function formatShort(v) {
        if (Math.abs(v) >= 1000000000) return (v / 1000000000).toFixed(1).replace(/\.0$/, '') + ' Miliar';
        if (Math.abs(v) >= 1000000) return (v / 1000000).toFixed(1).replace(/\.0$/, '') + ' Jt';
        if (Math.abs(v) >= 1000) return (v / 1000).toFixed(0) + ' Rb';
        return v;
    }

    /* Period switching */
    const periodButtons = document.querySelectorAll('#periodButtons button');
    const setPeriod = (period) => {
        fetch(API_BASE + '?period=' + period)
            .then(r => r.json())
            .then(data => {
                flowChart.data.labels = data.labels;
                flowChart.data.datasets[0].data = data.income;
                flowChart.data.datasets[1].data = data.expense;
                flowChart.update();
            })
            .catch(err => console.error(err));
    };
    periodButtons.forEach(btn => btn.addEventListener('click', () => {
        periodButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        setPeriod(btn.dataset.period);
    }));
    // Default active: 1M
    const defaultBtn = [...periodButtons].find(b => b.dataset.period === '1m');
    if (defaultBtn) defaultBtn.classList.add('active');

    /* ----- Expense doughnut ----- */
    const exEl = document.getElementById('expenseChart');
    if (exEl) {
        const p = c.palette;
        new Chart(exEl, {
            type: 'doughnut',
            data: {
                labels: EXPENSE_DATA.labels,
                datasets: [{
                    data: EXPENSE_DATA.data,
                    backgroundColor: [p.indigo, p.amber, p.blue, p.red, p.teal, p.violet, p.pink, p.slate],
                    borderWidth: 3, borderColor: document.documentElement.classList.contains('dark') ? '#111a2e' : '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 12, usePointStyle: true } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + rupiah(ctx.parsed) } }
                }
            }
        });
    }

    /* ----- Sales by category doughnut ----- */
    const scEl = document.getElementById('salesCatChart');
    if (scEl) {
        const p = c.palette;
        new Chart(scEl, {
            type: 'doughnut',
            data: {
                labels: SALES_CAT_DATA.labels,
                datasets: [{
                    data: SALES_CAT_DATA.data,
                    backgroundColor: [p.indigo, p.amber, p.blue, p.green, p.violet],
                    borderWidth: 3, borderColor: document.documentElement.classList.contains('dark') ? '#111a2e' : '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 12, usePointStyle: true } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + rupiah(ctx.parsed) } }
                }
            }
        });
    }

    window.addEventListener('themechange', () => location.reload());
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>