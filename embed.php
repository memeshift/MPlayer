<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — embed.php                        │
 * │  Self-contained single-track mini-player for         │
 * │  embedding on external sites via <iframe>.           │
 * │  Usage: embed.php?t=url-encoded-filename.mp3         │
 * └──────────────────────────────────────────────────────┘
 *
 * Security:
 *  • Only accepts a filename (basename), never a path
 *  • Validates file is inside MUSIC_DIR via realpath()
 *  • Reuses scan.php ID3 parser via require_once
 *  • No user input touches the filesystem
 *
 * CHANGELOG
 * ──────────────────────────────────────────────────────
 * v1.0  Initial build. Option A style: yellow titlebar removed per
 *       design decision, art block fills full height, DM Mono font,
 *       125px height, responsive width, play/pause via HTML5 audio,
 *       links back to music.memeshift.com with ?t= deep link.
 * v1.2  Fixed asset URLs being relative. When embed.php loads inside an
 *       iframe on an external domain, relative paths like 'music/' and
 *       'art.php' resolve against the embedding site's origin, not
 *       music.memeshift.com. All three URLs (audio, art, player link)
 *       are now fully qualified absolute URLs to music.memeshift.com.
 *       invalid 'ALLOWALL' value (not a recognised XFO token — browsers
 *       treat unknown values as DENY). X-Frame-Options header omitted
 *       entirely; Content-Security-Policy: frame-ancestors * is the
 *       correct modern replacement. .htaccess rule added to unset any
 *       host-injected X-Frame-Options header for embed.php only.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/id3.php'; // pure parser library — no output, no side effects

// ── Validate ?t= parameter ──
$raw = isset($_GET['t']) ? (string) $_GET['t'] : '';
if ($raw === '') { http_response_code(400); exit('Missing track parameter.'); }

$filename = basename(rawurldecode($raw));
$ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) { http_response_code(400); exit('Invalid file type.'); }

$musicDir = realpath(MUSIC_DIR);
if ($musicDir === false) { http_response_code(500); exit('Music directory not found.'); }
$musicDir = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$filepath = $musicDir . $filename;
$real     = realpath($filepath);
if ($real === false || strpos($real, $musicDir) !== 0 || !is_file($real)) {
    http_response_code(404); exit('Track not found.');
}

// ── Parse tags ──
$tags      = parseID3($real);
$title     = htmlspecialchars($tags['title']  ?: pathinfo($filename, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8');
$artist    = htmlspecialchars($tags['artist'] ?: '', ENT_QUOTES, 'UTF-8');
$album     = htmlspecialchars($tags['album']  ?: '', ENT_QUOTES, 'UTF-8');
$year      = htmlspecialchars($tags['year']   ?: '', ENT_QUOTES, 'UTF-8');
$hasArt    = $tags['has_art'];
// ── Build absolute URLs — embed.php is served inside iframes on external
// domains, so relative paths would resolve against the embedding site.
// All asset URLs must be fully qualified to music.memeshift.com.
$baseUrl   = 'https://music.memeshift.com';
$fileEnc   = rawurlencode($filename);
$audioUrl  = $baseUrl . '/music/' . rawurlencode($filename);
$artUrl    = $baseUrl . '/art.php?f=' . $fileEnc;
$playerUrl = $baseUrl . '/?t=' . $fileEnc;

// ── Security headers ──
// X-Frame-Options is intentionally omitted — this file is designed to be
// embedded in iframes on any domain. The modern equivalent is:
//   Content-Security-Policy: frame-ancestors *
// which all current browsers honour. If your host injects X-Frame-Options: DENY
// globally, override it in .htaccess with:
//   <Files "embed.php">
//     Header always unset X-Frame-Options
//   </Files>
header('Content-Type: text/html; charset=utf-8');
header('Content-Security-Policy: frame-ancestors *');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?> — Memeshift</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  width: 100%;
  height: 125px;
  overflow: hidden;
  background: #0a0a04;
  font-family: 'DM Mono', 'Courier New', monospace;
}

.player {
  width: 100%;
  height: 125px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #2a2010;
  background: #0a0a04;
}

/* Art block — fills full height of content area */
.art-block {
  width: 103px;
  flex-shrink: 0;
  background: #141008;
  border-right: 1px solid #2a2010;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
}

.art-block img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.art-placeholder {
  color: #2e2810;
  font-size: 36px;
  line-height: 1;
  user-select: none;
}

/* Right: info + controls */
.content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 10px 14px 10px 14px;
  min-width: 0;
  overflow: hidden;
}

.track-info { min-width: 0; }

