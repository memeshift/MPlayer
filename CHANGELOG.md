# .+Memeshift+. Player — CHANGELOG

**Source of truth: index.html (current build)**
**Last reconciled: May 2026**

Entries are drawn from three sources, ranked by reliability:
1. **index.html inline block** (v1.0–v1.43) — authoritative; written at time of change
2. **Current index.html code** — confirms what is actually implemented
3. **Standalone CHANGELOG.md** — supplementary; some entries diverge from code

Gaps and conflicts are documented explicitly rather than papered over.

---

## v1.47 — PENDING (not yet implemented)

WCAG-compliant responsive font scaling and touch target sizing.
Described in previous CHANGELOG.md but `clamp()` is absent from current
index.html. Zero instances found in code audit. Do not treat as shipped.

**Planned changes (for reference when implementing):**
- `clamp()` responsive font sizing across all UI elements
- Touch targets enlarged to WCAG 2.1 Level AAA (44×44px minimum)
- Media queries for ≤480px and ≤360px breakpoints
- iOS form input `font-size: 16px` to prevent auto-zoom on focus
- `html { font-size: clamp(...) }` responsive base

---

## v1.46

**Accessibility (5 fixes):**

1. **`:focus-visible`** — keyboard focus indicators on all interactive elements.
2. **`aria-label`** on all symbol-only transport buttons; **`aria-pressed`** kept in sync with play, shuffle, and repeat state.
3. **`#sr-status`** — visually-hidden `aria-live="polite"` region announces Now playing / Paused / Stopped to screen readers.
4. **Contrast** — `--pl-num`, `--pl-dur`, `--info-key`, `--sort-text` raised to ≥4.5:1 in both themes. Specific values:
   - Memeshift: `--pl-num` `#554e30` → `#7a7248`; `--pl-dur` → `#8a8258`; `--sort-text` `#666040` → `#9a9060`
   - Winamp: `--pl-num` / `--pl-dur` `#0a6b0a` → `#2a8c2a`; `--info-key` `#0a6b0a` → `#2a8c2a`
5. **Playlist keyboard operability** — items get `tabindex="0"`, Enter/Space activates; `aria-selected` synced with `markCurrent()`.

---

## v1.45

Fixed iOS background audio interruption.

**Root cause:** routing `<audio>` through `AudioContext` via `createMediaElementSource()` causes iOS to cut audio when the page backgrounds, because iOS suspends the `AudioContext` and the `<audio>` element with it.

**Fix:** detect iOS via `userAgent` / `maxTouchPoints` and skip `AudioContext` routing entirely on those devices. Visualiser shows idle bars on iOS (acceptable tradeoff). All other browsers unaffected.

---

## v1.44

Background audio and lock screen controls.

- Added `playsinline` and `x-webkit-airplay="allow"` to `<audio>` element.
- Implemented Media Session API via `updateMediaSession(t)`: sets track metadata (title, artist, album, artwork) so iOS lock screen and Android media notification show the now-playing track.
- Action handlers registered: play, pause, nexttrack, previoustrack, seekbackward (−10s), seekforward (+10s).

---

## [undocumented — present in current build, version unknown]

**Favicons and web app manifest.** SVG, PNG (96×96, 32×32, 16×16), ICO,
and Apple touch icon (180×180) added to `<head>`. `site.webmanifest`
linked. Not present in the v1.43 build; appeared alongside v1.44–v1.46
changes. No version number assigned in any source.

**Desktop volume slider fix (mislabeled v1.37 in code comment).**
Two root causes resolved:
- CSS selector mismatch: `#volume` rule did not match `id="volume-desktop"`, leaving the slider unsized under `appearance:none` on some browsers. Fixed to `#volume-desktop`.
- `audio.volume` has no effect once `<audio>` is routed through `createMediaElementSource()`. Volume now controlled via a `GainNode` inserted into the Web Audio chain (`src → gainNode → analyser → destination`).
- Added `change` event listener alongside `input` for cross-browser coverage; volume stored in `S.volume` so it is applied immediately when `setupAudio()` runs.

*Note: the inline code comment labels this v1.37, but v1.37 is already
documented (sticky panel, below). The fix is not present in the v1.43
build, placing it in the v1.44–v1.46 window. Version number in the
comment is an error.*

---

## v1.43

Share col refinements.

- **Desktop:** two separate links (»share, »embed) collapsed into one »Share / Embed link that opens the modal directly. `info-share-key` gets `white-space:nowrap` so the label never wraps.
- **Mobile:** icon button hidden via base CSS (`display:none`), only revealed inside ≤600px media query — guarantees it never appears on desktop. Icon centred horizontally below the label via `align-items:center` on the share col.

