<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — id3_write.php                     │
 * │  ID3v2.3 tag WRITER. Pure functions, no output, no    │
 * │  side effects beyond the files it's explicitly told   │
 * │  to write. Safe to require_once from any script.      │
 * └──────────────────────────────────────────────────────┘
 *
 * Provides: writeID3Tags()
 *
 * Always rebuilds the tag as a fresh ID3v2.3 header, regardless of what
 * version (if any) was there before — this app's own reader (id3.php)
 * already fully supports v2.3, so this is the one format we need to
 * produce. No unsynchronization, no extended header, no footer.
 *
 * ponytail: rebuilding from our own known frame set means any other
 * frames the original file had (rare custom TXXX, etc.) are dropped on
 * edit. Acceptable since the upload form is meant to be the source of
 * truth for these fields; revisit only if that turns out to matter.
 *
 * Used by: upload_commit.php
 */

require_once __DIR__ . '/id3.php'; // sanitiseText()/sanitiseUrl() reused for safety, though callers should already have sanitized

/**
 * Rewrite $srcPath's ID3v2 tag with $tags and write the result to
 * $destPath (may be the same path — a temp file is used internally and
 * atomically renamed over the destination).
 *
 * $tags: ['title','artist','album','year','track','comment','buy_url','info_url']
 * $newArtData/$newArtMime: raw bytes + mime of new cover art, or null to
 * leave art untouched (existing art, if any, is preserved — see below).
 */
function writeID3Tags(string $srcPath, string $destPath, array $tags, ?string $newArtData = null, ?string $newArtMime = null): bool {
    $fh = @fopen($srcPath, 'rb');
    if (!$fh) return false;

    $audioStart = iw_findAudioStart($fh);

    // Preserve existing art if the caller didn't supply replacement art.
    // Rebuilding the tag from scratch would otherwise silently delete
    // album art on a text-only edit.
    if ($newArtData === null) {
        $existing = iw_extractAPIC($srcPath);
        if ($existing !== null) {
            $newArtData = $existing['data'];
            $newArtMime = $existing['mime'];
        }
    }

    $frames = '';
    $frames .= iw_textFrame('TIT2', $tags['title'] ?? '');
    $frames .= iw_textFrame('TPE1', $tags['artist'] ?? '');
    $frames .= iw_textFrame('TALB', $tags['album'] ?? '');
    $frames .= iw_textFrame('TYER', $tags['year'] ?? '');
    $frames .= iw_textFrame('TRCK', $tags['track'] ?? '');
    $frames .= iw_commFrame($tags['comment'] ?? '');
    $frames .= iw_wxxxFrame($tags['buy_url'] ?? '');
    $frames .= iw_woafFrame($tags['info_url'] ?? '');
    if ($newArtData !== null && $newArtMime !== null) {
        $frames .= iw_apicFrame($newArtMime, $newArtData);
    }

    $header = "ID3" . "\x03\x00" . "\x00" . iw_synchsafe(strlen($frames));

    $tmpPath = $destPath . '.tmp' . bin2hex(random_bytes(4));
    $out = @fopen($tmpPath, 'wb');
    if (!$out) { fclose($fh); return false; }

    fwrite($out, $header);
    fwrite($out, $frames);

    fseek($fh, $audioStart);
    stream_copy_to_stream($fh, $out);

    fclose($fh);
    fclose($out);

    if (!rename($tmpPath, $destPath)) {
        @unlink($tmpPath);
        return false;
    }
    return true;
}

/* ── Locate where the audio payload starts, skipping any existing ID3v2
   header and a trailing ID3v1 128-byte block (checked separately by the
   caller via file size — this only handles the leading v2 tag). ── */
function iw_findAudioStart($fh): int {
    fseek($fh, 0);
    $header = fread($fh, 10);
    if (strlen($header) < 10 || substr($header, 0, 3) !== 'ID3') {
        return 0;
    }
    $tagSize = 0;
    for ($i = 6; $i <= 9; $i++) {
        $tagSize = ($tagSize << 7) | (ord($header[$i]) & 0x7F);
    }
    return 10 + $tagSize;
}

function iw_synchsafe(int $size): string {
    return pack('C4',
        ($size >> 21) & 0x7F,
        ($size >> 14) & 0x7F,
        ($size >> 7) & 0x7F,
        $size & 0x7F
    );
}

function iw_frameHeader(string $id, int $size): string {
    return $id . pack('N', $size) . "\x00\x00";
}

/* UTF-16 (with BOM) text frame — encoding byte 1, matches what
   id3.php's decodeText() already decodes for encoding 1/2. */
