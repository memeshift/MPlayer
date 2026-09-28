<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — art.php                                   │
 * │  Extracts and serves embedded album art from a       │
 * │  single MP3 file. Read-only. No user data written.   │
 * └──────────────────────────────────────────────────────┘
 *
 * Usage: art.php?f=url-encoded-filename.mp3
 *
 * Security:
 *  • Only accepts a filename (basename), never a path
 *  • Validates the file is inside MUSIC_DIR via realpath()
 *  • Only serves image/jpeg, image/png, image/gif, image/webp
 *  • Serves a safe inline SVG placeholder if no art is found
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/id3_write.php'; // iw_extractAPIC()

// ── Security headers ──
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── Validate parameter ──
$raw = isset($_GET['f']) ? (string) $_GET['f'] : '';
if ($raw === '') { servePlaceholder(); exit; }

// Decode URL encoding, then strip to basename only (no path traversal)
$filename = basename(rawurldecode($raw));

// Extension check
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($ext !== ALLOWED_EXT) { servePlaceholder(); exit; }

// ── Resolve path safely ──
$musicDir = realpath(MUSIC_DIR);
if ($musicDir === false) { servePlaceholder(); exit; }
$musicDir = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$filepath = $musicDir . $filename;
$real     = realpath($filepath);

if ($real === false || strpos($real, $musicDir) !== 0 || !is_file($real)) {
    servePlaceholder();
    exit;
}

// ── Extract art ──
$art = iw_extractAPIC($real);

if ($art === null) {
    servePlaceholder();
    exit;
}

// ── Serve image ──
if (ART_CACHE_TTL > 0) {
    header('Cache-Control: public, max-age=' . (int) ART_CACHE_TTL);
}
header('Content-Type: ' . $art['mime']);
header('Content-Length: ' . strlen($art['data']));
echo $art['data'];
exit;

/* ══════════════════════════════════════════════════════════
   SVG PLACEHOLDER
══════════════════════════════════════════════════════════ */
function servePlaceholder(): void {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=3600');
    // Simple dark square with a music note
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
       . '<rect width="100" height="100" fill="#0d1a0d"/>'
       . '<text x="50" y="63" font-size="44" text-anchor="middle" fill="#14ff14" '
       . 'font-family="monospace" opacity="0.6">♪</text>'
       . '</svg>';
}
