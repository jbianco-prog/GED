#!/usr/bin/env php
<?php
/**
 * bin/manage-users.php — Gestion des utilisateurs en ligne de commande
 *
 * Usage :
 *   php bin/manage-users.php list
 *   php bin/manage-users.php create --nom="Jean Dupont" --email=jean@co.fr --password=secret123 --role=user
 *   php bin/manage-users.php reset-password --email=jean@co.fr --password=nouveau123
 *   php bin/manage-users.php activate --email=jean@co.fr
 *   php bin/manage-users.php deactivate --email=jean@co.fr
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';

spl_autoload_register(function (string $class): void {
    $map = [
        'Database'  => '/src/Models/Database.php',
        'AuditLog'  => '/src/Models/AuditLog.php',
        'UserModel' => '/src/Models/UserModel.php',
        'Folder'    => '/src/Models/Folder.php',
        'H'         => '/src/Helpers/Helpers.php',
    ];
    if (isset($map[$class])) require_once ROOT_PATH . $map[$class];
});

date_default_timezone_set('Europe/Paris');

$command = $argv[1] ?? 'help';
$opts    = getopt('', ['nom:', 'email:', 'password:', 'role:']);

echo "\n=== GED — Gestion utilisateurs ===\n\n";

switch ($command) {

    case 'list':
        $users = Database::fetchAll('SELECT id, nom, email, role, actif, created_at FROM users ORDER BY id');
        printf("%-4s %-25s %-30s %-8s %-8s %s\n", 'ID', 'Name', 'Email', 'Role', 'Active', 'Created');
        echo str_repeat('-', 90) . "\n";
        foreach ($users as $u) {
            printf("%-4d %-25s %-30s %-8s %-8s %s\n",
                $u['id'], $u['nom'], $u['email'], $u['role'],
                $u['actif'] ? 'Oui' : 'Non',
                $u['created_at']
            );
        }
        echo "\n" . count($users) . " utilisateur(s)\n";
        break;

    case 'create':
        $nom      = $opts['nom']      ?? null;
        $email    = $opts['email']    ?? null;
        $password = $opts['password'] ?? null;
        $role     = $opts['role']     ?? 'user';

        if (!$nom || !$email || !$password) {
            echo "Usage : php bin/manage-users.php create --nom=\"Name\" --email=email@ex.fr --password=xxx [--role=admin]\n";
            exit(1);
        }
        try {
            $id = UserModel::create($nom, $email, $password, $role);
            echo "✓ Utilisateur créé (ID #$id) : $email [$role]\n";
        } catch (Exception $e) {
            echo "✗ Erreur : " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'reset-password':
        $email    = $opts['email']    ?? null;
        $password = $opts['password'] ?? null;
        if (!$email || !$password) {
            echo "Usage : php bin/manage-users.php reset-password --email=email@ex.fr --password=nouveau\n";
            exit(1);
        }
        $user = UserModel::findByEmail($email);
        if (!$user) { echo "✗ Utilisateur introuvable : $email\n"; exit(1); }
        if (strlen($password) < 8) { echo "✗ Mot de passe trop court (min 8 caractères)\n"; exit(1); }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $user['id']]);
        echo "✓ Mot de passe réinitialisé pour $email\n";
        break;

    case 'activate':
    case 'deactivate':
        $email  = $opts['email'] ?? null;
        $active = $command === 'activate';
        if (!$email) { echo "Usage : php bin/manage-users.php $command --email=email@ex.fr\n"; exit(1); }
        $user = UserModel::findByEmail($email);
        if (!$user) { echo "✗ Utilisateur introuvable : $email\n"; exit(1); }
        UserModel::setActive($user['id'], $active);
        echo "✓ Compte " . ($active ? 'activé' : 'désactivé') . " : $email\n";
        break;

    default:
        echo "Commandes disponibles :\n";
        echo "  list                              Lister tous les utilisateurs\n";
        echo "  create --nom=... --email=... --password=... [--role=admin|user]\n";
        echo "  reset-password --email=... --password=...\n";
        echo "  activate --email=...\n";
        echo "  deactivate --email=...\n";
        break;
}

echo "\n";
