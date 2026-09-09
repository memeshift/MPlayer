<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — admin-style.php                           │
 * │  Shared CSS for login/reset/upload admin pages.      │
 * │  No output on its own — call mp_admin_css() inside a │
 * │  <style> block. Kept separate so five pages don't    │
 * │  each carry their own copy of the same WCAG-AA rules.│
 * └──────────────────────────────────────────────────────┘
 *
 * Palette borrowed from index.html's studio theme
 * (--chrome, --titlebar-bg, --pl-sel-bg) so admin pages read as
 * part of the same app, not a bolted-on afterthought.
 */

function mp_admin_css(): string {
    return <<<CSS
:root {
  --bg:        #0a0a0a;
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
  --font-ui:   'Lora', Georgia, serif;
  --font-body: 'Lora', Georgia, serif;
}
* { box-sizing: border-box; }
body {
  background-color: var(--bg);
  background-image: var(--bg-image);
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
.page-header {  
padding:5px 0 10px 0}
p.lede { color: var(--text-dim);
  margin-top: 0;
  border: 1px solid var(--border);
  padding: 1em;
  border-radius: 1px;
background:var(--panel);}
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
.top-nav { display: flex; gap: 0; margin-bottom: 8px; }
.nav-tab { flex: 1 1 40%; min-width: 0; display: flex; align-items: center; justify-content: center; min-height: 48px; border: 1px solid var(--border); border-right: none; background: var(--panel); color: var(--text-dim); font-family: var(--font-ui); font-weight: bold; text-decoration: none; transition: all 0.2s; }
.nav-tab:last-of-type { border-right: 1px solid var(--border); }
.nav-tab:hover { background: rgba(250, 201, 70, 0.05); }
.nav-tab[aria-current="page"] { background: var(--accent); color: var(--accent-text); }
.nav-logout { flex: 1 1 20%; min-width: 0; display: flex; align-items: center; justify-content: center; min-height: 48px; padding: 10px 8px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); font-family: var(--font-ui); font-size: 0.9rem; text-decoration: none; transition: all 0.2s; }
.nav-logout:hover { background: rgba(200, 184, 106, 0.05); }
.nav-menu-wrap { position: relative; flex: 1 1 20%; min-width: 0; }
.nav-menu-trigger { width: 100%; gap: 6px; }
.nav-menu-trigger svg { width: 18px; height: 18px; flex: none; fill: currentColor; }
.nav-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 180px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  z-index: 20;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
}
.nav-menu[hidden] { display: none; }
.nav-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 8px 12px;
  border-radius: 4px;
  color: var(--text-dim);
  font-family: var(--font-ui);
  text-decoration: none;
  font-size: 0.95rem;
}
.nav-menu-item:hover, .nav-menu-item:focus-visible { background: rgba(250, 201, 70, 0.08); color: var(--text); }
.nav-menu-item svg { width: 14px; height: 14px; flex: none; fill: currentColor; opacity: 0.8; }
#art-preview-wrap, .art-preview-wrap { position: relative; }
.art-remove-btn {
  position: absolute;
  top: -10px;
  right: -10px;
  width: 28px;
  height: 28px;
  min-width: 28px;
  min-height: 28px;
  border-radius: 50%;
  background: var(--error);
  color: #fff;
  border: 2px solid var(--bg);
  font-size: 16px;
  line-height: 1;
  padding: 0;
}
.art-remove-btn:hover { background: #ff8a8a; }
.art-dropzone {
  position: relative;
  display: block;
  width: 100%;
  border: 2px dashed var(--border);
  border-radius: 6px;
  padding: 20px 16px;
  text-align: center;
  color: var(--text-dim);
  background: var(--panel);
  cursor: pointer;
}
.art-dropzone:hover, .art-dropzone.drag-over { border-color: var(--accent-hi); color: var(--text); }
.art-dropzone-title { display: block; font-family: var(--font-ui); font-weight: bold; color: var(--text); }
.art-dropzone .hint { display: block; margin-top: 4px; margin-bottom: 0; }
.art-dropzone input[type=file] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  min-height: 0;
  opacity: 0;
  cursor: pointer;
  padding: 0;
  border: none;
}
CSS;
}

/**
 * Shared top nav for admin pages: Upload/Library tabs plus a dropdown
 * (Settings/Customize/Stats/Logout) replacing the old plain "Log out" link.
 * $active is 'upload', 'library', 'settings', or 'stats'.
 */
function mp_admin_nav_html(string $active): string {
    $tab = function (string $href, string $label, string $key) use ($active): string {
        $current = $active === $key ? ' aria-current="page"' : '';
        return '<a href="' . $href . '" class="nav-tab"' . $current . '>' . $label . '</a>';
    };
    $menuItem = function (string $href, string $label, string $key, bool $newTab = false) use ($active): string {
        $current = $active === $key ? ' aria-current="page"' : '';
        $extra = $newTab
            ? ' target="_blank" rel="noopener noreferrer"><span>' . $label . '</span>'
              . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3zM5 5h6v2H5v12h12v-6h2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/></svg>'
            : '>' . $label;
        return '<a href="' . $href . '" role="menuitem" class="nav-menu-item"' . $current . $extra . '</a>';
    };
    return '<div class="top-nav">'
        . $tab('upload.php', 'Upload', 'upload')
        . $tab('library.php', 'Library', 'library')
        . '<div class="nav-menu-wrap">'
        . '<button type="button" class="nav-logout nav-menu-trigger" id="nav-menu-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="nav-menu">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>'
        . '<span>Menu</span>'
        . '</button>'
        . '<div class="nav-menu" id="nav-menu" role="menu" hidden>'
        . $menuItem('settings.php', 'Settings', 'settings')
        . $menuItem('index.html?customize=1', 'Customize', 'customize', true)
        . $menuItem('stats.php', 'Stats', 'stats')
        . $menuItem('logout.php', 'Log out', 'logout')
        . '</div>'
        . '</div>'
        . '</div>'
        . '<script>(function(){'
        . 'var t=document.getElementById("nav-menu-trigger"),m=document.getElementById("nav-menu");'
        . 'if(!t||!m)return;'
        . 'function close(){m.hidden=true;t.setAttribute("aria-expanded","false");}'
        . 'function open(){m.hidden=false;t.setAttribute("aria-expanded","true");}'
        . 't.addEventListener("click",function(e){e.stopPropagation();m.hidden?open():close();});'
        . 'document.addEventListener("click",function(e){if(!m.hidden&&!m.contains(e.target)&&e.target!==t)close();});'
        . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&!m.hidden){close();t.focus();}});'
        . '})();</script>';
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
