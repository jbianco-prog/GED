<?php
// public/preview.php — Prévisualisation inline d'un fichier
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
$file   = FileModel::find($fileId);
if (!$file) { http_response_code(404); die('Fichier introuvable.'); }

$ext     = strtolower($file['extension']);
$previewType = null;
if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg'])) { $previewType = 'image'; }
elseif ($ext === 'pdf')                                             { $previewType = 'pdf'; }
elseif (in_array($ext, ['txt','csv','json','xml','md','log']))      { $previewType = 'text'; }

AuditLog::log(Auth::id(), 'file_preview', 'file', $fileId, $file['nom_courant']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview: <?= H::e($file['nom_courant']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
    <style>
        .preview-header { position:sticky;top:0;z-index:100;background:#fff;border-bottom:1px solid var(--gray-200);padding:10px 20px;display:flex;align-items:center;gap:10px; }
        .preview-body   { padding:16px; }
        .preview-pdf    { width:100%;height:calc(100vh - 70px);border:none; }
        .preview-text   { background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px;font-family:'Consolas','Monaco',monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-all;max-height:calc(100vh - 120px);overflow:auto; }
    </style>
</head>
<body>
<div class="preview-header">
    <span style="font-size:22px"><?= H::fileIcon($ext) ?></span>
    <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:14px"><?= H::e($file['nom_courant']) ?></div>
        <div class="text-muted text-small"><?= H::formatSize($file['taille']) ?> · <?= H::e($file['mime_type']) ?></div>
    </div>
    <a href="file.php?id=<?= $fileId ?>" class="btn btn-secondary btn-sm">← Details</a>
    <a href="download.php?id=<?= $fileId ?>" class="btn btn-primary btn-sm">⬇ Download</a>
</div>

<div class="preview-body">
<?php if (!$previewType): ?>
    <div class="empty-state">
        <div class="empty-state__icon"><?= H::fileIcon($ext) ?></div>
        <div class="empty-state__text">Preview not available for files of type "<?= H::e($ext) ?>".<br>
        <a href="download.php?id=<?= $fileId ?>" class="btn btn-primary" style="margin-top:12px;display:inline-flex">⬇ Download</a></div>
    </div>
<?php elseif ($previewType === 'image'): ?>
    <div style="text-align:center">
        <img src="preview_raw.php?id=<?= $fileId ?>" alt="<?= H::e($file['nom_courant']) ?>"
             style="max-width:100%;display:block;margin:0 auto;border-radius:4px;box-shadow:0 2px 12px rgba(0,0,0,.15)">
    </div>
<?php elseif ($previewType === 'pdf'): ?>
    <iframe class="preview-pdf" src="preview_raw.php?id=<?= $fileId ?>#toolbar=1" title="<?= H::e($file['nom_courant']) ?>"></iframe>
<?php elseif ($previewType === 'text'): ?>
    <?php
    $content   = file_get_contents($file['chemin_stockage']);
    $content   = mb_convert_encoding($content, 'UTF-8', 'auto');
    $preview   = mb_substr($content, 0, 200000);
    $truncated = mb_strlen($content) > 200000;
    ?>
    <?php if ($truncated): ?>
    <div class="alert alert-info" style="margin-bottom:8px">Affichage limited to the first 200,000 characters. <a href="download.php?id=<?= $fileId ?>">Download full version</a></div>
    <?php endif; ?>
    <pre class="preview-text"><?= H::e($preview) ?></pre>
<?php endif; ?>
</div>
</body>
</html>