function iw_textFrame(string $id, string $text): string {
    $text = trim(sanitiseText($text));
    if ($text === '') return '';
    $body = "\x01" . mb_convert_encoding($text, 'UTF-16', 'UTF-8');
    return iw_frameHeader($id, strlen($body)) . $body;
}

function iw_commFrame(string $text): string {
    $text = trim(sanitiseText($text));
    if ($text === '') return '';
    // encoding(1) + language(3) + empty UTF-16 description + null separator + UTF-16 text
    $body = "\x01" . 'eng' . "\x00\x00" . mb_convert_encoding($text, 'UTF-16', 'UTF-8');
    return iw_frameHeader('COMM', strlen($body)) . $body;
}

/* WXXX (buy_url): encoding(0, latin1) + empty description + raw URL bytes.
   Matches parseWXXX()'s reader. */
function iw_wxxxFrame(string $url): string {
    $url = sanitiseUrl($url);
    if ($url === '') return '';
    $body = "\x00" . "\x00" . $url;
    return iw_frameHeader('WXXX', strlen($body)) . $body;
}

/* WOAF (info_url): raw URL bytes only, no encoding byte — id3.php's reader
   calls sanitiseUrl($data) directly on the frame body. */
function iw_woafFrame(string $url): string {
    $url = sanitiseUrl($url);
    if ($url === '') return '';
    return iw_frameHeader('WOAF', strlen($url)) . $url;
}

/* APIC (front cover art): encoding(0) + mime + null + pic-type(3=front
   cover) + empty description + null + raw image bytes. */
function iw_apicFrame(string $mime, string $data): string {
    $body = "\x00" . $mime . "\x00" . "\x03" . "\x00" . $data;
    return iw_frameHeader('APIC', strlen($body)) . $body;
}

/* ══════════════════════════════════════════════════════════
   APIC EXTRACTOR — duplicated from art.php (iw_-prefixed to avoid symbol
   collisions if both files are ever require_once'd in the same request),
   matching this codebase's existing convention of duplicating small pure
   parsing functions rather than sharing them (see scan.php's own copy of
   parseID3).
══════════════════════════════════════════════════════════ */
function iw_extractAPIC(string $filepath): ?array {
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
            fread($fh, 2);
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

        $maxArt = 10 * 1024 * 1024;
        $apicData = fread($fh, min($frameSize, $maxArt));
        fclose($fh);

        return $frameId === 'APIC' ? iw_parseAPICv23($apicData) : iw_parseAPICv22($apicData);
    }

    fclose($fh);
    return null;
}

function iw_parseAPICv23(string $data): ?array {
    if (strlen($data) < 4) return null;
    $encoding = ord($data[0]);
    $mimeEnd  = strpos($data, "\x00", 1);
    if ($mimeEnd === false) return null;
    $mime = strtolower(substr($data, 1, $mimeEnd - 1));
    $rest = substr($data, $mimeEnd + 2);

    if ($encoding === 1 || $encoding === 2) {
        $pos = 0;
        while ($pos + 1 < strlen($rest)) {
            if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") { $pos += 2; break; }
            $pos += 2;
        }
    } else {
        $nul = strpos($rest, "\x00");
        $pos = ($nul === false) ? 0 : $nul + 1;
    }

    return iw_validateAndReturnArt(substr($rest, $pos), $mime);
}

function iw_parseAPICv22(string $data): ?array {
    if (strlen($data) < 6) return null;
    $format = strtoupper(substr($data, 1, 3));
    $rest   = substr($data, 5);
    $nul = strpos($rest, "\x00");
    $pos = ($nul === false) ? 0 : $nul + 1;
    $mime = ($format === 'PNG') ? 'image/png' : 'image/jpeg';
    return iw_validateAndReturnArt(substr($rest, $pos), $mime);
}

function iw_validateAndReturnArt(string $imgData, string $mimeHint): ?array {
    if (strlen($imgData) < 4) return null;
    if (substr($imgData, 0, 2) === "\xFF\xD8") {
        $mime = 'image/jpeg';
    } elseif (substr($imgData, 0, 8) === "\x89PNG\r\n\x1a\n") {
        $mime = 'image/png';
    } elseif (substr($imgData, 0, 6) === 'GIF87a' || substr($imgData, 0, 6) === 'GIF89a') {
        $mime = 'image/gif';
    } elseif (substr($imgData, 0, 4) === 'RIFF' && substr($imgData, 8, 4) === 'WEBP') {
        $mime = 'image/webp';
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeHint, $allowed, true)) return null;
        $mime = $mimeHint;
    }
    return ['mime' => $mime, 'data' => $imgData];
}
