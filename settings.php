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
<div class="page-header">
  <h1>Settings</h1>
</div>
<p class="lede">Your site's name, icon, and the social links shown in the player's title bar. Leave a social field blank to hide that icon. After saving, visit your <a href="/?fresh=1" target="_blank">live site</a> to see the changes.</p>
<?php echo mp_sr_status_html(); ?>

<form id="social-form">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="icon" id="icon" value="<?php echo htmlspecialchars($settings['icon'], ENT_QUOTES, 'UTF-8'); ?>">

  <div class="grid-2">
    <div class="field">
      <label for="site_name">Site name</label>
      <input type="text" id="site_name" name="site_name" maxlength="60" value="<?php echo htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?>">
      <p class="hint">Shown in the player title bar, the browser tab, and password-reset emails.</p>
    </div>

    <div class="field">
      <label>Site icon</label>
      <div id="icon-preview-wrap" hidden style="margin-bottom:12px;">
        <img id="icon-preview" class="art-preview" src="favicon.php" alt="Current site icon">
        <button type="button" id="remove-icon-btn" class="art-remove-btn" aria-label="Remove site icon">&times;</button>
      </div>
      <label id="icon-dropzone" class="art-dropzone" for="icon_file">
        <span class="art-dropzone-title">Upload site icon</span>
        <span class="hint">Drop an image here or click to browse.</span>
        <input type="file" id="icon_file" accept="image/png,image/jpeg,image/gif,image/webp">
      </label>
      <p class="hint" id="icon-hint">A square PNG works best. Max <?php echo (int) MAX_ART_MB; ?>MB.</p>
      <div id="icon-msg" role="status"></div>
    </div>
  </div>

  <div class="field">
    <label for="landing_text">Library landing text</label>
    <textarea id="landing_text" name="landing_text" maxlength="200" rows="3"><?php echo htmlspecialchars($settings['landing_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
    <p class="hint">Shown above the row buttons on the mobile library landing page. Leave blank to hide. <span id="landing_text-count"></span></p>
  </div>

  <div class="field">
    <label for="alias_label">Alias row label</label>
    <input type="text" id="alias_label" name="alias_label" maxlength="30" value="<?php echo htmlspecialchars($settings['alias_label'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Aliases">
    <p class="hint">Library row that groups tracks by artist name. Rename it to whatever fits how you release music — "Monikers", "Projects", "Collaborations".</p>
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
(function () {
  var el = document.getElementById('landing_text');
  var count = document.getElementById('landing_text-count');
  function update() { count.textContent = el.value.length + '/' + el.maxLength; }
  el.addEventListener('input', update);
  update();
})();

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
      document.getElementById('icon-preview-wrap').hidden = false;
      document.getElementById('icon-dropzone').style.display = 'none';
      out.className = 'msg msg-success';
      out.textContent = 'Icon uploaded. Click the Save button below to apply it.';
    })
    .catch(function (err) {
      out.className = 'msg msg-error';
      out.textContent = err.message;
    });
});

// Remove icon button
document.getElementById('remove-icon-btn').addEventListener('click', function (e) {
  e.preventDefault();
  document.getElementById('icon_file').value = '';
  document.getElementById('icon').value = '';
  document.getElementById('icon-preview-wrap').hidden = true;
  document.getElementById('icon-dropzone').style.display = '';
  document.getElementById('icon-msg').className = '';
  document.getElementById('icon-msg').textContent = '';
});

// On page load, show preview if icon already exists
(function () {
  var iconPath = document.getElementById('icon').value;
  if (iconPath) {
    document.getElementById('icon-preview').src = iconPath;
    document.getElementById('icon-preview-wrap').hidden = false;
    document.getElementById('icon-dropzone').style.display = 'none';
  }
})();

// Drag and drop for icon
var iconDropzone = document.getElementById('icon-dropzone');
['dragover', 'dragenter'].forEach(function (evt) {
  iconDropzone.addEventListener(evt, function (e) {
    e.preventDefault();
    iconDropzone.classList.add('drag-over');
  });
});
['dragleave', 'drop'].forEach(function (evt) {
  iconDropzone.addEventListener(evt, function (e) {
    e.preventDefault();
    iconDropzone.classList.remove('drag-over');
  });
});
iconDropzone.addEventListener('drop', function (e) {
  var f = e.dataTransfer.files && e.dataTransfer.files[0];
  if (!f) return;
  var dt = new DataTransfer();
  dt.items.add(f);
  document.getElementById('icon_file').files = dt.files;
  document.getElementById('icon_file').dispatchEvent(new Event('change'));
});
</script>
</body>
</html>
