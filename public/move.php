<?php
// public/move.php — Page/modal de déplacement de fichier vers un autre dossier
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

Auth::verifyCsrf();

$fileId      = (int)($_POST['file_id']       ?? 0);
$newFolderId = (int)($_POST['new_folder_id'] ?? 0);

if (!$fileId || !$newFolderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters.']);
    exit;
}

try {
    $file = FileModel::find($fileId);
    if (!$file) throw new Exception('Fichier introuvable.');

    // Vérifier droits
    if (!Auth::isAdmin() && $file['uploaded_by'] !== Auth::id()) {
        throw new Exception('Permission denied.');
    }

    $folder = Folder::find($newFolderId);
    if (!$folder) throw new Exception('Dossier de destination introuvable.');

    if ($file['folder_id'] === $newFolderId) {
        throw new Exception('File is already in this folder.');
    }

    FileModel::move($fileId, $newFolderId, Auth::id());
    echo json_encode(['success' => true, 'folder_id' => $newFolderId, 'folder_nom' => $folder['nom']]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
