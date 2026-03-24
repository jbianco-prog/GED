<?php
// public/file.php — Détail d'un fichier
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
$file   = FileModel::find($fileId);
if (!$file) { http_response_code(404); include ROOT_PATH . '/views/errors/404.php'; exit; }

$metadata   = FileModel::getMetadata($fileId);
$analysis   = FileModel::getAnalysis($fileId);
$breadcrumb = Folder::breadcrumb($file['folder_id']);
$breadcrumb[] = ['id' => 0, 'nom' => $file['nom_courant']];

// ── Calculer $isSensitive AVANT tout HTML ─────────────────────────────────────
$isSensitive = !Auth::isAdmin()
    && Settings::isDlpEnforcedForFile($file['id'])
    && in_array($analysis['niveau_sensibilite'] ?? 'non_analyse', ['sensible','sensible_eleve']);
$fileUrl   = APP_URL . '/download.php?id=' . $file['id'];
$fileTitle = urlencode($file['nom_courant']);

Layout::start(H::e($file['nom_courant']), $file['folder_id']);
?>
<div class="toolbar">
    <?php if ($isSensitive): ?>
    <button class="btn btn-primary" disabled style="opacity:.45;cursor:not-allowed;filter:grayscale(1)" title="Download blocked — DLP active">⬇ Download 🔒</button>
    <?php else: ?>
    <a class="btn btn-primary" href="download.php?id=<?= $file['id'] ?>">⬇ Download</a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="preview.php?id=<?= $file['id'] ?>">👁 Preview</a>
    <button class="btn btn-secondary" onclick="renameFilePrompt(<?= $file['id'] ?>, '<?= H::e(addslashes($file['nom_courant'])) ?>')">✏ Rename</button>
    <div style="position:relative;display:inline-block">
        <button class="btn btn-secondary <?= $isSensitive ? 'btn-share-disabled' : '' ?>"
                id="btn-share"
                <?= $isSensitive ? 'disabled title="Sharing disabled: sensitive document"' : 'onclick="toggleShareMenu(event)"' ?>
                style="<?= $isSensitive ? 'opacity:.45;cursor:not-allowed;filter:grayscale(1)' : '' ?>">
            🔗 Partager <?= $isSensitive ? '🔒' : '▾' ?>
        </button>
        <?php if (!$isSensitive): ?>
        <div id="share-menu" class="dropdown-menu hidden" style="min-width:180px;left:0;right:auto">
            <a href="https://www.dropbox.com/home?upload=<?= $fileUrl ?>" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px" fill="#0061FF"><path d="M6 2L0 6.5l6 4.5 6-4.5zm12 0l-6 4.5 6 4.5 6-4.5zM0 15.5L6 20l6-4.5-6-4.5zm18 0l-6 4.5 6 4.5 6-4.5zM6 21.5l6 4.5 6-4.5-6-4.5z"/></svg>Dropbox
            </a>
            <a href="https://onedrive.live.com/about/en-us/" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px"><path d="M17.5 12.5a5.5 5.5 0 00-10.6-1.9A4 4 0 004 18h13.5a3.5 3.5 0 000-7z" fill="#094AB2"/><path d="M20 18H8.5a3.5 3.5 0 010-7 3.5 3.5 0 01.4.02A5 5 0 0118.5 14a3 3 0 011.5 4z" fill="#28A8E0"/></svg>OneDrive
            </a>
            <div class="sep"></div>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($fileUrl) ?>" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:5px" fill="#1877F2"><path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.791-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>Facebook
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($fileUrl) ?>" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:5px" fill="#0A66C2"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>LinkedIn
            </a>
            <div class="sep"></div>
            <a href="mailto:?subject=<?= $fileTitle ?>&body=<?= urlencode("Bonjour,\n\nVeuillez trouver ci-joint le document : " . $file['nom_courant'] . "\n\nLien : " . $fileUrl) ?>">
                ✉ Email
            </a>
            <button onclick="window.print()">🖨 Print</button>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($isSensitive): ?>
    <span class="text-small" style="color:var(--warning);display:flex;align-items:center;gap:4px">
        ⚠ Sharing disabled — sensitive document
    </span>
    <?php endif; ?>
    <button class="btn btn-danger" onclick="deleteFileGo(<?= $file['id'] ?>, '<?= H::e(addslashes($file['nom_courant'])) ?>', <?= $file['folder_id'] ?>)">🗑 Delete</button>
    <a class="btn btn-secondary" href="index.php?folder=<?= $file['folder_id'] ?>">← Back</a>
</div>

<div class="content">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

