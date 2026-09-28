<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — scan.php                                  │
 * │  Scans the music/ directory, reads ID3 tags,         │
 * │  returns a sanitised JSON array. Read-only.          │
 * └──────────────────────────────────────────────────────┘
 *
 * Security measures:
 *  • No user input touches the filesystem (no GET/POST params used)
 *  • Every path is validated with realpath() before reading
 *  • Output is JSON-encoded (no raw file data)
 *  • PHP execution errors are suppressed from output
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/id3.php';

// ── Output headers ──
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (SCAN_CACHE_TTL > 0) {
    header('Cache-Control: public, max-age=' . (int) SCAN_CACHE_TTL);
} else {
    header('Cache-Control: no-store');
}

// ── Validate music directory ──
$musicDir = realpath(MUSIC_DIR);
if ($musicDir === false || !is_dir($musicDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Music directory not found. Check MUSIC_DIR in config.php.']);
    exit;
}
$musicDir = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// ── Collect .mp3 files ──
$files = glob($musicDir . '*.mp3');
if ($files === false) $files = [];

// Also look for uppercase .MP3 (some uploads)
$filesUpper = glob($musicDir . '*.MP3');
if ($filesUpper) $files = array_merge($files, $filesUpper);

sort($files); // default alphabetical order

// ── Build track list ──
$tracks = [];
foreach ($files as $filepath) {
    $real = realpath($filepath);
    if ($real === false) continue;

    // Path traversal guard
    if (strpos($real, $musicDir) !== 0) continue;

    $filename = basename($real);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== ALLOWED_EXT) continue;

    $tags            = parseID3($real);
    $tags['file']    = rawurlencode($filename);
    $tags['filesize'] = filesize($real) ?: 0;
    $tags['mtime']    = filemtime($real) ?: 0;
    // Duration is filled client-side via HTML5 audio metadata event.
    // We set 0 here; JS updates it after each track loads.
    $tags['duration'] = 0;

    $tracks[] = $tags;
}

$tracks = applyTrackOrder($tracks);

echo json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
