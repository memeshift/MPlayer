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
