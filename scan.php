<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — scan.php                         │
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
    if (!in_array($ext, ALLOWED_EXT, true)) continue;

    $tags            = parseID3($real);
    $tags['file']    = rawurlencode($filename);
    $tags['filesize'] = filesize($real) ?: 0;
    // Duration is filled client-side via HTML5 audio metadata event.
    // We set 0 here; JS updates it after each track loads.
    $tags['duration'] = 0;

    $tracks[] = $tags;
}

echo json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

/* ══════════════════════════════════════════════════════════
   ID3 PARSER
   Handles ID3v2.2, v2.3, v2.4 with ID3v1 fallback.
   Extracts: title, artist, album, year, track, comment, buy_url
             and a boolean has_art (actual art served by art.php).
══════════════════════════════════════════════════════════ */

function parseID3(string $filepath): array {
    $result = [
        'title'   => '',
        'artist'  => '',
        'album'   => '',
        'year'    => '',
        'track'   => '',
        'comment' => '',
        'buy_url'  => '',    // WXXX / WXX frame URL
        'info_url' => '',    // WOAF frame URL — official audio file webpage
        'has_art'  => false,
    ];

    $fh = @fopen($filepath, 'rb');
    if (!$fh) {
        $result['title'] = pathinfo($filepath, PATHINFO_FILENAME);
        return $result;
    }

    $header = fread($fh, 10);

    if (strlen($header) >= 10 && substr($header, 0, 3) === 'ID3') {
        $majorVer = ord($header[3]);
        $flags    = ord($header[5]);

        // Synchsafe integer → total tag size (bytes after the 10-byte header)
        $tagSize = 0;
        for ($i = 6; $i <= 9; $i++) {
            $tagSize = ($tagSize << 7) | (ord($header[$i]) & 0x7F);
        }
        $tagEnd = 10 + $tagSize;

        // Skip optional extended header
        if ($flags & 0x40) {
            $extRaw = fread($fh, 4);
            if (strlen($extRaw) === 4) {
                if ($majorVer === 4) {
                    $extSize = 0;
                    for ($i = 0; $i < 4; $i++) {
                        $extSize = ($extSize << 7) | (ord($extRaw[$i]) & 0x7F);
                    }
                } else {
                    $extSize = unpack('N', $extRaw)[1];
                }
                fseek($fh, max(0, $extSize - 4), SEEK_CUR);
            }
        }

        $frameHeaderSize = ($majorVer >= 3) ? 10 : 6;

        while (ftell($fh) < ($tagEnd - $frameHeaderSize)) {
            if ($majorVer >= 3) {
                $frameId  = fread($fh, 4);
                $sizeRaw  = fread($fh, 4);
                if (strlen($frameId) < 4 || strlen($sizeRaw) < 4) break;
                fread($fh, 2); // frame flags

                if ($majorVer === 4) {
                    $frameSize = 0;
                    for ($i = 0; $i < 4; $i++) {
                        $frameSize = ($frameSize << 7) | (ord($sizeRaw[$i]) & 0x7F);
                    }
                } else {
                    $frameSize = unpack('N', $sizeRaw)[1];
                }
            } else {
                // ID3v2.2 — 3-byte IDs and sizes
                $frameId  = fread($fh, 3);
                $sizeRaw  = fread($fh, 3);
                if (strlen($frameId) < 3 || strlen($sizeRaw) < 3) break;
                $frameSize = (ord($sizeRaw[0]) << 16) | (ord($sizeRaw[1]) << 8) | ord($sizeRaw[2]);
            }

            $frameId = rtrim($frameId, "\x00");
            if ($frameSize <= 0 || $frameId === '' || $frameId[0] === "\x00") break;

            // Limit per-frame read to avoid huge APIC frames eating memory
            $readBytes = min($frameSize, 65536);
            $data      = fread($fh, $readBytes);
            if ($frameSize > $readBytes) {
                fseek($fh, $frameSize - $readBytes, SEEK_CUR);
            }

            $isText = in_array($frameId, [
                'TIT2','TPE1','TALB','TYER','TDRC','TRCK','COMM',
                'TT2', 'TP1', 'TAL', 'TYE', 'TRK', 'COM',
            ], true);
            $isApic = ($frameId === 'APIC' || $frameId === 'PIC');
            $isWxxx = ($frameId === 'WXXX' || $frameId === 'WXX');
            $isWoaf = ($frameId === 'WOAF');
            $isTxxx = ($frameId === 'TXXX');

            if ($isApic) {
                $result['has_art'] = true;
                continue;
            }

            // ── WXXX / WXX — user-defined URL frame ──
            // Structure: [enc byte][description \0-terminated][URL]
            // URL is always ISO-8859-1 regardless of encoding byte.
            if ($isWxxx) {
                if (strlen($data) >= 2) {
                    $url = parseWXXX($data);
                    if ($url !== '' && empty($result['buy_url'])) {
                        $result['buy_url'] = $url;
                    }
                }
                continue;
            }

            // ── WOAF — official audio file webpage (proper W-frame) ──
            // Structure: plain URL bytes, no encoding byte.
            // NOTE: Some taggers (including Mp3tag) may instead write this
            // as a TXXX frame with description "WOAF" — handled below.
            if ($isWoaf) {
                $url = sanitiseUrl($data);
                if ($url !== '' && empty($result['info_url'])) {
                    $result['info_url'] = $url;
                }
                continue;
            }

            // ── TXXX — user-defined text frame ──
            // Mp3tag writes unrecognised W-frame names (like WOAF) as TXXX
            // frames with the frame name as description and the URL as value.
            // Structure: [enc byte][description \0-terminated][value]
            if ($isTxxx) {
                if (strlen($data) >= 2 && empty($result['info_url'])) {
                    $parsed = parseTXXX($data);
                    // Match description case-insensitively to "WOAF"
                    if (strcasecmp($parsed['desc'], 'WOAF') === 0) {
                        $url = sanitiseUrl($parsed['value']);
                        if ($url !== '') {
                            $result['info_url'] = $url;
                        }
                    }
                }
                continue;
            }

            if (!$isText) continue;

            $encoding = strlen($data) > 0 ? ord($data[0]) : 0;

            // ── COMMENT frame ──
            if ($frameId === 'COMM' || $frameId === 'COM') {
                if (strlen($data) < 5) continue;
                // [enc][lang:3][short_desc\0][text]
                $rest = substr($data, 4);
                if ($encoding === 1 || $encoding === 2) {
                    // UTF-16: find double-null boundary
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
                    $pos = ($nul === false) ? strlen($rest) : $nul + 1;
                }
                $commentRaw = substr($rest, $pos);
                $result['comment'] = sanitiseText(decodeText($commentRaw, $encoding));
                continue;
            }

            // ── Text frames ──
            $text = sanitiseText(decodeText(substr($data, 1), $encoding));

            switch ($frameId) {
                case 'TIT2': case 'TT2':
                    $result['title']  = $text; break;
                case 'TPE1': case 'TP1':
                    $result['artist'] = $text; break;
                case 'TALB': case 'TAL':
                    $result['album']  = $text; break;
                case 'TYER': case 'TYE':
                    if (empty($result['year'])) $result['year'] = substr($text, 0, 4);
                    break;
                case 'TDRC':
                    $result['year'] = substr($text, 0, 4); break;
                case 'TRCK': case 'TRK':
                    $result['track'] = explode('/', $text)[0]; break;
            }
        }
    }

    // ── ID3v1 fallback ──
    if (empty($result['title'])) {
        fseek($fh, -128, SEEK_END);
        $v1 = fread($fh, 128);
        if (strlen($v1) === 128 && substr($v1, 0, 3) === 'TAG') {
            $result['title']   = sanitiseText(mb_convert_encoding(rtrim(substr($v1, 3,  30)), 'UTF-8', 'ISO-8859-1'));
            $result['artist']  = sanitiseText(mb_convert_encoding(rtrim(substr($v1, 33, 30)), 'UTF-8', 'ISO-8859-1'));
            $result['album']   = sanitiseText(mb_convert_encoding(rtrim(substr($v1, 63, 30)), 'UTF-8', 'ISO-8859-1'));
            $result['year']    = sanitiseText(trim(substr($v1, 93, 4)));
            $result['comment'] = sanitiseText(mb_convert_encoding(rtrim(substr($v1, 97, 30)), 'UTF-8', 'ISO-8859-1'));
        }
    }

    fclose($fh);

    // Last fallback: use filename as title
    if (empty($result['title'])) {
        $result['title'] = pathinfo($filepath, PATHINFO_FILENAME);
    }

    return $result;
}

