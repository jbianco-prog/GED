<?php
// public/download.php — Téléchargement sécurisé d'un fichier
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
$file   = FileModel::find($fileId);

if (!$file) {
    http_response_code(404);
    die('File not found.');
}

if (!file_exists($file['chemin_stockage'])) {
    http_response_code(404);
    die('Physical file not found.');
}

// ── Restriction DLP ───────────────────────────────────────────────────────────
// Bloquer si : non-admin ET (DLP global actif OU société sous DLP forcé) ET fichier sensible
if (!Auth::isAdmin() && Settings::isDlpEnforcedForFile($fileId)) {
    $analysis = FileModel::getAnalysis($fileId);
    $niveau   = $analysis['niveau_sensibilite'] ?? 'non_analyse';
    if (in_array($niveau, ['sensible', 'sensible_eleve'])) {
        AuditLog::log(Auth::id(), 'download_blocked_dlp', 'file', $fileId,
            "Download blocked (DLP): {$file['nom_courant']} — level $niveau"
        );
        http_response_code(403);
        include ROOT_PATH . '/views/errors/403_dlp.php';
        exit;
    }
}

AuditLog::log(Auth::id(), 'file_download', 'file', $fileId, "Fichier: {$file['nom_courant']}");

$fileName = $file['nom_courant'];
$mimeType = $file['mime_type'] ?: 'application/octet-stream';

while (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
header('Content-Length: ' . $file['taille']);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($file['chemin_stockage']);
exit;
