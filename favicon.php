<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — favicon.php                               │
 * │  Serves the site icon set in settings.php, falling   │
 * │  back to the bundled default mark. Read-only, no auth│
 * │  — same trust level as art.php / site-config.php.    │
 * │  index.html is static HTML and cannot read the       │
 * │  settings file itself, so its icon <link> points     │
 * │  here instead.                                       │
 * └──────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$path = __DIR__ . '/favicon.svg';
$mime = 'image/svg+xml';

$icon = (string) mp_read_site_settings()['icon'];
if ($icon !== '' && preg_match('#^images/icons/[A-Za-z0-9_\-]+\.(jpe?g|png|gif|webp)$#', $icon, $m)) {
    $candidate = __DIR__ . '/' . $icon;
    if (is_file($candidate)) {
        $path = $candidate;
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                 'gif' => 'image/gif', 'webp' => 'image/webp'][strtolower($m[1])];
    }
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
// Short TTL, not ART_CACHE_TTL: a changed icon should show up the same day,
// and the file is a few KB.
header('Cache-Control: public, max-age=300');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
