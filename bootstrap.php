<?php
// bootstrap.php — Chargement de l'application

require_once __DIR__ . '/config/config.php';

// ── Autoloader PSR-4 simplifié ────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'Database'             => '/src/Models/Database.php',
        'AuditLog'             => '/src/Models/AuditLog.php',
        'Folder'               => '/src/Models/Folder.php',
        'FileModel'            => '/src/Models/FileModel.php',
        'Auth'                 => '/src/Auth/Auth.php',
        'MetadataExtractor'    => '/src/Services/MetadataExtractor.php',
        'TextExtractor'        => '/src/Services/TextExtractor.php',
        'SensitivityDetector'  => '/src/Services/SensitivityDetector.php',
        'ClaudeAiService'      => '/src/Services/ClaudeAiService.php',
        'ClassificationEngine' => '/src/Services/ClassificationEngine.php',
        'UploadController'     => '/src/Controllers/UploadController.php',
        'H'                    => '/src/Helpers/Helpers.php',
        'User'                 => '/src/Models/User.php',
        'UserModel'            => '/src/Models/UserModel.php',
        'Layout'               => '/src/Helpers/Layout.php',
        'Settings'             => '/src/Models/Settings.php',
    ];
    if (isset($map[$class])) {
        require_once ROOT_PATH . $map[$class];
    }
});

// ── Gestion des erreurs ───────────────────────────────────────────────────────
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return false;
});

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Europe/Paris');

// ── Session ───────────────────────────────────────────────────────────────────
Auth::init();

// ── Création des répertoires de stockage si absents ───────────────────────────
foreach ([STORAGE_PATH, TMP_PATH] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}
