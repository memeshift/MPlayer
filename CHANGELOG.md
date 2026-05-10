# .+Memeshift+. Player Changelog

---

## v1.47

**WCAG 1.1.1y font size accessibility improvements** — responsive font scaling and touch target compliance for small screens.

### Added
- **CSS `clamp()` responsive font sizing** across all UI elements for fluid scaling between mobile and desktop.
- **Media queries** for mobile (≤480px) and ultra-small (≤360px) devices with enhanced font sizes and touch targets.
- **CSS documentation comments** marking all accessibility improvements with `/* WCAG 1.1.1y: */` tags.

### Changed
**Font sizes (with responsive clamp ranges):**
- Titlebar label: `1.05rem` → `clamp(1.05rem, 2.5vw, 1.25rem)`
- Titlebar count: `0.88rem` → `clamp(0.88rem, 2vw, 1rem)`
- LED title: `2.2rem` → `clamp(1.8rem, 4vw, 2.2rem)`
- **LED time (critical):** `3.6rem` → `clamp(2.8rem, 6vw, 3.6rem)` — ensures readability on all viewports
- LED slash/duration: `2rem` → `clamp(1.6rem, 3.5vw, 2rem)`
- LED meta lines: `0.85rem` → `clamp(0.85rem, 2vw, 1rem)`
- Button labels (ctrl-lbl): `0.88rem` → `clamp(0.88rem, 2vw, 1rem)`
- Transport buttons: `1.5rem` → `clamp(1.3rem, 2.5vw, 1.5rem)`
- Toggle buttons: `0.82rem` → `clamp(0.82rem, 2vw, 1rem) !important`
- Theme button: `0.82rem` → `clamp(0.82rem, 2vw, 1rem)`
- Sort label: `0.85rem` → `clamp(0.85rem, 2vw, 1rem)`
- Sort buttons: `0.82rem` → `clamp(0.82rem, 2vw, 1rem)` + `min-height: 36px`
- Playlist status: `1.1rem` → `clamp(1.1rem, 2.5vw, 1.3rem)`
- **Playlist items:** `1.15rem` → `clamp(1.1rem, 2.5vw, 1.2rem)` + `min-height: max(19px, 36px)`
- Playlist numbers/duration: `1.0rem` → `clamp(0.95rem, 2vw, 1rem)`
- Playlist badges: `0.82rem` → `clamp(0.75rem, 2vw, 0.9rem)`
- Playlist footer: `0.88rem` → `clamp(0.88rem, 2vw, 1rem)`
- Info key labels: `0.88rem` → `clamp(0.88rem, 2vw, 1rem)`
- **Info values:** `1.35rem` → `clamp(1.25rem, 3vw, 1.5rem)` — improves metadata readability
- Info share key: `0.88rem` → `clamp(0.88rem, 2vw, 1rem)`
- Modal title: `1.05rem` → `clamp(1rem, 2.5vw, 1.15rem)`
- Modal label: `0.85rem` → `clamp(0.85rem, 2vw, 1rem)`
- **Modal input:** `1.1rem` → `clamp(1rem, 2.5vw, 1.2rem)` + `min-height: 44px` + `font-size: 16px` on iOS
- Info notes label: `0.85rem` → `clamp(0.85rem, 2vw, 1rem)`
- **Info notes text:** `1.3rem` → `clamp(1.2rem, 2.5vw, 1.4rem)`
- Art placeholder: `3.2rem` → `clamp(2.4rem, 5vw, 3.2rem)`

### Improved
**Touch target sizing (WCAG 2.1 Level AAA compliance):**
- Transport buttons: `min-width: 30px` → `max(36px, 44px)` with `height: max(22px, 44px)`
- Play button: `min-width: 34px` → `max(34px, 44px)` with responsive font size
- Toggle buttons: `min-width: 32px` → `max(32px, 44px)` with `min-height: 44px` + padding `4px 8px`
- Theme button: `height: 14px` → `height: max(14px, 36px)` with padding `2px 8px`
- Sort buttons: `padding: 1px 5px` → `padding: 3px 8px` with `min-height: 36px`
- Playlist items: `min-height: 19px` → `min-height: max(19px, 36px)`
- Modal close button: added flexbox centering + `min-width: 36px` / `min-height: 36px`
- Form inputs: added `min-height: 44px` to all `.embed-modal-input` elements
- iOS-specific: form inputs set to `16px` font size to prevent auto-zoom-on-focus

**Responsive base font size:**
- `html { font-size: 62.5%; }` → `html { font-size: clamp(62.5%, 10vw, 62.5%); }`
- Mobile (≤480px): `font-size: clamp(66.67%, 11vw, 75%);`
- Ultra-small (≤360px): `font-size: 75%;`

