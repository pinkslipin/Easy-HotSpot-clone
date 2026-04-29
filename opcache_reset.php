<?php
// One-shot opcache reset. Visit this URL once to force PHP to re-read all
// source files. Safe to delete afterwards.
header('Content-Type: text/plain');
if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo $ok ? "opcache_reset() = OK\n" : "opcache_reset() returned false\n";
} else {
    echo "opcache extension not loaded — nothing to reset\n";
}

// Also confirm the in-memory packages data after reset.
require __DIR__ . '/packages_config.php';
$json = getPackagesForJavaScript();
$data = json_decode($json, true);
echo "\nind_1h.profile_suffix = ";
var_dump($data['ind_1h']['profile_suffix'] ?? 'MISSING');
echo "ind_3h.profile_suffix = ";
var_dump($data['ind_3h']['profile_suffix'] ?? 'MISSING');
echo "day_8_18.profile_suffix = ";
var_dump($data['day_8_18']['profile_suffix'] ?? 'MISSING');
