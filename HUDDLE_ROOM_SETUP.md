# Huddle Room Booking System Setup

## Overview
The Huddle Room Booking System allows customers to book your dedicated meeting/work space with:
- **Calendar-based booking interface** for easy date/time selection
- **Occupancy-based dynamic pricing** (5 pax @ ₱260/hr, 8 pax @ ₱450/hr, +₱58 per extra person)
- **Staff management dashboard** to check in bookings and adjust final occupancy
- **Hours of operation management** (currently set to 24/7)

## Files Created

### Database & Configuration
- `huddle_room_bookings.sql` — Database schema (3 new tables)

### Public-Facing Pages
- `huddle_room_booking.php` — Customer booking interface with interactive calendar

### Staff Pages
- `huddle_room_manage.php` — Staff dashboard for check-in and management

### API Endpoints
- `ajax_huddle_get_calendar.php` — Get availability calendar for a month
- `ajax_huddle_book.php` — Create a new booking
- `ajax_huddle_checkin.php` — Check in and finalize occupancy
- `ajax_huddle_cancel.php` — Cancel a booking

## Installation Steps

### 1. Run the Database Migration
```bash
mysql -u your_user -p your_database < huddle_room_bookings.sql
```

Or in phpMyAdmin:
1. Go to your `mikrotik` database
2. Click **SQL** tab
3. Copy the contents of `huddle_room_bookings.sql` and paste
4. Click **Go**

### 2. Add Menu Links to Dashboard

Edit `dashboard.php` and add these links in the "Quick Links" section:

```html
<a href="huddle_room_booking.php" class="btn btn-info btn-sm">
    <i class="fa fa-calendar"></i> Book Huddle Room
</a>

<a href="huddle_room_manage.php" class="btn btn-warning btn-sm">
    <i class="fa fa-tasks"></i> Manage Bookings (Staff)
</a>
```

### 3. Update Navigation Menu (Optional)

Add to your main navigation (in `header.php` or wherever menu items are defined):
```html
<li><a href="huddle_room_booking.php">Huddle Room Booking</a></li>
<li><a href="huddle_room_manage.php">Manage Bookings</a></li>
```

## Features

### For Customers (huddle_room_booking.php)
✓ Interactive calendar showing available dates  
✓ Real-time pricing calculation  
✓ Book by occupancy (5, 8, or 10+ people)  
✓ Choose 1-hour or full-day (8-hour) slots  
✓ Enter contact details and company info  
✓ Automatic booking confirmation  

### For Staff (huddle_room_manage.php)
✓ View all upcoming bookings  
✓ Check in bookings  
✓ Adjust final occupancy (e.g., 5 booked → 3 attended)  
✓ Prices auto-adjust based on final occupancy  
✓ Cancel bookings with reason  
✓ View payment status  

## Pricing Configuration

Pricing is managed in the `huddle_room_pricing` table:

```sql
SELECT * FROM huddle_room_pricing;
```

Current rates:
- **5 people**: ₱260/hour (₱1,680/8-hour day)
- **8 people**: ₱450/hour (₱2,880/8-hour day)
- **10+ people**: ₱600/hour (₱3,840/8-hour day) + ₱58 per additional person

To change rates:
```sql
UPDATE huddle_room_pricing SET hourly_rate = 300 WHERE occupancy_level = 5;
```

## Availability Management

Modify operating hours via the `huddle_room_availability` table:

Currently: **Open 24/7 all days**

To set specific hours (e.g., 9 AM - 10 PM):
```sql
UPDATE huddle_room_availability 
SET opens_at = '09:00:00', closes_at = '22:00:00' 
WHERE day_of_week IN (1,2,3,4,5);  -- Mon-Fri

UPDATE huddle_room_availability 
SET is_open = 0 
WHERE day_of_week IN (0);  -- Close Sundays
```

Day codes: `0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat`

## Workflow

### Booking Flow (Customers)
1. Customer visits `huddle_room_booking.php`
2. Selects date from calendar
3. Fills form: name, email, start time, duration, occupancy
4. System calculates price
5. Click "Complete Booking"
6. Booking saved with status = **pending**
7. Confirmation email sent

### Check-In Flow (Staff)
1. Staff views `huddle_room_manage.php`
2. Finds booking with status = **confirmed**
3. Click **"Check In"**
4. Staff enters final occupancy (may differ from booked)
5. System recalculates price (if needed)
6. Booking marked **checked_in**
7. Session begins

### Completion
- Can be manual (staff marks as completed)
- Or auto (session time expires)

## Database Schema

### `huddle_room_bookings`
- `id` (PK)
- `booking_ref` — Human-readable reference (HUB-08042026-001)
- `booking_date` — Date of booking
- `start_time`, `end_time` — Time range
- `visitor_name`, `visitor_email`, `visitor_phone` — Customer info
- `booked_occupancy` — Occupancy at booking time
- `final_occupancy` — Actual occupancy at check-in (NULL until checked in)
- `base_price`, `extra_price`, `total_price` — Pricing
- `status` — pending | confirmed | checked_in | completed | cancelled
- `checked_in_by`, `checked_in_at` — Staff who checked in
- `notes` — Internal staff notes

### `huddle_room_pricing`
- `id` (PK)
- `occupancy_level` — 5, 8, 10, etc.
- `hourly_rate` — Price per hour
- `daily_rate` — Price for 8-hour day
- `active` — Is this tier enabled?

### `huddle_room_availability`
- `id` (PK)
- `day_of_week` — 0-6 (Sun-Sat)
- `is_open` — Room open today?
- `opens_at`, `closes_at` — Operating hours

## Security Notes

✓ All endpoints require authentication (`require_auth()`)
✓ CSRF protection on POST requests
✓ Audit logging for all actions
✓ Row-level locking to prevent double-booking
✓ Email validation on bookings

## Troubleshooting

**"Table doesn't exist" error:**
→ Run `huddle_room_bookings.sql` migration

**Calendar not loading:**
→ Check browser console for AJAX errors
→ Verify `ajax_huddle_get_calendar.php` is accessible

**Pricing not calculating:**
→ Verify `huddle_room_pricing` table has entries
→ Check selected occupancy level matches existing pricing tiers

**Can't check in bookings:**
→ Verify booking status is `confirmed`
→ Check user has admin role (required for `require_auth()`)

## Future Enhancements

- [ ] Email notifications for bookings/cancellations
- [ ] Payment integration (refunds, deposits)
- [ ] Recurring bookings
- [ ] Room capacity limits
- [ ] Blackout dates (maintenance, private events)
- [ ] Waitlist for fully booked dates
- [ ] Integration with hotspot for WiFi pass
- [ ] Booking history/reports for analytics
