<?php
// public/index.php — Explorateur de fichiers (version complète avec recherche + profil)
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$rootFolder      = Folder::root();
$currentFolderId = (int)($_GET['folder'] ?? $rootFolder['id'] ?? 1);
$currentFolder   = Folder::find($currentFolderId);
if (!$currentFolder) { $currentFolderId = $rootFolder['id'] ?? 1; $currentFolder = $rootFolder; }

$subFolders  = Folder::children($currentFolderId);
$files       = FileModel::inFolder($currentFolderId);
$breadcrumb  = Folder::breadcrumb($currentFolderId);
$sidebarTree = Folder::getTree($rootFolder['id'] ?? 1);

function renderTree(array $tree, int $currentId, int $d = 0): void {
    if (empty($tree)) return;
    echo '<ul class="tree" style="padding-left:'.($d*12).'px">';
    foreach ($tree as $n) {
        $hasC = !empty($n['children']);
        echo '<li class="tree-item"><div class="tree-item__row '.($n['id']==$currentId?'active':'').'">';
        echo '<span class="tree-item__toggle">'.($hasC?'▼':'&nbsp;').'</span>';
        echo '<span style="margin-right:4px">📁</span>';
        echo '<a href="'.APP_URL.'/index.php?folder='.$n['id'].'" style="color:inherit;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'.H::e($n['nom']).'</a>';
        echo '</div>';
        if ($hasC) { echo '<div class="tree-item__children">'; renderTree($n['children'], $currentId, $d+1); echo '</div>'; }
        echo '</li>';
    }
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= H::e(Auth::csrfToken()) ?>">
    <title><?= H::e($currentFolder['nom'] ?? 'Documents') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
</head>
<body>
<header class="topbar">
    <div class="topbar__logo"><?= APP_NAME ?></div>
    <div class="topbar__spacer"></div>
    <form class="topbar__search" action="search.php" method="GET">
        <button type="submit">🔍</button>
        <input type="search" name="q" placeholder="Rechercher…"
               value="" autocomplete="off">
    </form>
    <div class="topbar__user" style="margin-left:12px">
        <a href="<?= APP_URL ?>/profile.php" style="display:flex;align-items:center;gap:6px;color:#fff;text-decoration:none">
            <div class="topbar__avatar"><?= strtoupper(substr($_SESSION['user_nom']??'?',0,2)) ?></div>
            <?= H::e($_SESSION['user_nom'] ?? '') ?>
        </a>
        <?php if (Auth::isAdmin()): ?><a href="<?= APP_URL ?>/admin.php" class="topbar__btn" style="font-size:12px">⚙ Admin</a><?php endif; ?>
        <a href="<?= APP_URL ?>/logout.php" class="topbar__btn">Sign out</a>
    </div>
</header>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar__section">
            <div class="sidebar__label">Navigation</div>
            <a class="sidebar__item active" href="<?= APP_URL ?>/index.php"><span class="ico">📁</span> Documents</a>
            <a class="sidebar__item" href="<?= APP_URL ?>/search.php"><span class="ico">🔍</span> Recherche</a>
            <?php if (Auth::isAdmin()): ?>
            <a class="sidebar__item" href="<?= APP_URL ?>/admin.php"><span class="ico">⚙</span> Administration</a>
            <a class="sidebar__item" href="<?= APP_URL ?>/logs.php"><span class="ico">📋</span> Journaux</a>
            <?php endif; ?>
        </div>
        <div class="sidebar__section">
            <div class="sidebar__label">Arborescence</div>
            <?php renderTree($sidebarTree, $currentFolderId); ?>
        </div>
    </aside>
    <div class="main">
        <nav class="breadcrumb">
            <?php foreach ($breadcrumb as $i => $c): ?>
                <?php if ($i < count($breadcrumb)-1): ?>
                    <a href="index.php?folder=<?= $c['id'] ?>"><?= H::e($c['nom']) ?></a><span>›</span>
                <?php else: ?>
                    <span class="current"><?= H::e($c['nom']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div id="flash-container"></div>
        <?php $flash = H::getFlash(); if ($flash): ?>
        <div style="padding:8px 24px 0"><div class="alert alert-<?= H::e($flash['type']) ?>"><?= H::e($flash['msg']) ?></div></div>
        <?php endif; ?>
        <div class="toolbar">
            <button class="btn btn-primary" id="btn-upload">⬆ Upload files</button>
            <div class="toolbar__sep"></div>
            <button class="btn btn-secondary" id="btn-new-folder">📁 New folder</button>
            <?php if (count($subFolders)+count($files)===0 && $currentFolderId!==($rootFolder['id']??1)): ?>
            <div class="toolbar__sep"></div>
            <button class="btn btn-danger btn-sm" onclick="deleteFolder(<?= $currentFolderId ?>,'<?= H::e(addslashes($currentFolder['nom'])) ?>')">🗑 Delete this folder</button>
            <?php endif; ?>
            <div class="ml-auto" style="font-size:12px;color:var(--gray-400)"><?= count($subFolders) ?> folder(s) · <?= count($files) ?> file(s)</div>
        </div>
        <!-- Barre d'actions groupées (visible uniquement quand fichiers sélectionnés) -->
        <div id="bulk-bar" class="bulk-bar hidden">
            <span id="bulk-count" class="bulk-bar__count">0 file(s) selected</span>
            <div class="toolbar__sep" style="background:rgba(255,255,255,.3)"></div>
            <button class="btn bulk-btn" id="btn-bulk-reanalyze"
                    onclick="bulkReanalyze()" title="Re-analyze selected files">
                🔄 Re-analyze
            </button>
            <?php if (Auth::isAdmin()): ?>
            <button class="btn bulk-btn bulk-btn--danger" id="btn-bulk-delete"
                    onclick="bulkDelete()" title="Delete selected files">
                🗑 Delete
            </button>
            <?php endif; ?>
            <button class="btn bulk-btn" onclick="clearSelection()" style="margin-left:auto">
                ✕ Deselect all
            </button>
        </div>

        <div class="content">
            <?php if (empty($subFolders) && empty($files)): ?>
            <div class="empty-state"><div class="empty-state__icon">📂</div><div class="empty-state__text">This folder is empty.<br>Upload files or create a subfolder.</div></div>
            <?php else: ?>
            <table class="file-table">
                <thead><tr><th style="width:36px"><input type="checkbox" id="chk-all" title="Select all" style="cursor:pointer;width:15px;height:15px"></th><th>Name</th><th style="width:80px">Size</th><th style="width:110px">Uploaded by</th><th style="width:100px">Date</th><th style="width:120px">Sensitivity</th><th style="width:70px">DLP</th><th style="width:300px;white-space:nowrap">AI Summary</th><th style="width:40px"></th></tr></thead>
                <tbody>
                    <?php foreach ($subFolders as $fo): ?>
                    <tr class="folder-row">
                        <td style="text-align:center"><span class="folder-icon">📁</span></td>
                        <td><a href="index.php?folder=<?= $fo['id'] ?>" style="color:inherit;font-weight:500"><?= H::e($fo['nom']) ?></a> <span class="text-muted text-small">— <?= $fo['nb_sous_dossiers'] ?> ss-folder(s) · <?= $fo['nb_fichiers'] ?> file(s)</span></td>
                        <td class="text-muted">—</td>
                        <td class="text-muted text-small"><?= H::e($fo['created_by_nom']??'—') ?></td>
                        <td class="text-muted text-small"><?= H::formatDate($fo['created_at']) ?></td>
                        <td>—</td>
                        <td></td>
                        <td class="actions-cell">
                            <button class="actions-btn">⋯</button>
                            <div class="dropdown-menu hidden">
                                <a href="index.php?folder=<?= $fo['id'] ?>">📂 Open</a>
                                <button onclick="renameFolderPrompt(<?= $fo['id'] ?>,'<?= H::e(addslashes($fo['nom'])) ?>')">✏ Rename</button>
                                <div class="sep"></div>
                                <button class="danger" onclick="deleteFolder(<?= $fo['id'] ?>,'<?= H::e(addslashes($fo['nom'])) ?>')">🗑 Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($files as $fi): ?>
                    <tr data-file-id="<?= $fi['id'] ?>" class="file-row">
                        <td style="text-align:center">
                            <input type="checkbox" class="file-chk" data-id="<?= $fi['id'] ?>"
                                   data-name="<?= H::e(addslashes($fi['nom_courant'])) ?>"
                                   style="cursor:pointer;width:15px;height:15px"
                                   onclick="updateSelection(event)">
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px">
                                <span class="file-icon <?= H::fileIconClass($fi['extension']) ?>"><?= H::fileIcon($fi['extension']) ?></span>
                                <a href="file.php?id=<?= $fi['id'] ?>" style="color:var(--gray-900)"><?= H::e($fi['nom_courant']) ?></a>
                            </div>
                        </td>
                        <td class="text-muted text-small"><?= H::formatSize($fi['taille']) ?></td>
                        <td class="text-muted text-small"><?= H::e($fi['uploaded_by_nom']??'—') ?></td>
                        <td class="text-muted text-small"><?= H::formatDate($fi['created_at']) ?></td>
                        <td><?= H::sensitivityBadge($fi['niveau_sensibilite']) ?></td>
                        <td style="text-align:center">
                            <?php
                            $dlpForFile = Settings::isDlpEnforcedForFile($fi['id']);
                            $metaFi     = FileModel::getMetadata($fi['id']);
                            $coForced   = false;
                            if (!empty($metaFi['societe'])) {
                                foreach (Settings::dlpCompanies() as $co) {
                                    if (stripos(trim($metaFi['societe']), trim($co)) !== false) { $coForced = true; break; }
                                }
                            }
                            if ($coForced): ?>
                                <span title="Protected company — DLP enforced" style="cursor:default">🛡️🔴</span>
                            <?php elseif (Settings::dlpEnabled()): ?>
                                <span title="Global DLP active" style="cursor:default">🛡️</span>
                            <?php else: ?>
                                <span title="DLP inactive" style="color:var(--gray-300);cursor:default">🔓</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($fi['resume_ai'])): ?>
                                <?php foreach (array_map('trim', explode(',', $fi['resume_ai'])) as $mot): ?>
                                <span style="display:inline-block;background:var(--primary-light);color:var(--primary);border-radius:10px;padding:1px 7px;font-size:11px;font-weight:600;margin:1px;white-space:nowrap"><?= H::e($mot) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?><span class="text-muted text-small">—</span><?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <button class="actions-btn">⋯</button>
                            <div class="dropdown-menu hidden">
                                <a href="file.php?id=<?= $fi['id'] ?>">🔍 Details</a>
                                <a href="preview.php?id=<?= $fi['id'] ?>" target="_blank">👁 Preview</a>
                                <a href="download.php?id=<?= $fi['id'] ?>">⬇ Download</a>
                                <button onclick="renameFilePrompt(<?= $fi['id'] ?>,'<?= H::e(addslashes($fi['nom_courant'])) ?>')">✏ Rename</button>
                                <div class="sep"></div>
                                <button class="danger" onclick="deleteFile(<?= $fi['id'] ?>,'<?= H::e(addslashes($fi['nom_courant'])) ?>')">🗑 Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Modal Upload -->
<div class="modal-overlay hidden" id="modal-upload">
    <div class="modal">
        <div class="modal__header"><span class="modal__title">Upload files</span><button class="modal__close" onclick="closeModal('modal-upload')">×</button></div>
        <p class="text-muted text-small" style="margin-bottom:12px">Target folder: <strong><?= H::e($currentFolder['nom']) ?></strong></p>
        <form id="upload-form" action="upload.php" method="POST" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>
            <input type="hidden" id="upload-folder-id" name="folder_id" value="<?= $currentFolderId ?>">
            <div class="upload-zone" id="upload-zone">
                <div class="upload-zone__icon">📤</div>
                <div class="upload-zone__text">Drag files here or click to select</div>
                <div class="upload-zone__sub">Max <?= MAX_FILE_SIZE/1024/1024 ?> MB per file</div>
                <input type="file" id="upload-input" name="files[]" multiple style="display:none">
            </div>
            <ul class="upload-list" id="upload-list"></ul>
        </form>
        <?php if (Auth::isAdmin()): ?>
        <div id="admin-force-zone" style="margin-top:12px;padding:10px 12px;background:#fff4ce;
             border:1px solid #ffe08a;border-radius:4px;display:none">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#7a4100">
                <input type="checkbox" id="force-sensitive" style="width:16px;height:16px;cursor:pointer">
                <span>
                    <strong>⚠ Force upload of sensitive documents</strong><br>
                    <span style="font-size:12px;font-weight:400">
                        Files classified as sensitive will still be saved in the DMS.
                        This action is recorded in the audit logs.
                    </span>
                </span>
            </label>
        </div>
        <?php endif; ?>
        <div class="modal__footer"><button class="btn btn-secondary" onclick="closeModal('modal-upload')">Close</button></div>
    </div>
</div>
<!-- Modal New folder -->
<div class="modal-overlay hidden" id="modal-new-folder">
    <div class="modal">
        <div class="modal__header"><span class="modal__title">New folder</span><button class="modal__close" onclick="closeModal('modal-new-folder')">×</button></div>
        <form id="new-folder-form">
            <input type="hidden" name="parent_id" value="<?= $currentFolderId ?>">
            <div class="form-group"><label class="form-label">Folder name</label><input class="form-control" type="text" id="folder-name" name="folder_name" placeholder="E.g. Contracts 2026" required autofocus maxlength="200"></div>
            <div class="modal__footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-new-folder')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
document.getElementById('btn-upload')?.addEventListener('click',()=>openModal('modal-upload'));

// ── Sélection multiple ────────────────────────────────────────────────────────
// CSRF est déjà défini dans app.js — on réutilise la variable globale

function updateSelection(e) {
    if (e) e.stopPropagation();
    const checked = document.querySelectorAll('.file-chk:checked');
    const total   = document.querySelectorAll('.file-chk').length;
    const bar     = document.getElementById('bulk-bar');
    const count   = document.getElementById('bulk-count');
    const chkAll  = document.getElementById('chk-all');

    // Mettre à jour l'état de la case "tout sélectionner"
    chkAll.checked       = checked.length === total && total > 0;
    chkAll.indeterminate = checked.length > 0 && checked.length < total;

    // Afficher/masquer la barre d'actions
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        count.textContent = checked.length + ' fichier(s) sélectionné(s)';
    } else {
        bar.classList.add('hidden');
    }

    // Surligner les lignes sélectionnées
    document.querySelectorAll('.file-row').forEach(row => {
        const chk = row.querySelector('.file-chk');
        row.classList.toggle('selected', chk?.checked ?? false);
    });
}

