<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — library.php                               │
 * │  Auth-gated admin page: browse everything already in │
 * │  music/, edit a track's tags in place, or delete it. │
 * │  Talks to library_actions.php. No changes needed to  │
 * │  scan.php/art.php/embed.php/index.html.              │
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
<title>Library — <?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
.track-row { border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px; overflow: hidden; }
.track-summary { display: flex; align-items: center; gap: 12px; padding: 10px 12px; cursor: pointer; background: none; border: none; width: 100%; text-align: left; color: inherit; font: inherit; }
.track-summary:hover, .track-summary:focus-visible { background: var(--panel); }
.track-summary:focus-visible { outline: 3px solid #707070; outline-offset: 2px; }
.track-summary img, .track-summary .no-art { width: 44px; height: 44px; border-radius: 4px; object-fit: cover; flex-shrink: 0; }
.track-summary .no-art { display: flex; align-items: center; justify-content: center; background: var(--border); color: var(--text-dim); font-size: 0.7em; }
.track-summary .art-preview[hidden], .track-summary .no-art[hidden] { display: none; }
.track-summary .meta { flex: 1; min-width: 0; }
.track-summary .meta .t-title { font-weight: 600; }
.track-summary .meta .t-artist { color: var(--text-dim); font-size: 0.9em; }
.row-select { width: 20px; height: 20px; flex-shrink: 0; display: none; }
body.select-mode .row-select { display: block; }
body.select-mode .row-handle { display: none; }
.row-handle { display: flex; align-items: center; gap: 2px; margin-left: auto; flex-shrink: 0; }
.row-handle .move-up-btn, .row-handle .move-down-btn { opacity: 0; }
.track-row:hover .row-handle .move-up-btn, .track-row:hover .row-handle .move-down-btn,
.row-handle .move-up-btn:focus-visible, .row-handle .move-down-btn:focus-visible { opacity: 1; }
.row-handle button { min-height: 28px; min-width: 28px; padding: 0; font-size: 0.8em; background: transparent; color: var(--text-dim); }
.row-handle button:hover { background: var(--border); }
.track-row:hover .row-handle button:disabled, .row-handle button:disabled { opacity: 0; cursor: not-allowed; }
.drag-grip { padding: 4px 6px; color: var(--text-dim); cursor: grab; touch-action: none; }
.track-row:hover .drag-grip, .track-row:focus-within .drag-grip { color: var(--text); }
.track-row.dragging { opacity: 0.4; }
.track-row.drag-over-before { border-top: 2px solid var(--accent); }
.track-row.drag-over-after { border-bottom: 2px solid var(--accent); }
#order-saved-msg { transition-property: opacity; transition-timing-function: linear; margin-bottom: 10px; }
#order-saved-msg[hidden] { display: none; }
#order-saved-msg.fade-out { opacity: 0; }
.list-actions { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
#delete-selected-btn[hidden] { display: none; }
.list-actions .delete-selected-btn { background: var(--error-bg); color: var(--error); border: 1px solid var(--error); }
.list-actions .delete-selected-btn:hover:not(:disabled) { background: var(--error); color: #fff; }
.list-actions .delete-selected-btn:disabled { opacity: 0.4; cursor: not-allowed; }
body.select-mode .list-actions { position: sticky; top: 0; z-index: 10; background: var(--bg); padding: 10px 0; margin: 0 0 10px; border-bottom: 1px solid var(--border); }
.track-edit { display: none; padding: 0 12px 14px; }
.track-edit.open { display: block; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }
.edit-filename { font-family: 'DM Mono', monospace; font-size: 0.85rem; color: #8f834d; word-break: break-all; margin-bottom: 12px; }
.art-row { display: flex; flex-direction: column; gap: 14px; align-items: flex-start; margin-bottom: 14px; }
.art-row .art-preview-wrap { width: 100%; }
.track-edit .art-preview { width: 100%; height: auto; aspect-ratio: 1 / 1; }
.row-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px; }
.row-actions .delete-btn { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
.row-actions .delete-btn:hover { background: var(--error-bg); border-color: var(--error); color: var(--error); }
.row-msg { transition-property: opacity; transition-timing-function: linear; margin-top: 10px; }
.row-msg.fade-out { opacity: 0; }
#empty-msg { display: none; color: var(--text-dim); }
</style>
</head>
<body>
<?php echo mp_admin_nav_html('library'); ?>
<p class="lede">Reorder, edit or delete your published tracks. </p>
<div class="page-header">
  <h1>Library</h1>
</div>
<?php echo mp_sr_status_html(); ?>
<div id="msg" role="alert"></div>
<p id="empty-msg">No tracks yet — <a href="upload.php">upload one</a>.</p>
<div class="list-actions">
  <button type="button" id="select-toggle-btn" class="btn">Select</button>
  <button type="button" id="delete-selected-btn" class="delete-selected-btn" hidden disabled>Delete selected</button>
</div>
<div id="order-saved-msg" class="msg msg-success" hidden></div>
<div id="track-list"></div>
<template id="track-template">
  <div class="track-row">
    <div class="track-summary" role="button" tabindex="0">
      <input type="checkbox" class="row-select" aria-label="Select track">
      <img class="art-preview" alt="" hidden>
      <div class="no-art" hidden>No art</div>
      <div class="meta">
        <div class="t-title"></div>
        <div class="t-artist"></div>
      </div>
      <div class="row-handle">
        <button type="button" class="move-up-btn" aria-label="Move track up">&#9650;</button>
        <button type="button" class="move-down-btn" aria-label="Move track down">&#9660;</button>
        <span class="drag-grip" aria-hidden="true">&#10303;</span>
      </div>
    </div>
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
      <div class="edit-filename"></div>
      <div class="field"><label>Artist<input type="text" name="artist" maxlength="200"></label></div>
      <div class="field"><label>Album<input type="text" name="album" maxlength="200"></label></div>
      <div class="grid-2">
        <div class="field"><label>Year<input type="text" name="year" maxlength="4" inputmode="numeric"></label></div>
        <div class="field"><label>Track number<input type="text" name="track" maxlength="10" inputmode="numeric"></label></div>
      </div>
      <div class="field"><label>Notes / comment<textarea name="comment" maxlength="1000"></textarea></label></div>
      <div class="field"><label>Buy link<input type="url" name="buy_url" maxlength="500" placeholder="https://"></label></div>
      <div class="field"><label>More-info link<input type="url" name="info_url" maxlength="500" placeholder="https://"></label></div>
      <div class="row-actions">
        <button type="button" class="delete-btn">Delete track</button>
        <button type="submit" class="save-btn">Save changes</button>
      </div>
      <div class="row-msg msg msg-success" hidden></div>
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
  const orderMsg = $('order-saved-msg');
  const selectToggleBtn = $('select-toggle-btn');
  const deleteSelectedBtn = $('delete-selected-btn');
  let justSavedFile = null;
  let justSavedAt = null;
  let openForm = null;
  let selectMode = false;
  let dragSrc = null;

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
    refreshMoveButtons();
  }

  function buildRow(t) {
    const node = template.content.cloneNode(true);

    const row = node.querySelector('.track-row');
    row.dataset.file = t.file;
    row.dataset.title = t.title || decodeURIComponent(t.file);

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

    const toggleOpen = () => {
      if (selectMode) return;
      const isOpen = editForm.classList.contains('open');
      if (openForm && openForm !== editForm) openForm.classList.remove('open');
      editForm.classList.toggle('open', !isOpen);
      openForm = !isOpen ? editForm : null;
    };
    summary.addEventListener('click', (e) => {
      if (e.target.closest('.row-select') || e.target.closest('.row-handle')) return;
      toggleOpen();
    });
    summary.addEventListener('keydown', (e) => {
      if (e.target !== summary) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleOpen();
      }
    });

    wireRowSelect(node, row);
    wireRowMove(node, row);
    wireRowDrag(row);

    const realFilename = decodeURIComponent(t.file);
    editForm.querySelector('.edit-filename').textContent = 'File: ' + realFilename;
    editForm.addEventListener('submit', (e) => onSave(e, realFilename));
    editForm.querySelector('.delete-btn').addEventListener('click', () => onDelete(realFilename, t.title));

    if (realFilename === justSavedFile) {
      if (openForm && openForm !== editForm) openForm.classList.remove('open');
      editForm.classList.add('open');
      openForm = editForm;
      const rowMsg = node.querySelector('.row-msg');
      rowMsg.textContent = 'Saved.';
      const remaining = Math.max(0, 3000 - (Date.now() - justSavedAt));
      justSavedFile = null;
      justSavedAt = null;
      const collapse = () => {
        rowMsg.hidden = true;
        rowMsg.classList.remove('fade-out');
        rowMsg.style.transitionDuration = '';
        editForm.classList.remove('open');
        if (openForm === editForm) openForm = null;
        summary.focus();
      };
      if (remaining <= 0) {
        collapse();
      } else {
        rowMsg.hidden = false;
        rowMsg.style.transitionDuration = remaining + 'ms';
        requestAnimationFrame(() => {
          requestAnimationFrame(() => rowMsg.classList.add('fade-out'));
        });
        rowMsg.addEventListener('transitionend', collapse, { once: true });
        setTimeout(collapse, remaining + 200);
      }
    }

    return node;
  }

  function wireRowSelect(node, row) {
    const checkbox = node.querySelector('.row-select');
    checkbox.addEventListener('change', updateDeleteSelectedBtn);
  }

  function updateDeleteSelectedBtn() {
    const n = listEl.querySelectorAll('.row-select:checked').length;
    deleteSelectedBtn.textContent = n ? `Delete selected (${n})` : 'Delete selected';
    deleteSelectedBtn.disabled = n === 0;
  }

  function setSelectMode(on) {
    selectMode = on;
    document.body.classList.toggle('select-mode', on);
    selectToggleBtn.textContent = on ? 'Cancel' : 'Select';
    deleteSelectedBtn.hidden = !on;
    if (!on) {
      listEl.querySelectorAll('.row-select').forEach((cb) => { cb.checked = false; });
    }
    updateDeleteSelectedBtn();
  }

  selectToggleBtn.addEventListener('click', () => setSelectMode(!selectMode));

  deleteSelectedBtn.addEventListener('click', async () => {
    const rows = [...listEl.querySelectorAll('.track-row')].filter(
      (r) => r.querySelector('.row-select').checked
    );
    if (!rows.length) return;
    const titles = rows.map((r) => r.dataset.title);
    if (!confirm(`Delete these ${rows.length} track(s)? This cannot be undone.\n\n` + titles.join('\n'))) return;

    announce('Deleting…', false);
    try {
      for (const row of rows) {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('file', decodeURIComponent(row.dataset.file));
        fd.append('csrf', csrfToken);
        const resp = await fetch('library_actions.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (!resp.ok || data.error) throw new Error(data.error || 'Could not delete one of the selected tracks.');
      }
      showOrderSaved('Selected tracks deleted.', false);
    } catch (err) {
      showOrderSaved(err.message || 'Could not delete the selected tracks.', true);
    } finally {
      setSelectMode(false);
      loadTracks();
    }
  });

  function showOrderSaved(text, isError) {
    orderMsg.textContent = text;
    orderMsg.className = 'msg ' + (isError ? 'msg-error' : 'msg-success');
    orderMsg.hidden = false;
    orderMsg.classList.remove('fade-out');
    orderMsg.style.transitionDuration = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    const collapse = () => { orderMsg.hidden = true; orderMsg.classList.remove('fade-out'); };
    requestAnimationFrame(() => {
      orderMsg.style.transitionDuration = '2000ms';
      requestAnimationFrame(() => orderMsg.classList.add('fade-out'));
    });
    orderMsg.addEventListener('transitionend', collapse, { once: true });
    setTimeout(collapse, 2200);
  }

  async function saveOrder() {
    const order = [...listEl.querySelectorAll('.track-row')].map((r) => decodeURIComponent(r.dataset.file));
    try {
      const fd = new FormData();
      fd.append('action', 'reorder');
      fd.append('order', JSON.stringify(order));
      fd.append('csrf', csrfToken);
      const resp = await fetch('library_actions.php', { method: 'POST', body: fd });
      const data = await resp.json();
      if (!resp.ok || data.error) throw new Error(data.error || 'Could not save the new order.');
      showOrderSaved('Order saved.', false);
    } catch (err) {
      showOrderSaved(err.message || 'Could not save the new order.', true);
    }
  }

  function wireRowMove(node, row) {
    const upBtn = node.querySelector('.move-up-btn');
    const downBtn = node.querySelector('.move-down-btn');
    upBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const prev = row.previousElementSibling;
      if (!prev) return;
      listEl.insertBefore(row, prev);
      row.querySelector('.move-up-btn').focus();
      refreshMoveButtons();
      saveOrder();
    });
    downBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const next = row.nextElementSibling;
      if (!next) return;
      listEl.insertBefore(next, row);
      row.querySelector('.move-down-btn').focus();
      refreshMoveButtons();
      saveOrder();
    });
  }

  function refreshMoveButtons() {
    const rows = listEl.querySelectorAll('.track-row');
    rows.forEach((r, i) => {
      r.querySelector('.move-up-btn').disabled = i === 0;
      r.querySelector('.move-down-btn').disabled = i === rows.length - 1;
    });
  }

  function wireRowDrag(row) {
    const grip = row.querySelector('.drag-grip');
    grip.addEventListener('mousedown', () => { row.draggable = true; });
    row.addEventListener('mouseup', () => {
      if (!row.classList.contains('dragging')) row.draggable = false;
    });
    row.addEventListener('dragend', () => {
      row.draggable = false;
      row.classList.remove('dragging');
      listEl.querySelectorAll('.track-row').forEach((r) => r.classList.remove('drag-over-before', 'drag-over-after'));
      if (dragSrc === row) {
        dragSrc = null;
        refreshMoveButtons();
        saveOrder();
      }
    });
    row.addEventListener('dragstart', (e) => {
      dragSrc = row;
      row.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', '');
    });
    row.addEventListener('dragover', (e) => {
      if (!dragSrc || dragSrc === row) return;
      e.preventDefault();
      const rect = row.getBoundingClientRect();
      const before = e.clientY < rect.top + rect.height / 2;
      row.classList.toggle('drag-over-before', before);
      row.classList.toggle('drag-over-after', !before);
    });
    row.addEventListener('dragleave', () => {
      row.classList.remove('drag-over-before', 'drag-over-after');
    });
    row.addEventListener('drop', (e) => {
      if (!dragSrc || dragSrc === row) return;
      e.preventDefault();
      const rect = row.getBoundingClientRect();
      const before = e.clientY < rect.top + rect.height / 2;
      listEl.insertBefore(dragSrc, before ? row : row.nextElementSibling);
      row.classList.remove('drag-over-before', 'drag-over-after');
    });
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
    const clickedAt = Date.now();

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
      justSavedFile = file;
      justSavedAt = clickedAt;
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
      showOrderSaved('Track deleted.', false);
      loadTracks();
    } catch (err) {
      showOrderSaved(err.message || 'Could not delete that track.', true);
      loadTracks();
    }
  }

  loadTracks();
})();
</script>
</body>
</html>