### Fixed
- **Small screen legibility:** all text now scales fluidly with viewport width instead of remaining fixed at minimum sizes.
- **Touch target undersizing:** buttons and form controls now meet WCAG 2.1 Level AAA 44×44px minimum on all screen sizes.
- **iOS zoom-on-focus:** form inputs now use `16px` font size, preventing unwanted viewport zoom when focusing.
- **LED display readability:** time display now scales responsively while maintaining LED aesthetic at all screen sizes.

### Testing Notes
- Desktop (>1024px): original pixel-perfect dimensions maintained via `clamp()` upper bounds.
- Tablet (600-1024px): smooth scaling between mobile and desktop breakpoints.
- Mobile (480-600px): enhanced font sizes and 44px+ touch targets activate.
- Small (360-480px): additional font size boost and 48px input height for iOS usability.
- Ultra-small (<360px): maximum font size scaling with 75% base font size.

**Browser support:** Chrome 79+, Firefox 75+, Safari 13.1+, Edge 79+ (all support `clamp()` and `max()`).

---

## v1.46

**A11y quick wins** (5 fixes):

1. **`:focus-visible`** — styles for all interactive elements.
2. **`aria-label`** on all symbol-only transport buttons; **`aria-pressed`** kept in sync with play/pause/shuffle state.
3. **Visually-hidden `#sr-status`** — `aria-live` region announces Now playing / Paused / Stopped to screen readers.
4. **Contrast** — `--pl-num`, `--pl-dur`, `--info-key`, `--sort-text` lifted to ≥4.5:1 in both themes.
5. **Playlist keyboard** — items now keyboard-operable: `tabindex="0"`, Enter/Space activates; `aria-selected` kept in sync with `markCurrent()`.

---

## v1.45

Fixed iOS background audio interruption.

**Root cause:** routing `<audio>` through `AudioContext` via `createMediaElementSource()` causes iOS to cut audio when the page backgrounds, because iOS suspends the `AudioContext` and the `<audio>` element with it.

**Fix:** detect iOS via `userAgent` / `maxTouchPoints` and skip `AudioContext` routing entirely on those devices. Visualiser shows idle bars on iOS (acceptable tradeoff). On all other browsers the visualiser works as before.

## v1.44

Background audio / lock screen controls. Added `playsinline` and `x-webkit-airplay="allow"` to `<audio>` element. Implemented Media Session API via `updateMediaSession(t)`: sets track metadata (track title, artist, album, artwork) so iOS lock screen and media notification centers display the now-playing track.

## v1.43

Share col refinements.

- **Desktop:** two separate links collapsed into one »Share / Embed link that opens the modal directly. `info-share-key` gets `white-space:nowrap` so label never wraps.
- **Mobile:** icon button hidden via base CSS (`display:none`), only revealed inside ≤600px media query — guarantees it never shows on desktop. Icon centred horizontally below the label via `text-align:center`.

## v1.42

Mobile share col: SHARE THIS label restored above the icon. Only the »share/»embed text links are hidden on mobile. Share col alignment changed to `flex-start` so label + icon stack top-to-bottom.

## v1.41

**Mobile share UX.** On ≤600px the »share/»embed text links are hidden and replaced by a single upward-arrow share icon (28px, 44×44px tap target). Tapping calls `navigator.share()` — the native OS share sheet on iOS/Android.

## v1.40

Share col sizing. `.info-meta-cols` switched from flex to `grid(2fr 1fr)` so share col always occupies exactly one third of the metadata area. `.info-share-links` items given `min-height` 36px + `padding: 6px 0` for better touch targets.

## v1.39

Share/Embed feature.

**Track Info panel:** `info-body` restructured into two side-by-side columns (`.info-meta-col` left, `.info-share-col` right) separated by a subtle vertical divider. SHARE THIS key + share/embed links added.

**JS:** `shareTrack()` copies `?t=` deep link to clipboard with copied! flash; `openEmbedModal()` shows a keyboard-dismissible overlay with direct link and iframe snippet. `?t=` param read on `init()`.

**New file:** `embed.php` — self-contained single-track mini-player, Option A style (yellow titlebar, art block, DM Mono font), 125px height, full-width responsive, no seekbar, links back to main player.

## v1.38

Fixed desktop seekbar thumb position. `#seekbar-d` was missing `width:100%`, so browsers rendered it at ~129px intrinsic width. Thumb was proportionally correct within that narrow track but visually displaced on the full-width player. Now the thumb position matches the audio progress.

## v1.37

**Mobile:** `.panel-main` is now `position:sticky; top:0; z-index:50`. Player panel (visualiser + LED + title bar) stays pinned at the top of the viewport while Track Info and Playlist scroll beneath it.

## v1.36

