<?php
// public/preview-serve.php — Sert le fichier inline pour la prévisualisation
// NE PAS utiliser Content-Disposition: attachment

require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
$file   = FileModel::find($fileId);

if (!$file) { http_response_code(404); exit; }
if (!file_exists($file['chemin_stockage'])) { http_response_code(404); exit; }

$ext = strtolower($file['extension']);
$allowedInline = ['jpg','jpeg','png','gif','webp','bmp','pdf','mp4','webm','mp3','wav','ogg','txt','csv','json','xml'];

if (!in_array($ext, $allowedInline)) {
    http_response_code(403);
    exit;
}

$mimeType = $file['mime_type'] ?: 'application/octet-stream';

// Sécurité : forcer les types MIME connus pour éviter XSS
$safeMimes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif',  'webp' => 'image/webp', 'bmp' => 'image/bmp',
    'pdf' => 'application/pdf',
    'mp4' => 'video/mp4',  'webm' => 'video/webm',
    'mp3' => 'audio/mpeg', 'wav'  => 'audio/wav',  'ogg' => 'audio/ogg',
    'txt' => 'text/plain', 'csv'  => 'text/plain',
    'json'=> 'text/plain', 'xml'  => 'text/plain',
];
$mimeType = $safeMimes[$ext] ?? 'application/octet-stream';

while (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes($file['nom_courant']) . '"');
header('Content-Length: ' . $file['taille']);
header('Cache-Control: private, max-age=600');
header('X-Content-Type-Options: nosniff');

readfile($file['chemin_stockage']);
exit;
