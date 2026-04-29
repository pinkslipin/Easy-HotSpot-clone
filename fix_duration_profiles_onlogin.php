<?php
/**
 * fix_duration_profiles_onlogin.php
 *
 * One-time CLI fix for the early-disconnect bug.
 *
 * What it does:
 *   1. Finds all hotspot user profiles whose on-login contains "scheduler add".
 *   2. Skips window profiles (DayPass, NightPass, MindspaceUnli, RevieweeDay) —
 *      their on-login is the immediate-window-check and must stay.
 *   3. Skips RouterOS system profiles (default, default-trial).
 *   4. Clears the on-login on all other duration profiles. RouterOS native
 *      limit-uptime then handles disconnect correctly (uptime-based, paused
 *      while user is offline).
 *   5. Removes any active schedulers that match the buggy pattern
 *      (their on-event sets limit-uptime=1s — that's the signature).
 *
 * Run once:  php fix_duration_profiles_onlogin.php
 *
 * Idempotent: re-running is safe. It only touches profiles/schedulers that
 * still match the buggy pattern.
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

chdir(__DIR__);
require_once 'config.php';
require_once 'routeros_api.php';

$WINDOW_PROFILES = ['DayPass', 'NightPass', 'MindspaceUnli', 'RevieweeDay'];
$SYSTEM_PROFILES = ['default', 'default-trial'];
$EXCLUDE = array_merge($WINDOW_PROFILES, $SYSTEM_PROFILES);

echo "Connecting to RouterOS...\n";
$connection = createRouterConnection($host, $user, $pass);
if (!$connection['success']) {
    fwrite(STDERR, "Connection failed: " . ($connection['error'] ?? 'unknown') . "\n");
    exit(1);
}
$util   = $connection['util'];
$client = $connection['client'];

// ── Step 1: Clean profiles ────────────────────────────────────────────────
echo "\n[1/2] Scanning hotspot user profiles...\n";
$util->setMenu('/ip/hotspot/user/profile');
$profiles = $util->getAll();

$updated = 0;
$skipped = 0;
foreach ($profiles as $p) {
    $name    = $p->getProperty('name') ?? '';
    $onLogin = $p->getProperty('on-login') ?? '';

    if (in_array($name, $EXCLUDE, true)) {
        echo "  SKIP  $name (window/system profile)\n";
        $skipped++;
        continue;
    }
    if (stripos($onLogin, 'scheduler add') === false) {
        echo "  SKIP  $name (already clean)\n";
        $skipped++;
        continue;
    }

    $id = $p->getProperty('.id');
    $query = new \RouterOS\Query('/ip/hotspot/user/profile/set');
    $query->equal('.id', $id);
    $query->equal('on-login', '');
    $client->query($query);

    echo "  CLEAN $name (cleared scheduler-creating on-login)\n";
    $updated++;
}
echo "Profiles: $updated cleaned, $skipped skipped\n";

// ── Step 2: Purge buggy active schedulers ────────────────────────────────
echo "\n[2/2] Scanning active schedulers for buggy pattern...\n";
$util->setMenu('/system/scheduler');
$schedulers = $util->getAll();

$purged = 0;
$kept   = 0;
foreach ($schedulers as $s) {
    $name    = $s->getProperty('name') ?? '';
    $onEvent = $s->getProperty('on-event') ?? '';

    // Buggy schedulers always set limit-uptime=1s in their on-event.
    // Legitimate user schedulers (if any) won't have this signature.
    if (stripos($onEvent, 'limit-uptime=1s') === false) {
        $kept++;
        continue;
    }

    $util->remove($s->getProperty('.id'));
    echo "  PURGE $name\n";
    $purged++;
}
echo "Schedulers: $purged purged, $kept kept\n";

echo "\nDone. Connected users will now expire only when their actual\n";
echo "uptime exceeds limit-uptime (RouterOS native behavior). Window\n";
echo "packages continue to be enforced by their on-login script + cron.\n";