<!-- Colonne gauche -->
<div>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <span style="font-size:40px"><?= H::fileIcon($file['extension']) ?></span>
            <div>
                <div style="font-size:16px;font-weight:600"><?= H::e($file['nom_courant']) ?></div>
                <div class="text-muted text-small"><?= H::e($file['folder_chemin']) ?>/<?= H::e($file['nom_courant']) ?></div>
            </div>
        </div>
        <div class="detail-section">
            <div class="detail-title">General information</div>
            <div class="detail-row"><span class="detail-key">Original name</span><span class="detail-val"><?= H::e($file['nom_original']) ?></span></div>
            <div class="detail-row"><span class="detail-key">Extension</span><span class="detail-val">.<?= H::e($file['extension']) ?></span></div>
            <div class="detail-row"><span class="detail-key">MIME type</span><span class="detail-val"><?= H::e($file['mime_type']) ?></span></div>
            <div class="detail-row"><span class="detail-key">Size</span><span class="detail-val"><?= H::formatSize($file['taille']) ?></span></div>
            <div class="detail-row"><span class="detail-key">Uploaded by</span><span class="detail-val"><?= H::e($file['uploaded_by_nom'] ?? '—') ?></span></div>
            <div class="detail-row"><span class="detail-key">Upload date</span><span class="detail-val"><?= H::formatDate($file['created_at']) ?></span></div>
            <div class="detail-row"><span class="detail-key">Folder</span><span class="detail-val"><?= H::e($file['folder_nom']) ?></span></div>
        </div>
    </div>

    <?php if ($metadata): ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px">
        <div class="detail-section">
            <div class="detail-title">📋 Extracted metadata</div>
            <?php foreach ([
                'auteur'=>'Author','titre'=>'Title','sujet'=>'Subject','societe'=>'Company',
                'logiciel_createur'=>'Software','nb_pages'=>'Pages','langue'=>'Language',
                'mots_cles'=>'Keywords','date_creation_doc'=>'Created','date_modification_doc'=>'Modified',
            ] as $key => $label):
                if (!empty($metadata[$key])): ?>
                <div class="detail-row">
                    <span class="detail-key"><?= H::e($label) ?></span>
                    <span class="detail-val"><?= H::e($metadata[$key]) ?></span>
                </div>
            <?php endif; endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Colonne droite : classification -->
