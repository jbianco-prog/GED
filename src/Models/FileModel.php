<?php
// src/Models/FileModel.php

class FileModel {

    public static function find(int $id): ?array {
        return Database::fetchOne(
            'SELECT f.*, u.nom as uploaded_by_nom, fo.nom as folder_nom, fo.chemin as folder_chemin
             FROM files f
             LEFT JOIN users u  ON u.id  = f.uploaded_by
             LEFT JOIN folders fo ON fo.id = f.folder_id
             WHERE f.id = ?',
            [$id]
        );
    }

    public static function inFolder(int $folderId): array {
        return Database::fetchAll(
            'SELECT f.*, u.nom as uploaded_by_nom,
                    fa.niveau_sensibilite, fa.score_ia, fa.cb_detectee,
                    fa.mots_cles_detectes, fa.resume_ai, fa.analysed_at
             FROM files f
             LEFT JOIN users u       ON u.id  = f.uploaded_by
             LEFT JOIN file_analysis fa ON fa.file_id = f.id
             WHERE f.folder_id = ?
             ORDER BY f.nom_courant ASC',
            [$folderId]
        );
    }

    public static function save(array $data): int {
        return Database::insert(
            'INSERT INTO files
                (nom_original, nom_courant, nom_stockage, extension, mime_type, taille, chemin_stockage, folder_id, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['nom_original'],
                $data['nom_courant'],
                $data['nom_stockage'],
                $data['extension'],
                $data['mime_type'],
                $data['taille'],
                $data['chemin_stockage'],
                $data['folder_id'],
                $data['uploaded_by'],
            ]
        );
    }

    public static function rename(int $id, string $newName, int $userId): void {
        $file = self::find($id);
        if (!$file) throw new Exception('File not found.');

        $newName = self::sanitizeName($newName, $file['extension']);
        // Vérifier doublon dans le même dossier
        $exists = Database::fetchOne(
            'SELECT id FROM files WHERE nom_courant = ? AND folder_id = ? AND id != ?',
            [$newName, $file['folder_id'], $id]
        );
        if ($exists) throw new Exception("Un fichier « $newName » existe already in this folder.");

        Database::execute('UPDATE files SET nom_courant = ? WHERE id = ?', [$newName, $id]);
        AuditLog::log($userId, 'file_rename', 'file', $id, "{$file['nom_courant']} → $newName");
    }

    public static function delete(int $id, int $userId): void {
        $file = self::find($id);
        if (!$file) throw new Exception('File not found.');

        // Suppression physique
        if (file_exists($file['chemin_stockage'])) {
            @unlink($file['chemin_stockage']);
        }
        Database::execute('DELETE FROM files WHERE id = ?', [$id]);
        AuditLog::log($userId, 'file_delete', 'file', $id, "File: {$file['nom_courant']}");
    }

    public static function move(int $id, int $newFolderId, int $userId): void {
        $file = self::find($id);
        if (!$file) throw new Exception('File not found.');
        Database::execute('UPDATE files SET folder_id = ? WHERE id = ?', [$newFolderId, $id]);
        AuditLog::log($userId, 'file_move', 'file', $id, "To folder #$newFolderId");
    }

