# MPlayer

A PHP-based, self-hosted music player for artists and musicians. No database required: MP3s live in a folder on the server and the app scans their ID3 tags for artist, title, album art, and links. Works on mobile and desktop wherever you host it.

Visitors browse and play a directory of MP3s in the browser — no accounts, no database. A single admin account can log in and upload/edit tracks, set the site name and icon, and recolour the player, all through the browser (see [Admin login & uploads](#admin-login--uploads)) — everyone else just gets the read-only player.

A fresh install ships blank: no tracks, no artwork, no social links, no branding. Everything visitor-facing is set from the admin pages after you log in.

> **Live example**: [music.memeshift.com](https://music.memeshift.com)

> **Current build: v1.46** (see [CHANGELOG.md](CHANGELOG.md))

---

## What it does

- Scans a folder of MP3 files and reads their ID3 tags (title, artist, album, year, track, notes, album art, buy links, info links)
- Streams audio in the browser with an HTML5 `<audio>` element
- Displays a real-time spectrum visualiser (Web Audio API); on iOS the visualiser stays idle because routing audio through `AudioContext` would stop background playback (see changelog v1.45)
- Shows a scrollable, sortable playlist with a full track info panel
- Two visual themes: a Winamp-inspired dark skin and a warmer "MPlayer" skin (yellow/teal), recolourable from the admin Customize panel
- **Deep links**: open the player with `?t=` plus a URL-encoded filename (e.g. `/?t=mytrack.mp3`) to start on a specific track after the scan loads
- **Share / embed**: from the UI, copy a direct link or an `<iframe>` snippet that loads `embed.php` (single-track mini player for external sites)
- **Media Session API** (where supported): lock screen / notification metadata and transport actions on mobile and desktop browsers
- Desktop and mobile layouts — on viewports **600px wide and under**, transport controls use a fixed dock at the bottom; hardware volume is used on mobile (no on-screen volume slider there)
- **Admin login & browser upload**: a single admin account can log in at `/login.php` and publish MP3s at `/upload.php` — pick a file, review tags prefilled from its existing ID3 data, edit, publish. No FTP needed for day-to-day track uploads (see [Admin login & uploads](#admin-login--uploads))
- **Site settings**: name, browser-tab icon and social links at `/settings.php`; colours and background image from the Customize panel on the player itself

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
├── config.example.php ← Copy to config.php on install; the only file you edit by hand
├── auth.php           ← Session/CSRF/login-lockout helpers + site-settings read/write
├── admin-style.php    ← Shared CSS for the admin pages (login/reset/upload)
├── setup.php          ← One-time admin-account creation; delete after first run
├── settings.php       ← Site name, icon and social links (auth-gated)
├── settings_save.php  ← Saves settings.php and the Customize panel
├── site-config.php    ← Serves the public settings as JSON for index.html
├── background_upload.php ← Validates and stores uploaded icons and backgrounds
├── favicon.php        ← Serves the uploaded site icon, or the bundled default
├── manifest.php       ← PWA manifest, named from the site name
├── library.php        ← Track library: reorder, rename, delete (auth-gated)
├── library_actions.php← JSON endpoint behind library.php
├── stats.php          ← Library totals for the admin pages
├── customize_check.php← Tells index.html whether the visitor is logged in
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
├── favicon.svg      ← Bundled default icon, replaced per-site from Settings
├── images/          ← Created on demand for uploaded icons and backgrounds
│   ├── icons/
│   └── backgrounds/
├── music/           ← Uploaded/placed MP3 files live here
│   └── .htaccess    ← Disables directory listing and code execution
├── CHANGELOG.md     ← Full version history
├── LICENSE          ← GNU GPLv3
└── README.md
```

Three files live **outside** the web root, one directory above the app, and none of them are
part of this repo: `.mplayer-admin-<random>.json` (the admin account, written once by
`setup.php`), `.mplayer-site-settings-<random>.json` (site name, icon, socials, colours) and
`.mplayer-track-order-<random>.json` (the admin-set play order). `config.php` is per-install
and also not in the repo — see [Deployment](#deployment--setting-up-a-site-for-someone).

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

# config.php is per-install and not in the repo — start from the example
cp config.example.php config.php

# Drop your MP3s into the music/ folder, then start the PHP dev server
php -S localhost:8080

# Open in your browser
open http://localhost:8080
```

To use the admin pages locally, set `SETUP_CODE` in `config.php` to any phrase and visit
`http://localhost:8080/setup.php`.

---

## Deployment — setting up a site for someone

This is the whole path from a checkout to a working site that you can hand to someone
else. Steps 1–6 are yours; step 7 is theirs.

### 1. Build the zip

From the repo:

```bash
git archive -o mplayer.zip HEAD
```

`git archive` emits only files tracked by git, so the zip can never contain your own music,
settings, credentials or uploaded artwork — those are all gitignored or marked
`export-ignore` in `.gitattributes`. Sanity-check it:

```bash
unzip -l mplayer.zip | grep -cE 'mplayer-admin|\.mp3'   # expect 0
```

Note that `config.php` is deliberately **not** in the zip — only `config.example.php`.
That is what stops an update from ever overwriting a live site's settings.

### 2. Create the subdomain and website

In your host's control panel, create the subdomain and its website. Note two paths:

- the **document root**, e.g. `/home/<user>/domains/<subdomain>/public_html`
- the directory **one level above it**, e.g. `/home/<user>/domains/<subdomain>/`

The second one matters: the admin credentials and the site settings are written there, not
inside the web root, so they can never be served over HTTP even if `.htaccess` fails.

### 3. Upload and extract

Either the control panel's File Manager (upload `mplayer.zip` into `public_html` and use
"Extract"), or over SSH:

```bash
scp mplayer.zip you@yourhost:domains/<subdomain>/public_html/
ssh you@yourhost
cd domains/<subdomain>/public_html && unzip mplayer.zip && rm mplayer.zip
```

Either way, `index.html` must end up directly inside `public_html`, not in a nested
`mplayer/` folder.

### 4. Create `config.php`

Copy the example and edit two lines:

```bash
cp config.example.php config.php
```

```php
// Before
define('APP_BASE_URL', 'https://example.com');
define('SETUP_CODE', '');

// After
define('APP_BASE_URL', 'https://music.theirdomain.com');   // no trailing slash
define('SETUP_CODE', 'purple-kettle-19');                   // any phrase you invent
```

`APP_BASE_URL` is the site's canonical address. It is used for password-reset links and
for the absolute URLs in `embed.php`, and is deliberately never taken from the request's
`Host` header — that header is attacker-controlled, and trusting it would let someone
poison the reset link emailed to the site owner.

`SETUP_CODE` enables `setup.php` (step 6) and is what stops a passer-by claiming the admin
account before its owner does. Set it **before** uploading, not after.

`MUSIC_DIR` needs no change in a normal install — it defaults to the `music/` folder next
to `index.html`. Only set an absolute path if you move that folder:

```php
define('MUSIC_DIR', '/home/youraccount/domains/yoursite/public_html/music/');
```

### 5. Check permissions and that it's up

PHP must be able to write to the directory *above* `public_html` — that is where the
credentials and settings files are created. If it can't, `setup.php` will say so explicitly
rather than failing silently.

```bash
curl -sI https://their-domain.com/            | head -1   # expect HTTP/… 200
curl -sI https://their-domain.com/config.php  | head -1   # expect 403 — NOT 200
```

A non-200 on the first usually means the files landed in a nested folder, or the subdomain's
DNS hasn't propagated yet. **A 200 on the second is a real problem**: your host isn't
honouring `.htaccess`, and `config.php` is readable by anyone. See the nginx note below.

### 6. Send it over

Send three things: the site URL, the setup link `https://their-domain.com/setup.php`, and
the setup code you chose. You never send a password — they choose their own.

### 7. What they do

They open `setup.php`, enter the setup code, and pick a username, email and password (12+
characters). That writes the admin account and the page disables itself permanently.

### 8. Lock it down

Once they confirm they're in, `setup.php` already refuses to run — it 403s as soon as the
credentials file exists. Belt and braces: delete `setup.php` from the server and set
`SETUP_CODE` back to `''`.

### Updating an existing install later

Extract a newer zip over the top. `config.php` isn't in the zip, `music/` contents and
uploaded images are gitignored, and the credentials and settings live outside the web root
— so an update overwrites application code only.

Because `config.php` is never overwritten, an older one can be missing constants a newer
release added. Diff it against `config.example.php` after updating and copy across anything
new — `MP_DEFAULT_SITE_NAME` and `SETUP_CODE` were both added this way:

```bash
diff <(grep -o "define('[A-Z_]*'" config.php | sort) \
     <(grep -o "define('[A-Z_]*'" config.example.php | sort)
```

### Host compatibility

Tested on SiteGround, Bluehost, DreamHost, and Hostinger shared hosting.

**The bundled `.htaccess` only works on Apache and LiteSpeed.** On **nginx** it is ignored
completely, which means `config.php` and anything else it protects would be served to
anyone who asks. If you deploy to nginx you must translate those rules into the server
config yourself — the `curl -I` check in step 5 is what tells you.

**Apache note:** `.htaccess` denies direct access to several extensions including `.md`,
`.json` and `.ini`, so `README.md` and `CHANGELOG.md` are not downloadable from a live
site even though they're in the repo. That is intentional.

---

## Configuration

`config.php` is the only file you edit by hand. It is not in the repo or the zip — copy
`config.example.php` to `config.php` on first install, so later updates never overwrite it.

```php
// Canonical site URL — no trailing slash. Reset links and embed URLs.
define('APP_BASE_URL', 'https://example.com');

// Name shown until someone sets a site name in Settings
define('MP_DEFAULT_SITE_NAME', 'MPlayer');

// Enables setup.php. '' disables it. Clear it again after setup.
define('SETUP_CODE', '');

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

### Everything else is set in the app

Site name, browser-tab icon and social links are edited at `/settings.php`. Player colours
and the background image come from the **Customize** panel on the player itself when you're
logged in. None of it requires touching code, and all of it is stored outside the web root
in the site-settings JSON file.

Share and embed links are built from the browser's own origin, so they point at whatever
domain the app is served from with no configuration.

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

**No database, filesystem only.** There is exactly one admin account per install, stored as
a JSON file with a bcrypt/Argon2 password hash, kept outside the web root. There is no
signup flow and no ongoing account management — the account is created once by `setup.php`,
which then permanently refuses to run.

### One-time setup (required before login works at all)

A fresh install has no admin account. `setup.php` creates it, once.

1. Set `SETUP_CODE` in `config.php` to any phrase, and `APP_BASE_URL` to the site's real
   URL. Do this **before** the site is reachable, not after.
2. Open `/setup.php` and fill in the setup code, a username, an email for password resets,
   and a password of at least 12 characters.
3. That writes the credentials file to the path `config.php`'s `CREDENTIALS_FILE` constant
   points at — by default one directory *above* the app's own folder (e.g. above
   `public_html`), so it is never web-servable even if `.htaccess` doesn't apply. Confirm
   where your host's document root actually sits before relying on this.
4. Delete `setup.php` and set `SETUP_CODE` back to `''`.
5. Log in at `/login.php`.

**Three gates protect `setup.php`**, because it is the one endpoint that can create an
account:

- it returns 403 if the credentials file already exists, so it is dead the moment setup
  finishes, and on any install that already has an account;
- it returns 403 if `SETUP_CODE` is `''`;
- wrong setup codes are rate-limited with the same exponential backoff as the login form,
  so the code can't be brute-forced.

The remaining exposure is the window between the site going live and setup being completed:
anyone who reaches `setup.php` *with the correct code* in that window claims the account.
That is exactly what the setup code exists to prevent, so treat it like a password — send
it over the same channel you'd send one, and clear it afterwards.

**If setup can't write the credentials file** it tells you which directory it tried. That
directory — one level above the web root — must be writable by PHP. This is the single most
common first-install problem.

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

Toggle using the skin button in the transport area (label switches between **◈ WINAMP** and **◈ MPLAYER** depending on the active theme).

- **Winamp** — dark grey chrome, green LED display, Silkscreen + VT323 pixel fonts
- **MPlayer** — warm yellow/teal palette, Lora + DM Mono fonts

Theme choice is saved in `localStorage` (`msp-theme`). The first visit defaults to
**MPlayer** unless a saved choice exists. Both themes' colours can be overridden per-site
from the Customize panel.

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
- `setup.php` is gated three ways (credentials file must not exist, `SETUP_CODE` must be set, attempts are rate-limited) and should be deleted after first run
- Admin login/upload pages are auth-gated with CSRF protection, login/reset lockout, session invalidation on password change, and content-validated uploads — see [Admin login & uploads](#admin-login--uploads) for the full list
- **`.htaccess` is Apache/LiteSpeed only.** On nginx none of these file-access rules apply and you must reproduce them in the server config — verify with `curl -I https://your-site/config.php` and expect a non-200

---

## Site name, icon and background

All set from the admin pages — no code editing.

| What | Where |
| --- | --- |
| Site name (title bar, browser tab, reset emails) | `/settings.php` |
| Browser-tab icon | `/settings.php` — upload any square PNG/JPG/GIF/WEBP |
| Social links | `/settings.php` — a blank field hides that icon |
| Player colours | Customize panel on the player, when logged in |
| Background image | Customize panel — upload an image, set tiling and alignment |

A fresh install has all of these blank: no name (it shows `MPlayer`), a plain default icon,
no social icons at all, and no background image.

Uploaded icons and backgrounds are stored under `images/icons/` and `images/backgrounds/`
with random filenames, and are validated by magic bytes rather than file extension. The
icon is served through `favicon.php`, which falls back to the bundled default mark when
none has been uploaded.

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

Written by [Morgan Sully / Memeshift](https://www.memeshift.com).

The app ships unbranded — no logo, no background image, no social links. Anything you add to
your own install (icon, artwork, music) stays yours, and nothing from the author's own
deployment at [music.memeshift.com](https://music.memeshift.com) is included here.

---

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE).