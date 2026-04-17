<?php
/**
 * Huddle Room Booking System
 * Public-facing page for customers to book the Huddle Room
 */
require_once 'dbconfig.php';
require_once 'packages_config.php';
require_once 'security_helper.php';

// Start secure session (required for CSRF token to work)
secure_session_start();

// Generate CSRF token for form submission
$csrf = csrf_token();

// Fetch current pricing
$pricingStmt = $DB_con->query("SELECT * FROM huddle_room_pricing WHERE active = 1 ORDER BY occupancy_level");
$pricing_tiers = [];
while ($row = $pricingStmt->fetch(PDO::FETCH_ASSOC)) {
    $pricing_tiers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Huddle Room Booking — MindSpace</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px 0; }
        .booking-container { background: white; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto; overflow: hidden; }
        .booking-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .booking-header h1 { margin: 0; font-weight: 700; font-size: 32px; }
        .booking-header p { margin: 8px 0 0; opacity: 0.9; }
        .booking-body { padding: 40px; }
        
        .calendar-section { margin-bottom: 40px; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .calendar-header h4 { margin: 0; font-weight: 600; color: #333; }
        .calendar-nav { display: flex; gap: 10px; }
        .calendar-nav button { padding: 6px 12px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .calendar-nav button:hover { background: #f5f5f5; }
        
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 20px; }
        .calendar-day-header { text-align: center; font-weight: 600; color: #666; font-size: 12px; padding: 8px 0; }
        .calendar-day { text-align: center; padding: 12px 8px; border: 1px solid #e0e0e0; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
        .calendar-day:hover { border-color: #667eea; background: #f8f9ff; }
        .calendar-day.disabled { opacity: 0.5; cursor: not-allowed; background: #f9f9f9; }
        .calendar-day.selected { background: #667eea; color: white; border-color: #667eea; font-weight: 600; }
        .calendar-day.booked { background: #fff3cd; border-color: #ffc107; }
        .calendar-day-date { font-weight: 600; display: block; }
        .calendar-day-availability { font-size: 11px; color: #999; display: block; }
        .calendar-day.selected .calendar-day-availability { color: rgba(255,255,255,0.7); }
        
        .booking-form { background: #f9f9f9; padding: 20px; border-radius: 8px; }
        .booking-form h4 { margin-bottom: 20px; font-weight: 600; color: #333; }
        .form-group { margin-bottom: 16px; }
        .form-group label { font-weight: 600; color: #555; font-size: 13px; margin-bottom: 6px; display: block; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-row.full { grid-template-columns: 1fr; }
        
        .pricing-summary { background: white; border: 2px solid #667eea; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .pricing-summary h5 { margin: 0 0 12px; font-weight: 600; color: #333; }
        .price-breakdown { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 12px; }
        .price-item { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
        .price-item:last-child { border-bottom: none; }
        .price-item.total { font-weight: 700; color: #667eea; font-size: 16px; }
        
        .btn-book { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: transform 0.2s; }
        .btn-book:hover { transform: translateY(-2px); }
        .btn-book:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
        .alert-danger { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .alert-info { background: #eef; color: #33c; border: 1px solid #ccf; }
        
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="booking-container">
    <div class="booking-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>📅 Huddle Room Booking</h1>
            <p>Reserve your private space for meetings, team sessions, or focused work</p>
        </div>
        <a href="index.php" style="color: white; text-decoration: none; margin-right: 20px;" title="Back to Dashboard">
            <i class="fa fa-arrow-left" style="font-size: 24px;"></i>
        </a>
    </div>
    
    <div class="booking-body">
        <!-- Pricing Info -->
        <div class="pricing-summary">
            <h5>Pricing</h5>
            <?php foreach ($pricing_tiers as $tier): ?>
                <div class="price-item">
                    <span><?= $tier['occupancy_level'] ?> Person<?= $tier['occupancy_level'] > 1 ? 's' : '' ?></span>
                    <strong>₱<?= number_format($tier['hourly_rate'], 2) ?>/hr</strong>
                </div>
            <?php endforeach; ?>
            <div class="price-item">
                <span><small>Additional persons</small></span>
                <small><strong>₱56/person</strong></small>
            </div>
        </div>
        
        <!-- Messages -->
        <div id="message-area"></div>
        
        <!-- Calendar Selection -->
        <div class="calendar-section">
            <div class="calendar-header">
                <h4>📅 Select Date</h4>
                <div class="calendar-nav">
                    <button onclick="prevMonth()">&larr; Previous</button>
                    <span id="calendar-month" style="padding: 6px 12px; font-weight: 600;"></span>
                    <button onclick="nextMonth()">Next &rarr;</button>
                </div>
            </div>
            
            <!-- Calendar Grid -->
            <div class="calendar-grid" id="calendar-grid">
                <!-- Populated by JS -->
            </div>
        </div>
        
        <!-- Booking Form -->
        <form id="booking-form" class="booking-form">
            <h4>Your Details</h4>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="visitor_name">Full Name *</label>
                    <input type="text" id="visitor_name" name="visitor_name" required>
                </div>
                <div class="form-group">
                    <label for="visitor_email">Email Address *</label>
                    <input type="email" id="visitor_email" name="visitor_email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="visitor_phone">Phone Number</label>
                    <input type="tel" id="visitor_phone" name="visitor_phone">
                </div>
                <div class="form-group">
                    <label for="company_name">Company/Organization</label>
                    <input type="text" id="company_name" name="company_name">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="booking_date">Booking Date *</label>
                    <input type="text" id="booking_date" name="booking_date" placeholder="Select from calendar" readonly required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time *</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="duration_hours">Duration (Hours) *</label>
                    <input type="number" id="duration_hours" name="duration_hours" min="1" max="24" value="1" required>
                    <small style="color: #999; display: block; margin-top: 4px;">Enter 1-24 hours (full day = 8 hours)</small>
                </div>
                <div class="form-group">
                    <label for="occupancy">Expected Number of People *</label>
                    <select id="occupancy" name="occupancy" required>
                        <option value="">Select occupancy</option>
                        <option value="5">5 People (₱280/hr)</option>
                        <option value="8">8 People (₱485/hr)</option>
                    </select>
                </div>
            </div>
            
            <!-- Total Price Display -->
            <div class="pricing-summary">
                <div class="price-breakdown">
                    <div class="price-item">
                        <span>Hourly Rate</span>
                        <span id="rate-display">₱0.00</span>
                    </div>
                    <div class="price-item">
                        <span>Duration</span>
                        <span id="duration-display">1 hour</span>
                    </div>
                </div>
                <div class="price-item total">
                    <span>Total Price</span>
                    <span id="total-price-display">₱0.00</span>
                </div>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            
            <button type="submit" class="btn-book" id="btn-submit">
                <span id="btn-text">Complete Booking</span>
            </button>
        </form>
    </div>
</div>

<script src="js/jquery-2.1.1.min.js"></script>
<script>
// Calendar state
let currentDate = new Date();
let selectedDate = null;
const hourlyRates = {
    5: 280,
    8: 485
};

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Update header
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    $('#calendar-month').text(monthNames[month] + ' ' + year);
    
    // Fetch calendar data via AJAX
    $.ajax({
        url: 'ajax_huddle_get_calendar.php',
        type: 'GET',
        data: { year: year, month: month + 1 },
        dataType: 'json',
        success: function(data) {
            if (!data.success) {
                showMessage('Error loading calendar: ' + data.message, 'danger');
                return;
            }
            
            const grid = $('#calendar-grid');
            grid.empty();
            
            // Add day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(d => {
                grid.append('<div class="calendar-day-header">' + d + '</div>');
            });
            
            // Add empty cells for days before month starts
            const firstDay = new Date(year, month, 1).getDay();
            for (let i = 0; i < firstDay; i++) {
                grid.append('<div class="calendar-day disabled"></div>');
            }
            
            // Add day cells
            data.days.forEach(day => {
                const cell = $('<div class="calendar-day"></div>');
                const date = new Date(day.date);
                const dayNum = date.getDate();
                const dayClass = (day.date === selectedDate) ? 'selected' : '';
                
                if (!day.is_open) {
                    cell.addClass('disabled');
                    cell.html('<span class="calendar-day-date">' + dayNum + '</span><span class="calendar-day-availability">Closed</span>');
                } else if (day.available_slots <= 0) {
                    cell.addClass('booked');
                    cell.html('<span class="calendar-day-date">' + dayNum + '</span><span class="calendar-day-availability">Booked</span>');
                } else {
                    cell.addClass(dayClass);
                    cell.html('<span class="calendar-day-date">' + dayNum + '</span><span class="calendar-day-availability">' + day.available_slots + ' slot(s)</span>');
                    cell.click(function() { selectDate(day.date); });
                }
                
                grid.append(cell);
            });
        },
        error: function() {
            showMessage('Failed to load calendar data', 'danger');
        }
    });
}

function selectDate(date) {
    selectedDate = date;
    $('#booking_date').val(date);
    renderCalendar();
}

function prevMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
}

function updatePricingDisplay() {
    const occupancy = $('#occupancy').val();
    const duration = $('#duration_hours').val();
    
    if (!occupancy || !duration) {
        $('#rate-display').text('₱0.00');
        $('#total-price-display').text('₱0.00');
        $('#duration-display').text('');
        return;
    }
    
    const rate = hourlyRates[occupancy];
    const hours = parseInt(duration) || 0;
    
    if (hours < 1 || hours > 24) {
        $('#rate-display').text('₱0.00');
        $('#total-price-display').text('₱0.00');
        $('#duration-display').text('Invalid duration');
        return;
    }
    
    const total = rate * hours;
    
    $('#rate-display').text('₱' + number_format(rate, 2));
    $('#total-price-display').text('₱' + number_format(total, 2));
    $('#duration-display').text(hours + ' hour' + (hours > 1 ? 's' : ''));
}

function number_format(value, decimals) {
    return parseFloat(value).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function showMessage(msg, type) {
    const alertClass = 'alert alert-' + (type || 'info');
    $('#message-area').html('<div class="' + alertClass + '">' + msg + '</div>');
    window.scrollTo(0, 0);
}

// Form submission
$('#booking-form').on('submit', function(e) {
    e.preventDefault();
    
    if (!selectedDate) {
        showMessage('Please select a booking date', 'danger');
        return;
    }
    
    const btn = $('#btn-submit');
    btn.prop('disabled', true).html('<span class="spinner"></span>Booking...');
    
    $.ajax({
        url: 'ajax_huddle_book.php',
        type: 'POST',
        data: {
            visitor_name: $('#visitor_name').val(),
            visitor_email: $('#visitor_email').val(),
            visitor_phone: $('#visitor_phone').val(),
            company_name: $('#company_name').val(),
            booking_date: $('#booking_date').val(),
            start_time: $('#start_time').val(),
            duration_hours: $('#duration_hours').val(),
            occupancy: $('#occupancy').val(),
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                showMessage('✓ ' + data.message + ' — Confirmation email sent to ' + $('#visitor_email').val(), 'success');
                $('#booking-form')[0].reset();
                selectedDate = null;
                renderCalendar();
                setTimeout(() => {
                    window.location.href = 'index.php'; // Redirect to dashboard
                }, 3000);
            } else {
                showMessage('✗ ' + data.message, 'danger');
                btn.prop('disabled', false).html('<span id="btn-text">Complete Booking</span>');
            }
        },
        error: function() {
            showMessage('✗ An error occurred. Please try again.', 'danger');
            btn.prop('disabled', false).html('<span id="btn-text">Complete Booking</span>');
        }
    });
});

// Event listeners
$('#occupancy, #duration_hours').on('change', updatePricingDisplay);

function getCookie(name) {
    // Fallback - in production, use a proper CSRF token mechanism
    return document.querySelector('[name="csrf_token"]')?.value || '';
}

// Initial load
$(document).ready(function() {
    renderCalendar();
});
</script>
</body>
</html>