<div>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px">
        <?php if ($analysis): ?>
        <div class="detail-section">
            <div class="detail-title">🔍 Classification result</div>
            <div class="detail-row"><span class="detail-key">Status</span><span class="detail-val"><?= H::sensitivityBadge($analysis['niveau_sensibilite']) ?></span></div>
            <div class="detail-row"><span class="detail-key">Analyzed on</span><span class="detail-val"><?= H::formatDate($analysis['analysed_at']) ?></span></div>
            <div class="detail-row">
                <span class="detail-key">DLP Protection</span>
                <span class="detail-val">
                    <?php
                    $dlpEnforced     = Settings::isDlpEnforcedForFile($file['id']);
                    $dlpGlobal       = Settings::dlpEnabled();
                    $isCompanyForced = false;
                    $meta2           = FileModel::getMetadata($file['id']);
                    if (!empty($meta2['societe'])) {
                        foreach (Settings::dlpCompanies() as $co) {
                            if (stripos(trim($meta2['societe']), trim($co)) !== false) {
                                $isCompanyForced = true; break;
                            }
                        }
                    }
                    if ($isCompanyForced): ?>
                        <span class="badge badge-danger">🛡️ DLP enforced — protected company</span>
                        <span class="text-small text-muted" style="display:block;margin-top:3px">
                            Company : <?= H::e($meta2['societe']) ?> — permanent restrictions
                        </span>
                    <?php elseif ($dlpGlobal): ?>
                        <span class="badge badge-warning">🛡️ DLP active (global)</span>
                        <span class="text-small text-muted" style="display:block;margin-top:3px">
                            Restrictions enabled by administrator
                        </span>
                    <?php else: ?>
                        <span class="badge badge-success">✓ DLP inactive</span>
                        <span class="text-small text-muted" style="display:block;margin-top:3px">
                            No sharing or download restrictions
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($analysis['resume_ai'])): ?>
            <div class="detail-row">
                <span class="detail-key">AI Summary</span>
                <span class="detail-val">
                    <?php foreach (array_map('trim', explode(',', $analysis['resume_ai'])) as $mot): ?>
                        <span style="display:inline-block;background:var(--primary-light);color:var(--primary);
                                     border-radius:10px;padding:1px 9px;font-size:12px;font-weight:600;
                                     margin:1px 2px"><?= H::e($mot) ?></span>
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-key">Metadata</span>
                <span class="detail-val"><?= $analysis['metadata_analysee'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-secondary">Non</span>' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-key">Text content</span>
                <span class="detail-val"><?= $analysis['contenu_analyse'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-secondary">Non</span>' ?></span>
            </div>
            <?php if ($analysis['score_ia'] !== null): ?>
            <div class="detail-row">
                <span class="detail-key">AI Score</span>
                <span class="detail-val">
                    <span class="badge <?= $analysis['score_ia'] >= 70 ? 'badge-danger' : ($analysis['score_ia'] >= 40 ? 'badge-warning' : 'badge-success') ?>">
                        <?= round($analysis['score_ia']) ?> %
                    </span>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($analysis['mots_cles_detectes'])): ?>
            <div class="detail-row"><span class="detail-key">Keywords</span><span class="detail-val"><?= H::e($analysis['mots_cles_detectes']) ?></span></div>
            <?php endif; ?>
            <?php if ($analysis['cb_detectee']): ?>
            <div class="detail-row">
                <span class="detail-key">Credit card</span>
                <span class="detail-val">
                    <span class="badge badge-danger">⚠ <?= $analysis['nombre_cb'] ?> number(s)</span>
                    <div class="text-muted text-small">Numbers masked — not displayed.</div>
                </span>
            </div>
            <?php endif; ?>

            <?php if (!empty($analysis['raisons'])): ?>
            <div style="margin-top:14px">
                <div class="detail-title">Classification reasons</div>
                <div style="background:var(--gray-50);border-radius:4px;padding:10px;font-size:13px">
                    <?php foreach (explode(' | ', $analysis['raisons']) as $r): ?>
                        <div style="padding:2px 0">• <?= H::e($r) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($analysis['raisons_ia'])): ?>
            <div style="margin-top:10px">
                <div class="detail-title">AI analysis</div>
                <div style="background:var(--gray-50);border-radius:4px;padding:10px;font-size:13px">
                    <?php foreach (explode(' | ', $analysis['raisons_ia']) as $r): ?>
                        <div style="padding:2px 0">• <?= H::e($r) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (Auth::isAdmin()): ?>
            <div style="margin-top:14px">
                <button class="btn btn-secondary btn-sm" id="btn-reanalyze"
                        onclick="reanalyzeFile(<?= $file['id'] ?>)">
                    🔄 Re-run analysis
                </button>
                <span id="reanalyze-status" style="font-size:12px;margin-left:8px;color:var(--gray-400)"></span>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <div class="empty-state" style="padding:24px">
                <div class="empty-state__icon" style="font-size:24px">⏳</div>
                <div class="empty-state__text">Analyse non disponible.</div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div><!-- /grid -->
</div><!-- /content -->

<script>
function deleteFileGo(id, name, folderId) {
    if (!confirm('Delete "' + name + '"? This action is irreversible.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_file');
    fd.append('file_id', id);
    fd.append('_csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fetch('actions.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => { if (d.success) window.location.href='index.php?folder='+folderId; else alert('Error: '+(d.error??'Inconnue')); });
}
function toggleShareMenu(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('share-menu');
    if (menu) menu.classList.toggle('hidden');
}

function reanalyzeFile(fileId) {
    const btn    = document.getElementById('btn-reanalyze');
    const status = document.getElementById('reanalyze-status');

    btn.disabled = true;
    btn.textContent = '⏳ Analysis in progress…';
    status.textContent = 'Please wait…';
    status.style.color = 'var(--gray-400)';

    const fd = new FormData();
    fd.append('action',      'reanalyze_file');
    fd.append('file_id',     fileId);
    fd.append('_csrf_token', document.querySelector('meta[name="csrf-token"]').content);

    fetch('actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                status.textContent = '✓ Analysis complete — reloading…';
                status.style.color = 'var(--success)';
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                btn.disabled = false;
                btn.textContent = '🔄 Relancer l\'analyse';
                status.textContent = '✗ ' + (d.error ?? 'Unknown error');
                status.style.color = 'var(--danger)';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = '🔄 Relancer l\'analyse';
            status.textContent = '✗ Network error';
            status.style.color = 'var(--danger)';
        });
}
// Close share menu on outside click
document.addEventListener('click', (e) => {
    const menu = document.getElementById('share-menu');
    const btn  = document.getElementById('btn-share');
    if (menu && !menu.classList.contains('hidden') &&
        btn  && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add('hidden');
    }
});
</script>
<?php Layout::end($breadcrumb); ?>
