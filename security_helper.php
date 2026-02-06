<?php
/**
 * Security Helper - Centralized security functions
 * 
 * Provides: session hardening, CSRF protection, password hashing (bcrypt),
 * input sanitization, rate limiting, and output encoding.
 */

// ============================================================
// SESSION SECURITY
// ============================================================

/**
 * Start a secure session with hardened cookie settings
 */
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Harden session cookies
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,           // Session cookie (expires on browser close)
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,    // Only send over HTTPS when available
            'httponly'  => true,        // Not accessible via JavaScript
            'samesite'  => 'Strict'    // Prevent CSRF via cross-site requests
        ]);
        session_start();
    }
}

/**
 * Regenerate session ID (call after login to prevent session fixation)
 */
function secure_session_regenerate() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Generate a CSRF token and store in session
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field for forms
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validate a CSRF token from POST/GET data
 * @param string|null $token Token to validate (reads from $_POST/$_GET if null)
 * @return bool
 */
function csrf_validate($token = null) {
    if ($token === null) {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 
                 (isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '');
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate CSRF and die with error if invalid
 */
function csrf_require() {
    if (!csrf_validate()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.']);
        exit;
    }
}

// ============================================================
// PASSWORD HASHING (bcrypt migration from SHA-1)
// ============================================================

/**
 * Hash a password using bcrypt
 */
function secure_password_hash($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a hash (supports both bcrypt and legacy SHA-1)
 * If SHA-1 match found, returns true but caller should rehash
 */
function secure_password_verify($password, $hash) {
    // Try bcrypt first (new format)
    if (password_verify($password, $hash)) {
        return true;
    }
    
    // Fallback: check legacy SHA-1 hash for migration
    if (strlen($hash) === 40 && $hash === sha1($password)) {
        return true; // SHA-1 match - caller should upgrade the hash
    }
    
    return false;
}

/**
 * Check if a password hash needs upgrading from SHA-1 to bcrypt
 */
function password_needs_rehash_check($hash) {
    // SHA-1 hashes are exactly 40 hex characters
    if (strlen($hash) === 40 && ctype_xdigit($hash)) {
        return true; // Legacy SHA-1, needs upgrade
    }
    // Also check if bcrypt cost needs updating
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

// ============================================================
// AUTHENTICATION HELPERS
// ============================================================

/**
 * Require user to be logged in, otherwise die
 */
function require_auth() {
    secure_session_start();
    if (!isset($_SESSION['user_level']) || !isset($_SESSION['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
}

/**
 * Require admin (user_level == 1) access
 */
function require_admin() {
    require_auth();
    if ($_SESSION['user_level'] != 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Administrator access required']);
        exit;
    }
}

/**
 * Require at least unit head (user_level <= 2) access
 */
function require_unit_head() {
    require_auth();
    if ($_SESSION['user_level'] > 2) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unit Head or Administrator access required']);
        exit;
    }
}

/**
 * Require at least system user (user_level <= 3) access
 */
function require_user() {
    require_auth();
    if ($_SESSION['user_level'] > 3) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

// ============================================================
// INPUT SANITIZATION
// ============================================================

/**
 * Sanitize a string input
 */
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize for integer
 */
function sanitize_int($input) {
    return intval($input);
}

/**
 * Validate IP address
 */
function sanitize_ip($ip) {
    $filtered = filter_var(trim($ip), FILTER_VALIDATE_IP);
    return $filtered !== false ? $filtered : '';
}

/**
 * Sanitize a username (alphanumeric + underscore only)
 */
function sanitize_username($username) {
    return preg_replace('/[^a-zA-Z0-9_]/', '', trim($username));
}

/**
 * Validate and sanitize a color value (#hex format)
 */
function sanitize_color($color) {
    $color = trim($color);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return $color;
    }
    return '#000000'; // Safe default
}

/**
 * Sanitize custom CSS (strip dangerous patterns)
 */
function sanitize_css($css) {
    // Remove any HTML tags
    $css = strip_tags($css);
    // Remove javascript: URLs
    $css = preg_replace('/javascript\s*:/i', '', $css);
    // Remove expression()
    $css = preg_replace('/expression\s*\(/i', '', $css);
    // Remove url() with data: or javascript:
    $css = preg_replace('/url\s*\(\s*(\'|")?(?:javascript|data)\s*:/i', 'url($1blocked:', $css);
    // Remove @import
    $css = preg_replace('/@import/i', '', $css);
    // Remove </style> tag attempts
    $css = str_ireplace('</style>', '', $css);
    $css = str_ireplace('<style', '', $css);
    $css = str_ireplace('<script', '', $css);
    return $css;
}

// ============================================================
// OUTPUT ENCODING
// ============================================================

/**
 * HTML-encode a value for safe output
 */
function e($value) {
    if ($value === null) return '';
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Encode value for safe use in JavaScript string
 */
function ejs($value) {
    return addslashes(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

// ============================================================
// RATE LIMITING (Login brute-force protection)
// ============================================================

/**
 * Check if an IP is rate-limited for login
 * @param string $ip The IP address
 * @param int $maxAttempts Max failed attempts before lockout
 * @param int $lockoutMinutes How long to lock out (minutes)
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
 */
function check_login_rate_limit($ip, $maxAttempts = 5, $lockoutMinutes = 15) {
    global $DB_con;
    
    try {
        // Count recent failed attempts from this IP
        $stmt = $DB_con->prepare("SELECT COUNT(*) as attempts, MAX(created_at) as last_attempt 
            FROM audit_log 
            WHERE action = 'login_failed' 
            AND ip_address = :ip 
            AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)");
        $stmt->execute([':ip' => $ip, ':minutes' => $lockoutMinutes]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $attempts = (int)$row['attempts'];
        $remaining = max(0, $maxAttempts - $attempts);
        
        if ($attempts >= $maxAttempts) {
            // Calculate retry_after in seconds
            $lastAttempt = strtotime($row['last_attempt']);
            $lockoutEnd = $lastAttempt + ($lockoutMinutes * 60);
            $retryAfter = max(0, $lockoutEnd - time());
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $retryAfter,
                'message' => "Too many failed attempts. Please try again in " . ceil($retryAfter / 60) . " minutes."
            ];
        }
        
        return ['allowed' => true, 'remaining' => $remaining, 'retry_after' => 0];
        
    } catch (Exception $e) {
        // If rate limiting fails, allow the attempt (fail open for availability)
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }
}

// ============================================================
// SECURE CONFIG FILE WRITER (prevents RCE)
// ============================================================

/**
 * Safely write router config without allowing code injection
 */
function write_router_config($host, $user, $pass, $mockMode = null) {
    $host = sanitize_ip($host);
    $user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $user);
    // For password, escape any PHP-breaking characters
    $pass = addcslashes($pass, "'\\");
    
    if (empty($host)) {
        return false;
    }
    
    $content = "<?php \n";
    $content .= "/**\n * MikroTik Router Configuration\n * Auto-generated - do not edit manually\n */\n\n";
    $content .= "error_reporting(E_ALL & ~E_DEPRECATED);\n\n";
    
    if ($mockMode !== null) {
        $mockVal = $mockMode ? 'true' : 'false';
        $content .= "define('MOCK_MODE', {$mockVal});\n\n";
    } else {
        $content .= "// Preserve existing MOCK_MODE setting\n";
        $content .= "if (!defined('MOCK_MODE')) define('MOCK_MODE', false);\n\n";
    }
    
    $content .= "\$host = '{$host}';\n";
    $content .= "\$user = '{$user}';\n";
    $content .= "\$pass = '{$pass}';\n";
    $content .= "?>";
    
    return file_put_contents(__DIR__ . '/config.php', $content) !== false;
}
?>