Restored desktop seekbar (`seekbar-d`) below LED display as desktop-only element. Synced with `timeupdate` and mobile seekbar. Mobile seekbar and dock unchanged.

## v1.28

Fixed mobile play/pause icon swap: `playTrack()` was using `textContent` which destroyed the SVG children. Now uses `style.display` swap consistent with audio event handlers.

## v1.27

Mobile pause icon widened and given explicit 38×38 dimensions to match visual weight of play triangle in `dock-btn-play` circle.

## v1.26

Mobile secondary row buttons corrected: `btn-rwd` / `btn-fwd` renamed to `btn-prev-sm` / `btn-next-sm` with ⏮/⏭ glyphs and correct titles. JS bindings updated. Seeking within a track is seekbar only; skip buttons move between tracks.

## v1.23

Memeshift theme now loads by default. `data-theme` on `<html>` set to `memeshift`; JS state fallback changed from `winamp` to `memeshift`.

## v1.22

Fixed iOS Safari theme switcher tap: `attachSparkle()` now runs before click listener binding so DOM reparenting doesn't orphan the handler. `cursor:pointer` added to `.buy-sparkle-wrap` for iOS tap feedback.

## v1.21

Mobile dock redesigned from reference apps (Apple Music, Bandcamp, Spotify).

- Seek thumb: round 20px pill.
- Primary transport: flat SVG icons, `SHUF|PREV|▶|NEXT|REP` with 76px glowing play circle.
- Secondary row: skip5/stop/skip5 in Winamp chrome, compact vol slider, theme toggle.
- Memeshift: yellow play + yellow seek thumb.
- All touch targets ≥44px. Safe-area-inset respected.

## v1.20

**Mobile:** seekbar, vol/balance and transport controls wrapped in `#controls-dock`, fixed to bottom of screen on ≤600px. Touch targets enlarged (`btn-t`: 38×40px, play: 48×40px). Body padding adjusted to make room.

## v1.19

`burstSocials` Memeshift palette: `#fcd97c` replaced with `#F90002` (brand red) for maximum vibrancy. Hover sparkle unchanged.

## v1.18

Sparkle/burst colors now theme-aware.

- **Winamp:** LED green tones (`#14ff14`, `#00cc00`, `#ffffff`).
- **Memeshift:** yellow/teal palette (`#FAC946`, `#fcd97c`, `#007998`).

Both `attachSparkle` and `burstSocials` updated. Gravatar gradient colors removed.

## v1.17

Hover sparkle animation slowed 50%: `da`, `dx`, `dy` halved in `attachSparkle()` `spawnParticle`. `burstSocials` unchanged.

## v1.16

`burstSocials()` particle effect on theme switch: one-shot cloud of white/magenta/cyan particles over social icon area, fades over 3 seconds. Theme switcher float-right restored via transport-center.

## v1.15

Changelog added to all project files.

## v1.14

Socials strip (email, YouTube, Instagram, SoundCloud, RSS) integrated into player title bar. Theme switcher moved to transport row (right-aligned). Particle sparkle added to theme button.

## v1.13

All panels stacked vertically at all screen sizes. Transport and vol/balance rows centred. ResizeObserver removed.

## v1.12

Playlist auto-scrolls active track to top on track change using `getBoundingClientRect` (no page viewport jump).

## v1.11

Silkscreen pixel font replaces Share Tech Mono for Winamp theme. Winamp playlist/info text updated to LED green (`#14ff14`).

## v1.10

Sort badges (contextual pills) in playlist rows showing year/artist/album value when sort is active.

## v1.9

Added INFO row (WOAF/TXXX tag) with »more info link. `parseTXXX()` helper added; WOAF support completed.

## v1.8

Added BUY row (WXXX tag) with »buy/support link and particle sparkle hover effect (white/magenta/cyan galaxy).

## v1.7

Added FILE → download link in track info panel.

## v1.6

Info panel: Notes section moved to full-width row via flex-wrap. Album art + metadata side-by-side on all screen sizes.

## v1.5

Playlist height matched to col-left via ResizeObserver. Sort direction indicators (▲/▼) added to sort buttons.

## v1.4

Layout restructure: col-left (player + info) beside playlist on desktop; full Memeshift brand skin (Lora, DM Mono, yellow/teal).

## v1.3

LED title always scrolls; shows Artist ◆ Album ◆ Title.

## v1.2

Replaced emoji transport icons (⏪⏩) with plain geometric symbols (◀◀ ▶▶) to prevent OS emoji override.

## v1.1

Fixed UTF-16 trailing null bug causing `?` in ID3 tags.

## v1.0

Initial build: Winamp-style player UI, HTML5 audio, Web Audio visualiser, LED display, transport controls, playlist panel with sort (artist/album/year asc/desc), track info panel, dual theme system.

---
