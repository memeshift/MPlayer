<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — library_actions.php               │
 * │  Auth-gated JSON endpoint for library.php: list       │
 * │  what's in MUSIC_DIR, edit an existing file's tags    │
 * │  in place, or delete a file. No renaming on edit —     │
 * │  filenames stay fixed once uploaded (see library.php). │
 * └──────────────────────────────────────────────────────┘
 *
 * GET  ?action=list
 * POST action=edit    file, title, artist, album, year, track, comment,
 *                      buy_url, info_url, csrf, keep_art=1|0, optional art
 * POST action=delete   file, csrf
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/id3.php';
require_once __DIR__ . '/id3_write.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

mp_require_login();

/* Resolve a POSTed/GETed filename to a safe absolute path inside MUSIC_DIR,
   or null. Mirrors the basename()+ext-allowlist+realpath-containment idiom
   already used in art.php/scan.php/upload_commit.php. */
function la_resolveMusicFile(string $raw): ?string {
    $filename = basename($raw);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) return null;
    $musicDir = realpath(MUSIC_DIR);
    if ($musicDir === false) return null;
    $musicDir = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $real = realpath($musicDir . $filename);
    if ($real === false || strpos($real, $musicDir) !== 0 || !is_file($real)) return null;
    return $real;
}

$action = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_GET['action'] ?? '') : ($_POST['action'] ?? '');

if ($action === 'list') {
    header('Cache-Control: no-store');

    $musicDir = realpath(MUSIC_DIR);
    if ($musicDir === false || !is_dir($musicDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Music directory not found.']);
        exit;
    }
    $musicDir = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $files = glob($musicDir . '*.mp3') ?: [];
    $filesUpper = glob($musicDir . '*.MP3') ?: [];
    $files = array_merge($files, $filesUpper);
    sort($files);

    $tracks = [];
    foreach ($files as $filepath) {
        $real = realpath($filepath);
        if ($real === false || strpos($real, $musicDir) !== 0) continue;

        $filename = basename($real);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) continue;

        $tags = parseID3($real);
        $tags['file'] = rawurlencode($filename);
        $tags['filesize'] = filesize($real) ?: 0;
        $tracks[] = $tags;
    }

    echo json_encode(['tracks' => $tracks], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!mp_check_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired, please reload the page.']);
    exit;
}

if ($action === 'edit') {
    $path = la_resolveMusicFile((string)($_POST['file'] ?? ''));
    if ($path === null) {
        http_response_code(404);
        echo json_encode(['error' => 'That file is no longer in the library — refresh and try again.']);
        exit;
    }

    function la_capField(string $v, int $max): string {
        return mb_substr(sanitiseText($v), 0, $max);
    }

    $tags = [
        'title'    => la_capField((string)($_POST['title'] ?? ''), 200),
        'artist'   => la_capField((string)($_POST['artist'] ?? ''), 200),
        'album'    => la_capField((string)($_POST['album'] ?? ''), 200),
        'year'     => la_capField((string)($_POST['year'] ?? ''), 4),
        'track'    => la_capField((string)($_POST['track'] ?? ''), 10),
        'comment'  => la_capField((string)($_POST['comment'] ?? ''), 1000),
        'buy_url'  => mb_substr(sanitiseUrl((string)($_POST['buy_url'] ?? '')), 0, 500),
        'info_url' => mb_substr(sanitiseUrl((string)($_POST['info_url'] ?? '')), 0, 500),
    ];

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
        $newArtData = '';
        $newArtMime = null;
    }

    // ponytail: last-write-wins, no locking between concurrent admin edits —
    // this is a single-admin, low-traffic app (same reasoning as auth.php's
    // lockout scheme). Add optimistic locking if that ever stops being true.
    $ok = writeID3Tags($path, $path, $tags, $newArtData, $newArtMime);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write tags to the file.']);
        exit;
    }

    mp_log_event('library_edited:' . basename($path));
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $path = la_resolveMusicFile((string)($_POST['file'] ?? ''));
    if ($path === null) {
        http_response_code(404);
        echo json_encode(['error' => 'That file is no longer in the library — refresh and try again.']);
        exit;
    }

    $filename = basename($path);
    if (!unlink($path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not delete the file.']);
        exit;
    }

    mp_log_event('library_deleted:' . $filename);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);
