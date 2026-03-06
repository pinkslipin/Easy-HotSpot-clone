<?php
/**
 * Voucher Manager — browse, filter, and delete individual voucher records.
 * Deletions cascade: router user removed + session kicked + DB row gone
 *  → dashboard stats automatically reflect the change (live SQL queries).
 */
require_once 'security_helper.php';
secure_session_start();
require_auth();
require_once 'dbconfig.php';
require_once 'pricing_config.php';

// ── Filters from GET ──────────────────────────────────────────────────────────
$allowed_statuses = ['', 'Active', 'Used', 'Over', 'Expired'];
$status_filter  = isset($_GET['status'])  && in_array($_GET['status'], $allowed_statuses, true)
                  ? $_GET['status'] : '';
$package_filter = isset($_GET['package']) ? trim($_GET['package']) : '';
$date_from      = isset($_GET['from'])    ? trim($_GET['from'])    : '';
$date_to        = isset($_GET['to'])      ? trim($_GET['to'])      : '';
$search         = isset($_GET['q'])       ? trim($_GET['q'])       : '';

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset   = ($page - 1) * $per_page;

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($status_filter !== '') {
    $where[]  = 'status = :status';
    $params[':status'] = $status_filter;
}
if ($package_filter !== '') {
    $where[]  = '(package_id = :pkg OR package_name LIKE :pkglike OR limit_uptime = :pkg)';
    $params[':pkg']     = $package_filter;
    $params[':pkglike'] = '%' . $package_filter . '%';
}
if ($date_from !== '') {
    $where[]  = 'DATE(created_on) >= :dfrom';
    $params[':dfrom'] = $date_from;
}
if ($date_to !== '') {
    $where[]  = 'DATE(created_on) <= :dto';
    $params[':dto'] = $date_to;
}
if ($search !== '') {
    $where[]  = '(user_name LIKE :q OR created_by LIKE :q OR booking_id LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$whereSQL = implode(' AND ', $where);

// Count for pagination
$countStmt = $DB_con->prepare("SELECT COUNT(*) FROM hotspot_vouchers WHERE $whereSQL");
$countStmt->execute($params);
$total_rows  = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch page
$rowStmt = $DB_con->prepare("
    SELECT id, created_on, user_name, password,
           COALESCE(package_name, limit_uptime, 'Unknown') AS package_display,
           limit_uptime, price, status,
           COALESCE(created_by, '') AS issued_by,
           booking_id, batch_id
    FROM   hotspot_vouchers
    WHERE  $whereSQL
    ORDER  BY created_on DESC
    LIMIT  $per_page OFFSET $offset
");
$rowStmt->execute($params);
$vouchers = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct package list for filter dropdown
$pkgRows = $DB_con->query("
    SELECT DISTINCT COALESCE(package_name, limit_uptime, 'Unknown') AS pname
    FROM hotspot_vouchers ORDER BY pname
")->fetchAll(PDO::FETCH_COLUMN);

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<?php include('header.php'); ?>
<style>
body { background: #b8902a; }
.vm-wrap {
    max-width: 1300px;
    margin: 30px auto;
    padding: 0 15px;
}
.vm-card {
    background: #fff;
    border-radius: 10px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
    margin-bottom: 20px;
}
.vm-title {
    font-size: 22px;
    font-weight: 700;
    color: #2D3A8C;
    margin-bottom: 4px;
}
.vm-subtitle { color: #666; font-size: 13px; margin-bottom: 0; }
.filter-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.filter-row .form-group { margin-bottom: 0; }
.filter-row label { font-size: 12px; color: #555; margin-bottom: 3px; display: block; }
.filter-row .form-control { height: 34px; font-size: 13px; }
.btn-filter  { background: #2D3A8C; color: #fff; border: none; height: 34px; padding: 0 16px; border-radius: 4px; font-size: 13px; }
.btn-filter:hover { background: #1a2468; color: #fff; }
.btn-reset   { background: #aaa; color: #fff; border: none; height: 34px; padding: 0 14px; border-radius: 4px; font-size: 13px; }
.btn-reset:hover { background: #888; color: #fff; }
.vm-table th { background: #4A5568; color: #fff; font-size: 12px; white-space: nowrap; }
.vm-table td { font-size: 13px; vertical-align: middle; }
.vm-table tr:hover td { background: #f0f4ff; }
.badge-active   { background: #72bf48; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.badge-used     { background: #6c757d; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.badge-expired  { background: #FF432E; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.badge-over     { background: #fd7e14; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.action-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; background: #fff8e1;
    border: 1px solid #ffe082; border-radius: 6px;
    margin-bottom: 14px;
}
.action-bar span { font-size: 13px; color: #555; }
.btn-delete-sel {
    background: #dc3545; color: #fff; border: none;
    padding: 6px 18px; border-radius: 4px; font-size: 13px; font-weight: 600;
    cursor: pointer;
}
.btn-delete-sel:disabled { background: #ccc; cursor: not-allowed; }
.btn-delete-sel:not(:disabled):hover { background: #a71d2a; }
.pager { display: flex; gap: 6px; justify-content: center; }
.pager a, .pager span {
    padding: 5px 11px; border-radius: 4px; font-size: 13px;
    background: #e9ecef; color: #333; text-decoration: none;
}
.pager .active-page { background: #2D3A8C; color: #fff; }
.pager a:hover { background: #ced4da; }
</style>
<body>
<div class="vm-wrap">

    <!-- Header -->
    <div class="vm-card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <div class="vm-title">Voucher Manager</div>
            <p class="vm-subtitle">Select and delete test or unwanted vouchers. Deletions update the sales dashboard instantly.</p>
        </div>
        <a href="dashboard.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Filters -->
    <div class="vm-card">
        <form method="GET" action="voucher_manager.php">
        <div class="filter-row">
            <div class="form-group">
                <label>Search (username / issued by)</label>
                <input type="text" name="q" class="form-control" style="width:180px;"
                    placeholder="username…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" style="width:120px;">
                    <option value="">All</option>
                    <?php foreach (['Active','Used','Over','Expired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Package</label>
                <select name="package" class="form-control" style="width:160px;">
                    <option value="">All</option>
                    <?php foreach ($pkgRows as $pk): ?>
                    <option value="<?= htmlspecialchars($pk) ?>" <?= $package_filter === $pk ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pk) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From</label>
                <input type="date" name="from" class="form-control" style="width:140px;" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="to" class="form-control" style="width:140px;" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Filter</button>
                <a href="voucher_manager.php" class="btn-reset" style="display:inline-block;line-height:34px;">Reset</a>
            </div>
        </div>
        </form>
    </div>

    <!-- Table -->
    <div class="vm-card">
        <!-- Action bar -->
        <div class="action-bar">
            <input type="checkbox" id="select-all" title="Select all on this page">
            <span id="sel-count">0 selected</span>
            <button class="btn-delete-sel" id="btn-delete-sel" disabled>
                <i class="fa fa-trash"></i> Delete Selected
            </button>
            <span style="margin-left:auto;color:#888;">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $per_page, $total_rows)) ?>
                of <?= number_format($total_rows) ?> voucher(s)
            </span>
        </div>

        <div class="table-responsive">
        <table class="table table-bordered vm-table">
            <thead>
                <tr>
                    <th style="width:30px;"><i class="fa fa-check"></i></th>
                    <th>Date / Time</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Package</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Issued By</th>
                    <th>Booking ID</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($vouchers)): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:30px;">No vouchers match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($vouchers as $v): ?>
                <tr>
                    <td><input type="checkbox" class="row-check" data-username="<?= htmlspecialchars($v['user_name']) ?>"></td>
                    <td><?= htmlspecialchars($v['created_on']) ?></td>
                    <td><strong><?= htmlspecialchars($v['user_name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($v['password']) ?></code></td>
                    <td><?= htmlspecialchars($v['package_display']) ?></td>
                    <td><?= htmlspecialchars($v['limit_uptime'] ?? '—') ?></td>
                    <td>₱<?= number_format((float)$v['price'], 2) ?></td>
                    <td>
                        <?php $st = $v['status']; ?>
                        <span class="badge-<?= strtolower($st === 'Over' ? 'over' : $st) ?>">
                            <?= htmlspecialchars($st) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($v['issued_by'] ?: '—') ?></td>
                    <td style="font-size:11px;color:#888;"><?= htmlspecialchars($v['booking_id'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pager">
            <?php
            // Build query string without page
            $qp = $_GET;
            unset($qp['page']);
            $qs = http_build_query($qp);
            $qs = $qs ? $qs . '&' : '';

            $start = max(1, $page - 3);
            $end   = min($total_pages, $page + 3);
            if ($page > 1): ?>
                <a href="?<?= $qs ?>page=<?= $page-1 ?>">‹ Prev</a>
            <?php endif;
            if ($start > 1): ?>
                <a href="?<?= $qs ?>page=1">1</a>
                <?php if ($start > 2): ?><span>…</span><?php endif;
            endif;
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active-page"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
                <?php endif;
            endfor;
            if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?><span>…</span><?php endif; ?>
                <a href="?<?= $qs ?>page=<?= $total_pages ?>"><?= $total_pages ?></a>
            <?php endif;
            if ($page < $total_pages): ?>
                <a href="?<?= $qs ?>page=<?= $page+1 ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirm-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#dc3545;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                <h4 class="modal-title"><i class="fa fa-trash"></i> Delete Vouchers</h4>
            </div>
            <div class="modal-body">
                <p id="confirm-text"></p>
                <div class="alert alert-warning" style="font-size:13px;margin-bottom:0;">
                    <i class="fa fa-exclamation-triangle"></i>
                    This <strong>permanently removes</strong> the selected vouchers from the database
                    and from the router. The sales dashboard will reflect the change immediately.
                    This cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                    <i class="fa fa-trash"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var CSRF_TOKEN = <?= json_encode($csrf) ?>;

// ── Select all / count ────────────────────────────────────────────────────────
function updateCount() {
    var n = $('.row-check:checked').length;
    $('#sel-count').text(n + ' selected');
    $('#btn-delete-sel').prop('disabled', n === 0);
}

$('#select-all').on('change', function () {
    $('.row-check').prop('checked', this.checked);
    updateCount();
});

$(document).on('change', '.row-check', function () {
    var total = $('.row-check').length;
    var checked = $('.row-check:checked').length;
    $('#select-all').prop('indeterminate', checked > 0 && checked < total);
    $('#select-all').prop('checked', checked === total);
    updateCount();
});

// ── Delete button → confirmation modal ───────────────────────────────────────
$('#btn-delete-sel').on('click', function () {
    var n = $('.row-check:checked').length;
    $('#confirm-text').html(
        'You are about to delete <strong>' + n + ' voucher(s)</strong>. '
        + 'Their revenue will be removed from the sales dashboard.'
    );
    $('#confirm-modal').modal('show');
});

// ── Confirmed — send AJAX ─────────────────────────────────────────────────────
$('#confirm-delete-btn').on('click', function () {
    var usernames = [];
    $('.row-check:checked').each(function () {
        usernames.push($(this).data('username'));
    });

    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting…');

    $.ajax({
        url     : 'ajax_delete_vouchers.php',
        method  : 'POST',
        data    : { csrf_token: CSRF_TOKEN, usernames: usernames },
        dataType: 'json',
        success : function (data) {
            $('#confirm-modal').modal('hide');
            if (data.success) {
                // Brief success message then reload to reflect changes
                $('<div class="alert alert-success vm-wrap" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;min-width:300px;">'
                    + '<i class="fa fa-check-circle"></i> ' + data.message
                    + '</div>').appendTo('body');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                alert('Error: ' + (data.message || 'Delete failed.'));
                $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Yes, Delete');
            }
        },
        error   : function () {
            $('#confirm-modal').modal('hide');
            alert('Network error — please try again.');
            $btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Yes, Delete');
        }
    });
});
</script>
</body>
</html>
