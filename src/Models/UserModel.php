<?php
// src/Models/UserModel.php

class UserModel {

    public static function find(int $id): ?array {
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array {
        return Database::fetchOne('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    public static function all(): array {
        return Database::fetchAll('SELECT * FROM users ORDER BY created_at DESC');
    }

    public static function create(string $nom, string $email, string $password, string $role = 'user'): int {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Adresse e-mail invalide.');
        }
        if (self::findByEmail($email)) {
            throw new Exception("Email $email is already in use.");
        }
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters.');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        return Database::insert(
            'INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, ?)',
            [trim($nom), $email, $hash, $role]
        );
    }

    public static function updateProfile(int $id, string $nom, string $email): void {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Adresse e-mail invalide.');
        }
        // Check email uniqueness for other users
        $existing = Database::fetchOne(
            'SELECT id FROM users WHERE email = ? AND id != ?',
            [$email, $id]
        );
        if ($existing) {
            throw new Exception("Email $email is already in use by another account.");
        }
        Database::execute(
            'UPDATE users SET nom = ?, email = ? WHERE id = ?',
            [trim($nom), $email, $id]
        );
    }

    public static function changePassword(int $id, string $currentPassword, string $newPassword): void {
        $user = self::find($id);
        if (!$user) throw new Exception('Utilisateur introuvable.');
        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception('Mot de passe actuel incorrect.');
        }
        if (strlen($newPassword) < 8) {
            throw new Exception('New password must be at least 8 characters.');
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $id]);
    }

    public static function setActive(int $id, bool $active): void {
        Database::execute('UPDATE users SET actif = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function setRole(int $id, string $role): void {
        if (!in_array($role, ['admin', 'user'])) throw new Exception('Rôle invalide.');
        Database::execute('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    public static function delete(int $id): void {
        Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    /**
     * Statistiques de l'utilisateur
     */
    public static function stats(int $userId): array {
        return [
            'nb_fichiers'  => (int)(Database::fetchOne(
                'SELECT COUNT(*) c FROM files WHERE uploaded_by = ?', [$userId]
            )['c'] ?? 0),
            'taille_total' => (int)(Database::fetchOne(
                'SELECT COALESCE(SUM(taille),0) c FROM files WHERE uploaded_by = ?', [$userId]
            )['c'] ?? 0),
            'nb_actions'   => (int)(Database::fetchOne(
                'SELECT COUNT(*) c FROM audit_logs WHERE user_id = ?', [$userId]
            )['c'] ?? 0),
        ];
    }
}
