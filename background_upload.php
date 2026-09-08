<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — background_upload.php              │
 * │  Auth-gated: validates and stores a new player          │
 * │  background image, called from the Customize modal in  │
 * │  index.html. Same validation as cover-art uploads       │
 * │  (magic-byte sniff + MAX_ART_MB), stored as a real file │
 * │  in images/backgrounds/ (not embedded like cover art,   │
 * │  since this is a page background, not track metadata).  │
 * └──────────────────────────────────────────────────────┘
 *
 * POST csrf, image
 * Returns { ok:true, path:"images/backgrounds/xxxx.jpg" }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/id3_write.php'; // iw_validateAndReturnArt()

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

mp_require_login();

if (!mp_check_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired, please reload the page.']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No image uploaded.']);
    exit;
}

if ($_FILES['image']['size'] > MAX_ART_MB * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Image is larger than ' . MAX_ART_MB . 'MB.']);
    exit;
}

$raw = file_get_contents($_FILES['image']['tmp_name']);
$validated = iw_validateAndReturnArt($raw, mime_content_type($_FILES['image']['tmp_name']) ?: '');
if ($validated === null) {
    http_response_code(400);
    echo json_encode(['error' => 'That image is not a valid JPEG, PNG, GIF, or WEBP.']);
    exit;
}

$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$validated['mime']];
$dir = __DIR__ . '/images/backgrounds/';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create backgrounds directory.']);
    exit;
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
if (file_put_contents($dir . $filename, $validated['data'], LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the image.']);
    exit;
}

echo json_encode(['ok' => true, 'path' => 'images/backgrounds/' . $filename]);
