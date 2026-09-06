<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — library.php                       │
 * │  Auth-gated admin page: browse everything already in  │
 * │  music/, edit a track's tags in place, or delete it.  │
 * │  Talks to library_actions.php. No changes needed to   │
 * │  scan.php/art.php/embed.php/index.html.               │
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
<title>Library — .+Memeshift+. Player</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
.track-row { border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px; overflow: hidden; }
.track-summary { display: flex; align-items: center; gap: 12px; padding: 10px 12px; cursor: pointer; background: none; border: none; width: 100%; text-align: left; color: inherit; font: inherit; }
.track-summary img, .track-summary .no-art { width: 44px; height: 44px; border-radius: 4px; object-fit: cover; flex-shrink: 0; }
.track-summary .no-art { display: flex; align-items: center; justify-content: center; background: var(--border); color: var(--text-dim); font-size: 0.7em; }
.track-summary .art-preview[hidden], .track-summary .no-art[hidden] { display: none; }
.track-summary .meta { flex: 1; min-width: 0; }
.track-summary .meta .t-title { font-weight: 600; }
.track-summary .meta .t-artist { color: var(--text-dim); font-size: 0.9em; }
.track-edit { display: none; padding: 0 12px 14px; }
.track-edit.open { display: block; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }
.art-row { display: flex; gap: 14px; align-items: flex-start; flex-wrap: wrap; margin-bottom: 14px; }
.track-edit .art-preview { width: 70px; height: 70px; }
.row-actions { display: flex; gap: 10px; margin-top: 10px; }
.row-actions .delete-btn { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
#empty-msg { display: none; color: var(--text-dim); }
</style>
</head>
<body>
<div class="top-nav">
  <h1 style="margin:0;">Library</h1>
  <a href="upload.php">Upload</a>
  <a href="logout.php">Log out</a>
</div>
<p class="lede">Browse what's already published. Click a track to edit its tags or delete it.</p>

<?php echo mp_sr_status_html(); ?>
<div id="msg" role="alert"></div>
<p id="empty-msg">No tracks yet — <a href="upload.php">upload one</a>.</p>
<div id="track-list"></div>

<template id="track-template">
  <div class="track-row">
    <button type="button" class="track-summary">
      <img class="art-preview" alt="" hidden>
      <div class="no-art" hidden>No art</div>
      <div class="meta">
        <div class="t-title"></div>
        <div class="t-artist"></div>
      </div>
    </button>
    <form class="track-edit" enctype="multipart/form-data" novalidate>
      <div class="art-row">
        <div class="art-preview-wrap" hidden>
          <img class="edit-art-preview art-preview" alt="">
          <button type="button" class="art-remove-btn" aria-label="Remove cover art">&times;</button>
        </div>
        <label class="art-dropzone">
          <span class="art-dropzone-title">Add cover art</span>
          <span class="hint">Drop an image here, or click to browse.</span>
          <input type="file" class="art-input" name="art" accept="image/jpeg,image/png,image/gif,image/webp">
        </label>
        <input type="checkbox" class="remove-art-input" hidden>
      </div>
      <div class="field"><label>Title<input type="text" name="title" maxlength="200"></label></div>
      <div class="field"><label>Artist<input type="text" name="artist" maxlength="200"></label></div>
      <div class="field"><label>Album<input type="text" name="album" maxlength="200"></label></div>
      <div class="grid-2">
        <div class="field"><label>Year<input type="text" name="year" maxlength="4" inputmode="numeric"></label></div>
        <div class="field"><label>Track number<input type="text" name="track" maxlength="10" inputmode="numeric"></label></div>
      </div>
      <div class="field"><label>Notes / comment<textarea name="comment" maxlength="1000"></textarea></label></div>
      <div class="field"><label>Buy link (https://&hellip;)<input type="url" name="buy_url" maxlength="500" placeholder="https://"></label></div>
      <div class="field"><label>More-info link (https://&hellip;)<input type="url" name="info_url" maxlength="500" placeholder="https://"></label></div>
      <div class="row-actions">
        <button type="submit" class="save-btn">Save changes</button>
        <button type="button" class="delete-btn">Delete track</button>
      </div>
    </form>
  </div>
</template>

<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const status = $('sr-status');
  const msg = $('msg');
  const csrfToken = '<?php echo addslashes($csrf); ?>';

  function announce(text, isError) {
    status.textContent = text;
    msg.textContent = text;
    msg.className = text ? (isError ? 'msg msg-error' : 'msg msg-success') : '';
  }

  const listEl = $('track-list');
  const emptyMsg = $('empty-msg');
  const template = $('track-template');

  async function loadTracks() {
    announce('Loading library…', false);
    try {
      const resp = await fetch('library_actions.php?action=list');
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Could not load the library.');
      render(data.tracks);
      announce('', false);
    } catch (err) {
      announce(err.message || 'Could not load the library.', true);
    }
  }

  function render(tracks) {
    listEl.innerHTML = '';
    emptyMsg.style.display = tracks.length ? 'none' : 'block';
    for (const t of tracks) listEl.appendChild(buildRow(t));
  }

  function buildRow(t) {
    const node = template.content.cloneNode(true);

    const summary = node.querySelector('.track-summary');
    const summaryArt = node.querySelector('.track-summary .art-preview');
    const summaryNoArt = node.querySelector('.track-summary .no-art');
    if (t.has_art) {
      summaryArt.src = 'art.php?f=' + t.file;
      summaryArt.alt = 'Cover art for ' + (t.title || t.file);
      summaryArt.hidden = false;
    } else {
      summaryNoArt.hidden = false;
    }
    node.querySelector('.t-title').textContent = t.title || decodeURIComponent(t.file);
    node.querySelector('.t-artist').textContent = t.artist || '';

    const editForm = node.querySelector('.track-edit');
    editForm.querySelector('[name=title]').value = t.title || '';
    editForm.querySelector('[name=artist]').value = t.artist || '';
    editForm.querySelector('[name=album]').value = t.album || '';
    editForm.querySelector('[name=year]').value = t.year || '';
    editForm.querySelector('[name=track]').value = t.track || '';
    editForm.querySelector('[name=comment]').value = t.comment || '';
    editForm.querySelector('[name=buy_url]').value = t.buy_url || '';
    editForm.querySelector('[name=info_url]').value = t.info_url || '';

    const editArt = editForm.querySelector('.edit-art-preview');
    const editPreviewWrap = editForm.querySelector('.art-preview-wrap');
    const editDropzoneTitle = editForm.querySelector('.art-dropzone-title');
    if (t.has_art) {
      editArt.src = 'art.php?f=' + t.file;
      editArt.alt = '';
      editPreviewWrap.hidden = false;
      editDropzoneTitle.textContent = 'Replace cover art';
    }
    wireArtDropzone(editForm);

    summary.addEventListener('click', () => {
      editForm.classList.toggle('open');
    });

    const realFilename = decodeURIComponent(t.file);
    editForm.addEventListener('submit', (e) => onSave(e, realFilename));
    editForm.querySelector('.delete-btn').addEventListener('click', () => onDelete(realFilename, t.title));

    return node;
  }

  // Mirrors upload.php's art-dropzone behavior (drag/drop, click-to-browse,
  // remove button), scoped to one track row instead of a single global form.
  function wireArtDropzone(editForm) {
    const dropzone = editForm.querySelector('.art-dropzone');
    const dropzoneTitle = editForm.querySelector('.art-dropzone-title');
    const input = editForm.querySelector('.art-input');
    const previewWrap = editForm.querySelector('.art-preview-wrap');
    const preview = editForm.querySelector('.edit-art-preview');
    const removeBtn = editForm.querySelector('.art-remove-btn');
    const removeInput = editForm.querySelector('.remove-art-input');
    let localArtUrl = null;

    removeBtn.addEventListener('click', () => {
      input.value = '';
      if (localArtUrl) { URL.revokeObjectURL(localArtUrl); localArtUrl = null; }
      previewWrap.hidden = true;
      dropzoneTitle.textContent = 'Add cover art';
      removeInput.checked = true;
    });

    input.addEventListener('change', () => {
      const f = input.files[0];
      if (!f) return;
      removeInput.checked = false;
      if (localArtUrl) URL.revokeObjectURL(localArtUrl);
      localArtUrl = URL.createObjectURL(f);
      preview.src = localArtUrl;
      preview.alt = 'New cover art';
      previewWrap.hidden = false;
      dropzoneTitle.textContent = 'Replace cover art';
    });

    ['dragover', 'dragenter'].forEach(evt => dropzone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.add('drag-over');
    }));
    ['dragleave', 'drop'].forEach(evt => dropzone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.remove('drag-over');
    }));
    dropzone.addEventListener('drop', (e) => {
      const f = e.dataTransfer.files && e.dataTransfer.files[0];
      if (!f) return;
      const dt = new DataTransfer();
      dt.items.add(f);
      input.files = dt.files;
      input.dispatchEvent(new Event('change'));
    });
  }

  async function onSave(e, file) {
    e.preventDefault();
    const form = e.target;
    const saveBtn = form.querySelector('.save-btn');
    saveBtn.disabled = true;
    announce('Saving…', false);

    const fd = new FormData(form);
    fd.append('action', 'edit');
    fd.append('file', file);
    fd.append('csrf', csrfToken);
    if (form.querySelector('.remove-art-input').checked) {
      fd.delete('art');
      fd.append('keep_art', '0');
    } else if (!fd.get('art') || fd.get('art').size === 0) {
      fd.delete('art');
      fd.append('keep_art', '1');
    }

    try {
      const resp = await fetch('library_actions.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Could not save changes.');
      announce('Saved.', false);
      loadTracks();
    } catch (err) {
      announce(err.message || 'Could not save changes.', true);
      loadTracks();
    } finally {
      saveBtn.disabled = false;
    }
  }

  async function onDelete(file, title) {
    if (!confirm('Delete "' + (title || file) + '"? This cannot be undone.')) return;
    announce('Deleting…', false);
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('file', file);
    fd.append('csrf', csrfToken);
    try {
      const resp = await fetch('library_actions.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Could not delete that track.');
      announce('Track deleted.', false);
      loadTracks();
    } catch (err) {
      announce(err.message || 'Could not delete that track.', true);
      loadTracks();
    }
  }

  loadTracks();
})();
</script>
</body>
</html>
