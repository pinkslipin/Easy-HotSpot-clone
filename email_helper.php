<?php
/**
 * Email Helper - Send formatted emails via Brevo REST API
 * 
 * Uses Brevo REST API for reliable email delivery
 */

require_once __DIR__ . '/email_config.php';

/**
 * Send Huddle Room booking confirmation email
 * 
 * @param string $visitor_email
 * @param array $booking_details - should have: booking_ref, visitor_name, booking_date, start_time, end_time, duration_hours, booked_occupancy, total_price
 * @return bool
 */
function sendBookingConfirmationEmail($visitor_email, $booking_details) {
    $booking_ref = htmlspecialchars($booking_details['booking_ref']);
    $visitor_name = htmlspecialchars($booking_details['visitor_name']);
    $booking_date = htmlspecialchars($booking_details['booking_date']);
    $start_time = htmlspecialchars($booking_details['start_time']);
    $end_time = htmlspecialchars($booking_details['end_time']);
    $duration_hours = intval($booking_details['duration_hours']);
    $occupancy = intval($booking_details['booked_occupancy']);
    $total_price = floatval($booking_details['total_price']);
    
    // Format date and times for display
    $date_display = date('F j, Y', strtotime($booking_date));
    $time_display = date('g:i A', strtotime($start_time));
    $end_time_display = date('g:i A', strtotime($end_time));
    
    // Format price and duration for display
    $price_display = '₱' . number_format($total_price, 2);
    $duration_display = $duration_hours . ' hour' . ($duration_hours > 1 ? 's' : '');
    
    // Subject
    $subject = "✓ Huddle Room Booking Confirmed - Ref: $booking_ref";
    
    // HTML email body
    $html_body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .header p { margin: 8px 0 0; opacity: 0.9; font-size: 14px; }
        .content { background: white; padding: 30px; border-radius: 0 0 8px 8px; }
        .booking-ref { background: #f0f0f0; padding: 12px 16px; border-left: 4px solid #667eea; margin: 20px 0; font-family: monospace; font-weight: 600; }
        .details { margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #666; margin-right: 8px; }
        .detail-value { color: #333; }
        .price-section { background: #f9f9f9; padding: 16px; border-radius: 6px; margin: 20px 0; text-align: center; }
        .price-section .total { font-size: 32px; font-weight: 700; color: #667eea; }
        .price-section .label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px; }
        .actions { margin-top: 30px; text-align: center; }
        .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0; font-size: 12px; color: #999; text-align: center; }
        .alert { background: #eef; border-left: 4px solid #33c; padding: 12px 16px; margin: 20px 0; border-radius: 4px; }
        .alert p { margin: 0; font-size: 13px; color: #33c; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📅 Huddle Room Booking Confirmed</h1>
        <p>Your reservation is confirmed and ready</p>
    </div>
    
    <div class="content">
        <p style="margin-top: 0;">Hi <strong>$visitor_name</strong>,</p>
        
        <p>Thank you for booking the Huddle Room! Your reservation is confirmed and scheduled. Here are your booking details:</p>
        
        <div class="booking-ref">
            Reference: $booking_ref
        </div>
        
        <div class="details">
            <div class="detail-row">
                <span class="detail-label">📅 Date: </span>
                <span class="detail-value"><strong>$date_display</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">⏰ Time: </span>
                <span class="detail-value"><strong>$time_display – $end_time_display</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">⏱️ Duration: </span>
                <span class="detail-value"><strong>$duration_display</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">👥 Occupancy: </span>
                <span class="detail-value"><strong>$occupancy people</strong></span>
            </div>
        </div>
        
        <div class="price-section">
            <div class="label">Total Amount</div>
            <div class="total">$price_display</div>
        </div>
        
        <div class="alert">
            <p><strong>✓ What's next?</strong> Please arrive 10 minutes before your scheduled time. We look forward to hosting you! If you need to reschedule or cancel, contact us at least 24 hours in advance to avoid cancellation fees.</p>
        </div>
        
        <p style="color: #666; font-size: 13px;">
            <strong>Need to reschedule or cancel?</strong> Contact us at least 24 hours before your booking. Cancellations made within 24 hours may be subject to charges.
        </p>
        
        <div class="footer">
            <p>This is an automated confirmation email. Please keep this for your records.</p>
            <p>© Est. 2026 MindSpace. All rights reserved.</p>
            <p>Questions? Contact us at <a href="mailto:mindspace2026@gmail.com" style="color: #667eea; text-decoration: none;">mindspace2026@gmail.com</a></p>
        </div>
    </div>
</div>
</body>
</html>
HTML;
    
    // Plain text fallback
    $text_body = <<<TEXT
HUDDLE ROOM BOOKING CONFIRMED

Hi $visitor_name,

Thank you for booking the Huddle Room! Your reservation is confirmed.

BOOKING DETAILS
Reference:     $booking_ref
Date:          $date_display
Time:          $time_display – $end_time_display
Duration:      $duration_display
Occupancy:     $occupancy people
Total Amount:  $price_display

IMPORTANT: Please arrive 10 minutes early.

Need to reschedule or cancel? Contact us at least 24 hours before your booking. Cancellations made within 24 hours may be subject to fees.

© Est. 2026 MindSpace
Support: mindspace2026@gmail.com
TEXT;
    
    // Send email
    return sendEmail($visitor_email, $subject, $html_body, $text_body);
}

/**
 * Route to correct email sender (SMTP or PHP mail)
 */
function sendEmail($recipient_email, $subject, $html_body, $text_body) {
    if (ENABLE_SMTP) {
        return sendEmailViaBrevoAPI($recipient_email, $subject, $html_body, $text_body);
    } else {
        return sendEmailViaPhpMail($recipient_email, $subject, $html_body, $text_body);
    }
}

/**
 * Send email via Brevo REST API (most reliable method)
 */
function sendEmailViaBrevoAPI($recipient_email, $subject, $html_body, $text_body) {
    // Brevo API key from config
    $brevo_api_key = BREVO_API_KEY;
    
    $url = 'https://api.brevo.com/v3/smtp/email';
    
    $data = array(
        'sender' => array(
            'name' => EMAIL_FROM_NAME,
            'email' => EMAIL_FROM_ADDRESS
        ),
        'to' => array(
            array(
                'email' => $recipient_email
            )
        ),
        'replyTo' => array(
            'email' => EMAIL_REPLY_TO
        ),
        'subject' => $subject,
        'htmlContent' => $html_body,
        'textContent' => $text_body,
        'headers' => array(
            'X-Mailer' => 'MindSpace Booking System',
            'X-Priority' => '3'
        )
    );
    
    $json_data = json_encode($data);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json',
        'api-key: ' . $brevo_api_key
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("✗ BREVO API ERROR: cURL error - $curl_error (To: $recipient_email)");
        return false;
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        error_log("✓ BREVO API SUCCESS: Email sent to $recipient_email (HTTP $http_code)");
        return true;
    } else {
        error_log("✗ BREVO API ERROR: HTTP $http_code - $response (To: $recipient_email)");
        return false;
    }
}



?>
