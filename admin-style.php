<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — admin-style.php                   │
 * │  Shared CSS for login/reset/upload admin pages.       │
 * │  No output on its own — call mp_admin_css() inside a  │
 * │  <style> block. Kept separate so five pages don't      │
 * │  each carry their own copy of the same WCAG-AA rules. │
 * └──────────────────────────────────────────────────────┘
 *
 * Palette borrowed from index.html's Memeshift brand theme
 * (--chrome, --titlebar-bg, --pl-sel-bg) so admin pages read as
 * part of the same app, not a bolted-on afterthought.
 */

function mp_admin_css(): string {
    return <<<CSS
:root {
  --bg:        #0a0a0a;
  --bg-image:  url('https://www.memeshift.com/wp-content/uploads/2025/02/Scan-scaled.jpg');
  --bg-overlay:rgba(10, 8, 2, 0.82);
  --panel:     #141410;
  --text:      #FAC946;
  --text-dim:  #c8b86a;
  --link:      #6fd6ef;
  --accent:    #FAC946;
  --accent-hi: #fcd97c;
  --accent-text: #2a2000;
  --error:     #ff6b6b;
  --error-bg:  #2a0a0a;
  --border:    #554e30;
  --font-ui:   'DM Mono', 'Courier New', monospace;
  --font-body: 'Lora', Georgia, serif;
}
* { box-sizing: border-box; }
body {
  background-color: var(--bg);
  background-image: linear-gradient(var(--bg-overlay), var(--bg-overlay)), var(--bg-image);
  background-size: auto, contain;
  background-repeat: repeat;
  background-attachment: fixed;
  color: var(--text);
  font-family: var(--font-body);
  max-width: 480px;
  margin: 0 auto;
  padding: 24px 16px 64px;
  line-height: 1.5;
}
h1 {
  font-family: var(--font-ui);
  font-size: 1.3rem;
  color: var(--text);
  margin-bottom: 4px;
}
p.lede { color: var(--text-dim); margin-top: 0; }
form { display: flex; flex-direction: column; gap: 18px; margin-top: 24px; }
fieldset { border: 1px solid var(--border); border-radius: 4px; padding: 16px; }
legend { font-family: var(--font-ui); padding: 0 6px; color: var(--text-dim); }
label {
  display: block;
  font-family: var(--font-ui);
  margin-bottom: 6px;
  font-size: 0.95rem;
}
input[type=text], input[type=email], input[type=password],
input[type=number], input[type=url], input[type=file], textarea {
  width: 100%;
  min-height: 44px;
  font-size: max(16px, 1em);
  font-family: var(--font-ui);
  color: var(--text);
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 10px 12px;
}
textarea { min-height: 88px; resize: vertical; }
.field { margin-bottom: 4px; }
.hint { color: var(--text-dim); font-size: 0.85rem; margin-top: 4px; }
button, .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  min-width: 44px;
  font-size: 1rem;
  font-family: var(--font-ui);
  font-weight: bold;
  color: var(--accent-text);
  background: var(--accent);
  border: none;
  border-radius: 4px;
  padding: 10px 20px;
  cursor: pointer;
  text-decoration: none;
}
button:hover, .btn:hover { background: var(--accent-hi); }
button:disabled { opacity: 0.6; cursor: not-allowed; }
a { color: var(--link); }
a.secondary-action {
  display: inline-block;
  margin-top: 8px;
  min-height: 44px;
  line-height: 44px;
}
/* Visible focus ring on every interactive element — never suppressed. */
a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible {
  outline: 3px solid #707070;
  outline-offset: 2px;
}
.msg {
  border-radius: 4px;
  padding: 12px 14px;
  margin: 16px 0;
  font-size: 0.95rem;
}
.msg-error { background: var(--error-bg); color: var(--error); border: 1px solid var(--error); }
.msg-success { background: #06282e; color: var(--text); border: 1px solid var(--link); }
.art-preview {
  width: 120px;
  height: 120px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid var(--border);
  display: block;
  margin-bottom: 10px;
}
.top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.top-nav a { min-height: 44px; display: inline-flex; align-items: center; }
CSS;
}

/**
 * A visually-hidden but screen-reader-visible live region for form errors,
 * matching index.html's #sr-status idiom. Call once per page; update its
 * textContent from JS, or render server-side text inside it directly.
 */
function mp_sr_status_html(string $text = ''): string {
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return '<div id="sr-status" role="status" aria-live="polite" aria-atomic="true" '
         . 'style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;">'
         . $escaped . '</div>';
}
