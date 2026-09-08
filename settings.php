<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — settings.php                      │
 * │  Auth-gated admin page: which social icons show on    │
 * │  the public player, and where they link. Blank field  │
 * │  = icon hidden. Saves via settings_save.php into       │
 * │  SITE_SETTINGS_FILE's "social" key.                    │
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
<title>Settings — .+Memeshift+. Player</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
</style>
</head>
<body>
<?php echo mp_admin_nav_html('settings'); ?>
<p class="lede">Social icons shown in the player's title bar. Leave a field blank to hide that icon.</p>
<div class="page-header">
  <h1>Settings</h1>
</div>
<?php echo mp_sr_status_html(); ?>
<div id="msg" role="alert"></div>

<form id="social-form">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
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
</form>

<script>
document.getElementById('social-form').addEventListener('submit', function (e) {
  e.preventDefault();
  var msg = document.getElementById('msg');
  var sr = document.getElementById('sr-status');
  msg.className = '';
  msg.textContent = '';
  var fd = new FormData(this);
  fd.set('section', 'social');
  fetch('settings_save.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
      if (!res.ok) throw new Error(res.data.error || 'Save failed.');
      msg.className = 'msg msg-success';
      msg.textContent = 'Saved.';
      if (sr) sr.textContent = 'Settings saved.';
    })
    .catch(function (err) {
      msg.className = 'msg msg-error';
      msg.textContent = err.message;
      if (sr) sr.textContent = err.message;
    });
});
</script>
</body>
</html>