function clearSelection() {
    document.querySelectorAll('.file-chk').forEach(c => c.checked = false);
    const chkAll = document.getElementById('chk-all');
    if (chkAll) { chkAll.checked = false; chkAll.indeterminate = false; }
    updateSelection(null);
}

// Case "tout sélectionner"
document.getElementById('chk-all')?.addEventListener('change', function() {
    document.querySelectorAll('.file-chk').forEach(c => c.checked = this.checked);
    updateSelection(null);
});

// Clic sur une ligne → sélectionner (sauf si clic sur lien/bouton)
// Sélection uniquement via la case à cocher — pas de clic sur la ligne entière

// ── Récupérer les IDs sélectionnés ────────────────────────────────────────────
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.file-chk:checked'))
        .map(c => parseInt(c.dataset.id));
}
function getSelectedNames() {
    return Array.from(document.querySelectorAll('.file-chk:checked'))
        .map(c => c.dataset.name);
}

// ── Suppression groupée ───────────────────────────────────────────────────────
async function bulkDelete() {
    const ids   = getSelectedIds();
    const names = getSelectedNames();
    if (!ids.length) return;

    if (!confirm(`Supprimer ${ids.length} fichier(s) ?\n\n${names.slice(0,5).join('\n')}${names.length > 5 ? '\n…' : ''}\n\nCette action est irréversible.`)) return;

    const btn = document.getElementById('btn-bulk-delete');
    btn.disabled = true;
    btn.textContent = '⏳ Suppression…';

    let ok = 0, errors = [];
    for (const id of ids) {
        const fd = new FormData();
        fd.append('action',      'delete_file');
        fd.append('file_id',     id);
        fd.append('_csrf_token', CSRF);
        try {
            const r = await fetch('actions.php', { method:'POST', body:fd });
            const d = await r.json();
            if (d.success) ok++;
            else errors.push(d.error ?? 'Erreur inconnue');
        } catch { errors.push('Erreur réseau'); }
    }

    if (errors.length) alert(`${ok} supprimé(s). ${errors.length} erreur(s) : ${errors.join(', ')}`);
    window.location.reload();
}

