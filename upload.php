<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — upload.php                        │
 * │  Auth-gated admin page: pick an MP3, review/edit its  │
 * │  tags (prefilled from the file), publish it into      │
 * │  music/. Talks to upload_inspect.php / upload_commit  │
 * │  .php. No changes needed to scan.php/art.php/embed.php│
 * │  /index.html — the player already reads whatever ends │
 * │  up in the MP3's own ID3 tags.                        │
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Upload Track — .+Memeshift+. Player</title>
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
#drop-zone {
  border: 2px dashed var(--border);
  border-radius: 6px;
  padding: 28px 16px;
  text-align: center;
  color: var(--text-dim);
}
#drop-zone.drag-over { border-color: var(--accent-hi); color: var(--text); }
#step-edit { display: none; }
#art-row { display: flex; gap: 14px; align-items: flex-start; flex-wrap: wrap; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="top-nav">
  <h1 style="margin:0;">Upload Track</h1>
  <a href="logout.php">Log out</a>
</div>
<p class="lede">Pick an MP3. We'll read its existing tags so you can review or edit them before it's published.</p>

<?php echo mp_sr_status_html(); ?>
<div id="msg" role="alert"></div>

<div id="step-pick">
  <label for="file-input">MP3 file</label>
  <div id="drop-zone">
    <p>Drag an MP3 here, or</p>
    <button type="button" id="browse-btn">Browse files&hellip;</button>
    <input type="file" id="file-input" accept=".mp3,audio/mpeg" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">
  </div>
</div>

<form id="edit-form" enctype="multipart/form-data" novalidate>
  <div id="step-edit">
    <fieldset>
      <legend>Cover art</legend>
      <div id="art-row">
        <img id="art-preview" class="art-preview" alt="" hidden>
        <div id="art-placeholder" class="art-preview" style="display:flex;align-items:center;justify-content:center;color:var(--text-dim);">No art</div>
        <div>
          <label for="art-input">Replace cover art</label>
          <input type="file" id="art-input" name="art" accept="image/jpeg,image/png,image/gif,image/webp">
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input type="checkbox" id="remove-art-input" style="width:auto;min-height:auto;">
            Remove existing art
          </label>
        </div>
      </div>
    </fieldset>

    <div class="grid-2">
      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="200">
      </div>
      <div class="field">
        <label for="artist">Artist</label>
        <input type="text" id="artist" name="artist" maxlength="200">
      </div>
      <div class="field">
        <label for="album">Album</label>
        <input type="text" id="album" name="album" maxlength="200">
      </div>
      <div class="field">
        <label for="year">Year</label>
        <input type="text" id="year" name="year" maxlength="4" inputmode="numeric">
      </div>
      <div class="field">
        <label for="track">Track number</label>
        <input type="text" id="track" name="track" maxlength="10" inputmode="numeric">
      </div>
    </div>

    <div class="field">
      <label for="comment">Notes / comment</label>
      <textarea id="comment" name="comment" maxlength="1000"></textarea>
    </div>
    <div class="field">
      <label for="buy_url">Buy link (https://&hellip;)</label>
      <input type="url" id="buy_url" name="buy_url" maxlength="500" placeholder="https://">
    </div>
    <div class="field">
      <label for="info_url">More-info link (https://&hellip;)</label>
      <input type="url" id="info_url" name="info_url" maxlength="500" placeholder="https://">
    </div>

    <input type="hidden" id="token" name="token">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" id="publish-btn">Publish track</button>
  </div>
</form>

<div id="step-done" hidden>
  <div class="msg msg-success">Track published. It will appear in the player on its next refresh.</div>
  <button type="button" id="upload-another-btn">Upload another</button>
</div>

<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const status = $('sr-status');
  const msg = $('msg');

  function announce(text, isError) {
    status.textContent = text;
    msg.textContent = text;
    msg.className = text ? (isError ? 'msg msg-error' : 'msg msg-success') : '';
  }

  const dropZone = $('drop-zone');
  const fileInput = $('file-input');
  const browseBtn = $('browse-btn');

  browseBtn.addEventListener('click', () => fileInput.click());

  ['dragover', 'dragenter'].forEach(evt => dropZone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
  }));
  ['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
  }));
  dropZone.addEventListener('drop', (e) => {
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) inspectFile(f);
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) inspectFile(fileInput.files[0]);
  });

  async function inspectFile(file) {
    announce('Reading tags…', false);
    const fd = new FormData();
    fd.append('file', file);
    fd.append('csrf', document.querySelector('input[name=csrf]').value);
    try {
      const resp = await fetch('upload_inspect.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Upload failed.');

      $('token').value = data.token;
      $('title').value = data.title || '';
      $('artist').value = data.artist || '';
      $('album').value = data.album || '';
      $('year').value = data.year || '';
      $('track').value = data.track || '';
      $('comment').value = data.comment || '';
      $('buy_url').value = data.buy_url || '';
      $('info_url').value = data.info_url || '';

      const artPreview = $('art-preview');
      const artPlaceholder = $('art-placeholder');
      if (data.has_art) {
        artPreview.src = 'upload_inspect.php?preview_art=' + encodeURIComponent(data.token);
        artPreview.alt = 'Current cover art for ' + (data.title || 'this track');
        artPreview.hidden = false;
        artPlaceholder.hidden = true;
      } else {
        artPreview.hidden = true;
        artPlaceholder.hidden = false;
      }

      $('step-pick').hidden = true;
      $('step-edit').style.display = 'block';
      announce('Tags loaded — review and edit below, then publish.', false);
      $('title').focus();
    } catch (err) {
      announce(err.message || 'Could not read that file.', true);
    }
  }

  $('edit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const publishBtn = $('publish-btn');
    publishBtn.disabled = true;
    announce('Publishing…', false);

    const fd = new FormData($('edit-form'));
    if ($('remove-art-input').checked) {
      fd.delete('art');
      fd.append('keep_art', '0');
    } else if (!fd.get('art') || fd.get('art').size === 0) {
      fd.delete('art');
      fd.append('keep_art', '1');
    }

    try {
      const resp = await fetch('upload_commit.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Publish failed.');

      $('step-edit').style.display = 'none';
      $('step-done').hidden = false;
      announce('Track published.', false);
    } catch (err) {
      announce(err.message || 'Could not publish that track.', true);
    } finally {
      publishBtn.disabled = false;
    }
  });

  $('upload-another-btn').addEventListener('click', () => {
    $('edit-form').reset();
    $('step-done').hidden = true;
    $('step-pick').hidden = false;
    announce('', false);
  });
})();
</script>
</body>
</html>
