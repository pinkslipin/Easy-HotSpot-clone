<?php
/**
 * Email Configuration
 * 
 * Customize these settings for your email notifications
 */

// ========================================
// SENDER CONFIGURATION
// ========================================

// Email address to send from (use a domain you own or a local address for testing)
define('EMAIL_FROM_ADDRESS', 'mindspace2026@gmail.com');

// Display name for the sender
define('EMAIL_FROM_NAME', 'MindSpace Huddle Room');

// Reply-to email address (where customers can reply)
define('EMAIL_REPLY_TO', 'mindspace2026@gmail.com');

// ========================================
// SMTP CONFIGURATION (for production)
// ========================================

// If you need to use an SMTP server instead of PHP's mail() function,
// set ENABLE_SMTP to true and configure the details below.

define('ENABLE_SMTP', true);  // ENABLED - using Brevo API for production email

if (ENABLE_SMTP) {
    // Using Brevo REST API (most reliable)
    define('BREVO_API_KEY', 'your_brevo_api_key_here');  // Replace with your actual Brevo API key
}

// ========================================
// EMAIL TEMPLATES
// ========================================

// Company name and details
define('COMPANY_NAME', 'MindSpace');
define('COMPANY_YEAR', '2026');

// Booking confirmation message (customize this)
define('BOOKING_CONFIRMATION_POLICY', 
    'Please arrive 10 minutes early. Cancellations must be made at least 24 hours before your booking. ' .
    'Late cancellations (within 24 hours) may be subject to 50% of the booking charge.'
);

// Support contact info
define('SUPPORT_EMAIL', 'mindspace2026@gmail.com');
define('SUPPORT_PHONE', '+63 905 332 8085 or +63 929 530 5627');

?>
