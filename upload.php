<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — upload.php                                │
 * │  Auth-gated admin page: pick an MP3, review/edit its │
 * │  tags (prefilled from the file), publish it into     │
 * │  music/. Talks to upload_inspect.php / upload_commit │
 * │  .php. No changes needed to scan.php/art.php/embed.php│
 * │  /index.html — the player already reads whatever ends│
 * │  up in the MP3's own ID3 tags.                       │
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
<title>Upload Track — <?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
body.batch-active { max-width: 640px; }
.published-row { position: relative; padding-right: 40px; }
#drop-zone {
  width: 100%;
  border: 2px dashed var(--border);
  border-radius: 6px;
  padding: 20px 16px;
  text-align: center;
  color: var(--text-dim);
  background: var(--panel);
  cursor: pointer;
}
#drop-zone:hover, #drop-zone.drag-over { border-color: var(--accent-hi); color: var(--text); }
#step-edit { display: none; }
#art-row { display: flex; flex-direction: column; gap: 14px; align-items: flex-start; }
#art-row #art-preview-wrap { width: 100%; }
#art-row .art-preview { width: 100%; height: auto; aspect-ratio: 1 / 1; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }

.row-actions { display: flex; gap: 10px; margin-top: 10px; }
.row-actions .cancel-btn { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
#publish-btn.published { flex: 1; color: #1b4d2e; }
.batch-layout { display: flex; gap: 0; align-items: stretch; margin-top: 24px; }
#file-list {
  flex: 0 0 260px;
  border: 1px solid var(--border);
  border-radius: 4px 0 0 4px;
}
.file-tab {
  position: relative;
  border-left: 3px solid transparent;
  border-bottom: 1px solid var(--border);
}
.file-tab:last-child { border-bottom: none; }
.file-tab.selected:last-child { border-bottom: 1px solid var(--border); }
.file-tab.selected { border-left-color: var(--accent); }
/* Bridges the seam between the selected tab and the panel: an 8px strip in
   the shared panel background color, straddling file-list's right border
   so it fully covers that border regardless of subpixel/zoom rounding —
   more reliable than trying to align two separate borders pixel-for-pixel. */
