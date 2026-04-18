.+Memeshift+. Player Changelog
  ════════════════════════════════════════════════════════
  v1.0   Initial build: Winamp-style player UI, HTML5 audio,
         Web Audio visualiser, LED display, transport controls,
         playlist panel with sort (artist/album/year asc/desc),
         track info panel, dual theme system (Winamp / Memeshift).
  v1.1   Fixed UTF-16 trailing null bug causing '?' in ID3 tags.
  v1.2   Replaced emoji transport icons (⏪⏩) with plain geometric
         symbols (◀◀ ▶▶) to prevent OS emoji override.
  v1.3   LED title always scrolls; shows Artist ◆ Album ◆ Title.
  v1.4   Layout restructure: col-left (player + info) beside playlist
         on desktop; full Memeshift brand skin (Lora, DM Mono, yellow/teal).
  v1.5   Playlist height matched to col-left via ResizeObserver.
         Sort direction indicators (▲/▼) added to sort buttons.
  v1.6   Info panel: Notes section moved to full-width row via flex-wrap.
         Album art + metadata side-by-side on all screen sizes.
  v1.7   Added FILE → download link in track info panel.
  v1.8   Added BUY row (WXXX tag) with »buy/support link and
         particle sparkle hover effect (white/magenta/cyan galaxy).
  v1.9   Added INFO row (WOAF/TXXX tag) with »more info link.
         parseTXXX() helper added; WOAF support completed.
  v1.10  Sort badges (contextual pills) in playlist rows showing
         year/artist/album value when sort is active.
  v1.11  Silkscreen pixel font replaces Share Tech Mono for Winamp theme.
         Winamp playlist/info text updated to LED green (#14ff14).
  v1.12  Playlist auto-scrolls active track to top on track change
         using getBoundingClientRect (no page viewport jump).
  v1.13  All panels stacked vertically at all screen sizes.
         Transport and vol/balance rows centred. ResizeObserver removed.
  v1.14  Socials strip (email, YouTube, Instagram, SoundCloud, RSS)
         integrated into player title bar. Theme switcher moved to
         transport row (right-aligned). Particle sparkle added to theme button.
  v1.15  Changelog added to all project files.
  v1.16  burstSocials() particle effect on theme switch: one-shot cloud
         of white/magenta/cyan particles over social icon area, fades
         over 3 seconds. Theme switcher float-right restored via
         transport-center sub-group layout.
  v1.17  Hover sparkle animation slowed 50%: da, dx, dy halved in
         attachSparkle() spawnParticle. burstSocials unchanged.
  v1.18  Sparkle/burst colors now theme-aware. Winamp: LED green tones
         (#14ff14, #00cc00, #ffffff). Memeshift: yellow/teal palette
         (#FAC946, #fcd97c, #007998). Both attachSparkle and burstSocials
         updated. Gravatar gradient colors removed.
  v1.19  burstSocials Memeshift palette: #fcd97c replaced with #F90002
         (brand red) for maximum vibrancy. Hover sparkle unchanged.
  v1.20  Mobile: seekbar, vol/balance and transport controls wrapped in
         #controls-dock, fixed to bottom of screen on ≤600px. Touch
         targets enlarged (btn-t: 38×40px, play: 48×40px). Body padding
         accounts for dock height + iPhone safe-area-inset-bottom.
         Desktop layout completely unchanged.
  v1.21  Mobile dock redesigned from reference apps (Apple Music, Bandcamp,
         Spotify). Seek thumb: round 20px pill. Primary transport: flat SVG
         icons, SHUF|PREV|▶|NEXT|REP with 76px glowing play circle.
         Secondary row: skip5/stop/skip5 in Winamp chrome, compact vol
         slider, theme toggle. Memeshift: yellow play + yellow seek thumb.
         All touch targets ≥44px. Safe-area-inset respected.
  v1.22  Fixed iOS Safari theme switcher tap: attachSparkle() now runs
         before click listener binding so DOM reparenting doesn't orphan
         the handler. cursor:pointer added to .buy-sparkle-wrap for iOS
         tap recognition on non-anchor wrapper elements.
  v1.23  Memeshift theme now loads by default. data-theme on <html> set
         to memeshift; JS state fallback changed from winamp to memeshift.
  v1.26  Mobile secondary row buttons corrected: btn-rwd/btn-fwd renamed
         to btn-prev-sm/btn-next-sm with ⏮/⏭ glyphs and correct titles.
         JS bindings updated. Seeking within a track is seekbar-only.
  v1.27  Mobile pause icon widened and given explicit 38×38 dimensions
         to match visual weight of play triangle in dock-btn-play circle.
  v1.28  Fixed mobile play/pause icon swap: playTrack() was using
         textContent which destroyed the SVG children. Now uses
         style.display swap consistent with audio event handlers.
  v1.36  Restored desktop seekbar (seekbar-d) below LED display as
         desktop-only element. Synced with timeupdate and mobile seekbar.
         Mobile seekbar and dock unchanged.
  v1.37  Mobile: .panel-main is now position:sticky; top:0; z-index:50.
         Player panel (visualiser + LED + title bar) stays pinned at the
         top of the viewport while Track Info and Playlist scroll beneath
         it. No padding/height compensation needed — sticky keeps the
         element in document flow. Desktop layout unchanged.
  v1.38  Fixed desktop seekbar thumb position. #seekbar-d was missing
         width:100%, so browsers rendered it at ~129px intrinsic width.
         Thumb was proportionally correct within that narrow track but
         visually misaligned with the full panel. Now shares width:100%
         rule with #seekbar. Mobile seekbar unchanged.
  v1.39  Share/Embed feature. Track Info panel: info-body restructured
         into two side-by-side columns (.info-meta-col left,
         .info-share-col right) separated by a subtle vertical divider.
         SHARE THIS key + share/embed links flush-left in right col,
         matching ARTIST/ALBUM padding. JS: shareTrack() copies ?t= deep
         link to clipboard with copied! flash; openEmbedModal() shows a
         keyboard-dismissible overlay with direct link and iframe snippet.
         ?t= param read on init() to auto-play a shared track.
         New file: embed.php — self-contained single-track mini-player,
         Option A style (yellow titlebar, art block, DM Mono font),
         125px height, full-width responsive, no seekbar, links back to
         music.memeshift.com with ?t= deep link.
  v1.40  Share col sizing. .info-meta-cols switched from flex to
         grid(2fr 1fr) so share col always occupies exactly one third
         of the metadata area. .info-share-links items given min-height
         36px + padding for comfortable finger tap targets.
  v1.41  Mobile share UX. On ≤600px the »share/»embed text links are
         hidden and replaced by a single upward-arrow share icon (28px,
         44×44px tap target). Tapping calls navigator.share() — the
         native iOS/Android share sheet — with title, text, and ?t= deep
         link. Falls back to clipboard copy on browsers without Web Share
         API support. Desktop text links unchanged.
  v1.42  Mobile share col: SHARE THIS label restored above the icon.
         Only the »share/»embed text links are hidden on mobile.
         Share col alignment changed to flex-start so label + icon
         stack top-to-bottom flush left.
  v1.43  Share col refinements. Desktop: two separate links collapsed
         into one »Share / Embed link that opens the modal directly.
         info-share-key gets white-space:nowrap so label never wraps.
         Mobile: icon button hidden via base CSS (display:none), only
         revealed inside ≤600px media query — guarantees it never shows
         on desktop. Icon centred horizontally below the label via
         align-items:center on the share col.
  v1.44  Background audio / lock screen controls. Added playsinline and
         x-webkit-airplay="allow" to <audio> element. Implemented Media
         Session API via updateMediaSession(t): sets track metadata
         (title, artist, album, artwork via art.php) on every playTrack()
         call, and registers OS action handlers for play, pause,
         nexttrack, previoustrack, seekforward, seekbackward. Enables
         lock screen card, Control Centre widget, and background playback
         on iOS Safari and Android Chrome. Falls back silently on
         unsupported browsers.
  v1.45  Fixed iOS background audio interruption. Root cause: routing
         <audio> through AudioContext via createMediaElementSource() causes
         iOS to cut audio when the page backgrounds, because iOS suspends
         the AudioContext and the <audio> element is bound to it.
         Fix: detect iOS via userAgent/maxTouchPoints and skip AudioContext
         routing entirely on those devices. Visualiser shows idle bars on
         iOS (acceptable tradeoff). On all other browsers the visualiser
         works as before. Added visibilitychange listener to resume
         AudioContext when page returns to foreground on non-iOS.
  v1.46  A11y Quick Wins (5 fixes):
         FIX 1 — :focus-visible styles for all interactive elements.
         FIX 2 — aria-label on all symbol-only transport buttons;
                 aria-pressed kept in sync with play/pause/shuffle state.
         FIX 3 — Visually-hidden #sr-status aria-live region announces
                 Now playing / Paused / Stopped to screen readers.
         FIX 4 — Contrast corrections: --pl-num, --pl-dur, --info-key,
                 --sort-text lifted to ≥4.5:1 in both themes.
         FIX 5 — Playlist items now keyboard-operable: tabindex="0",
                 Enter/Space activates; aria-selected kept in sync
                 with markCurrent().
  ════════════════════════════════════════════════════════