// ── Réanalyse groupée ─────────────────────────────────────────────────────────
async function bulkReanalyze() {
    const ids = getSelectedIds();
    if (!ids.length) return;

    const btn = document.getElementById('btn-bulk-reanalyze');
    btn.disabled = true;
    btn.textContent = `⏳ Analyse 0/${ids.length}…`;

    let ok = 0, errors = [];
    for (let i = 0; i < ids.length; i++) {
        btn.textContent = `⏳ Analyse ${i+1}/${ids.length}…`;
        const fd = new FormData();
        fd.append('action',      'reanalyze_file');
        fd.append('file_id',     ids[i]);
        fd.append('_csrf_token', CSRF);
        try {
            const r = await fetch('actions.php', { method:'POST', body:fd });
            const d = await r.json();
            if (d.success) ok++;
            else errors.push(d.error ?? 'Erreur inconnue');
        } catch { errors.push('Erreur réseau'); }
    }

    btn.disabled = false;
    btn.textContent = '🔄 Réanalyser';
    if (errors.length) alert(`${ok} analysé(s). ${errors.length} erreur(s) : ${errors.join(', ')}`);
    else { clearSelection(); window.location.reload(); }
}

// ── Réanalyse fichier individuel (depuis le menu contextuel) ──────────────────
async function singleReanalyze(fileId, btnEl) {
    const origText = btnEl.textContent;
    btnEl.textContent = '⏳…';
    btnEl.disabled = true;

    const fd = new FormData();
    fd.append('action',      'reanalyze_file');
    fd.append('file_id',     fileId);
    fd.append('_csrf_token', CSRF);
    try {
        const r = await fetch('actions.php', { method:'POST', body:fd });
        const d = await r.json();
        if (d.success) { window.location.reload(); }
        else {
            btnEl.textContent = origText;
            btnEl.disabled = false;
            alert('Erreur : ' + (d.error ?? 'Inconnue'));
        }
    } catch {
        btnEl.textContent = origText;
        btnEl.disabled = false;
        alert('Erreur réseau');
    }
}
</script>
</body>
</html>
