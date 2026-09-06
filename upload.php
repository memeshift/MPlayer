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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
body.batch-active { max-width: 900px; }
#drop-zone {
  width: 100%;
  border: 2px dashed var(--border);
  border-radius: 6px;
  padding: 28px 16px;
  text-align: center;
  color: var(--text-dim);
  background: var(--panel);
}
#drop-zone.drag-over { border-color: var(--accent-hi); color: var(--text); }
#step-edit { display: none; }
#art-row { display: flex; flex-direction: column; gap: 14px; align-items: flex-start; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }

.batch-layout { display: flex; gap: 0; align-items: stretch; margin-top: 24px; }
#file-list {
  flex: 0 0 260px;
  border: 1px solid var(--border);
  border-radius: 4px 0 0 4px;
}
#file-list button {
  display: block;
  width: 100%;
  text-align: left;
  background: #0a0904;
  color: var(--text);
  position: relative;
  border: none;
  border-left: 3px solid transparent;
  border-bottom: 1px solid var(--border);
  border-radius: 0;
  font-family: var(--font-ui);
  padding: 10px 12px;
}
#file-list button:last-child { border-bottom: none; }
#file-list button.selected { background: var(--panel); color: var(--text); border-left-color: var(--accent); }
/* Bridges the seam between the selected tab and the panel: an 8px strip in
   the shared panel background color, straddling file-list's right border
   so it fully covers that border regardless of subpixel/zoom rounding —
   more reliable than trying to align two separate borders pixel-for-pixel. */
#file-list button.selected::after {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  right: -6px;
  width: 8px;
  background: var(--panel);
  z-index: 1;
}
#file-list button:hover:not(.selected) { background: #1f1f18; }
.file-title { display: block; font-size: 0.95rem; font-weight: bold; }
.file-status { display: block; font-size: 0.78rem; color: var(--text-dim); margin-top: 2px; }
.file-status.status-error { color: var(--error); }
#edit-panel {
  flex: 1;
  min-width: 0;
  position: relative;
  background: var(--panel);
  border: 1px solid var(--border);
  border-left: none;
  border-radius: 0 4px 4px 0;
  padding: 20px;
}
@media (max-width: 640px) {
  .batch-layout { flex-direction: column; }
  #file-list { flex: none; width: 100%; border-radius: 4px 4px 0 0; }
  #file-list button.selected::after { display: none; }
  #edit-panel { border-left: 1px solid var(--border); border-radius: 0 0 4px 4px; border-top: none; }
}
</style>
</head>
<body>
<div class="top-nav">
  <h1 style="margin:0;">Upload Tracks</h1>
  <a href="library.php">Library</a>
  <a href="logout.php">Log out</a>
</div>
<p class="lede">Find and upload MP3s, one or many at once. Each file's existing tags will be read so you can review or edit them before it's published.</p>

<?php echo mp_sr_status_html(); ?>
<div id="msg" role="alert"></div>

<div id="step-pick">
  <label for="file-input">MP3 file(s)</label>
  <div id="drop-zone">
    <p>Drag MP3s here, or</p>
    <button type="button" id="browse-btn">Browse files&hellip;</button>
    <input type="file" id="file-input" accept=".mp3,audio/mpeg" multiple style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">
  </div>
</div>

<div id="batch-view" style="display:none;">
  <div class="batch-layout">
    <nav id="file-list" role="tablist" aria-label="Staged files"></nav>
    <div id="edit-panel">
      <form id="edit-form" enctype="multipart/form-data" novalidate>
        <div id="step-edit" role="tabpanel">
          <fieldset>
            <legend>Cover art</legend>
            <div id="art-row">
              <div id="art-preview-wrap" hidden>
                <img id="art-preview" class="art-preview" alt="">
                <button type="button" id="remove-art-btn" class="art-remove-btn" aria-label="Remove cover art">&times;</button>
              </div>
              <label id="art-dropzone" class="art-dropzone" for="art-input">
                <span id="art-dropzone-title" class="art-dropzone-title">Add cover art</span>
                <span class="hint">Uploading an image here replaces the current cover art.</span>
                <input type="file" id="art-input" name="art" accept="image/jpeg,image/png,image/gif,image/webp">
              </label>
              <input type="checkbox" id="remove-art-input" hidden>
            </div>
          </fieldset>

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
          <div class="grid-2">
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
    </div>
  </div>
</div>