function parseTXXX(string $data): array {
    // TXXX frame layout (identical structure to WXXX):
    //   [1 byte: encoding][description: null-terminated][value]
    // Both description and value use the same encoding.
    $result = ['desc' => '', 'value' => ''];
    if (strlen($data) < 2) return $result;

    $encoding = ord($data[0]);
    $rest     = substr($data, 1);

    if ($encoding === 1 || $encoding === 2) {
        $pos = 0;
        while ($pos + 1 < strlen($rest)) {
            if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") {
                $pos += 2;
                break;
            }
            $pos += 2;
        }
        $descRaw = substr($rest, 0, max(0, $pos - 2));
        $valRaw  = substr($rest, $pos);
        $result['desc']  = sanitiseText(decodeText($descRaw, $encoding));
        $result['value'] = sanitiseText(decodeText($valRaw,  $encoding));
    } else {
        $nul = strpos($rest, "\x00");
        if ($nul === false) {
            $result['desc'] = sanitiseText(decodeText($rest, $encoding));
        } else {
            $result['desc']  = sanitiseText(decodeText(substr($rest, 0, $nul), $encoding));
            $result['value'] = sanitiseText(decodeText(substr($rest, $nul + 1), $encoding));
        }
    }

    return $result;
}

function parseWXXX(string $data): string {
    // WXXX frame layout:
    //   [1 byte: encoding][description: null-terminated in that encoding][URL: ISO-8859-1]
    // The encoding byte applies only to the description — the URL is always ISO-8859-1.

    if (strlen($data) < 2) return '';

    $encoding = ord($data[0]);
    $rest     = substr($data, 1); // everything after the encoding byte

    // Skip past the description (null-terminated, encoding-aware)
    if ($encoding === 1 || $encoding === 2) {
        // UTF-16: description ends at the first double-null on an even boundary
        $pos = 0;
        while ($pos + 1 < strlen($rest)) {
            if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") {
                $pos += 2;
                break;
            }
            $pos += 2;
        }
    } else {
        // ISO-8859-1 or UTF-8: description ends at the first single null
        $nul = strpos($rest, "\x00");
        $pos = ($nul === false) ? strlen($rest) : $nul + 1;
    }

    // Everything after the description is the URL
    $url = substr($rest, $pos);

    // URLs are ASCII/ISO-8859-1 — strip nulls and whitespace, validate loosely
    $url = trim(str_replace("\x00", '', $url));

    // Must start with a scheme to be usable (http/https/ftp etc.)
    if (!preg_match('#^https?://#i', $url)) return '';

    return $url;
}

