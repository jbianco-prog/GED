<?php
// src/Controllers/UploadController.php

class UploadController {

    public static function handle(): void {
        Auth::requireLogin();
        Auth::verifyCsrf();

        header('Content-Type: application/json');

        if (empty($_FILES['files'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file received.']);
            return;
        }

        $folderId    = (int)($_POST['folder_id'] ?? 0);
        $folder      = Folder::find($folderId);
        if (!$folder) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid target folder.']);
            return;
        }

        // Les admins ne sont jamais bloqués.
        // Les utilisateurs standard sont bloqués si :
        //   - DLP global actif, OU société du fichier dans liste DLP forcé
        // Note : à ce stade on ne connaît pas encore le file_id (fichier pas encore sauvé),
        // donc on vérifie uniquement le switch global ici.
        // La vérification par société est faite APRÈS l'analyse (voir ci-dessous).
        $forceUpload = Auth::isAdmin() || !empty($_POST['force_sensitive']);

        // Normaliser $_FILES pour gérer multiple
        $files = self::normalizeFiles($_FILES['files']);
        $results = [];

        foreach ($files as $file) {
            try {
                $results[] = self::processOne($file, $folderId, $forceUpload);
            } catch (Exception $e) {
                $results[] = ['success' => false, 'name' => $file['name'], 'error' => $e->getMessage()];
            }
        }

        echo json_encode(['results' => $results]);
    }

    private static function processOne(array $file, int $folderId, bool $forceUpload = false): array {
        // ── Validation ────────────────────────────────────────────────────────
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: code ' . $file['error']);
        }
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('Fichier trop lourd (max ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB).');
        }

        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (in_array($ext, BLOCKED_EXTENSIONS)) {
            throw new Exception("Extension « .$ext » interdite.");
        }
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            throw new Exception("Extension « .$ext » non autorisée.");
        }

        // Vérification MIME réelle
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        // ── Stockage temporaire sécurisé ──────────────────────────────────────
        $storageName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath    = STORAGE_PATH . '/' . $storageName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('Failed to move uploaded file.');
        }

        // ── Enregistrement BDD ────────────────────────────────────────────────
        $fileId = FileModel::save([
            'nom_original'    => $origName,
            'nom_courant'     => $origName,
            'nom_stockage'    => $storageName,
            'extension'       => $ext,
            'mime_type'       => $mimeType,
            'taille'          => $file['size'],
            'chemin_stockage' => $destPath,
            'folder_id'       => $folderId,
            'uploaded_by'     => Auth::id(),
        ]);

        AuditLog::log(Auth::id(), 'file_upload', 'file', $fileId, "File: $origName");

        // ── Pipeline d'analyse ────────────────────────────────────────────────
        $analysisResult = ClassificationEngine::run($fileId, $destPath, $ext);

        // ── Blocage si document sensible ──────────────────────────────────────
        $niveau = $analysisResult['niveau_sensibilite'] ?? 'non_analyse';
        if (in_array($niveau, ['sensible', 'sensible_eleve'])) {
            // Recalculer forceUpload en tenant compte de la société détectée dans les métadonnées
            // Un fichier d'une société sous DLP forcé est toujours bloqué pour les non-admins
            $dlpForced  = Settings::isDlpEnforcedForFile($fileId);
            $mustBlock  = !$forceUpload && (Settings::dlpEnabled() || $dlpForced);

            if (!$mustBlock) {
                // Admin ou DLP inactif ET société non forcée — on conserve avec log
                AuditLog::log(Auth::id(), 'file_upload_forced', 'file', $fileId,
                    "Upload kept despite sensitivity « $niveau » : $origName"
                );
            } else {
                // Utilisateur standard sous DLP — refus et suppression
                @unlink($destPath);
                Database::execute('DELETE FROM files WHERE id = ?', [$fileId]);
                AuditLog::log(Auth::id(), 'file_upload_blocked', 'file', null,
                    "Sensitive file rejected: $origName (level: $niveau)"
                );
                $raisons = $analysisResult['raisons'] ?? [];
                $msg     = is_array($raisons) ? implode(' | ', $raisons) : (string)$raisons;
                throw new Exception(
                    "⛔ Fichier refusé : document classified as « $niveau »." .
                    ($msg ? " Reason: $msg" : '')
                );
            }
        }

        return [
            'success' => true,
            'name'    => $origName,
            'file_id' => $fileId,
        ];
    }

    private static function normalizeFiles(array $filesInput): array {
        $files = [];
        if (is_array($filesInput['name'])) {
            for ($i = 0; $i < count($filesInput['name']); $i++) {
                $files[] = [
                    'name'     => $filesInput['name'][$i],
                    'type'     => $filesInput['type'][$i],
                    'tmp_name' => $filesInput['tmp_name'][$i],
                    'error'    => $filesInput['error'][$i],
                    'size'     => $filesInput['size'][$i],
                ];
            }
        } else {
            $files[] = $filesInput;
        }
        return $files;
    }
}
