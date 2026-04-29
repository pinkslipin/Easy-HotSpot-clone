<?php
/**
 * create_duration_profiles.php
 *
 * One-time CLI: creates the new semantic duration profiles in RouterOS.
 *
 * Naming aligns with package selection in the UI (1hr → 1 Hour, etc).
 * Old price-based profiles (50pesos, 150pesos, ...) are kept for backwards
 * compatibility with currently-active vouchers and removed manually later
 * once nobody is on them.
 *
 * On-login is empty: RouterOS native limit-uptime handles disconnect.
 *
 * Run once:  php create_duration_profiles.php
 *
 * Idempotent: existing profiles with the same name are updated, not duplicated.
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

chdir(__DIR__);
require_once 'config.php';
require_once 'routeros_api.php';

// Rate-limit tiers (Option A — tiered by package length)
$TIER_SHORT  = '30M/30M'; // 1h, 2h
$TIER_MEDIUM = '45M/45M'; // 3h, 5h
$TIER_LONG   = '60M/60M'; // multi-day

$PROFILES = [
    ['name' => '1hr',        'rate' => $TIER_SHORT],
    ['name' => '1hrStudent', 'rate' => $TIER_SHORT],
    ['name' => '2hr',        'rate' => $TIER_SHORT],
    ['name' => '3hr',        'rate' => $TIER_MEDIUM],
    ['name' => '5hr',        'rate' => $TIER_MEDIUM],
    ['name' => '3days',      'rate' => $TIER_LONG],
    ['name' => '7days',      'rate' => $TIER_LONG],
    ['name' => '1week',      'rate' => $TIER_LONG],
    ['name' => '15days',     'rate' => $TIER_LONG],
    ['name' => '1month',     'rate' => $TIER_LONG],
];

// Profile defaults shared across all duration profiles.
// Match existing profile conventions (see Winbox print of 50pesos).
$BASE = [
    'shared-users'       => '2',
    'mac-cookie-timeout' => '12h',
    'keepalive-timeout'  => '2m',
    'status-autorefresh' => '1m',
    'add-mac-cookie'     => 'yes',
    'idle-timeout'       => 'none',
    'transparent-proxy'  => 'no',
    'on-login'           => '',
];

echo "Connecting to RouterOS...\n";
$connection = createRouterConnection($host, $user, $pass);
if (!$connection['success']) {
    fwrite(STDERR, "Connection failed: " . ($connection['error'] ?? 'unknown') . "\n");
    exit(1);
}
$util   = $connection['util'];
$client = $connection['client'];

$util->setMenu('/ip/hotspot/user/profile');
$existing = $util->getAll();
$existingByName = [];
foreach ($existing as $p) {
    $existingByName[$p->getProperty('name')] = $p->getProperty('.id');
}

$created = 0;
$updated = 0;
$failed  = 0;

foreach ($PROFILES as $cfg) {
    $name = $cfg['name'];
    $rate = $cfg['rate'];

    $props = $BASE;
    $props['name']       = $name;
    $props['rate-limit'] = $rate;

    try {
        if (isset($existingByName[$name])) {
            $id = $existingByName[$name];
            $query = new \RouterOS\Query('/ip/hotspot/user/profile/set');
            $query->equal('.id', $id);
            foreach ($props as $k => $v) {
                if ($k === 'name') continue; // can't rename to same name on set
                $query->equal($k, $v);
            }
            $client->query($query);
            echo "  UPDATE  $name (rate=$rate)\n";
            $updated++;
        } else {
            $query = new \RouterOS\Query('/ip/hotspot/user/profile/add');
            foreach ($props as $k => $v) {
                $query->equal($k, $v);
            }
            $client->query($query);
            echo "  CREATE  $name (rate=$rate)\n";
            $created++;
        }
    } catch (Exception $e) {
        echo "  FAIL    $name: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nDone. Created: $created, Updated: $updated, Failed: $failed\n";
echo "\nThe walk-in form (seats.php) will now auto-select the matching\n";
echo "profile when staff picks a package. Old price-based profiles\n";
echo "(50pesos, 100pesos, ...) remain available until you delete them\n";
echo "once no active users are on them.\n";
