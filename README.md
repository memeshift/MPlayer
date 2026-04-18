# MPlayer

**.+Memeshift+. Player** — a PHP-based, self-hosted music player built by [Morgan Sully / Memeshift](https://www.memeshift.com) for artists and musicians. No database required: upload MP3s to a folder on the server and the app scans ID3 tags for artist, title, album art, and links. Works on mobile and desktop wherever you host it.

Visitors browse and play a directory of MP3s in the browser — no accounts, no in-app uploads, and no database.

**Current demo (my music!)**: [Memeshift Player](https://music.memeshift.com)

**Current build: v1.46** (see [CHANGELOG.md](CHANGELOG.md))

---

## What it does

- Scans a folder of MP3 files and reads their ID3 tags (title, artist, album, year, track, notes, album art, buy links, info links)
- Streams audio in the browser with an HTML5 `<audio>` element
- Displays a real-time spectrum visualiser (Web Audio API); on iOS the visualiser stays idle because routing audio through `AudioContext` would stop background playback (see changelog v1.45)
- Shows a scrollable, sortable playlist with a full track info panel
- Two visual themes: a Winamp-inspired dark skin and the Memeshift brand skin (yellow/teal)
- **Deep links**: open the player with `?t=` plus a URL-encoded filename (e.g. `/?t=mytrack.mp3`) to start on a specific track after the scan loads
- **Share / embed**: from the UI, copy a direct link or an `<iframe>` snippet that loads `embed.php` (single-track mini player for external sites)
- **Media Session API** (where supported): lock screen / notification metadata and transport actions on mobile and desktop browsers
- Desktop and mobile layouts — on viewports **600px wide and under**, transport controls use a fixed dock at the bottom; hardware volume is used on mobile (no on-screen volume slider there)

---

## Screenshots

*Coming soon*

---

## File structure

```
MPlayer/
├── index.html       ← Entire main-player frontend (HTML + CSS + JS, single file)
├── scan.php         ← Scans music/, reads ID3 tags, returns JSON for the playlist
├── art.php          ← Extracts and serves embedded album art from MP3s
├── embed.php        ← Single-track mini player for <iframe> embeds (?t=filename.mp3)
├── id3.php          ← Shared ID3 parser (required by embed.php; same logic as in scan.php)
├── config.php       ← The only file you must edit for paths
├── .htaccess        ← Security headers, blocks direct access to config.php; CORS for MP3 embeds
├── favicons/        ← Browser + PWA icon assets
│   ├── favicon.svg
│   ├── favicon.ico
│   ├── favicon-96x96.png
│   ├── favicon-32x32.png
│   ├── favicon-16x16.png
│   ├── apple-touch-icon.png
│   ├── web-app-manifest-192x192.png
│   └── web-app-manifest-512x512.png
├── music/           ← Put your MP3 files here
│   └── .htaccess    ← Disables directory listing and PHP execution
├── CHANGELOG.md     ← Full version history
├── LICENSE          ← GNU GPLv3
├── site.webmanifest ← Optional PWA manifest (customise paths/names for your host)
└── README.md
```

`**index.html` is fully self-contained** for the main UI: all CSS and JavaScript for the full player live inside it — no build step, no npm, no bundler. PHP endpoints (`scan.php`, `art.php`, `embed.php`) handle the server side.

---

## Requirements

- PHP 7.4 or later (shared hosting, VPS, or local)
- Web server with PHP support (Apache, Nginx, or the built-in PHP dev server)
- MP3 files with ID3 tags (v2.2, v2.3, or v2.4 — ID3v1 fallback supported)
- No database, no Composer, no external PHP libraries

---

## Quick start (local)

```bash
git clone https://github.com/memeshift/MPlayer.git
cd MPlayer

# Drop your MP3s into the music/ folder, then start the PHP dev server
php -S localhost:8080

# Open in your browser
open http://localhost:8080
```

That's it.

---

## Deployment (shared hosting)

1. Upload all files to your web root or a subdirectory (e.g. `public_html/player/`)
2. Upload your MP3 files into the `music/` folder
3. Open `config.php` and set `MUSIC_DIR` to the **absolute path** of your `music/` folder:

```php
define('MUSIC_DIR', '/home/youraccount/public_html/player/music/');
```

1. Visit the URL in your browser

The app has been tested on SiteGround, Bluehost, and DreamHost shared hosting.

**Apache note:** the bundled `.htaccess` denies direct access to several extensions including `.md`, so `README.md` / `CHANGELOG.md` may not be downloadable from the live site even though they are in the repo. That is intentional for security.

---

## Configuration

`config.php` is the only file you need to edit for **paths and caching**.

```php
// Path to your music folder (trailing slash required)
define('MUSIC_DIR', __DIR__ . '/music/');

// URL path used for audio streaming (relative to index.html)
define('MUSIC_URL', 'music/');

// Allowed file extensions
define('ALLOWED_EXT', ['mp3']);

// Browser cache durations (seconds)
define('SCAN_CACHE_TTL',  300);    // track listing — 5 minutes
define('ART_CACHE_TTL',   86400);  // album art — 24 hours
```

### Share and embed URLs (forks)

In `index.html`, the constants used for the **Share / embed** modal (`PLAYER_BASE`, and the generated `embed.php` / `?t=` links) default to the Memeshift deployment. If you self-host elsewhere, search for `PLAYER_BASE` in `index.html` and set it to your own origin (scheme + host + optional path prefix) so copied links and iframe `src` values point at your server.

---

## ID3 tag support

Tag parsing is implemented in PHP with no external libraries. `**scan.php`** contains the parser used to build the playlist JSON. `**id3.php**` holds the same `parseID3()` API for `**embed.php**`, which must not `require` `scan.php` (that file emits JSON when loaded).


| Tag              | Field           | Notes                         |
| ---------------- | --------------- | ----------------------------- |
| TIT2 / TT2       | Title           |                               |
| TPE1 / TP1       | Artist          |                               |
| TALB / TAL       | Album           |                               |
| TYER / TDRC      | Year            |                               |
| TRCK / TRK       | Track number    |                               |
| COMM             | Comment / Notes | Shown in the Track Info panel |
| APIC / PIC       | Album art       | Served via `art.php`          |
| WXXX             | Buy/support URL | Shows a `»buy/support` button |
| WOAF / TXXX:WOAF | Info URL        | Shows a `»more info` button   |


UTF-16 and UTF-8 encoded tags are both handled correctly. ID3v1 is used as a fallback if no ID3v2 tags are found.

### Tagging your files

Any standard ID3 tagger works. [Mp3tag](https://www.mp3tag.de/) (Windows/Mac) is recommended. To add a buy link or info link, write to the WXXX or WOAF fields respectively.

---

## Features in detail

### Two themes

Toggle using the skin button in the transport area (label switches between **◈ WINAMP** and **◈ MSHIFT** depending on the active theme).

- **Winamp** — dark grey chrome, green LED display, Silkscreen + VT323 pixel fonts
- **Memeshift** — yellow/teal brand colours, Lora + DM Mono fonts, tiled textile background

Theme choice is saved in `localStorage` (`msp-theme`). The first visit defaults to **Memeshift** unless a saved choice exists.

### Playlist

- Default order is **alphabetical by filename** (from `scan.php`)
- Sortable by Artist, Album, or Year — click again to reverse
- Sort badges on each row show the active sort value
- The current track is highlighted and scrolled into view; playlist rows support keyboard activation (Enter / Space) when focused

### Track info panel

Shows artist, album, year, a download link, and — when present in the ID3 tags — a buy/support link and a more info link. Album art appears as a thumbnail. The comment/notes field is shown in full below.

### Spectrum visualiser

Up to **22** frequency bars (capped from the analyser buffer) using the Web Audio API. Bars go green → yellow → red as intensity increases. When nothing is playing, an idle “ghost” state is drawn.

### Mobile layout

Below **600px** width, transport moves to a fixed bottom dock. The play control is a large circular button. Volume uses the device hardware where applicable.

### Particle effects

Hovering the `»buy/support` or `»more info` links, or the theme toggle, triggers a sparkle animation. Switching themes fires a short particle burst over the social icons area. Colours follow the active theme.

---

## Security

- No user input drives filesystem reads on `scan.php` — the music directory is scanned server-side only
- Paths are validated with `realpath()` before reading
- Only extensions in `ALLOWED_EXT` are accepted
- `config.php` is blocked from direct browser access via `.htaccess`
- The `music/` folder has its own `.htaccess` that disables directory listing and PHP execution
- Album art is validated by magic bytes before serving (`art.php`)
- `embed.php` only accepts a basename for `?t=` and checks it lies inside `MUSIC_DIR`

---

## Background image

To set a custom background, change the `--bg-image` CSS variable near the top of `index.html`:

```css
--bg-image: url('backgrounds/my-photo.jpg');
```

Leave it as `none` for a solid dark background in the Winamp theme variables. The Memeshift theme sets a tiled textile image from [memeshift.com](https://www.memeshift.com) by default.

---

## Keyboard shortcuts


| Key     | Action                         |
| ------- | ------------------------------ |
| `Space` | Play / Pause                   |
| `→`     | Next track                     |
| `←`     | Previous track                 |
| `S`     | Toggle shuffle                 |
| `R`     | Cycle repeat (off → all → one) |


---

## Changelog

The full version history is in **[CHANGELOG.md](CHANGELOG.md)** (v1.0 through the current build). Older copies of this project also carried a long comment block at the top of `index.html`; that block has been removed in favour of the standalone changelog file.

---

## Credits

Built for [Morgan Sully / Memeshift](https://www.memeshift.com). Brand colours, fonts, and the textile background image are part of the Memeshift visual identity; reuse those assets only in line with your own rights or permission from the rights holder.

---

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE).