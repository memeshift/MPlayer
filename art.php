<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — art.php                          │
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
if (!in_array($ext, ALLOWED_EXT, true)) { servePlaceholder(); exit; }

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
$art = extractAPIC($real);

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

/* ══════════════════════════════════════════════════════════
   APIC EXTRACTOR
   Parses ID3v2 tags to find and return the APIC frame.
══════════════════════════════════════════════════════════ */
function extractAPIC(string $filepath): ?array {
    $fh = @fopen($filepath, 'rb');
    if (!$fh) return null;

    $header = fread($fh, 10);
    if (strlen($header) < 10 || substr($header, 0, 3) !== 'ID3') {
        fclose($fh);
        return null;
    }

    $majorVer = ord($header[3]);
    $flags    = ord($header[5]);

    $tagSize = 0;
    for ($i = 6; $i <= 9; $i++) {
        $tagSize = ($tagSize << 7) | (ord($header[$i]) & 0x7F);
    }
    $tagEnd = 10 + $tagSize;

    // Skip extended header
    if ($flags & 0x40) {
        $extRaw = fread($fh, 4);
        if (strlen($extRaw) === 4) {
            $extSize = ($majorVer === 4)
                ? (function($r){ $s=0; for($i=0;$i<4;$i++) $s=($s<<7)|(ord($r[$i])&0x7F); return $s; })($extRaw)
                : unpack('N', $extRaw)[1];
            fseek($fh, max(0, $extSize - 4), SEEK_CUR);
        }
    }

    $frameHeaderSize = ($majorVer >= 3) ? 10 : 6;

    while (ftell($fh) < ($tagEnd - $frameHeaderSize)) {
        if ($majorVer >= 3) {
            $frameId = fread($fh, 4);
            $sizeRaw = fread($fh, 4);
            if (strlen($frameId) < 4 || strlen($sizeRaw) < 4) break;
            fread($fh, 2); // flags

            $frameSize = ($majorVer === 4)
                ? (function($r){ $s=0; for($i=0;$i<4;$i++) $s=($s<<7)|(ord($r[$i])&0x7F); return $s; })($sizeRaw)
                : unpack('N', $sizeRaw)[1];
        } else {
            $frameId = fread($fh, 3);
            $sizeRaw = fread($fh, 3);
            if (strlen($frameId) < 3 || strlen($sizeRaw) < 3) break;
            $frameSize = (ord($sizeRaw[0]) << 16) | (ord($sizeRaw[1]) << 8) | ord($sizeRaw[2]);
        }

        $frameId = rtrim($frameId, "\x00");
        if ($frameSize <= 0 || $frameId === '' || $frameId[0] === "\x00") break;

        $isApic = ($frameId === 'APIC' || $frameId === 'PIC');

        if (!$isApic) {
            fseek($fh, $frameSize, SEEK_CUR);
            continue;
        }

        // Read entire APIC frame (album art can be large — up to 10 MB)
        $maxArt = 10 * 1024 * 1024;
        $apicData = fread($fh, min($frameSize, $maxArt));
        fclose($fh);

        if ($frameId === 'APIC') {
            return parseAPICv23($apicData);
        } else {
            return parseAPICv22($apicData);
        }
    }

    fclose($fh);
    return null;
}

function parseAPICv23(string $data): ?array {
    if (strlen($data) < 4) return null;

    $encoding = ord($data[0]);
    $mimeEnd  = strpos($data, "\x00", 1);
    if ($mimeEnd === false) return null;

    $mime = strtolower(substr($data, 1, $mimeEnd - 1));
    // Skip pic_type byte after mime\0
    $rest = substr($data, $mimeEnd + 2);

    // Skip short description (null-terminated, encoding-aware)
    if ($encoding === 1 || $encoding === 2) {
        $pos = 0;
        while ($pos + 1 < strlen($rest)) {
            if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") {
                $pos += 2;
                break;
            }
            $pos += 2;
        }
    } else {
        $nul = strpos($rest, "\x00");
        $pos = ($nul === false) ? 0 : $nul + 1;
    }

    $imgData = substr($rest, $pos);
    return validateAndReturnArt($imgData, $mime);
}

function parseAPICv22(string $data): ?array {
    if (strlen($data) < 6) return null;
    $encoding = ord($data[0]);
    $format   = strtoupper(substr($data, 1, 3)); // "JPG" or "PNG"
    $rest     = substr($data, 5); // skip enc + format + pic_type

    // Skip description
    $nul = strpos($rest, "\x00");
    $pos = ($nul === false) ? 0 : $nul + 1;

    $imgData = substr($rest, $pos);
    $mime    = ($format === 'PNG') ? 'image/png' : 'image/jpeg';
    return validateAndReturnArt($imgData, $mime);
}

function validateAndReturnArt(string $imgData, string $mimeHint): ?array {
    if (strlen($imgData) < 4) return null;

    // Detect actual format from magic bytes
    if (substr($imgData, 0, 2) === "\xFF\xD8") {
        $mime = 'image/jpeg';
    } elseif (substr($imgData, 0, 8) === "\x89PNG\r\n\x1a\n") {
        $mime = 'image/png';
    } elseif (substr($imgData, 0, 6) === 'GIF87a' || substr($imgData, 0, 6) === 'GIF89a') {
        $mime = 'image/gif';
    } elseif (substr($imgData, 0, 4) === 'RIFF' && substr($imgData, 8, 4) === 'WEBP') {
        $mime = 'image/webp';
    } else {
        // Trust hint only for known types
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (in_array($mimeHint, $allowed, true)) {
            $mime = $mimeHint;
        } else {
            return null;
        }
    }

    return ['mime' => $mime, 'data' => $imgData];
}