---

## v1.42

Mobile share col: SHARE THIS label restored above the icon. Only the
»share / »embed text links are hidden on mobile. Share col alignment
changed to `flex-start` so label + icon stack top-to-bottom flush left.

---

## v1.41

Mobile share UX. On ≤600px the »share / »embed text links are hidden and
replaced by a single upward-arrow share icon (28px, 44×44px tap target).
Tapping calls `navigator.share()` — the native iOS/Android share sheet —
with title, text, and `?t=` deep link. Falls back to clipboard copy on
browsers without Web Share API support. Desktop text links unchanged.

---

## v1.40

Share col sizing. `.info-meta-cols` switched from flex to `grid(2fr 1fr)`
so the share col always occupies exactly one third of the metadata area.
`.info-share-links` items given `min-height: 36px` + `padding: 6px 0` for
comfortable tap targets.

---

## v1.39

Share / Embed feature.

**Track Info panel:** `info-body` restructured into two side-by-side
columns (`.info-meta-col` left, `.info-share-col` right) separated by a
subtle vertical divider. SHARE THIS key + share/embed links added to right
col, flush-left matching ARTIST/ALBUM padding.

**JS:** `shareTrack()` copies `?t=` deep link to clipboard with a
"copied!" flash; `openEmbedModal()` shows a keyboard-dismissible overlay
with a direct link and iframe snippet. `?t=` param read on `init()` to
auto-play a shared track.

**New file:** `embed.php` — self-contained single-track mini-player,
Option A style (yellow titlebar, art block, DM Mono font), 125px height,
full-width responsive, no seekbar, links back to main player with `?t=`
deep link.

---

## v1.38

Fixed desktop seekbar thumb position. `#seekbar-d` was missing
`width:100%`, so browsers rendered it at ~129px intrinsic width. Thumb was
proportionally correct within that narrow track but visually misaligned
with the full panel. Now shares `width:100%` rule with `#seekbar`. Mobile
seekbar unchanged.

---

## v1.37

Mobile: `.panel-main` is now `position:sticky; top:0; z-index:50`. Player
panel (visualiser + LED + title bar) stays pinned at the top of the
viewport while Track Info and Playlist scroll beneath it. No
padding/height compensation needed — sticky keeps the element in document
flow. Desktop layout unchanged.

---

## v1.36

Restored desktop seekbar (`seekbar-d`) below LED display as desktop-only
element. Synced with `timeupdate` and mobile seekbar. Mobile seekbar and
dock unchanged.

---

## v1.29–v1.35 — NO RECORD

Seven versions with no entry in any source. Known to have existed (version
sequence is confirmed by v1.28 and v1.36 entries in the inline block).
The handoff summary lists two fixes that likely fall here:
- Theme switcher not working: `<html data-theme="memeshift">` was
  permanently overriding `applyTheme()` on `#app`. Removed from `<html>`,
  kept only on `#app`.
- Mobile extra buttons showing >600px: mobile dock elements not in
  `mobile-only` wrapper — fixed with wrapping div.

Both fixes are confirmed present in current code; version numbers unknown.

---

## v1.28

Fixed mobile play/pause icon swap. `playTrack()` was using `textContent`
which destroyed the SVG children. Now uses `style.display` swap consistent
with audio event handlers.

---

## v1.27

Mobile pause icon widened and given explicit 38×38 dimensions to match
visual weight of play triangle in `dock-btn-play` circle.

---

## v1.26

Mobile secondary row buttons corrected. `btn-rwd` / `btn-fwd` renamed to
`btn-prev-sm` / `btn-next-sm` with ⏮/⏭ glyphs and correct titles. JS
bindings updated. Seeking within a track is seekbar-only; skip buttons
move between tracks.

---

## v1.24–v1.25 — NO RECORD

Two versions with no entry in any source. Fall between v1.23 (Memeshift
default theme) and v1.26 (mobile secondary button fix). Content unknown.

---

## v1.23

Memeshift theme now loads by default. `data-theme` on `<html>` set to
`memeshift`; JS state fallback changed from `winamp` to `memeshift`.

---

## v1.22

Fixed iOS Safari theme switcher tap. `attachSparkle()` now runs before
click listener binding so DOM reparenting doesn't orphan the handler.
`cursor:pointer` added to `.buy-sparkle-wrap` for iOS tap recognition on
non-anchor wrapper elements.

---

## v1.21

Mobile dock redesigned from reference apps (Apple Music, Bandcamp,
Spotify).

