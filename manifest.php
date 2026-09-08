<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — manifest.php                              │
 * │  Web app manifest, named from the site name set in   │
 * │  settings.php. Read-only, no auth — same trust level │
 * │  as site-config.php. Replaces the old static         │
 * │  site.webmanifest, which hardcoded one site's name   │
 * │  and icon files.                                     │
 * └──────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');

$settings = mp_read_site_settings();
$name     = mp_site_name();

echo json_encode([
    'name'             => $name,
    'short_name'       => mb_substr($name, 0, 12),
    'start_url'        => '/',
    'display'          => 'standalone',
    'theme_color'      => $settings['design']['titlebar_color'],
    'background_color' => '#0a0802',
    'icons'            => [[
        // No 'type': favicon.php serves whatever format the admin
        // uploaded (or the bundled SVG), so declaring one would be a lie
        // half the time and browsers would drop the icon.
        'src'     => 'favicon.php',
        'sizes'   => 'any',
        'purpose' => 'any',
    ]],
], JSON_UNESCAPED_SLASHES);
