<?php
// public/preview_raw.php — Sert le fichier brut pour la prévisualisation (images, PDF)
// Ce fichier ne renverra JAMAIS de HTML exécutable
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
$file   = FileModel::find($fileId);

if (!$file) { http_response_code(404); exit; }
if (!file_exists($file['chemin_stockage'])) { http_response_code(404); exit; }

$ext = strtolower($file['extension']);

// N'autoriser QUE les formats sûrs pour l'inline
$allowedInline = ['jpg','jpeg','png','gif','webp','bmp','svg','pdf'];
if (!in_array($ext, $allowedInline)) {
    http_response_code(403);
    exit;
}

// Forcer un MIME type sûr — ne jamais faire confiance au mime_type stocké pour le rendu inline
$safeMimes = [
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png'  => 'image/png',  'gif'  => 'image/gif',
    'webp' => 'image/webp', 'bmp'  => 'image/bmp',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
];
$mimeType = $safeMimes[$ext] ?? 'application/octet-stream';

AuditLog::log(Auth::id(), 'file_preview_raw', 'file', $fileId, $file['nom_courant']);

while (ob_get_level()) ob_end_clean();

header('Content-Type: '     . $mimeType);
header('Content-Length: '   . filesize($file['chemin_stockage']));
header('Content-Disposition: inline; filename="' . addslashes($file['nom_courant']) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
// Empêcher l'exécution de SVG potentiellement malveillant
if ($ext === 'svg') {
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'');
}

readfile($file['chemin_stockage']);
exit;
