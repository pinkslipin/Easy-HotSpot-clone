<?php
/**
 * MindSpace Workspace Hub — Seat Map
 * Staff-only page for reserving, occupying, and releasing seats.
 */
require_once 'security_helper.php';
header('X-Frame-Options: DENY');
require_auth();          // any logged-in staff member
require_once 'dbconfig.php';
require_once 'seat_expiry.php';
require_once 'config.php';
require_once 'packages_config.php';

// Release any stale reservations before rendering
expireSeats($DB_con);

// CSRF token for JS
$csrf = csrf_token();

// Fetch bandwidth profiles from router for the walk-in voucher form
$seat_profiles = [];
if (defined('MOCK_MODE') && MOCK_MODE === true) {
    require_once 'mock_router.php';
    $seat_profiles = ['default'];
} else {
    require_once 'routeros_api.php';
    $seat_conn = createRouterConnection($host, $user, $pass);
    if ($seat_conn['success']) {
        $seat_router = $seat_conn['util'];
        $seat_router->setMenu('/ip hotspot user profile');
        foreach ($seat_router->getAll() as $item) {
            $seat_profiles[] = $item->getProperty('name');
        }
    }
}
if (empty($seat_profiles)) {
    $seat_profiles = ['default'];
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include('header.php'); ?>
<style>
/* ══════════════════════════════════════════════════════════════
   Seat Map — Layout & Zone Sections
══════════════════════════════════════════════════════════════ */
.seats-page { padding: 20px 0 40px; }

.seat-page-header {
    text-align: center;
    margin-bottom: 28px;
}
.seat-page-header h2 {
    font-weight: 700;
    color: #333;
    margin-bottom: 6px;
}
.seat-page-header p {
    color: #888;
    font-size: 13px;
}

/* Summary bar */
.seat-summary-bar {
    text-align: center;
    margin-bottom: 24px;
}
.seat-summary-badge {
    font-size: 13px;
    padding: 6px 14px;
    border-radius: 20px;
    margin: 0 4px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.seat-refresh-ts { font-size: 11px; }
#seat-refresh-countdown {
    display: inline-block;
    min-width: 28px;
    font-weight: 700;
}

/* ══════════════════════════════════════════════════════════════
   Zone Section
══════════════════════════════════════════════════════════════ */
.seat-zone-section {
    background    : #fff;
    border-radius : 12px;
    padding       : 20px 24px 24px;
    margin-bottom : 24px;
    box-shadow    : 0 3px 10px rgba(0,0,0,.09);
}
.seat-zone-header {
    display         : flex;
    justify-content : space-between;
    align-items     : center;
    margin-bottom   : 18px;
    padding-bottom  : 10px;
    border-bottom   : 2px solid #f0f0f0;
}
.seat-zone-title {
    font-size   : 16px;
    font-weight : 700;
    color       : #444;
    letter-spacing: 0.4px;
}
.seat-zone-count {
    font-size : 12px;
    color     : #999;
}

/* ══════════════════════════════════════════════════════════════
   Seat Grid & Card
   Each "seat" is a top-down chair icon built entirely in CSS.
   The ::before pseudo-element = the backrest.
   The div itself            = the seat cushion.
   Inspired by the provided PNG reference icons.
══════════════════════════════════════════════════════════════ */
.seat-grid {
    display               : grid;
    grid-template-columns : repeat(auto-fill, minmax(80px, 1fr));
    gap                   : 18px 12px;
    padding-top           : 12px;
}

/* ── Chair body (cushion) ── */
.seat {
    position       : relative;
    width          : 72px;
    height         : 58px;
    margin         : 14px auto 0;   /* top margin = space for backrest */
    border-radius  : 11px;
    border         : 3px solid #1a1a1a;
    display        : flex;
    align-items    : center;
    justify-content: center;
    transition     : transform .14s ease, box-shadow .14s ease, filter .14s ease;
    user-select    : none;
}

/* ── Backrest (top of chair) ── */
.seat::before {
    content          : '';
    position         : absolute;
    top              : -19px;
    left             : 50%;
    transform        : translateX(-50%);
    width            : 32px;
    height           : 16px;
    background       : #f5c542;   /* always golden-yellow like the PNG */
    border           : 3px solid #1a1a1a;
    border-radius    : 7px 7px 0 0;
    border-bottom    : 0;
}
/* Two small posts on top of the backrest (matches PNG prong detail) */
.seat::after {
    content          : '';
    position         : absolute;
    top              : -29px;
    left             : 50%;
    transform        : translateX(-50%);
    width            : 20px;
    height           : 12px;
    border-left      : 3px solid #1a1a1a;
    border-right     : 3px solid #1a1a1a;
    border-top       : 3px solid #1a1a1a;
    border-radius    : 6px 6px 0 0;
    background       : transparent;
}

/* ── Seat label (number text) ── */
.seat-label {
    font-size  : 10px;
    font-weight: 800;
    color      : rgba(0,0,0,.55);
    line-height: 1;
    text-align : center;
    z-index    : 1;
    pointer-events: none;
}

/* ── Status colours ── */
.seat-available { background: #5cb85c; }
.seat-reserved  { background: #f0ad4e; }
.seat-occupied  { background: #e9534f; }

/* ── Interactions ── */
.seat-clickable { cursor: pointer; }
.seat-clickable:hover {
    transform  : translateY(-3px) scale(1.06);
    box-shadow : 0 6px 18px rgba(0,0,0,.2);
    filter     : brightness(1.08);
}
.seat-clickable:active {
    transform  : translateY(0) scale(.97);
    box-shadow : none;
}

/* ══════════════════════════════════════════════════════════════
   Legend
══════════════════════════════════════════════════════════════ */
.seat-legend {
    display        : flex;
    justify-content: center;
    flex-wrap      : wrap;
    gap            : 18px;
    margin-bottom  : 28px;
}
.seat-legend-item {
    display    : flex;
    align-items: center;
    gap        : 8px;
    font-size  : 13px;
    color      : #555;
    font-weight: 600;
}
.seat-legend-dot {
    width        : 18px;
    height       : 18px;
    border-radius: 4px;
    border       : 2px solid #1a1a1a;
    flex-shrink  : 0;
}
.dot-available { background: #5cb85c; }
.dot-reserved  { background: #f0ad4e; }
.dot-occupied  { background: #e9534f; }

/* ══════════════════════════════════════════════════════════════
   Toast area
══════════════════════════════════════════════════════════════ */
#seat-toast-area { margin-bottom: 16px; }
.seat-toast {
    border-radius : 8px;
    font-size     : 13px;
    box-shadow    : 0 3px 10px rgba(0,0,0,.12);
}

/* ══════════════════════════════════════════════════════════════
   Action Modal
══════════════════════════════════════════════════════════════ */
#seat-action-modal .modal-header {
    background   : linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color        : white;
    border-radius: 6px 6px 0 0;
}
#seat-action-modal .modal-title { font-weight: 700; }
.seat-modal-meta { text-align: center; margin-bottom: 12px; }
.seat-modal-meta .label { font-size: 13px; padding: 5px 14px; border-radius: 20px; }
#seat-modal-info {
    text-align : center;
    font-size  : 13px;
    color      : #888;
    margin-top : 6px;
}
#seat-modal-spinner { text-align: center; margin: 6px 0; display: none; }
.seat-modal-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 16px; }
.seat-modal-actions .btn {
    min-width    : 130px;
    border-radius: 6px;
    font-weight  : 600;
}

/* Voucher form inside walk-in / check-in modal */
#seat-walkin-form { margin-top: 4px; }
#seat-walkin-form hr { margin: 12px 0 14px; border-color: #e8e8e8; }
#seat-walkin-form .walkin-form-title {
    font-size  : 13px;
    font-weight: 700;
    color      : #555;
    text-align : center;
    margin-bottom: 12px;
}
#seat-walkin-form .form-group { margin-bottom: 9px; }
#seat-walkin-form label { font-size: 12px; font-weight: 600; margin-bottom: 3px; color: #555; }
#seat-walkin-form .form-control { height: 32px; font-size: 13px; padding: 4px 8px; }
.walkin-form-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }

/* ══════════════════════════════════════════════════════════════
   Seat Detail Panel — live session info for occupied / reserved seats
══════════════════════════════════════════════════════════════ */
#seat-detail-panel { margin-top: 4px; }
.detail-loading { text-align: center; padding: 18px 0; color: #888; font-size: 13px; }
.detail-table { margin: 0; width: 100%; }
.detail-table th {
    font-size  : 11px;
    font-weight: 700;
    color      : #888;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    width      : 38%;
    padding    : 5px 6px;
    white-space: nowrap;
    vertical-align: middle;
}
.detail-table td { font-size: 13px; padding: 5px 6px; vertical-align: middle; }
.detail-table>tbody>tr>td, .detail-table>tbody>tr>th { border-top: 1px solid #f2f2f2; }

/* Time-remaining chip */
.time-chip {
    display      : inline-block;
    font-size    : 18px;
    font-weight  : 800;
    padding      : 4px 12px;
    border-radius: 8px;
    line-height  : 1.3;
}
.time-chip-ok    { background: #d4edda; color: #218838; }
.time-chip-warn  { background: #fff3cd; color: #856404; }
.time-chip-crit  { background: #f8d7da; color: #c0392b; }
.time-chip-na    { background: #f0f0f0; color: #888; }

/* Online status dot */
.status-dot {
    display     : inline-block;
    width       : 9px;
    height      : 9px;
    border-radius: 50%;
    margin-right: 5px;
    vertical-align: middle;
}
.dot-online  { background: #28a745; box-shadow: 0 0 0 2px rgba(40,167,69,.25); }
.dot-offline { background: #aaa; }

/* Extend panel */
#seat-extend-panel { margin-top: 10px; }
#seat-extend-panel .extend-title {
    font-size  : 12px;
    font-weight: 700;
    color      : #555;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.extend-quick-btns { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.extend-quick-btns .btn {
    flex: 1;
    min-width: 55px;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 4px;
    border-radius: 6px;
}
.btn-extend-opt        { background: #f0f0f0; color: #444; border: 1px solid #ccc; }
.btn-extend-opt:hover  { background: #667eea; color: white; border-color: #667eea; }
.btn-extend-opt.active { background: #667eea; color: white; border-color: #667eea; }
/* Transfer panel */
#seat-transfer-panel { margin-top: 10px; }
#seat-transfer-panel .transfer-title {
    font-size  : 12px;
    font-weight: 700;
    color      : #555;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
#seat-transfer-panel select.form-control {
    font-size: 13px;
    height: 34px;
}
.transfer-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }

/* Detail footer */
.detail-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; gap: 8px; }

/* ══════════════════════════════════════════════════════════════
   Nav buttons
══════════════════════════════════════════════════════════════ */
.seat-nav { text-align: center; margin-bottom: 28px; }
.seat-nav .btn { border-radius: 20px; margin: 3px; font-weight: 600; }

/* ══════════════════════════════════════════════════════════════
   Responsive tweaks
══════════════════════════════════════════════════════════════ */
@media (max-width: 480px) {
    .seat-grid { grid-template-columns: repeat(auto-fill, minmax(66px, 1fr)); gap: 14px 8px; }
    .seat { width: 60px; height: 48px; }
    .seat-label { font-size: 9px; }
}
</style>

<body>
<div class="container seats-page">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <div class="seat-page-header no_print">
        <h2><i class="fa fa-th-large"></i>&nbsp; Workspace Seat Map</h2>
        <p>Click any seat to reserve, walk-in, or view live session info &mdash; map refreshes every 30 s</p>
    </div>

    <!-- ── Navigation ──────────────────────────────────────────────────────── -->
    <div class="seat-nav no_print">
        <a href="index.php"     class="btn btn-default"><i class="fa fa-home"></i>&nbsp; Main Menu</a>
        <a href="dashboard.php" class="btn btn-default"><i class="fa fa-line-chart"></i>&nbsp; Dashboard</a>
        <button id="btn-refresh-now" class="btn btn-info" onclick="loadSeatsNow()">
            <i class="fa fa-refresh"></i>&nbsp; Refresh Now &nbsp;
            <span id="seat-refresh-countdown" class="badge" style="background:rgba(255,255,255,.3);">30s</span>
        </button>
    </div>

    <!-- ── Legend ──────────────────────────────────────────────────────────── -->
    <div class="seat-legend">
        <div class="seat-legend-item">
            <div class="seat-legend-dot dot-available"></div>
            <span>Available &mdash; Walk-in (issue voucher) or Reserve</span>
        </div>
        <div class="seat-legend-item">
            <div class="seat-legend-dot dot-reserved"></div>
            <span>Reserved &mdash; Check In (issue voucher) or Release</span>
        </div>
        <div class="seat-legend-item">
            <div class="seat-legend-dot dot-occupied"></div>
            <span>Occupied &mdash; click to view session &amp; extend time</span>
        </div>
    </div>

    <!-- ── Toast area ──────────────────────────────────────────────────────── -->
    <div id="seat-toast-area"></div>

    <!-- ── Summary bar ─────────────────────────────────────────────────────── -->
    <div class="seat-summary-bar" id="seat-summary">
        <span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading…</span>
    </div>

    <!-- ── Seat map (populated by seats.js) ────────────────────────────────── -->
    <div id="seat-map-container">
        <!-- Zone sections injected here by renderSeatMap() -->
    </div>

</div><!-- /.container -->


<!-- ══════════════════════════════════════════════════════════════════════════
     Seat Action Modal
     Context-aware buttons: Reserve / Occupy / Release shown based on status.
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="seat-action-modal" tabindex="-1" role="dialog" aria-labelledby="seat-action-modal-label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" style="color:white;opacity:.9;">&times;</button>
                <h4 class="modal-title" id="seat-action-modal-label">
                    <i class="fa fa-chair"></i>&nbsp;
                    Seat <span id="seat-modal-title">—</span>
                </h4>
            </div>

            <div class="modal-body">
                <div class="seat-modal-meta">
                    <p class="text-muted" style="margin-bottom:8px; font-size:13px;">
                        <i class="fa fa-map-marker"></i>&nbsp;<span id="seat-modal-zone">—</span>
                    </p>
                    <span class="label" id="seat-modal-badge">—</span>
                </div>

                <div id="seat-modal-info" style="display:none;"></div>

                <div id="seat-modal-spinner">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                </div>

                <!-- ── Action buttons (context-aware visibility) ───────── -->
                <div class="seat-modal-actions">

                    <!-- Walk-in: available seats → occupy immediately + issue voucher -->
                    <button id="btn-seat-walkin"
                            class="btn btn-success btn-seat-action"
                            style="display:none;">
                        <i class="fa fa-sign-in"></i>&nbsp; Walk-in
                    </button>

                    <!-- Reserve only: available seats → hold for later -->
                    <button id="btn-seat-reserve"
                            class="btn btn-default btn-seat-action"
                            style="display:none;">
                        <i class="fa fa-bookmark"></i>&nbsp; Reserve
                    </button>

                    <!-- Check In: reserved seats → occupy + issue voucher -->
                    <button id="btn-seat-checkin"
                            class="btn btn-warning btn-seat-action"
                            style="display:none;">
                        <i class="fa fa-sign-in"></i>&nbsp; Check In
                    </button>

                    <!-- Release: reserved or occupied → free the seat
                         (also mirrored in detail-footer below) -->
                    <button id="btn-seat-release"
                            class="btn btn-danger btn-seat-action"
                            style="display:none;">
                        <i class="fa fa-unlock"></i>&nbsp; Release
                    </button>

                </div>

                <!-- ── Live session detail panel (occupied / reserved) ─── -->
                <div id="seat-detail-panel" style="display:none;">

                    <div class="detail-loading" id="seat-detail-loading">
                        <i class="fa fa-spinner fa-spin"></i>&nbsp; Loading session info&hellip;
                    </div>

                    <div id="seat-detail-content" style="display:none;">
                        <table class="table detail-table">
                            <tbody>
                                <tr>
                                    <th>Customer</th>
                                    <td id="dd-username">&mdash;</td>
                                </tr>
                                <tr>
                                    <th>Package</th>
                                    <td id="dd-package">&mdash;</td>
                                </tr>
                                <tr>
                                    <th>Seated</th>
                                    <td id="dd-occupied-since">&mdash;</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="dd-online-status">&mdash;</td>
                                </tr>
                                <tr id="dd-uptime-row">
                                    <th>Session Uptime</th>
                                    <td id="dd-session-uptime">&mdash;</td>
                                </tr>
                                <tr id="dd-timeleft-row">
                                    <th>Time Remaining</th>
                                    <td id="dd-time-left">&mdash;</td>
                                </tr>
                                <tr id="dd-finish-row">
                                    <th>Finishes At</th>
                                    <td id="dd-finish">&mdash;</td>
                                </tr>
                                <tr id="dd-ip-row">
                                    <th>IP Address</th>
                                    <td id="dd-ip">&mdash;</td>
                                </tr>
                                <tr id="dd-mac-row">
                                    <th>MAC Address</th>
                                    <td id="dd-mac">&mdash;</td>
                                </tr>
                                <tr id="dd-data-row">
                                    <th>Data Usage</th>
                                    <td id="dd-data">&mdash;</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Extend time panel (hidden until Extend button clicked) -->
                        <div id="seat-extend-panel" style="display:none;">
                            <hr style="margin:10px 0;">
                            <p class="extend-title"><i class="fa fa-clock-o"></i>&nbsp; Add Time</p>
                            <div class="extend-quick-btns">
                                <button class="btn btn-extend-opt" data-mins="30">+30 min</button>
                                <button class="btn btn-extend-opt" data-mins="60">+1 hr</button>
                                <button class="btn btn-extend-opt" data-mins="120">+2 hr</button>
                                <button class="btn btn-extend-opt" data-mins="180">+3 hr</button>
                                <button class="btn btn-extend-opt" data-mins="300">+5 hr</button>
                                <button class="btn btn-extend-opt" data-mins="360">+6 hr</button>
                            </div>
                            <div style="display:flex; gap:8px; justify-content:flex-end;">
                                <button id="btn-extend-cancel" class="btn btn-sm btn-default">
                                    <i class="fa fa-times"></i>&nbsp; Cancel
                                </button>
                                <button id="btn-extend-confirm" class="btn btn-sm btn-primary btn-seat-action" disabled>
                                    <i class="fa fa-check"></i>&nbsp; Confirm Extension
                                </button>
                            </div>
                        </div>

                        <!-- Transfer seat panel (hidden until Change Seat clicked) -->
                        <div id="seat-transfer-panel" style="display:none;">
                            <hr style="margin:10px 0;">
                            <p class="transfer-title"><i class="fa fa-exchange"></i>&nbsp; Move to Seat</p>
                            <select id="swt-destination" class="form-control">
                                <option value="">— select available seat —</option>
                            </select>
                            <div id="swt-creds-row" style="display:none; margin-top:12px;">
                                <hr style="margin: 10px 0 12px;">
                                <p class="transfer-title" style="margin-bottom:8px;">
                                    <i class="fa fa-key"></i>&nbsp; New Credentials
                                    <small class="text-muted" style="font-weight:400; text-transform:none; letter-spacing:0;">&nbsp;(customer will reconnect with these)</small>
                                </p>
                                <div class="form-group" style="margin-bottom:7px;">
                                    <label style="font-size:12px; font-weight:600; color:#555;">Username</label>
                                    <input type="text" id="swt-username" class="form-control" autocomplete="off">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size:12px; font-weight:600; color:#555;">Password</label>
                                    <input type="text" id="swt-password" class="form-control" autocomplete="off">
                                </div>
                            </div>
                            <div class="transfer-footer">
                                <button id="btn-transfer-cancel" class="btn btn-sm btn-default">
                                    <i class="fa fa-times"></i>&nbsp; Cancel
                                </button>
                                <button id="btn-transfer-confirm" class="btn btn-sm btn-warning btn-seat-action" disabled>
                                    <i class="fa fa-exchange"></i>&nbsp; Confirm Move
                                </button>
                            </div>
                        </div>

                        <!-- Detail footer actions -->
                        <div class="detail-footer">
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button id="btn-detail-extend" class="btn btn-sm btn-info btn-seat-action"
                                        style="display:none;">
                                    <i class="fa fa-plus-circle"></i>&nbsp; Extend Time
                                </button>
                                <button id="btn-detail-transfer" class="btn btn-sm btn-warning btn-seat-action"
                                        style="display:none;">
                                    <i class="fa fa-exchange"></i>&nbsp; Change Seat
                                </button>
                            </div>
                            <button id="btn-detail-release" class="btn btn-sm btn-danger btn-seat-action"
                                    style="display:none;">
                                <i class="fa fa-unlock"></i>&nbsp; Release Seat
                            </button>
                        </div>

                    </div><!-- /#seat-detail-content -->
                </div><!-- /#seat-detail-panel -->

                <!-- ── Inline voucher form (Walk-in / Check-in) ─────────── -->
                <div id="seat-walkin-form" style="display:none;">
                    <hr>
                    <p class="walkin-form-title">
                        <i class="fa fa-ticket"></i>&nbsp; Issue Voucher
                    </p>

                    <div class="form-group">
                        <label for="swf-username">Username</label>
                        <input type="text" id="swf-username" class="form-control"
                               placeholder="e.g. guest001" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="swf-password">Password</label>
                        <input type="text" id="swf-password" class="form-control"
                               placeholder="e.g. pass001" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="swf-package">Package</label>
                        <select id="swf-package" class="form-control">
                            <?php
                            $grouped = getPackagesGroupedByCategory();
                            foreach ($grouped as $catId => $catData):
                            ?>
                                <optgroup label="<?= htmlspecialchars($catData['name']) ?>">
                                <?php foreach ($catData['packages'] as $pkg):
                                    $dname = $pkg['name'];
                                    if ($pkg['type'] === 'window' && isset($pkg['window_description'])) {
                                        $dname .= ' (' . $pkg['window_description'] . ')';
                                    }
                                    $sel = ($pkg['id'] === 'ind_1h') ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($pkg['id']) ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($dname) . ' &mdash; ' . formatPrice($pkg['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="swf-profile">Bandwidth Profile
                            <span id="swf-profile-auto-badge"
                                  class="label label-info"
                                  style="display:none; font-size:10px; margin-left:4px; vertical-align:middle;">auto</span>
                        </label>
                        <select id="swf-profile" class="form-control">
                            <?php foreach ($seat_profiles as $sp): ?>
                                <option value="<?= htmlspecialchars($sp) ?>"><?= htmlspecialchars($sp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="swf-limit-bytes">Data Limit (GB &mdash; 0 = unlimited)</label>
                        <input type="number" id="swf-limit-bytes" class="form-control"
                               value="0" min="0" step="1">
                    </div>

                    <div class="walkin-form-footer">
                        <button id="btn-walkin-cancel" type="button" class="btn btn-sm btn-default">
                            <i class="fa fa-arrow-left"></i>&nbsp; Back
                        </button>
                        <button id="btn-walkin-submit" type="button" class="btn btn-sm btn-success btn-seat-action">
                            <i class="fa fa-check"></i>&nbsp; Issue &amp; Seat
                        </button>
                    </div>
                </div><!-- /#seat-walkin-form -->

            </div><!-- /.modal-body -->

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /#seat-action-modal -->


<!-- ── Scripts ─────────────────────────────────────────────────────────────── -->
<script>
/* CSRF token injected for use by seats.js */
var CSRF_TOKEN = <?= json_encode($csrf) ?>;

/* Package data for auto-populating data limits */
var PACKAGE_DATA = <?php echo getPackagesForJavaScript(); ?>;

/* Allow the Refresh Now button to trigger an immediate reload */
function loadSeatsNow() {
    if (typeof window._loadSeats === 'function') window._loadSeats();
}
</script>

<script src="js/seats.js?v=<?= filemtime('js/seats.js') ?>"></script>
</body>
</html>
