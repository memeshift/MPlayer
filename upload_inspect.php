<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — upload_inspect.php                │
 * │  Auth-gated. Stages an uploaded MP3, reads its ID3    │
 * │  tags, and returns them as prefill JSON. Nothing is   │
 * │  written to MUSIC_DIR here — see upload_commit.php.   │
 * └──────────────────────────────────────────────────────┘
 *
 * POST multipart/form-data: file=<mp3>, csrf=<token>
 * GET  ?preview_art=<token>: streams the staged file's embedded art
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/id3.php';
require_once __DIR__ . '/id3_write.php'; // iw_extractAPIC() for the art preview

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

mp_session_start();

if (!is_dir(STAGING_DIR)) {
    @mkdir(STAGING_DIR, 0755, true);
}

/* ── Opportunistic cleanup of stale staged files (no cron on this host) ── */
foreach (glob(STAGING_DIR . '*.mp3') ?: [] as $f) {
    if (filemtime($f) < time() - 3600) {
        @unlink($f);
    }
}

/* ── Art preview for a staged file (GET) ── */
if (isset($_GET['preview_art'])) {
    mp_require_login();
    $token = (string)$_GET['preview_art'];
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || !in_array($token, $_SESSION['staging_tokens'] ?? [], true)) {
        http_response_code(404);
        exit;
    }
    $path = STAGING_DIR . $token . '.mp3';
    if (!is_file($path)) { http_response_code(404); exit; }
    $art = iw_extractAPIC($path);
    if ($art === null) { http_response_code(404); exit; }
    header('Content-Type: ' . $art['mime']);
    header('Content-Length: ' . strlen($art['data']));
    echo $art['data'];
    exit;
}

/* ── Stage a new upload (POST) ── */
header('Content-Type: application/json');
mp_require_login();

if (!mp_check_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired, please reload the page.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded, or the upload failed.']);
    exit;
}

$upload = $_FILES['file'];

if ($upload['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File is larger than ' . MAX_UPLOAD_MB . 'MB.']);
    exit;
}

if (!iw_looksLikeMP3($upload['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'That file does not look like a valid MP3.']);
    exit;
}

$token = bin2hex(random_bytes(16));
$stagedPath = STAGING_DIR . $token . '.mp3';

if (!move_uploaded_file($upload['tmp_name'], $stagedPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the uploaded file.']);
    exit;
}

$_SESSION['staging_tokens'][] = $token;
$_SESSION['staging_names'][$token] = basename($upload['name']);
mp_log_event('upload_staged');

$tags = parseID3($stagedPath);
$tags['token'] = $token;
echo json_encode($tags, JSON_UNESCAPED_UNICODE);

/* Scan the first 64KB for an ID3 header or an MPEG frame sync — extension
   alone is never trusted (matches art.php/scan.php's "validate the bytes,
   not the name" convention). */
function iw_looksLikeMP3(string $path): bool {
    $fh = @fopen($path, 'rb');
    if (!$fh) return false;
    $chunk = fread($fh, 65536);
    fclose($fh);
    if ($chunk === false || $chunk === '') return false;

    if (substr($chunk, 0, 3) === 'ID3') return true;

    $len = strlen($chunk);
    for ($i = 0; $i < $len - 1; $i++) {
        if (ord($chunk[$i]) === 0xFF && (ord($chunk[$i + 1]) & 0xE0) === 0xE0) {
            return true;
        }
    }
    return false;
}