function decodeText(string $raw, int $encoding): string {
    switch ($encoding) {
        case 1:
            // UTF-16 with BOM (most common: UTF-16LE with \xFF\xFE preamble).
            // Do NOT rtrim individual \x00 bytes — every character is 2 bytes,
            // so the last byte of any ASCII/Latin character IS a \x00.
            // Strip only a trailing double-null (the UTF-16 null terminator) if present.
            if (strlen($raw) >= 2 && substr($raw, -2) === "\x00\x00") {
                $raw = substr($raw, 0, -2);
            }
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16');

        case 2:
            // UTF-16BE without BOM — same reasoning as case 1.
            if (strlen($raw) >= 2 && substr($raw, -2) === "\x00\x00") {
                $raw = substr($raw, 0, -2);
            }
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');

        case 3:
            // UTF-8 — single-byte null terminator safe to strip.
            return rtrim($raw, "\x00");

        default:
            // ISO-8859-1 (encoding byte 0x00) — single-byte, safe to strip.
            return mb_convert_encoding(rtrim($raw, "\x00"), 'UTF-8', 'ISO-8859-1');
    }
}

function sanitiseUrl(string $raw): string {
    $url = trim(str_replace("\x00", '', $raw));
    if (!preg_match('#^https?://#i', $url)) return '';
    return $url;
}

function sanitiseText(string $s): string {
    // Remove null bytes and non-printable characters, trim whitespace
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return trim($s ?? '');
}
