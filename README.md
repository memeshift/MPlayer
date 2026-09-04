# MPlayer

**.+Memeshift+. Player** — a PHP-based, self-hosted music player built by [Morgan Sully / Memeshift](https://www.memeshift.com) for artists and musicians. No database required: MP3s live in a folder on the server and the app scans their ID3 tags for artist, title, album art, and links. Works on mobile and desktop wherever you host it.

Visitors browse and play a directory of MP3s in the browser — no accounts, no database. A single admin account can log in and upload/edit tracks through the browser (see [Admin login & uploads](#admin-login--uploads) below) — everyone else still just gets the read-only player.

> **Current demo (my music!)**: [Memeshift Player](https://music.memeshift.com)

> **Current build: v1.46** (see [CHANGELOG.md](CHANGELOG.md))

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
- **Admin login & browser upload**: a single admin account can log in at `/login.php` and publish MP3s at `/upload.php` — pick a file, review tags prefilled from its existing ID3 data, edit, publish. No FTP needed for day-to-day track uploads (see [Admin login & uploads](#admin-login--uploads))

---

## Screenshots

*Coming soon*

---

## File structure

```
MPlayer/
├── index.html         ← Entire main-player frontend (HTML + CSS + JS, single file)
├── scan.php           ← Scans music/, reads ID3 tags, returns JSON for the playlist
├── art.php            ← Extracts and serves embedded album art from MP3s
├── embed.php          ← Single-track mini player for <iframe> embeds (?t=filename.mp3)
├── id3.php            ← Shared ID3 parser (required by embed.php; same logic as in scan.php)
├── id3_write.php      ← ID3 tag WRITER, used by upload_commit.php to save edited tags
├── config.php         ← The only file you must edit for paths
├── auth.php           ← Session/CSRF/login-lockout helpers for the admin pages
├── admin-style.php    ← Shared CSS for the admin pages (login/reset/upload)
├── login.php          ← Admin login
├── logout.php         ← Admin logout
├── forgot-password.php← Request a password-reset email
├── reset-password.php ← Set a new password from a reset link
├── upload.php         ← Admin upload/edit-tags page (auth-gated)
├── upload_inspect.php ← Stages an uploaded MP3, reads its tags for prefill
├── upload_commit.php  ← Writes edited tags, moves the file into music/
├── staging/           ← Holds in-progress uploads before they're published
│   └── .htaccess      ← Blocks all direct access
├── .user.ini          ← Raises PHP's upload size/time limits for upload.php
├── .htaccess          ← Security headers; blocks config.php, credentials file, sensitive extensions; CORS for MP3 embeds
├── favicons/        ← Browser + PWA icon assets
│   ├── favicon.svg
│   ├── favicon.ico
│   ├── favicon-96x96.png
│   ├── favicon-32x32.png
│   ├── favicon-16x16.png
│   ├── apple-touch-icon.png
│   ├── web-app-manifest-192x192.png
│   └── web-app-manifest-512x512.png
├── music/           ← Uploaded/placed MP3 files live here
│   └── .htaccess    ← Disables directory listing and PHP execution
├── CHANGELOG.md     ← Full version history
├── LICENSE          ← GNU GPLv3
├── site.webmanifest ← Optional PWA manifest (customise paths/names for your host)
└── README.md
```

The admin credentials file itself (`.mplayer-admin-<random>.json`) is **not** part of this repo and isn't created by any script — see [Admin login & uploads](#admin-login--uploads) for how to generate it.

`**index.html` is fully self-contained** for the main UI: all CSS and JavaScript for the full player live inside it — no build step, no npm, no bundler. PHP endpoints (`scan.php`, `art.php`, `embed.php`) handle the server side.

---

## Requirements

- PHP 8.1 or later (needed for the admin login/upload pages; the read-only player alone works on PHP 7.4+)
- Web server with PHP support, ideally Apache-compatible `.htaccess` handling (Apache, LiteSpeed, or the built-in PHP dev server — see the note under Admin login & uploads about `.htaccess` and `php -S`)
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

The app has been tested on SiteGround, Bluehost, DreamHost, and Hostinger shared hosting.

**Apache note:** the bundled `.htaccess` denies direct access to several extensions including `.md`, `.json`, and `.ini`, so `README.md` / `CHANGELOG.md` may not be downloadable from the live site even though they are in the repo. That is intentional for security.

If you want the admin login/upload feature, one more step is required before it works — see [Admin login & uploads](#admin-login--uploads).

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

Tag parsing is implemented in PHP with no external libraries. `**scan.php`** contains the parser used to build the playlist JSON. `**id3.php`** holds the same `parseID3()` API for `**embed.php`**, which must not `require` `scan.php` (that file emits JSON when loaded).


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

Any standard ID3 tagger works. [Mp3tag](https://www.mp3tag.de/) (Windows/Mac) is recommended. To add a buy link or info link, write to the WXXX or WOAF fields respectively. Or use the built-in upload page (below), which writes these same fields directly.

---

## Admin login & uploads

A single admin account can log in and publish tracks through the browser instead of FTP — pick an MP3, review a form prefilled from its existing ID3 tags (title, artist, album, year, track, comment, buy/info links, cover art), edit anything, and publish. It writes the edited fields straight into the MP3's own ID3 tags, so `scan.php`/`art.php`/`embed.php`/`index.html` need no changes and no separate database — the file itself stays the single source of truth, same as every other track in `music/`.

**No database, filesystem only.** There is exactly one admin account, stored as a JSON file with a bcrypt/Argon2 password hash — there's no signup flow and no web endpoint that creates this file, on purpose, so there's never a live "create admin" route to secure.

### One-time setup (required before login works at all)

1. Decide on a username and password (12+ characters) and an email address for password-reset links.
2. Generate the password hash locally or over SSH — this never sends anything anywhere, it just prints a hash to your terminal:
   ```bash
   php -r "var_dump(defined('PASSWORD_ARGON2ID'));"   # check which algorithm your PHP build supports
   php -r "echo password_hash('your-password', PASSWORD_ARGON2ID), PHP_EOL;"   # or PASSWORD_DEFAULT if the above was false
   ```
3. Create the credentials file by hand with that hash:
   ```json
   {
     "username": "your-username",
     "password_hash": "<paste the hash from step 2>",
     "email": "you@example.com",
     "session_version": 0,
     "created_at": "2026-09-04T00:00:00+00:00"
   }
   ```
4. Upload it via SFTP/File Manager to the path `config.php`'s `CREDENTIALS_FILE` constant points at — by default one directory *above* this app's own folder (e.g. above `public_html` on Hostinger), so it's never web-servable even if `.htaccess` somehow doesn't apply. Confirm where your host's document root actually sits before relying on this path.
5. Update `APP_BASE_URL` in `config.php` if you're not deploying to `music.memeshift.com` — password-reset emails link to this exact URL, deliberately never derived from the request's `Host` header (that header is attacker-controlled and could otherwise be used to poison the reset link sent to your inbox).
6. Visit `/login.php`.

### Password reset

`/login.php` links to `/forgot-password.php`. It emails a single-use link (valid 30 minutes) to the address in the credentials file — never to an address typed into the form — and responds identically whether or not the identifier matched, so it can't be used to check whether an account exists. Completing a reset invalidates every other logged-in session immediately.

**Email deliverability isn't guaranteed out of the box** — reset emails go through PHP's `mail()`, which depends on this domain already having working SPF/DKIM (it likely does, since WordPress mail already flows through it, but send yourself a real test reset and check spam before relying on it).

### Security measures built in

Argon2id/bcrypt password hashing (self-upgrading), CSRF tokens on every form, exponential-backoff lockout on repeated login/reset attempts, session-fixation protection (`session_regenerate_id()` on every login and password change), real content validation on uploads (magic bytes / MPEG frame sync, not just file extension), path-traversal guards matching the pattern already used by `scan.php`/`art.php`/`embed.php`, and an append-only audit log of login/reset events (IP + timestamp only, no secrets). Full reasoning is in the project's plan history — ask if you want the complete threat-model writeup.

### Local testing note

PHP's built-in dev server (`php -S`) **ignores `.htaccess` entirely** — so `config.php`, the credentials file, and `staging/` will appear world-readable in local testing even though they're blocked in production. Don't take a clean `php -S` test as proof these are protected; verify with `curl -I` against the real deployed URLs instead (see Deployment).

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
- Admin login/upload pages are auth-gated with CSRF protection, login/reset lockout, session invalidation on password change, and content-validated uploads — see [Admin login & uploads](#admin-login--uploads) for the full list

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