.track-title {
  color: #FAC946;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: 0.02em;
  margin-bottom: 3px;
}

.track-artist {
  color: #907840;
  font-size: 11px;
  letter-spacing: 0.06em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 1px;
}

.track-meta {
  color: #504830;
  font-size: 10px;
  letter-spacing: 0.04em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Controls row */
.controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.play-btn {
  width: 32px;
  height: 32px;
  background: #FAC946;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: transform 0.08s, background 0.1s;
  -webkit-tap-highlight-color: transparent;
}

.play-btn:hover  { background: #fcd97c; }
.play-btn:active { transform: scale(0.90); }

.play-icon  { display: block; }
.pause-icon { display: none; }

.play-btn svg {
  pointer-events: none;
}

.time-display {
  color: #504830;
  font-size: 10px;
  letter-spacing: 0.04em;
  flex-shrink: 0;
  margin-left: 8px;
}

.listen-link {
  color: #007998;
  font-size: 10px;
  letter-spacing: 0.03em;
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.12s;
  margin-left: auto;
}

.listen-link:hover { color: #00a8c8; }

/* Content row: art + content side by side */
.player-body {
  flex: 1;
  display: flex;
  overflow: hidden;
  min-height: 0;
}

/* Responsive: at very narrow widths shrink art block */
@media (max-width: 300px) {
  .art-block { width: 70px; }
  .track-title { font-size: 11px; }
  .listen-link { display: none; }
}
</style>
</head>
<body>

<div class="player">
  <div class="player-body">

    <!-- Album art -->
    <div class="art-block">
      <?php if ($hasArt): ?>
        <img src="<?= $artUrl ?>" alt="Album art for <?= $title ?>">
      <?php else: ?>
        <span class="art-placeholder">♪</span>
      <?php endif; ?>
    </div>

    <!-- Track info + controls -->
    <div class="content">

      <div class="track-info">
        <div class="track-title"><?= $title ?></div>
        <?php if ($artist): ?>
          <div class="track-artist"><?= $artist ?></div>
        <?php endif; ?>
        <?php
          $metaParts = array_filter([$album, $year]);
          if ($metaParts):
        ?>
          <div class="track-meta"><?= implode(' &nbsp;·&nbsp; ', $metaParts) ?></div>
        <?php endif; ?>
      </div>

      <div class="controls">
        <button class="play-btn" id="play-btn" aria-label="Play / Pause">
          <svg class="play-icon" id="play-icon" viewBox="0 0 24 24" fill="#1a1000" width="14" height="14"><path d="M8 5v14l11-7z"/></svg>
          <svg class="pause-icon" id="pause-icon" viewBox="0 0 24 24" fill="#1a1000" width="14" height="14" style="display:none"><path d="M4 3h5v18H4zm11 0h5v18h-5z"/></svg>
        </button>
        <span class="time-display" id="time-display">0:00</span>
        <a class="listen-link" href="<?= $playerUrl ?>" target="_blank" rel="noopener noreferrer">listen on memeshift.com »</a>
      </div>

    </div>
  </div>
</div>

<audio id="audio" src="<?= htmlspecialchars($audioUrl, ENT_QUOTES, 'UTF-8') ?>" preload="none"></audio>

<script>
(function() {
  'use strict';
  var audio   = document.getElementById('audio');
  var btn     = document.getElementById('play-btn');
  var playIco = document.getElementById('play-icon');
  var pauseIco= document.getElementById('pause-icon');
  var timeDsp = document.getElementById('time-display');

  function pad(n) { return String(n).padStart(2, '0'); }
  function fmt(s) {
    s = Math.floor(s || 0);
    return Math.floor(s / 60) + ':' + pad(s % 60);
  }

  btn.addEventListener('click', function() {
    if (audio.paused) {
      audio.play().catch(function() {});
    } else {
      audio.pause();
    }
  });

  audio.addEventListener('play', function() {
    playIco.style.display  = 'none';
    pauseIco.style.display = 'block';
  });

  audio.addEventListener('pause', function() {
    playIco.style.display  = 'block';
    pauseIco.style.display = 'none';
  });

  audio.addEventListener('timeupdate', function() {
    if (!audio.duration || isNaN(audio.duration)) return;
    timeDsp.textContent = fmt(audio.currentTime) + ' / ' + fmt(audio.duration);
  });

  audio.addEventListener('ended', function() {
    audio.currentTime = 0;
    playIco.style.display  = 'block';
    pauseIco.style.display = 'none';
    timeDsp.textContent    = '0:00';
  });
})();
</script>
</body>
</html>
