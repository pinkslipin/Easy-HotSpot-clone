<?php
/**
 * Export Sales Report as Excel (.xls) — SpreadsheetML format
 * Multiple sheets: Summary | By Package | Daily Sales | Transactions | Batches
 */
require_once 'security_helper.php';
secure_session_start();
require_auth();
require_once 'dbconfig.php';

// ── Data Queries ───────────────────────────────────────────────────────────────

function exRevenue(string $where): array {
    global $DB_con;
    $r = $DB_con->query("SELECT
        COUNT(*)                                              AS total_vouchers,
        COALESCE(SUM(price), 0)                              AS total_revenue,
        COUNT(CASE WHEN status = 'Active' THEN 1 END)        AS active,
        COUNT(CASE WHEN status IN ('Used','Over') THEN 1 END) AS used,
        COUNT(CASE WHEN status = 'Expired' THEN 1 END)       AS expired
        FROM hotspot_vouchers WHERE $where")->fetch(PDO::FETCH_ASSOC);
    return $r ?: [];
}

$today   = exRevenue("DATE(created_on) = CURDATE()");
$week    = exRevenue("created_on >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$month   = exRevenue("created_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$allTime = exRevenue("1=1");

$byPackage = $DB_con->query("SELECT
    COALESCE(package_name, limit_uptime, 'Unknown') AS package_name,
    limit_uptime,
    COUNT(*)                        AS qty,
    COALESCE(SUM(price), 0)         AS revenue,
    COUNT(CASE WHEN status = 'Active'              THEN 1 END) AS active,
    COUNT(CASE WHEN status IN ('Used','Over')       THEN 1 END) AS used
    FROM hotspot_vouchers
    WHERE created_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY COALESCE(package_name, limit_uptime), limit_uptime
    ORDER BY revenue DESC")->fetchAll(PDO::FETCH_ASSOC);

$dailySales = $DB_con->query("SELECT
    DATE(created_on)                AS sale_date,
    DAYNAME(created_on)             AS day_name,
    COUNT(*)                        AS vouchers,
    COALESCE(SUM(price), 0)         AS revenue
    FROM hotspot_vouchers
    WHERE created_on >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_on)
    ORDER BY sale_date ASC")->fetchAll(PDO::FETCH_ASSOC);

$transactions = $DB_con->query("SELECT
    created_on,
    user_name,
    password,
    COALESCE(package_name, limit_uptime, 'Unknown') AS package,
    limit_uptime,
    price,
    status,
    COALESCE(created_by, '') AS issued_by,
    booking_id
    FROM hotspot_vouchers
    ORDER BY created_on DESC
    LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$batches = $DB_con->query("SELECT
    batch_id,
    COALESCE(package_name, limit_uptime, 'Unknown') AS package_name,
    limit_uptime,
    COUNT(*)                                         AS total,
    COUNT(CASE WHEN status = 'Active'              THEN 1 END) AS active,
    COUNT(CASE WHEN status IN ('Used','Over')       THEN 1 END) AS used,
    COUNT(CASE WHEN status = 'Expired'             THEN 1 END) AS expired,
    COALESCE(SUM(price), 0)                          AS revenue,
    MIN(created_on)                                  AS created
    FROM hotspot_vouchers
    WHERE batch_id IS NOT NULL
    GROUP BY batch_id, COALESCE(package_name, limit_uptime), limit_uptime
    ORDER BY created DESC
    LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ────────────────────────────────────────────────────────────────────

function xStr(string $v): string {
    return '<Cell><Data ss:Type="String">' . htmlspecialchars($v, ENT_XML1) . '</Data></Cell>';
}
function xNum($v): string {
    return '<Cell><Data ss:Type="Number">' . (is_numeric($v) ? $v : 0) . '</Data></Cell>';
}
function xLabel(string $v): string {
    return '<Cell ss:StyleID="label"><Data ss:Type="String">' . htmlspecialchars($v, ENT_XML1) . '</Data></Cell>';
}
function xHeader(string $v): string {
    return '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($v, ENT_XML1) . '</Data></Cell>';
}
function xTitle(string $v): string {
    return '<Cell ss:StyleID="title"><Data ss:Type="String">' . htmlspecialchars($v, ENT_XML1) . '</Data></Cell>';
}
function xMoney($v): string {
    return '<Cell ss:StyleID="money"><Data ss:Type="Number">' . (is_numeric($v) ? $v : 0) . '</Data></Cell>';
}
function row(string ...$cells): string {
    return '<Row>' . implode('', $cells) . '</Row>';
}
function blankRow(): string {
    return '<Row/>';
}

// ── Output ─────────────────────────────────────────────────────────────────────

$filename = 'MindSpace_Sales_Report_' . date('Y-m-d') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook
    xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">

<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Title>MindSpace Sales Report</Title>
    <Author>MindSpace Hotspot System</Author>
    <Created><?= date('Y-m-d') ?>T00:00:00Z</Created>
</DocumentProperties>

<Styles>
    <Style ss:ID="Default"/>
    <Style ss:ID="title">
        <Font ss:Bold="1" ss:Size="14" ss:Color="#FFFFFF"/>
        <Interior ss:Color="#2D3A8C" ss:Pattern="Solid"/>
        <Alignment ss:Horizontal="Left"/>
    </Style>
    <Style ss:ID="header">
        <Font ss:Bold="1" ss:Color="#FFFFFF"/>
        <Interior ss:Color="#4A5568" ss:Pattern="Solid"/>
        <Alignment ss:Horizontal="Center"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/>
        </Borders>
    </Style>
    <Style ss:ID="label">
        <Font ss:Bold="1" ss:Color="#2D3A8C"/>
    </Style>
    <Style ss:ID="money">
        <NumberFormat ss:Format="&quot;₱&quot;#,##0.00"/>
    </Style>
    <Style ss:ID="alt">
        <Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="dateCell">
        <NumberFormat ss:Format="YYYY-MM-DD HH:MM:SS"/>
    </Style>
</Styles>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SHEET 1: SUMMARY
════════════════════════════════════════════════════════════════════════════ -->
<Worksheet ss:Name="Summary">
<Table ss:DefaultColumnWidth="140">
<Column ss:Width="200"/>
<Column ss:Width="120"/>
<Column ss:Width="120"/>
<Column ss:Width="120"/>
<Column ss:Width="120"/>

<?= row(xTitle('MindSpace Hotspot — Sales Report   Generated: ' . date('F d, Y  H:i'))) ?>
<?= blankRow() ?>

<?= row(xLabel('REVENUE SUMMARY'), xHeader('Today'), xHeader('Last 7 Days'), xHeader('Last 30 Days'), xHeader('All Time')) ?>
<?= row(xStr('Total Revenue'),    xMoney($today['total_revenue']),   xMoney($week['total_revenue']),   xMoney($month['total_revenue']),   xMoney($allTime['total_revenue'])) ?>
<?= row(xStr('Vouchers Sold'),    xNum($today['total_vouchers']),   xNum($week['total_vouchers']),    xNum($month['total_vouchers']),    xNum($allTime['total_vouchers'])) ?>
<?= blankRow() ?>

<?= row(xLabel('VOUCHER STATUS'), xHeader('Today'), xHeader('Last 7 Days'), xHeader('Last 30 Days'), xHeader('All Time')) ?>
<?= row(xStr('Active'),           xNum($today['active']),   xNum($week['active']),   xNum($month['active']),   xNum($allTime['active'])) ?>
<?= row(xStr('Used'),             xNum($today['used']),     xNum($week['used']),     xNum($month['used']),     xNum($allTime['used'])) ?>
<?= row(xStr('Expired'),          xNum($today['expired']),  xNum($week['expired']),  xNum($month['expired']),  xNum($allTime['expired'])) ?>

</Table>
</Worksheet>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SHEET 2: SALES BY PACKAGE (Last 30 Days)
════════════════════════════════════════════════════════════════════════════ -->
<Worksheet ss:Name="Sales by Package">
<Table ss:DefaultColumnWidth="140">
<Column ss:Width="200"/>
<Column ss:Width="100"/>
<Column ss:Width="80"/>
<Column ss:Width="120"/>
<Column ss:Width="80"/>
<Column ss:Width="80"/>

<?= row(xTitle('Sales by Package — Last 30 Days')) ?>
<?= blankRow() ?>
<?= row(xHeader('Package'), xHeader('Duration'), xHeader('Qty Sold'), xHeader('Revenue'), xHeader('Active'), xHeader('Used')) ?>
<?php foreach ($byPackage as $i => $p): ?>
<?= row(
    xStr($p['package_name']),
    xStr($p['limit_uptime'] ?? ''),
    xNum($p['qty']),
    xMoney($p['revenue']),
    xNum($p['active']),
    xNum($p['used'])
) ?>
<?php endforeach; ?>
<?= blankRow() ?>
<?php
    $pkgTotal = array_sum(array_column($byPackage, 'qty'));
    $pkgRev   = array_sum(array_column($byPackage, 'revenue'));
?>
<?= row(xLabel('TOTAL'), xStr(''), xNum($pkgTotal), xMoney($pkgRev), xStr(''), xStr('')) ?>

</Table>
</Worksheet>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SHEET 3: DAILY SALES (Last 7 Days)
════════════════════════════════════════════════════════════════════════════ -->
<Worksheet ss:Name="Daily Sales">
<Table ss:DefaultColumnWidth="140">
<Column ss:Width="120"/>
<Column ss:Width="120"/>
<Column ss:Width="100"/>
<Column ss:Width="120"/>

<?= row(xTitle('Daily Sales — Last 7 Days')) ?>
<?= blankRow() ?>
<?= row(xHeader('Date'), xHeader('Day'), xHeader('Vouchers'), xHeader('Revenue')) ?>
<?php foreach ($dailySales as $d): ?>
<?= row(
    xStr($d['sale_date']),
    xStr($d['day_name']),
    xNum($d['vouchers']),
    xMoney($d['revenue'])
) ?>
<?php endforeach; ?>
<?= blankRow() ?>
<?= row(xLabel('TOTAL'), xStr(''), xNum(array_sum(array_column($dailySales, 'vouchers'))), xMoney(array_sum(array_column($dailySales, 'revenue')))) ?>

</Table>
</Worksheet>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SHEET 4: RECENT TRANSACTIONS (Last 200)
════════════════════════════════════════════════════════════════════════════ -->
<Worksheet ss:Name="Transactions">
<Table ss:DefaultColumnWidth="130">
<Column ss:Width="150"/>
<Column ss:Width="100"/>
<Column ss:Width="80"/>
<Column ss:Width="160"/>
<Column ss:Width="80"/>
<Column ss:Width="100"/>
<Column ss:Width="80"/>
<Column ss:Width="100"/>
<Column ss:Width="100"/>

<?= row(xTitle('Recent Transactions — Last 200 Records')) ?>
<?= blankRow() ?>
<?= row(xHeader('Date / Time'), xHeader('Username'), xHeader('Password'), xHeader('Package'), xHeader('Duration'), xHeader('Price'), xHeader('Status'), xHeader('Issued By'), xHeader('Booking ID')) ?>
<?php foreach ($transactions as $t): ?>
<?= row(
    xStr($t['created_on']),
    xStr($t['user_name']),
    xStr($t['password']),
    xStr($t['package']),
    xStr($t['limit_uptime'] ?? ''),
    xMoney($t['price']),
    xStr($t['status']),
    xStr($t['issued_by']),
    xStr($t['booking_id'] ?? '')
) ?>
<?php endforeach; ?>

</Table>
</Worksheet>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SHEET 5: BATCH PERFORMANCE
════════════════════════════════════════════════════════════════════════════ -->
<Worksheet ss:Name="Batch Performance">
<Table ss:DefaultColumnWidth="130">
<Column ss:Width="160"/>
<Column ss:Width="160"/>
<Column ss:Width="80"/>
<Column ss:Width="60"/>
<Column ss:Width="60"/>
<Column ss:Width="60"/>
<Column ss:Width="60"/>
<Column ss:Width="100"/>
<Column ss:Width="150"/>

<?= row(xTitle('Batch Performance — Last 50 Batches')) ?>
<?= blankRow() ?>
<?= row(xHeader('Batch ID'), xHeader('Package'), xHeader('Duration'), xHeader('Total'), xHeader('Active'), xHeader('Used'), xHeader('Expired'), xHeader('Revenue'), xHeader('Created')) ?>
<?php foreach ($batches as $b): ?>
<?= row(
    xStr($b['batch_id'] ?? ''),
    xStr($b['package_name']),
    xStr($b['limit_uptime'] ?? ''),
    xNum($b['total']),
    xNum($b['active']),
    xNum($b['used']),
    xNum($b['expired']),
    xMoney($b['revenue']),
    xStr($b['created'])
) ?>
<?php endforeach; ?>

</Table>
</Worksheet>

</Workbook>
