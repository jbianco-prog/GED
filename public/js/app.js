// public/js/app.js — GED Documentaire

'use strict';

// ── CSRF token helper ──────────────────────────────────────────────────────────
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Fetch JSON helper ──────────────────────────────────────────────────────────
async function apiPost(url, formData) {
    formData.append('_csrf_token', CSRF);
    const res = await fetch(url, { method: 'POST', body: formData });
    return res.json();
}

// ── Flash message ──────────────────────────────────────────────────────────────
function showFlash(msg, type = 'info') {
    const el = document.getElementById('flash-container');
    if (!el) return;
    el.innerHTML = `<div class="alert alert-${type}">${escHtml(msg)}</div>`;
    setTimeout(() => el.innerHTML = '', 4000);
}

function escHtml(str) {
    return String(str).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

// ── Dropdown menus ─────────────────────────────────────────────────────────────
document.addEventListener('click', (e) => {
    // Close all file dropdowns except share menu (handled separately)
    if (!e.target.closest('.actions-cell')) {
        document.querySelectorAll('.actions-cell .dropdown-menu').forEach(d => d.classList.add('hidden'));
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.actions-btn');
    if (!btn) return;
    e.stopPropagation(); // empêche le clic de remonter vers .file-row
    const menu = btn.nextElementSibling;
    document.querySelectorAll('.dropdown-menu').forEach(d => { if (d !== menu) d.classList.add('hidden'); });
    menu.classList.toggle('hidden');
});

// ── Modal helper ───────────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.add('hidden'));
    }
});

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.add('hidden');
    }
});

// ── Upload ─────────────────────────────────────────────────────────────────────
(function initUpload() {
    const zone    = document.getElementById('upload-zone');
    const input   = document.getElementById('upload-input');
    const list    = document.getElementById('upload-list');
    const form    = document.getElementById('upload-form');
    if (!zone || !input || !form) return;

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    input.addEventListener('change', () => handleFiles(input.files));

    async function handleFiles(files) {
        if (!files.length) return;
        list.innerHTML = '';
        const folderId    = document.getElementById('upload-folder-id').value;
        const forceCheck  = document.getElementById('force-sensitive');
        const forceZone   = document.getElementById('admin-force-zone');
        let   anyBlocked  = false;

        for (const file of files) {
            const item = document.createElement('li');
            item.className = 'upload-item';
            item.innerHTML = `
                <span class="upload-item__name">${escHtml(file.name)}</span>
                <div class="progress"><div class="progress__bar" style="width:0%"></div></div>
                <span class="upload-item__status text-muted">Pending…</span>`;
            list.appendChild(item);

            const bar    = item.querySelector('.progress__bar');
            const status = item.querySelector('.upload-item__status');

            bar.style.width = '30%';
            status.textContent = 'Uploading…';

            const fd = new FormData();
            fd.append('files[]', file);
            fd.append('folder_id', folderId);
            fd.append('_csrf_token', CSRF);
            // Send admin force flag only if checkbox is checked
            if (forceCheck?.checked) {
                fd.append('force_sensitive', '1');
            }

            try {
                const res  = await fetch(form.action, { method: 'POST', body: fd });
                const data = await res.json();
                bar.style.width = '100%';
                const r = data.results?.[0];
                if (r?.success) {
                    const forced = forceCheck?.checked;
                    status.textContent = forced ? '✓ Saved (admin override)' : '✓ Analyzed';
                    status.style.color = forced ? 'var(--warning)' : 'var(--success)';
                } else {
                    const isSensitiveBlock = r?.error?.startsWith('⛔');
                    status.textContent = '✗ ' + (r?.error ?? 'Erreur');
                    status.style.color = 'var(--danger)';
                    // Afficher la zone "forcer" si un refus sensible a eu lieu
                    if (isSensitiveBlock && forceZone) {
                        anyBlocked = true;
                    }
                }
            } catch (err) {
                status.textContent = '✗ Network error';
                status.style.color = 'var(--danger)';
            }
        }

        // Show admin checkbox if at least one file was blocked
        if (anyBlocked && forceZone) {
            forceZone.style.display = 'block';
        }

        // Recharger seulement si aucun blocage (sinon l'admin doit choisir)
        if (!anyBlocked) {
            setTimeout(() => { window.location.reload(); }, 1200);
        }
    }
})();

// ── Rename folder ──────────────────────────────────────────────────────────────
function renameFolderPrompt(id, currentName) {
    const newName = prompt('New folder name:', currentName);
    if (!newName || newName.trim() === currentName) return;
    const fd = new FormData();
    fd.append('action', 'rename_folder');
    fd.append('folder_id', id);
    fd.append('new_name', newName.trim());
    fd.append('_csrf_token', CSRF);
    fetch('actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.reload();
            else alert('Erreur : ' + (d.error ?? 'Inconnue'));
        });
}

// ── Rename file ────────────────────────────────────────────────────────────────
function renameFilePrompt(id, currentName) {
    const ext = currentName.split('.').pop();
    const baseName = currentName.slice(0, -(ext.length + 1));
    const newBase = prompt('New name (without extension):', baseName);
    if (!newBase || newBase.trim() === baseName) return;
    const fd = new FormData();
    fd.append('action', 'rename_file');
    fd.append('file_id', id);
    fd.append('new_name', newBase.trim() + '.' + ext);
    fd.append('_csrf_token', CSRF);
    fetch('actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.reload();
            else alert('Erreur : ' + (d.error ?? 'Inconnue'));
        });
}

// ── Delete file ────────────────────────────────────────────────────────────────
function deleteFile(id, name) {
    if (!confirm('Delete "' + name + '"? This action is irreversible.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_file');
    fd.append('file_id', id);
    fd.append('_csrf_token', CSRF);
    fetch('actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.reload();
            else alert('Erreur : ' + (d.error ?? 'Inconnue'));
        });
}

// ── Delete folder ──────────────────────────────────────────────────────────────
function deleteFolder(id, name) {
    if (!confirm('Delete folder "' + name + '"? It must be empty.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_folder');
    fd.append('folder_id', id);
    fd.append('_csrf_token', CSRF);
    fetch('actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.reload();
            else alert('Erreur : ' + (d.error ?? 'Inconnue'));
        });
}

// ── Create folder modal ────────────────────────────────────────────────────────
(function initNewFolder() {
    const btn  = document.getElementById('btn-new-folder');
    const form = document.getElementById('new-folder-form');
    if (!btn || !form) return;
    btn.addEventListener('click', () => openModal('modal-new-folder'));
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        fd.append('action', 'create_folder');
        fd.append('_csrf_token', CSRF);
        const data = await fetch('actions.php', { method: 'POST', body: fd }).then(r => r.json());
        if (data.success) {
            closeModal('modal-new-folder');
            window.location.reload();
        } else {
            alert('Erreur : ' + (data.error ?? 'Inconnue'));
        }
    });
})();

// ── Sidebar tree toggle ────────────────────────────────────────────────────────
document.querySelectorAll('.tree-item__toggle').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const children = btn.closest('.tree-item').querySelector('.tree-item__children');
        if (children) {
            const open = children.style.display !== 'none';
            children.style.display = open ? 'none' : 'block';
            btn.textContent = open ? '▶' : '▼';
        }
    });
});