    public static function saveMetadata(int $fileId, array $meta): void {
        $existing = Database::fetchOne('SELECT id FROM file_metadata WHERE file_id = ?', [$fileId]);
        if ($existing) {
            Database::execute(
                'UPDATE file_metadata SET auteur=?, titre=?, sujet=?, societe=?,
                  date_creation_doc=?, date_modification_doc=?, logiciel_createur=?,
                  nb_pages=?, langue=?, mots_cles=?, json_complet=?
                 WHERE file_id=?',
                [
                    $meta['auteur'] ?? null,
                    $meta['titre'] ?? null,
                    $meta['sujet'] ?? null,
                    $meta['societe'] ?? null,
                    $meta['date_creation_doc'] ?? null,
                    $meta['date_modification_doc'] ?? null,
                    $meta['logiciel_createur'] ?? null,
                    $meta['nb_pages'] ?? null,
                    $meta['langue'] ?? null,
                    $meta['mots_cles'] ?? null,
                    isset($meta['json_complet']) ? json_encode($meta['json_complet']) : null,
                    $fileId,
                ]
            );
        } else {
            Database::insert(
                'INSERT INTO file_metadata (file_id, auteur, titre, sujet, societe,
                  date_creation_doc, date_modification_doc, logiciel_createur,
                  nb_pages, langue, mots_cles, json_complet)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $fileId,
                    $meta['auteur'] ?? null,
                    $meta['titre'] ?? null,
                    $meta['sujet'] ?? null,
                    $meta['societe'] ?? null,
                    $meta['date_creation_doc'] ?? null,
                    $meta['date_modification_doc'] ?? null,
                    $meta['logiciel_createur'] ?? null,
                    $meta['nb_pages'] ?? null,
                    $meta['langue'] ?? null,
                    $meta['mots_cles'] ?? null,
                    isset($meta['json_complet']) ? json_encode($meta['json_complet']) : null,
                ]
            );
        }
    }

    public static function saveAnalysis(int $fileId, array $data): void {
        $existing = Database::fetchOne('SELECT id FROM file_analysis WHERE file_id = ?', [$fileId]);
        if ($existing) {
            Database::execute(
                'UPDATE file_analysis SET
                  texte_extrait=?, mots_cles_detectes=?, cb_detectee=?, nombre_cb=?,
                  score_ia=?, verdict_ia=?, raisons_ia=?, resume_ai=?,
                  niveau_sensibilite=?, raisons=?,
                  metadata_analysee=?, contenu_analyse=?, analysed_at=NOW()
                 WHERE file_id=?',
                [
                    $data['texte_extrait']     ?? null,
                    isset($data['mots_cles_detectes']) ? implode(', ', $data['mots_cles_detectes']) : null,
                    $data['cb_detectee']        ?? 0,
                    $data['nombre_cb']          ?? 0,
                    $data['score_ia']           ?? null,
                    $data['verdict_ia']         ?? null,
                    $data['raisons_ia']         ?? null,
                    $data['resume_ai']          ?? null,
                    $data['niveau_sensibilite'] ?? 'non_analyse',
                    isset($data['raisons'])     ? implode(' | ', $data['raisons']) : null,
                    $data['metadata_analysee']  ?? 0,
                    $data['contenu_analyse']    ?? 0,
                    $fileId,
                ]
            );
        } else {
            Database::insert(
                'INSERT INTO file_analysis
                  (file_id, texte_extrait, mots_cles_detectes, cb_detectee, nombre_cb,
                   score_ia, verdict_ia, raisons_ia, resume_ai,
                   niveau_sensibilite, raisons, metadata_analysee, contenu_analyse, analysed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $fileId,
                    $data['texte_extrait']     ?? null,
                    isset($data['mots_cles_detectes']) ? implode(', ', $data['mots_cles_detectes']) : null,
                    $data['cb_detectee']        ?? 0,
                    $data['nombre_cb']          ?? 0,
                    $data['score_ia']           ?? null,
                    $data['verdict_ia']         ?? null,
                    $data['raisons_ia']         ?? null,
                    $data['resume_ai']          ?? null,
                    $data['niveau_sensibilite'] ?? 'non_analyse',
                    isset($data['raisons'])     ? implode(' | ', $data['raisons']) : null,
                    $data['metadata_analysee']  ?? 0,
                    $data['contenu_analyse']    ?? 0,
                ]
            );
        }
    }

    public static function getMetadata(int $fileId): ?array {
        return Database::fetchOne('SELECT * FROM file_metadata WHERE file_id = ?', [$fileId]);
    }

    public static function getAnalysis(int $fileId): ?array {
        return Database::fetchOne('SELECT * FROM file_analysis WHERE file_id = ?', [$fileId]);
    }

    private static function sanitizeName(string $name, string $ext): string {
        $name = trim($name);
        // Retirer l'extension si l'utilisateur l'a incluse
        $nameNoExt = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $name);
        $nameNoExt = preg_replace('/[\/\\\:*?"<>|]/', '', $nameNoExt);
        $nameNoExt = trim(preg_replace('/\s+/', ' ', $nameNoExt));
        if (empty($nameNoExt)) throw new Exception('Invalid file name.');
        return $nameNoExt . '.' . $ext;
    }
}
