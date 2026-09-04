<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — upload_commit.php                 │
 * │  Auth-gated. Writes the (possibly edited) tags into   │
 * │  the staged MP3 and moves it into MUSIC_DIR. Once     │
 * │  this succeeds, scan.php/art.php pick the track up    │
 * │  automatically — no other file needs to change.       │
 * └──────────────────────────────────────────────────────┘
 *
 * POST: token, title, artist, album, year, track, comment, buy_url,
 *       info_url, csrf, keep_art=1|0, optional art=<image file>
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/id3.php';
require_once __DIR__ . '/id3_write.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

mp_require_login();

if (!mp_check_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired, please reload the page.']);
    exit;
}

$token = (string)($_POST['token'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token) || !in_array($token, $_SESSION['staging_tokens'] ?? [], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown or expired upload — please choose the file again.']);
    exit;
}

$stagedPath = STAGING_DIR . $token . '.mp3';
if (!is_file($stagedPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'That staged file is no longer available — please choose it again.']);
    exit;
}

/* ── Sanitize + length-cap every field ── */
function iw_capField(string $v, int $max): string {
    $v = sanitiseText($v);
    return mb_substr($v, 0, $max);
}

$tags = [
    'title'    => iw_capField((string)($_POST['title'] ?? ''), 200),
    'artist'   => iw_capField((string)($_POST['artist'] ?? ''), 200),
    'album'    => iw_capField((string)($_POST['album'] ?? ''), 200),
    'year'     => iw_capField((string)($_POST['year'] ?? ''), 4),
    'track'    => iw_capField((string)($_POST['track'] ?? ''), 10),
    'comment'  => iw_capField((string)($_POST['comment'] ?? ''), 1000),
    'buy_url'  => mb_substr(sanitiseUrl((string)($_POST['buy_url'] ?? '')), 0, 500),
    'info_url' => mb_substr(sanitiseUrl((string)($_POST['info_url'] ?? '')), 0, 500),
];

/* ── New art (optional) ── */
$newArtData = null;
$newArtMime = null;
$keepArt = !empty($_POST['keep_art']);

if (isset($_FILES['art']) && $_FILES['art']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['art']['size'] > MAX_ART_MB * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Cover art is larger than ' . MAX_ART_MB . 'MB.']);
        exit;
    }
    $raw = file_get_contents($_FILES['art']['tmp_name']);
    $validated = iw_validateAndReturnArt($raw, mime_content_type($_FILES['art']['tmp_name']) ?: '');
    if ($validated === null) {
        http_response_code(400);
        echo json_encode(['error' => 'That cover image is not a valid JPEG, PNG, GIF, or WEBP.']);
        exit;
    }
    $newArtData = $validated['data'];
    $newArtMime = $validated['mime'];
} elseif (!$keepArt) {
    // Explicit "remove art" — pass an empty sentinel so writeID3Tags()
    // doesn't fall back to preserving the original embedded art.
    $newArtData = '';
    $newArtMime = null;
}

// writeID3Tags() treats $newArtData===null as "preserve whatever art the
// file already has" and a non-null $newArtData with a null $newArtMime as
// "no art frame at all" — so the three cases above (new art / keep / remove)
// all resolve correctly through one call, no special-casing needed here.
$ok = writeID3Tags($stagedPath, $stagedPath, $tags, $newArtData, $newArtMime);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not write tags to the file.']);
    exit;
}

/* ── Collision-safe filename, derived from artist/title when available ── */
$base = trim($tags['artist'] . ' - ' . $tags['title'], ' -');
if ($base === '') {
    $base = pathinfo($_SESSION['staging_names'][$token] ?? $token, PATHINFO_FILENAME);
}
// Strip only what's actually unsafe in a filename (path separators, null
// bytes, control chars, and the handful of characters Windows/some
// filesystems reject) — keep accented/non-ASCII letters rather than
// dropping them, so e.g. "édition" doesn't become "dition".
$base = preg_replace('#[\x00-\x1F/\\\\:*?"<>|]#u', '', $base);
$base = trim(preg_replace('/\s+/u', ' ', $base));
if ($base === '' || $base === '.' || $base === '..') $base = $token;
$base = mb_substr($base, 0, 150);

$musicDir = realpath(MUSIC_DIR);
if ($musicDir === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Music directory is not configured correctly.']);
    exit;
}
$musicDir = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$finalName = $base . '.mp3';
$suffix = 2;
while (file_exists($musicDir . $finalName)) {
    $finalName = $base . ' (' . $suffix . ').mp3';
    $suffix++;
}

$finalPath = $musicDir . $finalName;
// Defense in depth: the filename above is entirely server-generated and
// sanitized, but confirm the resolved path still lands inside MUSIC_DIR
// before moving anything, matching the realpath-guard idiom used
// throughout this codebase.
if (strpos($finalPath, $musicDir) !== 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not resolve a safe destination filename.']);
    exit;
}

if (!rename($stagedPath, $finalPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not move the file into the music library.']);
    exit;
}

$_SESSION['staging_tokens'] = array_values(array_diff($_SESSION['staging_tokens'] ?? [], [$token]));
mp_log_event('upload_committed:' . $finalName);

echo json_encode(['ok' => true, 'file' => rawurlencode($finalName)]);
