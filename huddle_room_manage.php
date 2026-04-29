<?php
/**
 * Staff Dashboard: Huddle Room Management
 * View, check-in, and manage Huddle Room bookings
 */
require_once 'security_helper.php';
secure_session_start();
require_auth();
require_once 'dbconfig.php';
require_once 'config.php';
require_once 'packages_config.php';

// Fetch bandwidth profiles from router (same pattern as seats.php)
$bandwidth_profiles = [];
if (defined('MOCK_MODE') && MOCK_MODE === true) {
    require_once 'mock_router.php';
    $bandwidth_profiles = ['default'];
} else {
    require_once 'routeros_api.php';
    $profile_conn = createRouterConnection($host, $user, $pass);
    if ($profile_conn['success']) {
        $profile_router = $profile_conn['util'];
        $profile_router->setMenu('/ip hotspot user profile');
        foreach ($profile_router->getAll() as $item) {
            $bandwidth_profiles[] = $item->getProperty('name');
        }
    }
}
if (empty($bandwidth_profiles)) {
    $bandwidth_profiles = ['default'];
}

// Generate CSRF token for AJAX requests
$csrf = csrf_token();

// Get today's and upcoming bookings
$todayStmt = $DB_con->prepare("
    SELECT * FROM huddle_room_bookings
    WHERE booking_date >= CURDATE()
      AND status IN ('pending', 'confirmed', 'checked_in')
    ORDER BY booking_date ASC, start_time ASC
    LIMIT 50
");
$todayStmt->execute();
$upcoming_bookings = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Huddle Room Management — MindSpace</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <style>
        .booking-card { background: white; border-left: 4px solid #667eea; padding: 16px; margin-bottom: 12px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .booking-card.pending { border-left-color: #ffc107; }
        .booking-card.confirmed { border-left-color: #17a2b8; }
        .booking-card.checked_in { border-left-color: #28a745; }
        
        .booking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .booking-ref { font-weight: 700; font-size: 14px; color: #333; }
        .booking-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-checked_in { background: #d4edda; color: #155724; }
        
        .booking-details { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 12px; font-size: 13px; }
        .detail-item { }
        .detail-label { color: #999; font-size: 11px; margin-bottom: 4px; }
        .detail-value { font-weight: 600; color: #333; }
        
        .booking-actions { display: flex; gap: 8px; margin-top: 12px; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; }
        .btn-sm:hover { background: #f5f5f5; }
        .btn-sm.primary { background: #667eea; color: white; border-color: #667eea; }
        .btn-sm.danger { background: #dc3545; color: white; border-color: #dc3545; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        
        /* Two-Column Layout for Bookings + Active Vouchers */
        .huddle-layout-container { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; margin-top: 20px; }
        .huddle-bookings-panel { }
        .huddle-vouchers-panel { background: #f8f9fa; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; height: fit-content; position: sticky; top: 20px; }
        
        .voucher-item { background: white; padding: 12px; border-radius: 4px; margin-bottom: 8px; border-left: 3px solid #667eea; }
        .voucher-item.expired { border-left-color: #dc3545; opacity: 0.7; }
        .voucher-item.active { border-left-color: #28a745; }
        
        .voucher-username { font-weight: 700; color: #333; font-size: 13px; margin-bottom: 4px; }
        .voucher-ip { color: #666; font-size: 12px; }
        .voucher-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 6px; font-size: 11px; }
        .voucher-meta-item { color: #888; }
        .voucher-meta-label { color: #999; font-size: 10px; }
        .voucher-status { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; margin-top: 6px; }
        .voucher-status.active { background: #d4edda; color: #155724; }
        .voucher-status.expired { background: #f8d7da; color: #721c24; }
        
        .voucher-action-btn { padding: 4px 8px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px; font-weight: 600; transition: all 0.2s; }
        .voucher-action-btn:hover { background: #f5f5f5; border-color: #999; }
        .extend-btn { color: #ffc107; }
        .extend-btn:hover { background: #fff9e6; border-color: #ffc107; }
        .reset-btn { color: #17a2b8; }
        .reset-btn:hover { background: #e7f3f7; border-color: #17a2b8; }
        .remove-btn { color: #dc3545; }
        .remove-btn:hover { background: #ffe6e6; border-color: #dc3545; }
        
        /* Responsive: Stack on smaller screens */
        @media (max-width: 1200px) {
            .huddle-layout-container { grid-template-columns: 1fr; }
            .huddle-vouchers-panel { position: relative; top: 0; }
        }
        
        .modal-content { background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%; }
        .modal-content h3 { margin-bottom: 18px; }
        .modal-form-group { margin-bottom: 16px; }
        .modal-form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
        .modal-form-group input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="container" style="margin-top: 40px; margin-bottom: 40px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>📅 Huddle Room Management</h2>
            <p>Manage and check in Huddle Room bookings</p>
        </div>
        <a href="index.php" style="color: #333; text-decoration: none; padding: 10px 20px; background: #e0e0e0; border-radius: 4px; display: flex; align-items: center; gap: 8px;" title="Back to Dashboard">
            <i class="fa fa-arrow-left"></i> Back to Home
        </a>
    </div>
    
    <hr>
    
    <div id="message-area"></div>
    
    <!-- Two-Column Layout: Bookings (left) + Active Vouchers (right) -->
    <div class="huddle-layout-container">
        
        <!-- LEFT: Bookings -->
        <div class="huddle-bookings-panel">
            <?php if (empty($upcoming_bookings)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>No upcoming bookings yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_bookings as $booking): ?>
                    <div class="booking-card <?= $booking['status'] ?>">
                <div class="booking-header">
                    <div>
                        <div class="booking-ref"><?= htmlspecialchars($booking['booking_ref']) ?></div>
                        <small style="color: #999;"><?= htmlspecialchars($booking['visitor_name']) ?></small>
                    </div>
                    <div class="booking-status status-<?= $booking['status'] ?>">
                        <?= ucfirst($booking['status']) ?>
                    </div>
                </div>
                
                <div class="booking-details">
                    <div class="detail-item">
                        <div class="detail-label">Date & Time</div>
                        <div class="detail-value"><?= date('M d, Y', strtotime($booking['booking_date'])) ?></div>
                        <small><?= date('H:i', strtotime($booking['start_time'])) ?> - <?= date('H:i', strtotime($booking['end_time'])) ?></small>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Occupancy</div>
                        <div class="detail-value"><?= $booking['booked_occupancy'] ?> pax</div>
                        <?php if ($booking['final_occupancy']): ?>
                            <small style="color: #28a745;">Final: <?= $booking['final_occupancy'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Price</div>
                        <div class="detail-value">₱<?= number_format($booking['total_price'], 2) ?></div>
                        <?php if ($booking['paid_amount'] > 0): ?>
                            <small>Paid: ₱<?= number_format($booking['paid_amount'], 2) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Contact</div>
                        <small><?= htmlspecialchars($booking['visitor_email']) ?></small>
                        <?php if ($booking['visitor_phone']): ?>
                            <div><small><?= htmlspecialchars($booking['visitor_phone']) ?></small></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="booking-actions">
                    <?php if ($booking['status'] === 'confirmed'): ?>
                        <button class="btn-sm primary" onclick="openCheckinModal(<?= $booking['id'] ?>, <?= $booking['booked_occupancy'] ?>, <?= $booking['total_price'] ?>)">
                            ✓ Check In
                        </button>
                    <?php endif; ?>
                    <?php if ($booking['status'] !== 'completed'): ?>
                        <button class="btn-sm danger" onclick="cancelBooking(<?= $booking['id'] ?>, '<?= htmlspecialchars($booking['booking_ref']) ?>')">
                            ✗ Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
        </div><!-- /.huddle-bookings-panel -->
        
        <!-- RIGHT: Active Vouchers -->
        <div class="huddle-vouchers-panel">
            <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700;">
                <i class="fa fa-wifi"></i> Active Vouchers
            </h3>
            <div id="vouchers-list" style="font-size: 13px; color: #666;">
                <i class="fa fa-spinner fa-spin"></i> Loading...
            </div>
        </div><!-- /.huddle-vouchers-panel -->
        
    </div><!-- /.huddle-layout-container -->
</div>

<!-- Check-in Modal -->
<div class="modal-overlay" id="checkin-modal">
    <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>Check In & Issue Voucher</h3>
        <form id="checkin-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="booking_id" id="modal-booking-id" value="">
            
            <!-- CHECK-IN SECTION -->
            <div style="background: #f0f8ff; padding: 16px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #17a2b8;">
                <h5 style="margin: 0 0 12px; color: #0c5460;">Step 1: Finalize Occupancy</h5>
                <div class="modal-form-group">
                    <label>Final Number of Attendees</label>
                    <input type="number" id="final-occupancy" name="final-occupancy" min="1" max="50" required style="background: white;">
                </div>
                <div style="background: white; padding: 12px; border-radius: 4px; font-size: 13px;">
                    <strong>Updated Price</strong>
                    <div style="font-size: 16px; color: #0c5460; margin-top: 6px; font-weight: 700;">₱<span id="new-price">0.00</span></div>
                </div>
            </div>
            
            <!-- OPTIONAL VOUCHER SECTION -->
            <div>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; cursor: pointer; font-weight: 600;">
                    <input type="checkbox" id="issue-voucher-check" name="issue-voucher">
                    <i class="fa fa-ticket"></i> Issue Internet Voucher
                </label>
                
                <div id="voucher-form-section" style="display: none; background: #f9f9f9; padding: 16px; border-radius: 6px; border: 1px solid #e0e0e0; margin-bottom: 16px;">
                    <h5 style="margin: 0 0 12px; color: #333;">Step 2: Voucher Details</h5>
                    
                    <div class="modal-form-group">
                        <label>Username</label>
                        <input type="text" id="voucher-username" name="voucher-username" placeholder="e.g. guest001" autocomplete="off">
                    </div>
                    
                    <div class="modal-form-group">
                        <label>Password</label>
                        <input type="text" id="voucher-password" name="voucher-password" placeholder="e.g. pass001" autocomplete="off">
                    </div>
                    
                    <div class="modal-form-group">
                        <label>Package</label>
                        <select id="voucher-package" name="voucher-package">
                            <option value="">-- Select Package --</option>
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
                                        <?= htmlspecialchars($dname) . ' — ' . formatPrice($pkg['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="voucher-profile">Bandwidth Profile
                            <span id="voucher-profile-auto-badge"
                                  class="label label-info"
                                  style="display:none; font-size:10px; margin-left:4px; vertical-align:middle;">auto</span>
                        </label>
                        <select id="voucher-profile" name="voucher-profile">
                            <?php foreach ($bandwidth_profiles as $profile): ?>
                                <option value="<?= htmlspecialchars($profile) ?>"><?= htmlspecialchars($profile) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label>Data Limit (GB — 0 = unlimited)</label>
                        <input type="number" id="voucher-data-limit" name="voucher-data-limit" min="0" value="0">
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-sm" onclick="closeCheckinModal()">Cancel</button>
                <button type="submit" class="btn-sm primary">Confirm Check-In</button>
            </div>
        </form>
    </div>
</div>

<!-- Extend Voucher Time Modal -->
<div class="modal-overlay" id="extend-modal">
    <div class="modal-content" style="max-width: 500px;">
        <div style="background: #f0ad4e; padding: 16px; border-radius: 6px 6px 0 0; margin: -24px -24px 16px -24px; color: white;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700;">
                <i class="fa fa-clock-o"></i> Extend Time for <span id="extend-display-username">User</span>
            </h3>
        </div>
        <form id="extend-form">
            <input type="hidden" id="extend-hidden-username" value="">
            
            <div class="modal-form-group">
                <label style="font-weight: 600; margin-bottom: 12px;">Add Time:</label>
                <select id="extend-minutes-select" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="30">+30 Minutes</option>
                    <option value="60" selected>+1 Hour</option>
                    <option value="120">+2 Hours</option>
                    <option value="180">+3 Hours</option>
                    <option value="300">+5 Hours</option>
                    <option value="360">+6 Hours</option>
                </select>
            </div>
            
            <p style="font-size: 12px; color: #666; margin-top: 12px;">
                <i class="fa fa-info-circle"></i> Time is added to the user's remaining limit. They will NOT be disconnected.
            </p>
            
            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn-sm" onclick="closeExtendModal()">Cancel</button>
                <button type="button" class="btn-sm primary" onclick="submitExtendModal()" style="background: #f0ad4e; border-color: #f0ad4e; color: white;">
                    <i class="fa fa-check"></i> Extend Time
                </button>
            </div>
        </form>
    </div>
</div>

<script src="js/jquery-2.1.1.min.js"></script>
<script>
// ========================================
// PACKAGE DATA FOR AUTO-POPULATION
// ========================================
const PACKAGE_DATA = <?php echo getPackagesForJavaScript(); ?>;

// ========================================
// ACTIVE VOUCHERS PANEL
// ========================================
let vouchersRefreshTimer = null;

/**
 * Fetch and display active vouchers from huddle room bookings
 */
function loadActiveVouchers() {
    $.ajax({
        url: 'ajax_huddle_active_vouchers.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('Vouchers loaded:', data);
            if (data.success) {
                displayActiveVouchers(data.vouchers);
            } else {
                $('#vouchers-list').html('<small style="color: #999;">Error: ' + (data.message || 'Unknown error') + '</small>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading vouchers:', error);
            $('#vouchers-list').html('<small style="color: #999;">Unable to load vouchers (Network error)</small>');
        }
    });
}

/**
 * Render active vouchers in the right panel
 */
function displayActiveVouchers(vouchers) {
    const $list = $('#vouchers-list');
    
    if (!vouchers || vouchers.length === 0) {
        $list.html('<small style="color: #999; display: block; text-align: center; padding: 20px 0;">No active vouchers</small>');
        return;
    }
    
    let html = '';
    vouchers.forEach(function(v) {
        const statusClass = v.expired ? 'expired' : 'active';
        const statusText = v.expired ? '✗ EXPIRED' : '✓ ACTIVE';
        html += `
            <div class="voucher-item ${statusClass}">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <div class="voucher-username">${escapeHtml(v.username)}</div>
                        <div class="voucher-ip"><i class="fa fa-globe"></i> ${escapeHtml(v.address)}</div>
                        <div class="voucher-meta">
                            <div class="voucher-meta-item">
                                <div class="voucher-meta-label">Session</div>
                                <strong style="color: #333;">${escapeHtml(v.session_uptime)}</strong>
                            </div>
                            <div class="voucher-meta-item">
                                <div class="voucher-meta-label">Time Left</div>
                                <strong style="color: #333;">${escapeHtml(v.voucher_left)}</strong>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 4px; margin-left: 8px; flex-wrap: wrap; justify-content: flex-end;">
                        <button class="voucher-action-btn extend-btn" data-username="${escapeHtml(v.username)}" title="Extend time">
                            <i class="fa fa-clock-o"></i> Ext
                        </button>
                        <button class="voucher-action-btn reset-btn" data-username="${escapeHtml(v.username)}" title="Reset password">
                            <i class="fa fa-refresh"></i> Rst
                        </button>
                        <button class="voucher-action-btn remove-btn" data-username="${escapeHtml(v.username)}" title="Remove user">
                            <i class="fa fa-trash"></i> Del
                        </button>
                    </div>
                </div>
                <span class="voucher-status ${statusClass}" style="margin-top: 8px; display: block;">${statusText}</span>
            </div>
        `;
    });
    
    $list.html(html);
    
    // Bind action button handlers
    bindVoucherActions();
}

/**
 * Start auto-refresh of active vouchers (every 30 seconds)
 */
function startVouchersAutoRefresh() {
    loadActiveVouchers();
    vouchersRefreshTimer = setInterval(loadActiveVouchers, 30000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// ========================================
// VOUCHER ACTION HANDLERS
// ========================================

/**
 * Bind click handlers to voucher action buttons
 */
function bindVoucherActions() {
    $('#vouchers-list').off('click.voucher'); // Remove old handlers
    
    // Extend time button
    $('#vouchers-list').on('click.voucher', '.extend-btn', function() {
        const username = $(this).data('username');
        showExtendModal(username);
    });
    
    // Reset password button
    $('#vouchers-list').on('click.voucher', '.reset-btn', function() {
        const username = $(this).data('username');
        if (confirm('Reset password for ' + username + '? They will receive a new password.')) {
            handleResetPassword(username);
        }
    });
    
    // Remove user button
    $('#vouchers-list').on('click.voucher', '.remove-btn', function() {
        const username = $(this).data('username');
        if (confirm('Remove ' + username + ' from network? (They can reconnect with new login if voucher still valid)')) {
            handleRemoveUser(username);
        }
    });
}

/**
 * Show extend time modal
 */
function showExtendModal(username) {
    $('#extend-display-username').text(username);
    $('#extend-hidden-username').val(username);
    $('#extend-minutes-select').val('60'); // Default to 1 hour
    $('#extend-modal').addClass('active');
}

/**
 * Close extend modal
 */
function closeExtendModal() {
    $('#extend-modal').removeClass('active');
}

/**
 * Submit extend modal form
 */
function submitExtendModal() {
    const username = $('#extend-hidden-username').val();
    const minutes = parseInt($('#extend-minutes-select').val());
    
    if (!username || !minutes) {
        alert('Invalid input');
        return;
    }
    
    handleExtendTime(username, minutes);
    closeExtendModal();
}

/**
 * Extend voucher time
 */
function handleExtendTime(username, minutes) {
    $.ajax({
        url: 'ajax_extend_user.php',
        method: 'POST',
        data: {
            username: username,
            extend_minutes: minutes,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(data) {
            console.log('Extend response:', data);
            if (data.success) {
                alert('✓ Extended ' + username + ' by ' + minutes + ' minutes');
                loadActiveVouchers(); // Refresh list
            } else {
                alert('✗ Error: ' + (data.message || 'Failed to extend time'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Extend error:', {status: status, error: error, response: xhr.responseText});
            alert('✗ Error extending time: ' + (xhr.responseText ? xhr.responseText.substring(0, 100) : error));
        }
    });
}

/**
 * Reset user password
 */
function handleResetPassword(username) {
    $.ajax({
        url: 'ajax_kick_session.php',
        method: 'POST',
        data: {
            username: username,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                alert('✓ Session reset for ' + username + '. They must log in again.');
                loadActiveVouchers(); // Refresh list
            } else {
                alert('✗ Error: ' + (data.message || 'Failed to reset session'));
            }
        },
        error: function() {
            alert('✗ Network error while resetting session');
        }
    });
}

/**
 * Remove user from network completely
 */
function handleRemoveUser(username) {
    $.ajax({
        url: 'ajax_rem_user.php',
        method: 'POST',
        data: {
            username: username,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(data) {
            console.log('Remove response:', data);
            if (data.success) {
                alert('✓ Removed ' + username + ' from network');
                loadActiveVouchers(); // Refresh list
            } else {
                alert('✗ Error: ' + (data.message || 'Failed to remove user'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Remove error:', {status: status, error: error, response: xhr.responseText});
            alert('✗ Error removing user: ' + (xhr.responseText ? xhr.responseText.substring(0, 100) : error));
        }
    });
}

// ========================================
// PRICING & BOOKING LOGIC
// ========================================
// Pricing structure
const PRICING = {
    5: 280,    // 5 pax: ₱280/hr
    8: 485     // 8 pax and above: ₱485/hr
};
const EXTRA_PAX_RATE = 56;  // ₱56 per additional person

let currentBookingId = null;
let originalOccupancy = null;
let originalPrice = null;

function getPriceForOccupancy(occupancy) {
    // Determine the base hourly rate based on occupancy
    if (occupancy <= 5) return PRICING[5];
    return PRICING[8];  // 8 pax or more gets the base 8-pax rate
}

function getUpdatedHourlyRate(bookingOccupancy, finalOccupancy) {
    // Get the base hourly rate for the booked occupancy
    const baseHourlyRate = getPriceForOccupancy(bookingOccupancy);
    
    // If more people show up, add surcharge per extra person
    const extraPax = Math.max(0, finalOccupancy - bookingOccupancy);
    const extraCharge = extraPax * EXTRA_PAX_RATE;
    
    // Return the new hourly rate
    return baseHourlyRate + extraCharge;
}

function openCheckinModal(bookingId, occupancy, price) {
    currentBookingId = bookingId;
    originalOccupancy = occupancy;
    originalPrice = price;
    
    $('#modal-booking-id').val(bookingId);
    $('#final-occupancy').val(occupancy);
    $('#final-occupancy').attr('min', occupancy);  // Can't reduce below original
    updatePriceDisplay();
    $('#issue-voucher-check').prop('checked', false);
    $('#voucher-form-section').hide();
    
    // Clear voucher form
    $('#voucher-username').val('');
    $('#voucher-password').val('');
    // Auto-populate data limit from selected package
    updateDataLimitFromPackage();
    
    $('#checkin-modal').addClass('active');
}

/**
 * Update data limit field based on currently selected package.
 * Also auto-selects the matching bandwidth profile when the package
 * declares a profile_suffix (window passes + new semantic duration profiles).
 * Called when modal opens or when package dropdown changes.
 */
function updateDataLimitFromPackage() {
    const selectedPackageId = $('#voucher-package').val();
    if (!selectedPackageId || !PACKAGE_DATA[selectedPackageId]) return;

    const packageInfo = PACKAGE_DATA[selectedPackageId];
    $('#voucher-data-limit').val(packageInfo.data_limit_gb);

    const suffix = packageInfo.profile_suffix || null;
    const $profileSelect = $('#voucher-profile');
    if (suffix && $profileSelect.find('option[value="' + suffix + '"]').length) {
        $profileSelect.val(suffix);
        $('#voucher-profile-auto-badge').show();
    } else {
        $('#voucher-profile-auto-badge').hide();
    }
}

// Manual profile change clears the auto badge
$(document).on('change', '#voucher-profile', function () {
    $('#voucher-profile-auto-badge').hide();
});

function updatePriceDisplay() {
    const finalOccupancy = parseInt($('#final-occupancy').val()) || originalOccupancy;
    const updatedHourlyRate = getUpdatedHourlyRate(originalOccupancy, finalOccupancy);
    $('#new-price').text( updatedHourlyRate.toFixed(2) + '/hr');
}

function closeCheckinModal() {
    $('#checkin-modal').removeClass('active');
    currentBookingId = null;
}

// Update price when occupancy changes
$('#final-occupancy').on('input', function() {
    updatePriceDisplay();
});

// Toggle voucher form visibility
$('#issue-voucher-check').on('change', function() {
    if ($(this).is(':checked')) {
        $('#voucher-form-section').show();
    } else {
        $('#voucher-form-section').hide();
    }
});

// ========================================
// AUTO-POPULATE DATA LIMIT BY PACKAGE
// ========================================
/**
 * Update data limit field when package is selected
 * Uses the data_limit_gb from PACKAGE_DATA which is defined in packages_config.php
 * User can still manually override the value
 */
$('#voucher-package').on('change', updateDataLimitFromPackage);

$('#checkin-form').on('submit', function(e) {
    e.preventDefault();
    
    const btn = $(this).find('button[type="submit"]');
    const originalBtnText = btn.text();
    btn.prop('disabled', true).text('Processing...');
    
    const checkInData = {
        booking_id: currentBookingId,
        final_occupancy: $('#final-occupancy').val(),
        csrf_token: $('input[name="csrf_token"]').val()
    };

    function runCheckIn(afterVoucher) {
        $.ajax({
            url: 'ajax_huddle_checkin.php',
            type: 'POST',
            data: checkInData,
            dataType: 'json',
            success: function(data) {
                if (!data.success) {
                    showMessage('✗ Check-in failed: ' + data.message, 'danger');
                    btn.prop('disabled', false).text(originalBtnText);
                    return;
                }

                showMessage(afterVoucher ? '✓ Voucher issued and check-in successful!' : '✓ Check-in successful!', 'success');
                closeCheckinModal();
                setTimeout(() => location.reload(), 1500);
            },
            error: function() {
                showMessage('✗ An error occurred during check-in', 'danger');
                btn.prop('disabled', false).text(originalBtnText);
            }
        });
    }

    // If voucher is requested, issue voucher first to avoid partial check-in state on voucher errors
    if ($('#issue-voucher-check').is(':checked')) {
        issueVoucher(currentBookingId, function() {
            runCheckIn(true);
        }, function() {
            btn.prop('disabled', false).text(originalBtnText);
        });
    } else {
        runCheckIn(false);
    }
});

function issueVoucher(bookingId, onSuccess, onError) {
    const voucherData = {
        booking_id: bookingId,
        voucher_username: $('#voucher-username').val(),
        voucher_password: $('#voucher-password').val(),
        package_id: $('#voucher-package').val(),
        bandwidth_profile: $('#voucher-profile').val(),
        data_limit_gb: $('#voucher-data-limit').val(),
        csrf_token: $('input[name="csrf_token"]').val()
    };
    
    // Validate
    if (!voucherData.voucher_username || !voucherData.voucher_password || !voucherData.package_id) {
        showMessage('✗ Please fill in all voucher fields', 'danger');
        if (onError) onError();
        return;
    }
    
    $.ajax({
        url: 'ajax_huddle_issue_voucher.php',
        type: 'POST',
        data: voucherData,
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                if (onSuccess) onSuccess();
            } else {
                showMessage('✗ Voucher creation failed: ' + data.message, 'danger');
                if (onError) onError();
            }
        },
        error: function() {
            showMessage('✗ An error occurred while creating voucher', 'danger');
            if (onError) onError();
        }
    });
}

function cancelBooking(bookingId, ref) {
    if (!confirm('Cancel booking ' + ref + '?')) return;
    
    $.ajax({
        url: 'ajax_huddle_cancel.php',
        type: 'POST',
        data: {
            booking_id: bookingId,
            cancellation_reason: 'Cancelled by staff',
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                showMessage('✓ Booking cancelled', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showMessage('✗ ' + data.message, 'danger');
            }
        }
    });
}

function showMessage(msg, type) {
    const classes = 'alert alert-' + (type || 'info');
    $('#message-area').html('<div class="' + classes + '">' + msg + '</div>');
}

function getCookie(name) {
    return document.querySelector('[name="csrf_token"]')?.value || '';
}

// ========================================
// PAGE INITIALIZATION
// ========================================
$(document).ready(function() {
    // Start auto-refreshing active vouchers
    startVouchersAutoRefresh();
});
</script>
</body>
</html>
