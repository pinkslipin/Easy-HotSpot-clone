/**
 * seats.js — MindSpace Workspace Hub / Seat Map
 *
 * Responsibilities:
 *  • Load seat data from ajax_seats.php and render the zone grids
 *  • Handle seat-click interactions (reserve / occupy / release)
 *  • Show a Bootstrap modal with context-appropriate action buttons
 *  • Display toast notifications for success / error
 *  • Auto-refresh the map every 30 seconds without losing scroll position
 *
 * Depends on: jQuery 2.x, Bootstrap 3.3.x (already loaded by header.php)
 * Required global:  CSRF_TOKEN  (output by seats.php as a JS variable)
 */

(function ($) {
    'use strict';

    // ── Configuration ─────────────────────────────────────────────────────────
    var REFRESH_INTERVAL   = 30000;   // ms — same cadence as active-users modal
    var ENDPOINTS = {
        fetch    : 'ajax_seats.php',
        action   : 'ajax_seat_action.php',
        detail   : 'ajax_seat_detail.php',
        extend   : 'ajax_seat_extend.php',
        transfer : 'ajax_seat_transfer.php',
    };

    // Zone display order (matches SQL ORDER BY FIELD)
    var ZONE_ORDER = ['Main Entrance', 'Inner Room', 'Conference Room', 'Private Room'];

    // Cached seat data — used to populate the transfer destination dropdown
    var seatsCache = {};

    // Status label / badge colours
    var STATUS_META = {
        available : { label: 'Available', cls: 'seat-available', badge: 'label-success'  },
        reserved  : { label: 'Reserved',  cls: 'seat-reserved',  badge: 'label-warning'  },
        occupied  : { label: 'Occupied',  cls: 'seat-occupied',  badge: 'label-danger'   },
    };

    // Internal state
    var refreshTimer  = null;
    var pendingSeatId = null;   // seat being acted on (for spinner)
    var seenSystemNotifs = {};  // de-dup server events across refresh ticks

    // ── Bootstrap: launch ─────────────────────────────────────────────────────
    $(document).ready(function () {
        loadSeats();
        startAutoRefresh();
        bindModalEvents();
        // Expose for the Refresh Now button in seats.php
        window._loadSeats = loadSeats;
    });

    // ── Seat loading & rendering ──────────────────────────────────────────────

    /**
     * Fetch all seat data from the server and re-render the map.
     * Preserves vertical scroll position so the page doesn't jump on refresh.
     */
    function loadSeats() {
        var scrollY = window.scrollY || document.documentElement.scrollTop;

        $.ajax({
            url      : ENDPOINTS.fetch,
            method   : 'GET',
            data     : { csrf_token: CSRF_TOKEN },
            dataType : 'json',
            success  : function (data) {
                if (!data.success) {
                    showToast('error', 'Could not load seat map: ' + (data.message || 'unknown error'));
                    return;
                }
                renderSeatMap(data.seats, data.summary, data.ts);
                showSystemNotifications(data.notifications || []);
                window.scrollTo(0, scrollY);
            },
            error    : function () {
                showToast('error', 'Network error — could not refresh seat map.');
            },
        });
    }

    /**
     * Build and inject the full seat-map HTML.
     *
     * @param {Object} grouped   Keys = zone names, values = arrays of seat objects
     * @param {Object} summary   { total, available, reserved, occupied }
     * @param {string} ts        Server timestamp string
     */
    function renderSeatMap(grouped, summary, ts) {
        // Cache seat data so the transfer dropdown can read available seats
        seatsCache = grouped;

        // ── Summary bar ───────────────────────────────────────────────────────
        $('#seat-summary').html(
            summaryBadge(summary.available, 'Available', 'success') +
            summaryBadge(summary.reserved,  'Reserved',  'warning') +
            summaryBadge(summary.occupied,  'Occupied',  'danger')  +
            '<span class="seat-refresh-ts text-muted"> &nbsp;Last updated: ' + escHtml(ts) + '</span>'
        );

        // ── Zone grids ───────────────────────────────────────────────────────
        var $container = $('#seat-map-container').empty();

        ZONE_ORDER.forEach(function (zoneName) {
            var seats = grouped[zoneName];
            if (!seats || seats.length === 0) return;

            var $section = $('<div class="seat-zone-section">');

            // Zone header
            var availableInZone = seats.filter(function (s) { return s.status === 'available'; }).length;
            $section.append(
                '<div class="seat-zone-header">' +
                    '<span class="seat-zone-title">' + escHtml(zoneName) + '</span>' +
                    '<span class="seat-zone-count">' + seats.length + ' seats &middot; ' +
                        '<span class="text-success"><strong>' + availableInZone + '</strong> free</span>' +
                    '</span>' +
                '</div>'
            );

            // Seat grid
            var $grid = $('<div class="seat-grid">');
            seats.forEach(function (seat) {
                $grid.append(buildSeatCard(seat));
            });
            $section.append($grid);
            $container.append($section);
        });
    }

    /**
     * Build a single CSS seat-card element.
     *
     * @param  {Object} seat  Seat record from server
     * @return {jQuery}
     */
    function buildSeatCard(seat) {
        var meta    = STATUS_META[seat.status] || STATUS_META.available;
        var tooltip = buildTooltip(seat);
        var isClickable = true;   // all statuses are clickable — action differs

        var $seat = $(
            '<div class="seat ' + meta.cls + (isClickable ? ' seat-clickable' : '') + '"' +
                ' data-seat-id="'     + escAttr(seat.id)          + '"' +
                ' data-status="'      + escAttr(seat.status)       + '"' +
                ' data-seat-number="' + escAttr(seat.seat_number)  + '"' +
                ' data-zone="'        + escAttr(seat.zone)         + '"' +
                ' data-expires-in="'  + escAttr(seat.expires_in || '') + '"' +
                ' title="'            + escAttr(tooltip)           + '">' +
                '<span class="seat-label">' + escHtml(seat.seat_number) + '</span>' +
            '</div>'
        );

        // Attach click handler
        $seat.on('click', function () {
            onSeatClick($(this));
        });

        return $seat;
    }

    /**
     * Tooltip text for a seat card.
     */
    function buildTooltip(seat) {
        var meta = STATUS_META[seat.status] || STATUS_META.available;
        var tip  = seat.seat_number + ' \u2013 ' + meta.label;
        if (seat.status === 'reserved' && seat.expires_in) {
            tip += ' (expires in ' + seat.expires_in + ')';
        }
        return tip;
    }

    // ── Seat click handler ────────────────────────────────────────────────────

    /**
     * Opens the action modal with context-appropriate buttons.
     */
    function onSeatClick($seat) {
        var seatId     = $seat.data('seat-id');
        var status     = $seat.data('status');
        var seatNumber = $seat.data('seat-number');
        var zone       = $seat.data('zone');
        var expiresIn  = $seat.data('expires-in');
        var meta       = STATUS_META[status] || STATUS_META.available;

        // Populate modal header
        $('#seat-modal-title').text(seatNumber);
        $('#seat-modal-zone').text(zone);
        $('#seat-modal-badge')
            .text(meta.label)
            .removeClass('label-success label-warning label-danger')
            .addClass(meta.badge);

        // Extra info row (expiry countdown for reserved)
        if (status === 'reserved' && expiresIn) {
            $('#seat-modal-info').html('<i class="fa fa-clock-o"></i> Expires in <strong>' + escHtml(expiresIn) + '</strong>').show();
        } else {
            $('#seat-modal-info').hide();
        }

        // Show/hide action buttons based on current status
        // available → Walk-in (occupy + voucher) or Reserve (hold only)
        // reserved  → Check In (occupy + voucher) or Release
        // occupied  → detail panel only (Release lives in detail footer)
        $('#btn-seat-walkin').toggle(status === 'available');
        $('#btn-seat-reserve').toggle(status === 'available');
        $('#btn-seat-checkin').toggle(status === 'reserved');
        // Reserve action-bar Release only for reserved seats
        // (occupied seats: Release is in the detail footer)
        $('#btn-seat-release').toggle(status === 'reserved');

        // Always start with all extra panels hidden
        $('#seat-walkin-form').hide();
        $('#seat-detail-panel').hide();
        $('#seat-extend-panel').hide();
        if (status === 'occupied') {
            // Hide action button bar — everything is in the detail footer
            $('.seat-modal-actions').hide();
        } else {
            $('.seat-modal-actions').show();
        }

        // Store seat id on action buttons for use in the confirm handler
        $('#seat-action-modal').data('seat-id', seatId).data('seat-number', seatNumber);

        $('#seat-action-modal').modal('show');

        // Kick off live detail fetch for seats that have session data
        if (status === 'reserved' || status === 'occupied') {
            loadSeatDetail(seatId);
        }
    }

    // ── Modal action button bindings ──────────────────────────────────────────

    function bindModalEvents() {
        // Walk-in: available → show voucher form → occupy on submit
        $('#btn-seat-walkin').on('click', function () {
            showWalkinForm('walkin');
        });
        // Reserve only: available → hold without a voucher
        $('#btn-seat-reserve').on('click', function () {
            executeAction('reserve');
        });
        // Check In: reserved → show voucher form → occupy on submit
        $('#btn-seat-checkin').on('click', function () {
            showWalkinForm('checkin');
        });
        // Release (action bar — for reserved seats)
        $('#btn-seat-release').on('click', function () {
            executeAction('release');
        });
        // Release (detail footer — for occupied seats)
        $('#btn-detail-release').on('click', function () {
            executeAction('release');
        });
        // Voucher form — Back button
        $('#btn-walkin-cancel').on('click', hideWalkinForm);
        // Voucher form — Issue & Seat submit
        $('#btn-walkin-submit').on('click', submitWalkin);
        // ── Auto-populate data limit by package selection ────────────────────
        // When user selects a package, auto-fill the data limit from package config
        // User can still manually override this value
        $('#swf-package').on('change', updateDataLimitFromPackage);
        // ────────────────────────────────────────────────────────────────────
        // Extend — open panel
        $('#btn-detail-extend').on('click', showExtendPanel);
        // Extend — cancel
        $('#btn-extend-cancel').on('click', hideExtendPanel);
        // Extend — quick-select buttons
        $(document).on('click', '.btn-extend-opt', function () {
            $('.btn-extend-opt').removeClass('active');
            $(this).addClass('active');
            $('#btn-extend-confirm').prop('disabled', false);
            $('#seat-action-modal').data('extend-mins', parseInt($(this).data('mins'), 10));
        });
        // Extend — confirm
        $('#btn-extend-confirm').on('click', submitExtend);
        // Change Seat — open transfer panel
        $('#btn-detail-transfer').on('click', showTransferPanel);
        // Change Seat — cancel
        $('#btn-transfer-cancel').on('click', hideTransferPanel);
        // Change Seat — show credential fields when a seat is chosen
        $('#swt-destination').on('change', function () {
            var val = $(this).val();
            if (val) {
                // Auto-suggest credentials from destination seat label
                var label = $('#swt-destination option:selected').text().toLowerCase().replace('-', '');
                if (!$('#swt-username').val()) $('#swt-username').val(label);
                if (!$('#swt-password').val()) $('#swt-password').val(randomPassword());
                $('#swt-creds-row').show();
            } else {
                $('#swt-creds-row').hide();
            }
            $('#btn-transfer-confirm').prop('disabled', !val);
        });
        // Change Seat — confirm
        $('#btn-transfer-confirm').on('click', submitTransfer);
        // Reset form state whenever the modal fully closes
        $('#seat-action-modal').on('hidden.bs.modal', resetModal);
    }

    /**
     * Display the inline voucher form and store which action triggered it.
     * @param {string} action  'walkin' | 'checkin'
     */
    function showWalkinForm(action) {
        $('#seat-action-modal').data('walkin-action', action);
        $('.seat-modal-actions').hide();
        $('#seat-walkin-form').show();
        // Auto-populate data limit from the currently selected package
        updateDataLimitFromPackage();
        $('#swf-username').focus();
    }

    /**
     * Update the data limit field based on the current package selection.
     * Called when form is shown or when package dropdown changes.
     */
    function updateDataLimitFromPackage() {
        var selectedPackageId = $('#swf-package').val();
        if (selectedPackageId && typeof PACKAGE_DATA !== 'undefined' && PACKAGE_DATA[selectedPackageId]) {
            var packageInfo = PACKAGE_DATA[selectedPackageId];
            var dataLimitGb = packageInfo.data_limit_gb;
            $('#swf-limit-bytes').val(dataLimitGb);
        }
    }

    /** Hide the voucher form and restore the action buttons. */
    function hideWalkinForm() {
        $('#seat-walkin-form').hide();
        $('.seat-modal-actions').show();
    }

    /** Reset all form fields and view state when the modal is dismissed. */
    function resetModal() {
        hideWalkinForm();
        hideExtendPanel();
        hideTransferPanel();
        $('#seat-detail-panel').hide();
        $('#seat-detail-content').hide();
        $('#seat-detail-loading').show();
        $('#swf-username').val('');
        $('#swf-password').val('');
        $('#swf-limit-bytes').val('0');
        $('.btn-extend-opt').removeClass('active');
        $('#btn-extend-confirm').prop('disabled', true);
        $('#seat-action-modal').removeData('extend-mins');
    }

    /**
     * Submit the inline voucher form to ajax_seat_walkin.php.
     * On success: close modal, show a toast with the credentials, refresh map.
     */
    function submitWalkin() {
        var $modal     = $('#seat-action-modal');
        var seatId     = $modal.data('seat-id');
        var action     = $modal.data('walkin-action');
        var username   = $.trim($('#swf-username').val());
        var password   = $.trim($('#swf-password').val());
        var packageId  = $('#swf-package').val();
        var profile    = $('#swf-profile').val();
        var limitBytes = $('#swf-limit-bytes').val() || '0';

        if (!username) {
            $('#swf-username').focus();
            showToast('error', 'Username is required.');
            return;
        }
        if (!password) {
            $('#swf-password').focus();
            showToast('error', 'Password is required.');
            return;
        }

        setModalLoading(true);

        $.ajax({
            url      : 'ajax_seat_walkin.php',
            method   : 'POST',
            data     : {
                csrf_token  : CSRF_TOKEN,
                action      : action,
                seat_id     : seatId,
                username    : username,
                password    : password,
                package_id  : packageId,
                profile     : profile,
                limit_bytes : limitBytes,
            },
            dataType : 'json',
            success  : function (data) {
                setModalLoading(false);
                $modal.modal('hide');
                if (data.success) {
                    showToast(
                        'success',
                        '<strong>' + escHtml(data.username) + '</strong>' +
                        ' / ' + escHtml(data.password) +
                        ' &nbsp;&mdash;&nbsp; ' + escHtml(data.package_name) +
                        ' <span class="text-muted">(' + escHtml(data.price) + ')</span>',
                        true
                    );
                } else {
                    showToast('error', data.message || 'Action failed.');
                }
                loadSeats();
            },
            error    : function () {
                setModalLoading(false);
                $modal.modal('hide');
                showToast('error', 'Network error — please try again.');
            },
        });
    }

    // ── Seat detail loading ───────────────────────────────────────────────────

    /**
     * Fetch live session detail for an occupied / reserved seat from the server.
     * Shows #seat-detail-panel with a loading spinner until data arrives.
     */
    function loadSeatDetail(seatId) {
        // Show panel in loading state
        $('#seat-detail-content').hide();
        $('#seat-detail-loading').show();
        $('#seat-detail-panel').show();

        $.ajax({
            url      : ENDPOINTS.detail,
            method   : 'GET',
            data     : { csrf_token: CSRF_TOKEN, seat_id: seatId },
            dataType : 'json',
            success  : function (data) {
                if (data.success) {
                    renderDetailPanel(data);
                } else {
                    $('#seat-detail-loading').html(
                        '<i class="fa fa-exclamation-triangle text-warning"></i> ' +
                        escHtml(data.message || 'Could not load detail.')
                    );
                }
            },
            error    : function () {
                $('#seat-detail-loading').html(
                    '<i class="fa fa-exclamation-triangle text-warning"></i> Network error loading detail.'
                );
            },
        });
    }

    /**
     * Populate the detail panel with the data returned by ajax_seat_detail.php.
     */
    function renderDetailPanel(d) {
        // Customer & package rows
        $('#dd-username').html(
            d.voucher_username
                ? '<strong>' + escHtml(d.voucher_username) + '</strong>'
                : '<span class="text-muted">No voucher assigned</span>'
        );

        if (d.package_name) {
            var priceHtml = d.voucher_price !== null
                ? ' <span class="text-muted">(' + escHtml(formatPhpPrice(d.voucher_price)) + ')</span>'
                : '';
            $('#dd-package').html(escHtml(d.package_name) + priceHtml);
        } else {
            $('#dd-package').html('<span class="text-muted">&mdash;</span>');
        }

        $('#dd-occupied-since').text(d.occupied_since || d.expires_at_fmt || '—');
        if (d.status === 'reserved' && d.expires_at_fmt) {
            $('#dd-occupied-since').html(
                (d.occupied_since || 'Reserved') +
                ' <span class="text-muted">(expires ' + escHtml(d.expires_at_fmt) + ')</span>'
            );
        }

        // Online status
        var onlineDot  = d.is_online
            ? '<span class="status-dot dot-online"></span><strong class="text-success">Connected</strong>'
            : '<span class="status-dot dot-offline"></span><span class="text-muted">Not connected</span>';
        $('#dd-online-status').html(onlineDot);

        // Session uptime
        if (d.session_uptime) {
            $('#dd-session-uptime').text(d.session_uptime);
            $('#dd-uptime-row').show();
        } else {
            $('#dd-uptime-row').hide();
        }

        // Time remaining — chip with urgency colour
        if (d.time_left_fmt) {
            var chipClass = 'time-chip-na';
            if (d.time_left_secs !== null) {
                if      (d.time_left_secs > 1800) chipClass = 'time-chip-ok';
                else if (d.time_left_secs > 600)  chipClass = 'time-chip-warn';
                else                              chipClass = 'time-chip-crit';
            }
            $('#dd-time-left').html(
                '<span class="time-chip ' + chipClass + '">' + escHtml(d.time_left_fmt) + '</span>'
            );
            $('#dd-timeleft-row').show();
        } else {
            $('#dd-timeleft-row').hide();
        }

        // Finish time (e.g. "3:00 p.m.")
        if (d.finish_time_fmt) {
            $('#dd-finish').html('<strong>' + escHtml(d.finish_time_fmt) + '</strong>');
            $('#dd-finish-row').show();
        } else {
            $('#dd-finish-row').hide();
        }

        // IP / MAC
        if (d.ip_address) {
            $('#dd-ip').text(d.ip_address);
            $('#dd-ip-row').show();
        } else { $('#dd-ip-row').hide(); }

        if (d.mac_address) {
            $('#dd-mac').text(d.mac_address);
            $('#dd-mac-row').show();
        } else { $('#dd-mac-row').hide(); }

        // Data usage
        if (d.bytes_in_fmt || d.bytes_out_fmt) {
            $('#dd-data').html(
                '<i class="fa fa-arrow-down"></i> ' + escHtml(d.bytes_in_fmt || '0 B') +
                ' &nbsp; <i class="fa fa-arrow-up"></i> ' + escHtml(d.bytes_out_fmt || '0 B')
            );
            $('#dd-data-row').show();
        } else { $('#dd-data-row').hide(); }

        // Footer buttons
        if (d.voucher_username) {
            $('#btn-detail-extend').show();
            $('#btn-detail-transfer').show();
        } else {
            $('#btn-detail-extend').hide();
            $('#btn-detail-transfer').hide();
        }
        $('#btn-detail-release').show();

        // Reveal content, hide loader
        $('#seat-detail-loading').hide();
        $('#seat-detail-content').show();
    }

    /** Format a PHP float price as a local currency string (mirrors formatPrice()). */
    function formatPhpPrice(value) {
        return '\u20b1' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ── Extend panel helpers ──────────────────────────────────────────────────

    function showExtendPanel() {
        $('#btn-detail-extend').hide();
        $('#seat-extend-panel').show();
    }

    function hideExtendPanel() {
        $('#seat-extend-panel').hide();
        $('#btn-detail-extend').show();
        $('.btn-extend-opt').removeClass('active');
        $('#btn-extend-confirm').prop('disabled', true);
        $('#seat-action-modal').removeData('extend-mins');
    }

    // ── Transfer panel helpers ────────────────────────────────────────────────

    function showTransferPanel() {
        // Build dropdown from cached available seats (excluding current seat)
        var currentId = $('#seat-action-modal').data('seat-id');
        var $sel = $('#swt-destination').empty()
            .append('<option value="">— select available seat —</option>');

        ZONE_ORDER.forEach(function (zoneName) {
            var zoneSeats = seatsCache[zoneName];
            if (!zoneSeats) return;
            var available = zoneSeats.filter(function (s) {
                return s.status === 'available' && s.id !== currentId;
            });
            if (available.length === 0) return;
            var $grp = $('<optgroup>').attr('label', zoneName);
            available.forEach(function (s) {
                $grp.append($('<option>').val(s.id).text(s.seat_number));
            });
            $sel.append($grp);
        });

        // Check if there are any options beyond the placeholder
        if ($sel.find('option').length <= 1) {
            showToast('error', 'No available seats to move to right now.');
            return;
        }

        $('#btn-transfer-confirm').prop('disabled', true);
        $('#btn-detail-transfer').hide();
        $('#seat-transfer-panel').show();
        $sel.focus();
    }

    function hideTransferPanel() {
        $('#seat-transfer-panel').hide();
        $('#swt-destination').val('');
        $('#swt-username').val('');
        $('#swt-password').val('');
        $('#swt-creds-row').hide();
        $('#btn-transfer-confirm').prop('disabled', true);
        if ($('#seat-detail-content').is(':visible')) {
            $('#btn-detail-transfer').show();
        }
    }

    /**
     * POST the seat transfer to ajax_seat_transfer.php.
     */
    function submitTransfer() {
        var $modal      = $('#seat-action-modal');
        var fromId      = $modal.data('seat-id');
        var toId        = parseInt($('#swt-destination').val(), 10);
        var newUsername = $.trim($('#swt-username').val());
        var newPassword = $.trim($('#swt-password').val());

        if (!toId) {
            showToast('error', 'Please select a destination seat.');
            return;
        }
        if (!newUsername) {
            $('#swt-username').focus();
            showToast('error', 'New username is required.');
            return;
        }
        if (!newPassword) {
            $('#swt-password').focus();
            showToast('error', 'New password is required.');
            return;
        }

        setModalLoading(true);

        $.ajax({
            url      : ENDPOINTS.transfer,
            method   : 'POST',
            data     : {
                csrf_token   : CSRF_TOKEN,
                from_seat_id : fromId,
                to_seat_id   : toId,
                new_username : newUsername,
                new_password : newPassword,
            },
            dataType : 'json',
            success  : function (data) {
                setModalLoading(false);
                $modal.modal('hide');
                if (data.success) {
                    var info = data.message + '<br>'
                        + '<strong>User:</strong> ' + escHtml(data.new_username)
                        + ' &nbsp; <strong>Pass:</strong> ' + escHtml(data.new_password)
                        + ' &nbsp; <strong>Time left:</strong> ' + escHtml(data.remaining_time);
                    showToast('success', info, true);
                    loadSeats();
                } else {
                    showToast('error', data.message || 'Transfer failed.');
                    loadSeats();
                }
            },
            error    : function () {
                setModalLoading(false);
                $modal.modal('hide');
                showToast('error', 'Network error — please try again.');
            },
        });
    }

    /** Generate a short random password (4 digits). */
    function randomPassword() {
        return String(Math.floor(1000 + Math.random() * 9000));
    }

    /**
     * POST the chosen extension to ajax_seat_extend.php.
     */
    function submitExtend() {
        var $modal    = $('#seat-action-modal');
        var seatId    = $modal.data('seat-id');
        var extendMin = $modal.data('extend-mins');

        if (!extendMin) {
            showToast('error', 'Please select a time amount first.');
            return;
        }

        setModalLoading(true);

        $.ajax({
            url      : ENDPOINTS.extend,
            method   : 'POST',
            data     : {
                csrf_token     : CSRF_TOKEN,
                seat_id        : seatId,
                extend_minutes : extendMin,
            },
            dataType : 'json',
            success  : function (data) {
                setModalLoading(false);
                if (data.success) {
                    showToast('success', data.message);
                    hideExtendPanel();
                    // Reload detail to show updated time
                    loadSeatDetail(seatId);
                } else {
                    showToast('error', data.message || 'Extension failed.');
                }
            },
            error    : function () {
                setModalLoading(false);
                showToast('error', 'Network error — please try again.');
            },
        });
    }

    /**
     * Execute a simple seat action (reserve / release) via AJAX.
     */
    function executeAction(action) {
        var $modal    = $('#seat-action-modal');
        var seatId    = $modal.data('seat-id');
        var seatLabel = $modal.data('seat-number');

        // Loading state
        setModalLoading(true);
        pendingSeatId = seatId;

        $.ajax({
            url      : ENDPOINTS.action,
            method   : 'POST',
            data     : {
                csrf_token : CSRF_TOKEN,
                action     : action,
                seat_id    : seatId,
            },
            dataType : 'json',
            success  : function (data) {
                setModalLoading(false);
                $modal.modal('hide');

                if (data.success) {
                    showToast('success', data.message);
                    loadSeats();          // refresh map immediately
                } else {
                    showToast('error', data.message || 'Action failed.');
                    loadSeats();          // re-sync in case status changed elsewhere
                }
            },
            error    : function () {
                setModalLoading(false);
                $modal.modal('hide');
                showToast('error', 'Network error — please try again.');
            },
        });
    }

    // ── Auto-refresh ──────────────────────────────────────────────────────────

    function startAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(loadSeats, REFRESH_INTERVAL);
    }

    function showSystemNotifications(notifications) {
        if (!notifications || !notifications.length) return;

        notifications.forEach(function (n) {
            var key = [n.event || '', n.username || '', n.seat_id || ''].join('|');
            if (!key || seenSystemNotifs[key]) return;

            seenSystemNotifs[key] = true;
            showToast('success', n.message || 'Voucher expiry auto-logout verified.');
        });
    }

    // Reset the countdown display every second
    setInterval(function () {
        var remaining = REFRESH_INTERVAL - (Date.now() % REFRESH_INTERVAL);
        var secs = Math.ceil(remaining / 1000);
        $('#seat-refresh-countdown').text(secs + 's');
    }, 1000);

    // ── UI helpers ────────────────────────────────────────────────────────────

    function summaryBadge(count, label, type) {
        return '<span class="label label-' + type + ' seat-summary-badge">' +
               '<strong>' + count + '</strong> ' + label +
               '</span> ';
    }

    /**
     * Simple toast notification injected at the top of #seat-map-container.
     * Auto-dismisses after 4 s.
     */
    function showToast(type, message, allowHtml) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon       = type === 'success' ? 'check-circle'  : 'exclamation-triangle';
        var displayMsg = allowHtml ? message : escHtml(message);
        var $toast     = $(
            '<div class="alert ' + alertClass + ' seat-toast alert-dismissible" role="alert">' +
                '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                '<i class="fa fa-' + icon + '"></i> ' + displayMsg +
            '</div>'
        );
        $('#seat-toast-area').prepend($toast);
        setTimeout(function () { $toast.fadeOut(500, function () { $(this).remove(); }); }, 4000);
    }

    /**
     * Disable/enable action buttons inside the modal while a request is in-flight.
     */
    function setModalLoading(loading) {
        var $btns = $('#seat-action-modal .btn-seat-action');
        if (loading) {
            $btns.prop('disabled', true);
            $('#seat-modal-spinner').show();
        } else {
            $btns.prop('disabled', false);
            $('#seat-modal-spinner').hide();
        }
    }

    /** Escape HTML entities to prevent XSS in dynamically built markup. */
    function escHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }

    /** Escape for use inside HTML attribute values. */
    function escAttr(str) {
        return escHtml(str);
    }

})(jQuery);