.file-tab.selected::after {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  right: -6px;
  width: 8px;
  background: var(--panel);
  z-index: 1;
}
.tab-select {
  display: block;
  width: 100%;
  text-align: left;
  background: #0a0904;
  color: var(--text);
  border: none;
  border-radius: 0;
  font-family: var(--font-ui);
  padding: 10px 34px 10px 12px;
}
.file-tab.selected .tab-select { background: var(--panel); color: var(--text); }
.file-tab:not(.selected) .tab-select:hover { background: #1f1f18; }
.tab-remove-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  min-width: 24px;
  min-height: 24px;
  border-radius: 50%;
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text-dim);
  font-size: 15px;
  line-height: 1;
  padding: 0;
  z-index: 2;
}
.tab-remove-btn:hover { background: var(--error); border-color: var(--error); color: #fff; }
.file-title { display: block; font-size: 0.95rem; font-weight: bold; }
.file-status { display: block; font-size: 0.78rem; color: var(--text-dim); margin-top: 2px; }
.file-status.status-error { color: var(--error); }
.file-status.status-published { color: #4ade80; }
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
  .file-tab.selected::after { display: none; }
  #edit-panel { border-left: 1px solid var(--border); border-radius: 0 0 4px 4px; border-top: none; }
}
</style>
</head>
<body>
<?php echo mp_admin_nav_html('upload'); ?>
<p class="lede">Batch upload your MP3 files below.  You can edit the tags for each file before publishing to your <a href="/?fresh=1" target="_blank">live site</a>.</p>
<div class="page-header">
  <h1>Upload</h1>
</div>

<?php echo mp_sr_status_html(); ?>
<div id="msg" role="alert"></div>
<div id="published-log"></div>

<div id="step-pick">
  <label for="file-input" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;">MP3 file(s)</label>
  <div id="drop-zone">
    <p class="art-dropzone-title">Drop MP3s here</p>
    <p class="hint">Drop one or multiple MP3s here, or click to browse.</p>
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
                <span class="hint">Upload a square image (JPEG, PNG, GIF, WebP) to update cover art.</span>
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
            <label for="buy_url">Buy link</label>
            <input type="url" id="buy_url" name="buy_url" maxlength="500" placeholder="https://">
          </div>
          <div class="field">
            <label for="info_url">More-info link</label>
            <input type="url" id="info_url" name="info_url" maxlength="500" placeholder="https://">
          </div>
          <div class="field">
            <label class="checkbox-field"><input type="checkbox" id="download_enabled" name="download_enabled">Show a download link to visitors for this track</label>
          </div>

          <input type="hidden" id="token" name="token">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="row-actions">
            <button type="submit" id="publish-btn">Publish track</button>
            <button type="button" id="cancel-btn" class="cancel-btn">Remove from queue</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="step-done" hidden>
  <div class="msg msg-success">All staged tracks published. They will appear in the player on its <a href="<?php echo htmlspecialchars(APP_BASE_URL . '/?fresh=1', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">next refresh<span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;"> (opens in a new tab)</span></a>.</div>
  <button type="button" id="upload-another-btn">Upload another batch</button>
</div>

<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const status = $('sr-status');
  const msg = $('msg');
  const APP_BASE_URL = <?php echo json_encode(APP_BASE_URL); ?>;

  function announce(text, isError) {
    status.textContent = text;
    msg.textContent = text;
    msg.className = text ? (isError ? 'msg msg-error' : 'msg msg-success') : '';
  }

  const dropZone = $('drop-zone');
  const fileInput = $('file-input');

  dropZone.addEventListener('click', (e) => {
    if (e.target === fileInput) return;
    fileInput.click();
  });

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

  // Each item: { token, name, tags: {title,artist,album,year,track,comment,buy_url,info_url,download_enabled},
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
    // Some hosting WAFs 403 on punctuation like apostrophes in the
    // multipart filename header — strip it from the filename only, tags
    // (read from the file's actual ID3 data) are unaffected.
    const safeName = file.name.replace(/['"]/g, '');
    fd.append('file', file, safeName);
    fd.append('csrf', document.querySelector('input[name=csrf]').value);
    try {
      const resp = await fetch('upload_inspect.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Upload failed.');

      item.token = data.token;
      item.tags = {
        title: data.title || '', artist: data.artist || '', album: data.album || '',
        year: data.year || '', track: data.track || '', comment: data.comment || '',
        buy_url: data.buy_url || '', info_url: data.info_url || '',
        download_enabled: !!data.download_enabled
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

  const FILE_TITLE_CHAR_BUDGET = 26; // measured via uicheck: 28 chars is the one-line edge at 260px/Lora bold 0.95rem, 26 leaves a small margin
  function middleTruncate(str, maxChars) {
    if (str.length <= maxChars) return str;
    const m = str.match(/(\.[a-zA-Z0-9]{1,6})$/);
    const ext = m ? m[1] : '';
    const rest = ext ? str.slice(0, -ext.length) : str;
    const keep = Math.max(0, maxChars - ext.length - 1);
    const head = Math.ceil(keep / 2), tail = Math.floor(keep / 2);
    return rest.slice(0, head) + '…' + rest.slice(rest.length - tail) + ext;
  }

  function statusLabel(item) {
    switch (item.status) {
      case 'staging': return 'Reading tags…';
      case 'pending': return item.name;
      case 'publishing': return 'Publishing…';
      case 'published': return 'Published!';
      case 'error': return item.errorMsg || 'Error';
      default: return '';
    }
  }

  function renderList() {
    const list = $('file-list');
    list.innerHTML = '';
    items.forEach((item, i) => {
      if (item.status === 'published') return;
      const wrap = document.createElement('div');
      wrap.className = 'file-tab' + (i === selected ? ' selected' : '');
      wrap.setAttribute('role', 'presentation'); // keeps .tab-select a direct-enough child of the [role=tablist] for AT

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'tab-' + i;
      btn.className = 'tab-select';
      btn.setAttribute('role', 'tab');
      btn.setAttribute('aria-controls', 'step-edit');
      btn.setAttribute('aria-selected', i === selected ? 'true' : 'false');
      btn.disabled = item.status === 'staging';
      const titleEl = document.createElement('span');
      titleEl.className = 'file-title';
      // Falls back to the original filename while tags are still loading or
      // the title field is empty. Live typing is synced separately by the
      // #title input listener below, not here — reading the live form field
      // for the selected row would show the previous item's title for a
      // moment during selectItem()'s render-before-repopulate sequence.
      const fullTitle = item.tags.title || item.name;
      titleEl.textContent = middleTruncate(fullTitle, FILE_TITLE_CHAR_BUDGET);
      titleEl.title = fullTitle;
      const statusEl = document.createElement('span');
      statusEl.className = 'file-status' + (item.status === 'error' ? ' status-error' : item.status === 'published' ? ' status-published' : '');
      statusEl.textContent = statusLabel(item);
      btn.appendChild(titleEl);
      btn.appendChild(statusEl);
      btn.addEventListener('click', () => selectItem(i));
      wrap.appendChild(btn);

      if (item.status !== 'publishing' && item.status !== 'published') {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'tab-remove-btn';
        removeBtn.setAttribute('aria-label', 'Remove ' + (item.tags.title || item.name) + ' from queue');
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          cancelItem(i);
        });
        wrap.appendChild(removeBtn);
      }

      list.appendChild(wrap);
    });
  }

  const FIELD_IDS = ['title', 'artist', 'album', 'year', 'track', 'comment', 'buy_url', 'info_url', 'download_enabled', 'art-input', 'remove-art-input'];
  function setFieldsDisabled(disabled) {
    FIELD_IDS.forEach(id => { $(id).disabled = disabled; });
  }

  function saveFormIntoSelected() {
    if (selected === -1) return;
    const item = items[selected];
    item.tags = {
      title: $('title').value, artist: $('artist').value, album: $('album').value,
      year: $('year').value, track: $('track').value, comment: $('comment').value,
      buy_url: $('buy_url').value, info_url: $('info_url').value,
      download_enabled: $('download_enabled').checked
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
    $('download_enabled').checked = !!item.tags.download_enabled;
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
      $('art-dropzone-title').textContent = 'Update cover art';
    } else if (item.removeArt) {
      $('art-preview-wrap').hidden = true;
      $('art-dropzone-title').textContent = 'Add cover art';
    } else if (item.hasArt) {
      artPreview.src = 'upload_inspect.php?preview_art=' + encodeURIComponent(item.token);
      artPreview.alt = 'Current cover art for ' + (item.tags.title || 'this track');
      $('art-preview-wrap').hidden = false;
      $('art-dropzone-title').textContent = 'Update cover art';
    } else {
      $('art-preview-wrap').hidden = true;
      $('art-dropzone-title').textContent = 'Add cover art';
    }

    const isPublished = item.status === 'published';
    setFieldsDisabled(isPublished);
    const publishBtn = $('publish-btn');
    publishBtn.disabled = (item.status === 'publishing' || isPublished);
    publishBtn.textContent = isPublished ? 'Published' : 'Publish track';
    publishBtn.classList.toggle('published', isPublished);
    const cancelBtn = $('cancel-btn');
    cancelBtn.disabled = (item.status === 'publishing' || isPublished);
    cancelBtn.hidden = isPublished;

    $('step-edit').setAttribute('aria-labelledby', 'tab-' + i);
    $('step-edit').style.display = 'block';
  }

  // Keep the active tab's label in sync as the title field is edited.
  $('title').addEventListener('input', () => {
    if (selected === -1) return;
    const btn = document.getElementById('tab-' + selected);
    if (btn) {
      const fullTitle = $('title').value || items[selected].name;
      const titleEl = btn.querySelector('.file-title');
      titleEl.textContent = middleTruncate(fullTitle, FILE_TITLE_CHAR_BUDGET);
      titleEl.title = fullTitle;
    }
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
    $('art-dropzone-title').textContent = 'Update cover art';
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
    $('cancel-btn').disabled = true;
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
      setFieldsDisabled(true);
      status.textContent = item.name + ' published.'; // SR-only; the visible confirmation is the persistent row below
      msg.textContent = ''; // clear the transient "Publishing X…" line — it has no meaning once done
      msg.className = '';
      renderList();

      const row = document.createElement('div');
      row.className = 'msg msg-success published-row';
      const dismissBtn = document.createElement('button');
      dismissBtn.type = 'button';
      dismissBtn.className = 'tab-remove-btn';
      dismissBtn.setAttribute('aria-label', 'Dismiss published notice for ' + item.name);
      dismissBtn.textContent = '×';
      dismissBtn.addEventListener('click', () => row.remove());
      row.appendChild(dismissBtn);

      row.appendChild(document.createTextNode(item.name + ' '));
      const publishedLink = document.createElement('a');
      publishedLink.href = APP_BASE_URL + '/?t=' + data.file + '&fresh=1';
      publishedLink.target = '_blank';
      publishedLink.rel = 'noopener noreferrer';
      publishedLink.textContent = 'published';
      const newTabCue = document.createElement('span');
      newTabCue.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;';
      newTabCue.textContent = ' (opens in a new tab)';
      publishedLink.appendChild(newTabCue);
      row.appendChild(publishedLink);
      row.appendChild(document.createTextNode('.'));

      $('published-log').appendChild(row);

      selectNextPending();
    } catch (err) {
      item.status = 'error';
      item.errorMsg = err.message || 'Could not publish that track.';
      announce(item.name + ': ' + item.errorMsg, true);
      renderList();
      publishBtn.disabled = false;
      $('cancel-btn').disabled = false;
    }
  });

  function cancelItem(i) {
    if (i < 0 || i >= items.length) return;
    const item = items[i];
    if (item.status === 'publishing') return; // request in flight, ignore

    if (item.localArtUrl) URL.revokeObjectURL(item.localArtUrl);
    items.splice(i, 1);

    const wasSelected = (i === selected);
    if (wasSelected) selected = -1;
    else if (selected > i) selected -= 1; // keep pointing at the same logical item post-splice

    if (items.length === 0) {
      $('edit-form').reset();
      $('batch-view').style.display = 'none';
      document.body.classList.remove('batch-active');
      $('step-pick').hidden = false;
      announce('', false);
      return;
    }

    renderList();
    if (wasSelected) {
      selectNextPending();
      if (selected === -1) selectItem(0); // only error-status items remain
    }
  }

  $('cancel-btn').addEventListener('click', () => cancelItem(selected));

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