<div id="step-done" hidden>
  <div class="msg msg-success">All staged tracks published. They will appear in the player on its next refresh.</div>
  <button type="button" id="upload-another-btn">Upload another batch</button>
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
    const files = e.dataTransfer.files;
    if (files && files.length) stageFiles(files);
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) stageFiles(fileInput.files);
  });

  // Each item: { token, name, tags: {title,artist,album,year,track,comment,buy_url,info_url},
  //              hasArt, localArtFile, localArtUrl, removeArt,
  //              status: 'staging'|'pending'|'publishing'|'published'|'error', errorMsg }
  const items = [];
  let selected = -1;

  async function stageFiles(fileList) {
    document.body.classList.add('batch-active');
    $('step-pick').hidden = true;
    $('batch-view').style.display = 'block';

    for (const file of Array.from(fileList)) {
      const item = { name: file.name, status: 'staging', tags: {}, hasArt: false };
      items.push(item);
      renderList();
      await inspectFile(file, item);
    }
  }

  async function inspectFile(file, item) {
    announce('Reading tags for ' + file.name + '…', false);
    const fd = new FormData();
    fd.append('file', file);
    fd.append('csrf', document.querySelector('input[name=csrf]').value);
    try {
      const resp = await fetch('upload_inspect.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Upload failed.');

      item.token = data.token;
      item.tags = {
        title: data.title || '', artist: data.artist || '', album: data.album || '',
        year: data.year || '', track: data.track || '', comment: data.comment || '',
        buy_url: data.buy_url || '', info_url: data.info_url || ''
      };
      item.hasArt = !!data.has_art;
      item.status = 'pending';
      announce('Tags loaded for ' + file.name + '.', false);
    } catch (err) {
      item.status = 'error';
      item.errorMsg = err.message || 'Could not read that file.';
      announce(item.name + ': ' + item.errorMsg, true);
    }
    renderList();
    if (selected === -1 && item.status === 'pending') selectItem(items.indexOf(item));
  }

  function statusLabel(item) {
    switch (item.status) {
      case 'staging': return 'Reading tags…';
      case 'pending': return item.name;
      case 'publishing': return 'Publishing…';
      case 'published': return 'Published';
      case 'error': return item.errorMsg || 'Error';
      default: return '';
    }
  }

  function renderList() {
    const list = $('file-list');
    list.innerHTML = '';
    items.forEach((item, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'tab-' + i;
      btn.setAttribute('role', 'tab');
      btn.setAttribute('aria-controls', 'step-edit');
      btn.setAttribute('aria-selected', i === selected ? 'true' : 'false');
      btn.className = i === selected ? 'selected' : '';
      btn.disabled = item.status === 'staging';
      const titleEl = document.createElement('span');
      titleEl.className = 'file-title';
      // Falls back to the original filename while tags are still loading or
      // the title field is empty. Live typing is synced separately by the
      // #title input listener below, not here — reading the live form field
      // for the selected row would show the previous item's title for a
      // moment during selectItem()'s render-before-repopulate sequence.
      titleEl.textContent = item.tags.title || item.name;
      const statusEl = document.createElement('span');
      statusEl.className = 'file-status' + (item.status === 'error' ? ' status-error' : '');
      statusEl.textContent = statusLabel(item);
      btn.appendChild(titleEl);
      btn.appendChild(statusEl);
      btn.addEventListener('click', () => selectItem(i));
      list.appendChild(btn);
    });
  }

  function saveFormIntoSelected() {
    if (selected === -1) return;
    const item = items[selected];
    item.tags = {
      title: $('title').value, artist: $('artist').value, album: $('album').value,
      year: $('year').value, track: $('track').value, comment: $('comment').value,
      buy_url: $('buy_url').value, info_url: $('info_url').value
    };
  }

  function selectItem(i) {
    if (i === selected) return;
    saveFormIntoSelected();
    selected = i;
    renderList();
    const item = items[i];
    if (!item || item.status === 'staging') { $('step-edit').style.display = 'none'; return; }

    $('token').value = item.token;
    $('title').value = item.tags.title;
    $('artist').value = item.tags.artist;
    $('album').value = item.tags.album;
    $('year').value = item.tags.year;
    $('track').value = item.tags.track;
    $('comment').value = item.tags.comment;
    $('buy_url').value = item.tags.buy_url;
    $('info_url').value = item.tags.info_url;
    $('art-input').value = '';
    $('remove-art-input').checked = !!item.removeArt;

    const artPreview = $('art-preview');
    if (item.localArtFile) {
      // A file was picked/dropped for this item but not yet published —
      // restore it into the (shared) file input and preview so the choice
      // survives switching to another tab and back.
      const dt = new DataTransfer();
      dt.items.add(item.localArtFile);
      $('art-input').files = dt.files;
      artPreview.src = item.localArtUrl;
      artPreview.alt = 'New cover art';
      $('art-preview-wrap').hidden = false;
      $('art-dropzone-title').textContent = 'Replace cover art';
    } else if (item.removeArt) {
      $('art-preview-wrap').hidden = true;
      $('art-dropzone-title').textContent = 'Add cover art';
    } else if (item.hasArt) {
      artPreview.src = 'upload_inspect.php?preview_art=' + encodeURIComponent(item.token);
      artPreview.alt = 'Current cover art for ' + (item.tags.title || 'this track');
      $('art-preview-wrap').hidden = false;
      $('art-dropzone-title').textContent = 'Replace cover art';
    } else {
      $('art-preview-wrap').hidden = true;
      $('art-dropzone-title').textContent = 'Add cover art';
    }

    const publishBtn = $('publish-btn');
    publishBtn.disabled = (item.status === 'publishing' || item.status === 'published');
    publishBtn.textContent = item.status === 'published' ? 'Published' : 'Publish track';

    $('step-edit').setAttribute('aria-labelledby', 'tab-' + i);
    $('step-edit').style.display = 'block';
  }

  // Keep the active tab's label in sync as the title field is edited.
  $('title').addEventListener('input', () => {
    if (selected === -1) return;
    const btn = document.getElementById('tab-' + selected);
    if (btn) btn.querySelector('.file-title').textContent = $('title').value || items[selected].name;
  });

  $('remove-art-btn').addEventListener('click', () => {
    if (selected === -1) return;
    const item = items[selected];
    if (item.localArtUrl) URL.revokeObjectURL(item.localArtUrl);
    item.localArtFile = null;
    item.localArtUrl = null;
    item.removeArt = true;
    $('art-input').value = '';
    $('art-preview-wrap').hidden = true;
    $('art-dropzone-title').textContent = 'Add cover art';
    $('remove-art-input').checked = true;
  });

  // Picking a new image supersedes an earlier "remove" click, and previews
  // the local file immediately rather than waiting for it to be published.
  // Stored on the item (not just the shared input) so the choice survives
  // switching to another tab and back.
  $('art-input').addEventListener('change', () => {
    if (selected === -1) return;
    const f = $('art-input').files[0];
    if (!f) return;
    const item = items[selected];
    item.removeArt = false;
    if (item.localArtUrl) URL.revokeObjectURL(item.localArtUrl);
    item.localArtFile = f;
    item.localArtUrl = URL.createObjectURL(f);
    $('remove-art-input').checked = false;
    $('art-preview').src = item.localArtUrl;
    $('art-preview').alt = 'New cover art';
    $('art-preview-wrap').hidden = false;
    $('art-dropzone-title').textContent = 'Replace cover art';
  });

  const artDropzone = $('art-dropzone');
  ['dragover', 'dragenter'].forEach(evt => artDropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    artDropzone.classList.add('drag-over');
  }));
  ['dragleave', 'drop'].forEach(evt => artDropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    artDropzone.classList.remove('drag-over');
  }));
  artDropzone.addEventListener('drop', (e) => {
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (!f) return;
    const dt = new DataTransfer();
    dt.items.add(f);
    $('art-input').files = dt.files;
    $('art-input').dispatchEvent(new Event('change'));
  });

  function selectNextPending() {
    const next = items.findIndex(it => it.status === 'pending');
    if (next !== -1) {
      selected = -1; // force selectItem to re-render even if same index
      selectItem(next);
    } else if (items.length && items.every(it => it.status === 'published')) {
      $('batch-view').style.display = 'none';
      $('step-done').hidden = false;
    }
  }

  $('edit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (selected === -1) return;
    const item = items[selected];
    saveFormIntoSelected();

    const publishBtn = $('publish-btn');
    publishBtn.disabled = true;
    item.status = 'publishing';
    renderList();
    announce('Publishing ' + item.name + '…', false);

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

      item.status = 'published';
      announce(item.name + ' published.', false);
      renderList();
      selectNextPending();
    } catch (err) {
      item.status = 'error';
      item.errorMsg = err.message || 'Could not publish that track.';
      announce(item.name + ': ' + item.errorMsg, true);
      renderList();
      publishBtn.disabled = false;
    }
  });

  $('upload-another-btn').addEventListener('click', () => {
    items.length = 0;
    selected = -1;
    $('edit-form').reset();
    $('step-done').hidden = true;
    $('batch-view').style.display = 'none';
    document.body.classList.remove('batch-active');
    $('step-pick').hidden = false;
    announce('', false);
  });
})();
</script>
</body>
</html>
