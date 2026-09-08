<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — site-config.php                           │
 * │  Read-only, no auth — same trust level as scan.php/  │
 * │  art.php. Serves the admin-editable social links and │
 * │  player design settings for index.html to apply on   │
 * │  load. No user input, nothing but a JSON read.       │
 * └──────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: public, max-age=60');

echo json_encode(mp_read_site_settings(), JSON_UNESCAPED_SLASHES);
