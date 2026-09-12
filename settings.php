<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — settings.php                              │
 * │  Auth-gated admin page: site name, site icon, and    │
 * │  which social icons show on the public player. Blank │
 * │  social field = icon hidden. Saves via               │
 * │  settings_save.php under section=site.               │
 * └──────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-style.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (!empty($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000');
}

mp_require_login_page();
$csrf = mp_csrf_token();
$settings = mp_read_site_settings();
$social = $settings['social'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Settings — <?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
#msg { transition-property: opacity; transition-timing-function: linear; }
#msg.fade-out { opacity: 0; }
</style>
</head>
<body>
<?php echo mp_admin_nav_html('settings'); ?>
<p class="lede">Your site's name, icon, and the social links shown in the player's title bar. Leave a social field blank to hide that icon. After saving, visit your <a href="/?fresh=1" target="_blank">live site</a> to see the changes.</p>
<div class="page-header">
  <h1>Settings</h1>
</div>
<?php echo mp_sr_status_html(); ?>

<form id="social-form">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="icon" id="icon" value="<?php echo htmlspecialchars($settings['icon'], ENT_QUOTES, 'UTF-8'); ?>">

  <div class="field">
    <label for="site_name">Site name</label>
    <input type="text" id="site_name" name="site_name" maxlength="60" value="<?php echo htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?>">
    <p class="hint">Shown in the player title bar, the browser tab, and password-reset emails.</p>
  </div>

  <div class="field">
    <label for="icon_file">Site icon</label>
    <img id="icon-preview" src="favicon.php" alt="Current site icon" width="32" height="32" style="display:block;margin-bottom:8px;border-radius:4px">
    <input type="file" id="icon_file" accept="image/png,image/jpeg,image/gif,image/webp" aria-describedby="icon-hint">
    <p class="hint" id="icon-hint">The browser-tab icon. A square PNG works best. Max <?php echo (int) MAX_ART_MB; ?>MB.</p>
    <div id="icon-msg" role="status"></div>
  </div>

  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($social['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="you@example.com">
  </div>
  <div class="field">
    <label for="youtube">YouTube</label>
    <input type="url" id="youtube" name="youtube" value="<?php echo htmlspecialchars($social['youtube'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.youtube.com/...">
  </div>
  <div class="field">
    <label for="instagram">Instagram</label>
    <input type="url" id="instagram" name="instagram" value="<?php echo htmlspecialchars($social['instagram'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.instagram.com/...">
  </div>
  <div class="field">
    <label for="soundcloud">SoundCloud</label>
    <input type="url" id="soundcloud" name="soundcloud" value="<?php echo htmlspecialchars($social['soundcloud'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://soundcloud.com/...">
  </div>
  <div class="field">
    <label for="rss">RSS</label>
    <input type="url" id="rss" name="rss" value="<?php echo htmlspecialchars($social['rss'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com/rss">
  </div>
  <button type="submit">Save</button>
  <div id="msg" role="alert"></div>
</form>

<script>
document.getElementById('social-form').addEventListener('submit', function (e) {
  e.preventDefault();
  var msg = document.getElementById('msg');
  var sr = document.getElementById('sr-status');
  msg.className = '';
  msg.textContent = '';
  var fd = new FormData(this);
  fd.set('section', 'site');
  fetch('settings_save.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
      if (!res.ok) throw new Error(res.data.error || 'Save failed.');
      msg.className = 'msg msg-success';
      msg.textContent = 'Saved.';
      if (sr) sr.textContent = 'Settings saved.';
      msg.classList.remove('fade-out');
      msg.style.transitionDuration = '';
      const collapse = () => { msg.className = ''; msg.textContent = ''; };
      requestAnimationFrame(() => {
        msg.style.transitionDuration = '2000ms';
        requestAnimationFrame(() => msg.classList.add('fade-out'));
      });
      msg.addEventListener('transitionend', collapse, { once: true });
      setTimeout(collapse, 2200);
    })
    .catch(function (err) {
      msg.className = 'msg msg-error';
      msg.textContent = err.message;
      if (sr) sr.textContent = err.message;
    });
});

// Icon upload posts straight to background_upload.php, which stores the
// file and returns its path; the path goes into the hidden field and is
// only persisted when the form itself is saved.
document.getElementById('icon_file').addEventListener('change', function () {
  var file = this.files && this.files[0];
  if (!file) return;
  var out = document.getElementById('icon-msg');
  out.className = '';
  out.textContent = 'Uploading…';
  var fd = new FormData();
  fd.append('csrf', document.querySelector('input[name=csrf]').value);
  fd.append('kind', 'icon');
  fd.append('image', file);
  fetch('background_upload.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
      if (!res.ok) throw new Error(res.data.error || 'Upload failed.');
      document.getElementById('icon').value = res.data.path;
      document.getElementById('icon-preview').src = res.data.path + '?v=' + Date.now();
      out.className = 'msg msg-success';
      out.textContent = 'Icon uploaded. Press Save to apply it.';
    })
    .catch(function (err) {
      out.className = 'msg msg-error';
      out.textContent = err.message;
    });
});
</script>
</body>
</html>
