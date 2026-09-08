<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — config.php                       │
 * │  This is the ONLY file you need to edit.             │
 * └──────────────────────────────────────────────────────┘
 *
 * After editing, save and upload. No other changes needed.
 */

// ── Path to your music folder (trailing slash required) ──
// On shared hosting this is usually something like:
//   '/home/youraccount/public_html/player/music/'
// For local testing with `php -S localhost:8080`, the default below works.
define('MUSIC_DIR', __DIR__ . '/music/');

// ── URL path for audio streaming (relative to index.html) ──
// Leave as 'music/' unless you move the music folder.
define('MUSIC_URL', 'music/');

// ── Only these file extensions are allowed ──
define('ALLOWED_EXT', ['mp3']);

// ── Browser cache durations (seconds) ──
define('SCAN_CACHE_TTL',  300);    // 5 min  — track listing
define('ART_CACHE_TTL',   86400);  // 24 hrs — album art images

// ── Canonical site URL, used ONLY to build password-reset links/emails.
//    Never derive this from $_SERVER['HTTP_HOST'] — that header is
//    attacker-controlled on the request that triggers a reset email, and
//    using it would let someone poison the link sent to the real admin. ──
define('APP_BASE_URL', 'https://music.memeshift.com');

// ── Admin login + upload ──────────────────────────────────
// Credentials file: JSON, holds the admin password hash, reset-token state,
// and login-lockout counters. Kept OUTSIDE this app's own directory
// (dirname(__DIR__)) so it is never web-servable even if .htaccess rules
// don't apply for some reason. The random suffix is defense in depth on
// top of that. Generate the file itself by hand — see README for the
// one-time `php -r "echo password_hash(...)"` command; there is no web
// endpoint that creates or edits it.
define('CREDENTIALS_FILE', dirname(__DIR__) . '/.mplayer-admin-3b3e77d59cce3312.json');

// ── Site settings file: JSON, holds admin-editable social links and
//    player design (colors/background). Public-facing values only (no
//    secrets), but kept alongside CREDENTIALS_FILE outside the webroot
//    for consistency with the rest of the admin state. ──
define('SITE_SETTINGS_FILE', dirname(__DIR__) . '/.mplayer-site-settings-3b3e77d59cce3312.json');

// ── Track order file: JSON array of filenames, the admin-set play order
//    used by both scan.php (public player) and library_actions.php (admin
//    list). Same outside-webroot pattern as the files above. ──
define('TRACK_ORDER_FILE', dirname(__DIR__) . '/.mplayer-track-order-3b3e77d59cce3312.json');

/* Sort $tracks (each with a rawurlencode'd 'file' key) by their position in
   TRACK_ORDER_FILE (a flat JSON array of filenames). Tracks not listed there
   — e.g. newly uploaded ones — keep their existing relative order and sort
   after every listed track. Used by scan.php and library_actions.php. */
function applyTrackOrder(array $tracks): array {
    $order = [];
    if (is_file(TRACK_ORDER_FILE)) {
        $decoded = json_decode((string) file_get_contents(TRACK_ORDER_FILE), true);
        if (is_array($decoded)) $order = array_flip(array_values($decoded));
    }
    if (!$order) return $tracks;

    $withKeys = [];
    foreach ($tracks as $i => $t) {
        $filename = urldecode($t['file']);
        $withKeys[] = [$order[$filename] ?? (count($order) + $i), $i, $t];
    }
    usort($withKeys, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

    return array_map(fn($w) => $w[2], $withKeys);
}

// ── Where in-progress uploads are held before they're committed to
//    MUSIC_DIR (see upload_inspect.php / upload_commit.php) ──
define('STAGING_DIR', __DIR__ . '/staging/');

// ── Upload limits ──
define('MAX_UPLOAD_MB', 50);   // MP3 file
define('MAX_ART_MB', 5);       // cover art image
