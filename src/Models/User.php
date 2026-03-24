<?php
// src/Models/User.php

class User {

    public static function find(int $id): ?array {
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array {
        return Database::fetchOne('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
    }

    public static function all(): array {
        return Database::fetchAll('SELECT id, nom, email, role, actif, created_at FROM users ORDER BY created_at DESC');
    }

    public static function create(string $nom, string $email, string $plainPassword, string $role = 'user'): int {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email invalide.');
        if (self::findByEmail($email)) throw new Exception("L'email $email est déjà utilisé.");
        if (mb_strlen($plainPassword) < 8) throw new Exception('Mot de passe trop court (8 car. min).');
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        return Database::insert(
            'INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, ?)',
            [trim($nom), $email, $hash, $role]
        );
    }

    public static function updatePassword(int $id, string $newPassword): void {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $id]);
    }

    public static function setActive(int $id, bool $active): void {
        Database::execute('UPDATE users SET actif = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function setRole(int $id, string $role): void {
        if (!in_array($role, ['admin','user'])) throw new Exception('Rôle invalide.');
        Database::execute('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    public static function delete(int $id): void {
        Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    }
}
