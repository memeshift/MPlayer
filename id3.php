<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — id3.php                          │
 * │  Pure ID3 parser library. No output, no headers,     │
 * │  no side effects. Safe to require_once from any file. │
 * └──────────────────────────────────────────────────────┘
 *
 * Provides: parseID3(), parseTXXX(), parseWXXX(),
 *           decodeText(), sanitiseUrl(), sanitiseText()
 *
 * Used by: scan.php, embed.php, art.php
 *
 * CHANGELOG
 * ──────────────────────────────────────────────────────
 * v1.0  Extracted from scan.php v1.4. Identical parser logic,
 *       now in a side-effect-free include file so embed.php
 *       can require it without triggering scan.php's JSON output.
 */

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
        'buy_url'  => '',
        'info_url' => '',
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

        $tagSize = 0;
        for ($i = 6; $i <= 9; $i++) {
            $tagSize = ($tagSize << 7) | (ord($header[$i]) & 0x7F);
        }
        $tagEnd = 10 + $tagSize;

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
                fread($fh, 2);

                if ($majorVer === 4) {
                    $frameSize = 0;
                    for ($i = 0; $i < 4; $i++) {
                        $frameSize = ($frameSize << 7) | (ord($sizeRaw[$i]) & 0x7F);
                    }
                } else {
                    $frameSize = unpack('N', $sizeRaw)[1];
                }
            } else {
                $frameId  = fread($fh, 3);
                $sizeRaw  = fread($fh, 3);
                if (strlen($frameId) < 3 || strlen($sizeRaw) < 3) break;
                $frameSize = (ord($sizeRaw[0]) << 16) | (ord($sizeRaw[1]) << 8) | ord($sizeRaw[2]);
            }

            $frameId = rtrim($frameId, "\x00");
            if ($frameSize <= 0 || $frameId === '' || $frameId[0] === "\x00") break;

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

            if ($isApic) { $result['has_art'] = true; continue; }

            if ($isWxxx) {
                if (strlen($data) >= 2) {
                    $url = parseWXXX($data);
                    if ($url !== '' && empty($result['buy_url'])) {
                        $result['buy_url'] = $url;
                    }
                }
                continue;
            }

            if ($isWoaf) {
                $url = sanitiseUrl($data);
                if ($url !== '' && empty($result['info_url'])) {
                    $result['info_url'] = $url;
                }
                continue;
            }

            if ($isTxxx) {
                if (strlen($data) >= 2 && empty($result['info_url'])) {
                    $parsed = parseTXXX($data);
                    if (strcasecmp($parsed['desc'], 'WOAF') === 0) {
                        $url = sanitiseUrl($parsed['value']);
                        if ($url !== '') $result['info_url'] = $url;
                    }
                }
                continue;
            }

            if (!$isText) continue;

            $encoding = strlen($data) > 0 ? ord($data[0]) : 0;

            if ($frameId === 'COMM' || $frameId === 'COM') {
                if (strlen($data) < 5) continue;
                $rest = substr($data, 4);
                if ($encoding === 1 || $encoding === 2) {
                    $pos = 0;
                    while ($pos + 1 < strlen($rest)) {
                        if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") { $pos += 2; break; }
                        $pos += 2;
                    }
                } else {
                    $nul = strpos($rest, "\x00");
                    $pos = ($nul === false) ? strlen($rest) : $nul + 1;
                }
                $result['comment'] = sanitiseText(decodeText(substr($rest, $pos), $encoding));
                continue;
            }

            $text = sanitiseText(decodeText(substr($data, 1), $encoding));

            switch ($frameId) {
                case 'TIT2': case 'TT2':  $result['title']  = $text; break;
                case 'TPE1': case 'TP1':  $result['artist'] = $text; break;
                case 'TALB': case 'TAL':  $result['album']  = $text; break;
                case 'TYER': case 'TYE':  if (empty($result['year'])) $result['year'] = substr($text, 0, 4); break;
                case 'TDRC':              $result['year']   = substr($text, 0, 4); break;
                case 'TRCK': case 'TRK':  $result['track']  = explode('/', $text)[0]; break;
            }
        }
    }

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

    if (empty($result['title'])) {
        $result['title'] = pathinfo($filepath, PATHINFO_FILENAME);
    }

    return $result;
}

function parseTXXX(string $data): array {
    $result = ['desc' => '', 'value' => ''];
    if (strlen($data) < 2) return $result;
    $encoding = ord($data[0]);
    $rest     = substr($data, 1);
    if ($encoding === 1 || $encoding === 2) {
        $pos = 0;
        while ($pos + 1 < strlen($rest)) {
            if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") { $pos += 2; break; }
            $pos += 2;
        }
        $result['desc']  = sanitiseText(decodeText(substr($rest, 0, max(0, $pos - 2)), $encoding));
        $result['value'] = sanitiseText(decodeText(substr($rest, $pos), $encoding));
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
    if (strlen($data) < 2) return '';
    $encoding = ord($data[0]);
    $rest     = substr($data, 1);
    if ($encoding === 1 || $encoding === 2) {
        $pos = 0;
        while ($pos + 1 < strlen($rest)) {
            if ($rest[$pos] === "\x00" && $rest[$pos + 1] === "\x00") { $pos += 2; break; }
            $pos += 2;
        }
    } else {
        $nul = strpos($rest, "\x00");
        $pos = ($nul === false) ? strlen($rest) : $nul + 1;
    }
    $url = trim(str_replace("\x00", '', substr($rest, $pos)));
    if (!preg_match('#^https?://#i', $url)) return '';
    return $url;
}

function decodeText(string $raw, int $encoding): string {
    switch ($encoding) {
        case 1:
            if (strlen($raw) >= 2 && substr($raw, -2) === "\x00\x00") $raw = substr($raw, 0, -2);
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        case 2:
            if (strlen($raw) >= 2 && substr($raw, -2) === "\x00\x00") $raw = substr($raw, 0, -2);
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        case 3:
            return rtrim($raw, "\x00");
        default:
            return mb_convert_encoding(rtrim($raw, "\x00"), 'UTF-8', 'ISO-8859-1');
    }
}

function sanitiseUrl(string $raw): string {
    $url = trim(str_replace("\x00", '', $raw));
    if (!preg_match('#^https?://#i', $url)) return '';
    return $url;
}

function sanitiseText(string $s): string {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return trim($s ?? '');
}
