<?php
// public/search.php — Recherche full-text
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$query   = trim($_GET['q'] ?? '');
$results = [];
$nbTotal = 0;

if (mb_strlen($query) >= 2) {
    $like    = '%' . $query . '%';
    $results = Database::fetchAll(
        "SELECT f.id, f.nom_courant, f.extension, f.taille, f.created_at,
                fo.nom AS folder_nom, fo.chemin AS folder_chemin, fo.id AS folder_id,
                u.nom AS uploaded_by_nom,
                fa.niveau_sensibilite, fa.resume_ai,
                fm.auteur, fm.titre,
                fa.texte_extrait,
                CASE
                    WHEN f.nom_courant LIKE ? THEN 3
                    WHEN fm.titre LIKE ? OR fm.auteur LIKE ? THEN 2
                    WHEN fa.texte_extrait LIKE ? THEN 1
                    ELSE 0
                END AS relevance
         FROM files f
         LEFT JOIN folders fo       ON fo.id     = f.folder_id
         LEFT JOIN users u          ON u.id      = f.uploaded_by
         LEFT JOIN file_analysis fa ON fa.file_id = f.id
         LEFT JOIN file_metadata  fm ON fm.file_id = f.id
         WHERE f.nom_courant LIKE ?
            OR fm.titre      LIKE ?
            OR fm.auteur     LIKE ?
            OR fm.sujet      LIKE ?
            OR fa.texte_extrait LIKE ?
            OR fm.mots_cles  LIKE ?
         ORDER BY relevance DESC, f.created_at DESC
         LIMIT 100",
        [$like,$like,$like,$like, $like,$like,$like,$like,$like,$like]
    );
    $nbTotal = count($results);
    AuditLog::log(Auth::id(), 'search', null, null, "Requête: $query — $nbTotal result(s)");
}

function highlight(string $text, string $query, int $maxLen = 140): string {
    $pos     = mb_stripos($text, $query);
    $start   = $pos !== false ? max(0, $pos - 40) : 0;
    $excerpt = ($start > 0 ? '…' : '') . mb_substr($text, $start, $maxLen);
    $excerpt = H::e($excerpt) . (mb_strlen($text) > $start + $maxLen ? '…' : '');
    return preg_replace(
        '/' . preg_quote(H::e($query), '/') . '/iu',
        '<mark style="background:#fff3cd;padding:1px 3px;border-radius:2px;font-style:normal">$0</mark>',
        $excerpt
    );
}

$pageTitle = $query ? 'Search: ' . $query : 'Search';
Layout::start($pageTitle, 0);
?>

<div class="toolbar">
    <form action="search.php" method="GET" style="display:flex;gap:8px;align-items:center;flex:1;max-width:600px">
        <input class="form-control" type="text" name="q"
               value="<?= H::e($query) ?>"
               placeholder="File name, title, author, content…"
               autofocus style="flex:1;font-size:14px;padding:6px 10px">
        <button type="submit" class="btn btn-primary">🔍 Search</button>
    </form>
    <?php if ($nbTotal > 0): ?>
    <span class="text-muted text-small ml-auto"><?= $nbTotal ?> result(s)</span>
    <?php endif; ?>
</div>

<div class="content">
<?php if ($query && mb_strlen($query) < 2): ?>
    <div class="alert alert-warning">Please enter at least 2 characters.</div>

<?php elseif ($query && empty($results)): ?>
    <div class="empty-state">
        <div class="empty-state__icon">🔍</div>
        <div class="empty-state__text">Aucun résultat pour « <strong><?= H::e($query) ?></strong> »</div>
    </div>

<?php elseif (!empty($results)): ?>
    <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($results as $r): ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:14px 16px">
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:22px;flex-shrink:0"><?= H::fileIcon($r['extension']) ?></span>
            <div style="flex:1;min-width:0">
                <a href="file.php?id=<?= $r['id'] ?>"
                   style="font-size:14px;font-weight:600;color:var(--primary)">
                    <?= highlight($r['nom_courant'], $query, 80) ?>
                </a>
                <div class="text-muted text-small" style="margin-top:2px">
                    📁 <a href="index.php?folder=<?= $r['folder_id'] ?>" style="color:var(--gray-500)"><?= H::e($r['folder_chemin']) ?></a>
                    · <?= H::formatSize($r['taille']) ?>
                    · <?= H::formatDate($r['created_at']) ?>
                    · <?= H::e($r['uploaded_by_nom'] ?? '—') ?>
                </div>
                <?php if (!empty($r['resume_ai'])): ?>
                <div style="margin-top:4px">
                    <?php foreach (array_map('trim', explode(',', $r['resume_ai'])) as $mot): ?>
                    <span style="display:inline-block;background:var(--primary-light);color:var(--primary);
                                 border-radius:10px;padding:1px 8px;font-size:11px;font-weight:600;margin:1px"><?= H::e($mot) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="flex-shrink:0;display:flex;align-items:center;gap:6px">
                <?= H::sensitivityBadge($r['niveau_sensibilite']) ?>
                <a href="download.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm" title="Download">⬇</a>
            </div>
        </div>
        <?php
        $excerpt = $r['texte_extrait'] ?? '';
        if (!empty($excerpt) && mb_stripos($excerpt, $query) !== false): ?>
        <div style="border-top:1px solid var(--gray-100);padding-top:8px;margin-top:8px;
                    font-size:12px;color:var(--gray-500);font-style:italic;line-height:1.5">
            <?= highlight($excerpt, $query, 220) ?>
        </div>
        <?php elseif (!empty($r['titre'])): ?>
        <div style="border-top:1px solid var(--gray-100);padding-top:6px;margin-top:6px;
                    font-size:12px;color:var(--gray-500)">
            <?php if ($r['titre']): ?>Title: <?= highlight($r['titre'], $query, 100) ?><?php endif; ?>
            <?php if ($r['auteur']): ?> · Author: <?= highlight($r['auteur'], $query, 60) ?><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

<?php else: ?>
    <div class="empty-state">
        <div class="empty-state__icon">🔍</div>
        <div class="empty-state__text">Enter a term to search your documents.<br>
            <span class="text-small">Name, title, author, extracted content, keywords…</span>
        </div>
    </div>
<?php endif; ?>
</div>

<?php
$breadcrumb = [['id'=>null,'nom'=>'Documents'],['id'=>0,'nom'=>$pageTitle]];
Layout::end($breadcrumb);
?>
