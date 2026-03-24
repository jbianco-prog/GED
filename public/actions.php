<?php
// public/actions.php — API AJAX pour les actions CRUD
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();
Auth::verifyCsrf();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── Dossiers ─────────────────────────────────────────────────────────
        case 'create_folder':
            $parentId   = (int)($_POST['parent_id']   ?? 0);
            $folderName = trim($_POST['folder_name']  ?? '');
            if (!$folderName) throw new Exception('Folder name required.');
            $id = Folder::create($folderName, $parentId, Auth::id());
            echo json_encode(['success' => true, 'folder_id' => $id]);
            break;

        case 'rename_folder':
            $folderId = (int)($_POST['folder_id'] ?? 0);
            $newName  = trim($_POST['new_name']   ?? '');
            if (!$folderId || !$newName) throw new Exception('Missing parameters.');
            Folder::rename($folderId, $newName, Auth::id());
            echo json_encode(['success' => true]);
            break;

        case 'delete_folder':
            $folderId = (int)($_POST['folder_id'] ?? 0);
            if (!$folderId) throw new Exception('Missing folder ID.');
            Folder::delete($folderId, Auth::id());
            echo json_encode(['success' => true]);
            break;

        // ── Fichiers ──────────────────────────────────────────────────────────
        case 'rename_file':
            $fileId  = (int)($_POST['file_id']  ?? 0);
            $newName = trim($_POST['new_name']  ?? '');
            if (!$fileId || !$newName) throw new Exception('Missing parameters.');
            FileModel::rename($fileId, $newName, Auth::id());
            echo json_encode(['success' => true]);
            break;

        case 'delete_file':
            $fileId = (int)($_POST['file_id'] ?? 0);
            if (!$fileId) throw new Exception('Missing file ID.');
            // Vérifier que l'utilisateur est propriétaire ou admin
            $file = FileModel::find($fileId);
            if (!$file) throw new Exception('File not found.');
            if (!Auth::isAdmin() && $file['uploaded_by'] !== Auth::id()) {
                throw new Exception('Permission denied.');
            }
            FileModel::delete($fileId, Auth::id());
            echo json_encode(['success' => true]);
            break;

        case 'move_file':
            $fileId      = (int)($_POST['file_id']       ?? 0);
            $newFolderId = (int)($_POST['new_folder_id'] ?? 0);
            if (!$fileId || !$newFolderId) throw new Exception('Missing parameters.');
            FileModel::move($fileId, $newFolderId, Auth::id());
            echo json_encode(['success' => true]);
            break;

        case 'reanalyze_file':
            $fileId = (int)($_POST['file_id'] ?? 0);
            $file   = FileModel::find($fileId);
            if (!$file) throw new Exception('Fichier introuvable.');
            // Admin : tous les fichiers — Utilisateur : uniquement les siens
            if (!Auth::isAdmin() && $file['uploaded_by'] !== Auth::id()) {
                throw new Exception('Permission refusée.');
            }
            ClassificationEngine::run($fileId, $file['chemin_stockage'], $file['extension']);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Action inconnue : $action"]);
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}