- Seek thumb: round 20px pill.
- Primary transport: flat SVG icons, `SHUF|PREV|▶|NEXT|REP` with 76px glowing play circle.
- Secondary row: skip5/stop/skip5 in Winamp chrome, compact vol slider, theme toggle.
- Memeshift: yellow play circle + yellow seek thumb.
- All touch targets ≥44px. Safe-area-inset respected.

---

## v1.20

Mobile: seekbar, vol/balance and transport controls wrapped in
`#controls-dock`, fixed to bottom of screen on ≤600px. Touch targets
enlarged (`btn-t`: 38×40px, play: 48×40px). Body padding accounts for
dock height + iPhone safe-area-inset-bottom. Desktop layout unchanged.

---

## v1.19

`burstSocials` Memeshift palette: `#fcd97c` replaced with `#F90002`
(brand red) for maximum vibrancy. Hover sparkle unchanged.

---

## v1.18

Sparkle and burst colours now theme-aware.

- **Winamp:** LED green tones (`#14ff14`, `#00cc00`, `#ffffff`).
- **Memeshift:** yellow/teal palette (`#FAC946`, `#fcd97c`, `#007998`).

Both `attachSparkle` and `burstSocials` updated. Gravatar gradient colours
removed.

---

## v1.17

Hover sparkle animation slowed 50%. `da`, `dx`, `dy` halved in
`attachSparkle()` `spawnParticle`. `burstSocials` unchanged.

---

## v1.16

`burstSocials()` particle effect on theme switch: one-shot cloud of
white/magenta/cyan particles over social icon area, fades over 3 seconds.
Theme switcher float-right restored via transport-center sub-group layout.

---

## v1.15

Changelog added to all project files (index.html, scan.php, art.php,
config.php).

---

## v1.14

Socials strip (email, YouTube, Instagram, SoundCloud, RSS) integrated into
player title bar as 12px SVG icons. Theme switcher moved to transport row
(right-aligned). Particle sparkle added to theme button.

---

## v1.13

All panels stacked vertically at all screen sizes. Transport and
vol/balance rows centred. ResizeObserver removed.

---

## v1.12

Playlist auto-scrolls active track into view on track change using
`getBoundingClientRect()` relative scroll — no page viewport jump.

---

## v1.11

Silkscreen pixel font replaces Share Tech Mono for Winamp theme. Winamp
playlist and info text updated to LED green (`#14ff14`).

---

## v1.10

Sort badges (contextual pills) in playlist rows showing year/artist/album
value when a sort other than DEFAULT is active.

---

## v1.9

Added INFO row (WOAF/TXXX tag) with »more info link to Track Info panel.
`parseTXXX()` helper added to scan.php; WOAF support completed.

---

## v1.8

Added BUY row (WXXX tag) with »buy/support link to Track Info panel.
Particle sparkle hover effect (white/magenta/cyan galaxy) on the link.

---

## v1.7

Added FILE row with »download link in Track Info panel.

---

## v1.6

Info panel: Notes section moved to full-width row via flex-wrap. Album art
and metadata displayed side-by-side on all screen sizes.

---

## v1.5

Playlist height matched to col-left via ResizeObserver. Sort direction
indicators (▲/▼) added to sort buttons.

---

## v1.4

Layout restructure: col-left (player + info) beside playlist on desktop.
Full Memeshift brand skin: Lora, DM Mono, yellow/teal colour system.

---

## v1.3

LED title always scrolls; displays Artist ◆ Album ◆ Title.

---

## v1.2

Replaced emoji transport icons (⏪⏩) with plain geometric symbols (◀◀
▶▶) to prevent OS emoji override.

---

## v1.1

Fixed UTF-16 trailing null bug in ID3 tag decoder causing `?` to appear
at the end of tag values. `decodeText()` now strips trailing nulls
per-encoding after conversion, not before.

---

## v1.0

Initial build.

- Winamp-style player UI, HTML5 audio, Web Audio API visualiser (22 bars)
- LED display with scrolling marquee, time, kbps
- Transport controls: prev, back 5s, play/pause, stop, forward 5s, next, shuffle, repeat
- Playlist panel with sort: DEFAULT / ARTIST ▲▼ / ALBUM ▲▼ / YEAR ▲▼
- Track Info panel: title, artist, album, year, comment, album art
- Dual theme system: Winamp (dark/green LED) and Memeshift (yellow/teal)
- Backend: scan.php (ID3v2.2/v2.3/v2.4 + ID3v1 fallback), art.php (APIC extractor), config.php
- Security: `realpath()` path validation, `.mp3` allowlist, no user input touches filesystem
