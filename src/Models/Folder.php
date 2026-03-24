<?php
// src/Models/Folder.php

class Folder {

    public static function find(int $id): ?array {
        return Database::fetchOne('SELECT * FROM folders WHERE id = ?', [$id]);
    }

    public static function children(int $parentId): array {
        return Database::fetchAll(
            'SELECT f.*, u.nom as created_by_nom,
                    (SELECT COUNT(*) FROM folders c WHERE c.parent_id = f.id) as nb_sous_dossiers,
                    (SELECT COUNT(*) FROM files fi WHERE fi.folder_id = f.id) as nb_fichiers
             FROM folders f
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.parent_id = ?
             ORDER BY f.nom ASC',
            [$parentId]
        );
    }

    public static function root(): ?array {
        return Database::fetchOne('SELECT * FROM folders WHERE parent_id IS NULL LIMIT 1');
    }

    public static function create(string $nom, int $parentId, int $userId): int {
        $parent = self::find($parentId);
        if (!$parent) throw new Exception('Parent folder not found.');

        $nomSanitize = self::sanitizeName($nom);
        // Vérifier doublon
        $exists = Database::fetchOne(
            'SELECT id FROM folders WHERE nom = ? AND parent_id = ?',
            [$nomSanitize, $parentId]
        );
        if ($exists) throw new Exception("Un dossier « $nomSanitize » already exists here.");

        $chemin = rtrim($parent['chemin'], '/') . '/' . $nomSanitize;
        $id = Database::insert(
            'INSERT INTO folders (nom, parent_id, chemin, created_by) VALUES (?, ?, ?, ?)',
            [$nomSanitize, $parentId, $chemin, $userId]
        );
        AuditLog::log($userId, 'folder_create', 'folder', $id, "Folder: $chemin");
        return $id;
    }

    public static function rename(int $id, string $newName, int $userId): void {
        $folder = self::find($id);
        if (!$folder) throw new Exception('Folder not found.');
        if ($folder['parent_id'] === null) throw new Exception('Root folder cannot be renamed.');

        $newName = self::sanitizeName($newName);
        $parent  = self::find($folder['parent_id']);
        $newPath = rtrim($parent['chemin'], '/') . '/' . $newName;

        // Vérifier doublon
        $exists = Database::fetchOne(
            'SELECT id FROM folders WHERE nom = ? AND parent_id = ? AND id != ?',
            [$newName, $folder['parent_id'], $id]
        );
        if ($exists) throw new Exception("Un dossier « $newName » already exists here.");

        Database::execute('UPDATE folders SET nom = ?, chemin = ? WHERE id = ?', [$newName, $newPath, $id]);
        // Mettre à jour les chemins enfants récursivement
        self::updateChildrenPaths($id, $folder['chemin'], $newPath);
        AuditLog::log($userId, 'folder_rename', 'folder', $id, "{$folder['nom']} → $newName");
    }

    public static function delete(int $id, int $userId): void {
        $folder = self::find($id);
        if (!$folder) throw new Exception('Folder not found.');
        if ($folder['parent_id'] === null) throw new Exception('Cannot delete root folder.');

        $nbFichiers = (int) Database::fetchOne(
            'SELECT COUNT(*) as c FROM files WHERE folder_id IN (
                SELECT id FROM folders WHERE chemin LIKE ? OR id = ?
            )', [$folder['chemin'] . '/%', $id]
        )['c'];
        if ($nbFichiers > 0) throw new Exception("Le dossier contient $nbFichiers fichier(s). Supprimez-les d'abord.");

        Database::execute('DELETE FROM folders WHERE id = ?', [$id]);
        AuditLog::log($userId, 'folder_delete', 'folder', $id, "Folder: {$folder['chemin']}");
    }

    public static function breadcrumb(int $folderId): array {
        $path   = [];
        $folder = self::find($folderId);
        while ($folder) {
            array_unshift($path, $folder);
            $folder = $folder['parent_id'] ? self::find($folder['parent_id']) : null;
        }
        return $path;
    }

    public static function getTree(int $parentId = null, int $depth = 0): array {
        if ($depth > 10) return [];
        $root = $parentId ?? (self::root()['id'] ?? 1);
        $children = self::children($root);
        foreach ($children as &$child) {
            $child['children'] = self::getTree($child['id'], $depth + 1);
        }
        return $children;
    }

    private static function updateChildrenPaths(int $folderId, string $oldPath, string $newPath): void {
        $children = Database::fetchAll('SELECT * FROM folders WHERE parent_id = ?', [$folderId]);
        foreach ($children as $child) {
            $childNewPath = $newPath . '/' . $child['nom'];
            Database::execute('UPDATE folders SET chemin = ? WHERE id = ?', [$childNewPath, $child['id']]);
            self::updateChildrenPaths($child['id'], $child['chemin'], $childNewPath);
        }
    }

    private static function sanitizeName(string $name): string {
        $name = trim($name);
        $name = preg_replace('/[\/\\\:*?"<>|]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        if (empty($name)) throw new Exception('Invalid folder name.');
        if (mb_strlen($name) > 200) throw new Exception('Folder name too long (200 chars max).');
        return $name;
    }
}
