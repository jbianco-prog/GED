#!/usr/bin/env php
<?php
/**
 * bin/reanalyze.php — Script CLI de réanalyse en lot
 *
 * Usage :
 *   php bin/reanalyze.php                   # Réanalyse tous les fichiers non analysés
 *   php bin/reanalyze.php --all             # Réanalyse TOUS les fichiers
 *   php bin/reanalyze.php --file=42         # Réanalyse un fichier spécifique
 *   php bin/reanalyze.php --status=erreur   # Réanalyse les fichiers en erreur
 *   php bin/reanalyze.php --limit=10        # Limite le nombre de fichiers
 */

// Bootstrap CLI
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';

// Simuler une session CLI
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'ged_cli');
if (session_status() === PHP_SESSION_NONE) @session_start();

// Autoloader
spl_autoload_register(function (string $class): void {
    $map = [
        'Database'             => '/src/Models/Database.php',
        'AuditLog'             => '/src/Models/AuditLog.php',
        'Folder'               => '/src/Models/Folder.php',
        'FileModel'            => '/src/Models/FileModel.php',
        'UserModel'            => '/src/Models/UserModel.php',
        'Auth'                 => '/src/Auth/Auth.php',
        'MetadataExtractor'    => '/src/Services/MetadataExtractor.php',
        'TextExtractor'        => '/src/Services/TextExtractor.php',
        'SensitivityDetector'  => '/src/Services/SensitivityDetector.php',
        'ClaudeAiService'      => '/src/Services/ClaudeAiService.php',
        'ClassificationEngine' => '/src/Services/ClassificationEngine.php',
        'H'                    => '/src/Helpers/Helpers.php',
    ];
    if (isset($map[$class])) require_once ROOT_PATH . $map[$class];
});

date_default_timezone_set('Europe/Paris');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ── Parsing des arguments ──────────────────────────────────────────────────────
$opts = getopt('', ['all', 'file:', 'status:', 'limit:', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
GED — Script de réanalyse en lot
Usage : php bin/reanalyze.php [options]

Options :
  --all           Réanalyse tous les fichiers (même déjà analysés)
  --file=ID       Réanalyse un fichier spécifique (ID numérique)
  --status=STAT   Réanalyse les fichiers avec ce statut (ex: erreur, non_analyse)
  --limit=N       Nombre maximum de fichiers à traiter
  --dry-run       Simule sans modifier la base de données
  --help          Affiche cette aide

Statuts disponibles : non_analyse, en_cours, non_sensible, sensible, sensible_eleve, erreur
HELP;
    exit(0);
}

$isDryRun = isset($opts['dry-run']);
$limit    = isset($opts['limit']) ? (int)$opts['limit'] : 0;
$fileId   = isset($opts['file'])  ? (int)$opts['file']  : 0;
$status   = $opts['status'] ?? null;
$all      = isset($opts['all']);

echo "\n=== GED Réanalyse" . ($isDryRun ? ' [DRY-RUN]' : '') . " ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// ── Sélection des fichiers ─────────────────────────────────────────────────────
$sql    = 'SELECT f.id, f.nom_courant, f.extension, f.chemin_stockage FROM files f';
$params = [];

if ($fileId) {
    $sql    .= ' WHERE f.id = ?';
    $params[] = $fileId;
} elseif ($all) {
    $sql .= ' ORDER BY f.id ASC';
} elseif ($status) {
    $sql .= ' LEFT JOIN file_analysis fa ON fa.file_id = f.id WHERE fa.niveau_sensibilite = ? OR (fa.id IS NULL AND ? = \'non_analyse\')';
    $params[] = $status;
    $params[] = $status;
    $sql .= ' ORDER BY f.id ASC';
} else {
    // Par défaut : non analysés
    $sql .= ' LEFT JOIN file_analysis fa ON fa.file_id = f.id WHERE fa.id IS NULL OR fa.niveau_sensibilite = \'non_analyse\' OR fa.niveau_sensibilite = \'en_cours\'';
    $sql .= ' ORDER BY f.id ASC';
}

if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}

$files = Database::fetchAll($sql, $params);
$total = count($files);
echo "Fichiers à traiter : $total\n";

if ($total === 0) {
    echo "Nothing to do.\n\n";
    exit(0);
}

// ── Traitement ─────────────────────────────────────────────────────────────────
$success = 0;
$errors  = 0;

foreach ($files as $i => $file) {
    $num = $i + 1;
    echo "[$num/$total] {$file['nom_courant']} (ID #{$file['id']})... ";

    if (!file_exists($file['chemin_stockage'])) {
        echo "⚠ PHYSICAL FILE MISSING\n";
        $errors++;
        continue;
    }

    if ($isDryRun) {
        echo "OK (dry-run)\n";
        $success++;
        continue;
    }

    try {
        $result = ClassificationEngine::run($file['id'], $file['chemin_stockage'], $file['extension']);
        $niveau = $result['niveau_sensibilite'];
        $score  = isset($result['score_ia']) ? ' IA:' . round((float)$result['score_ia']) . '%' : '';
        echo "✓ $niveau$score\n";
        $success++;
    } catch (Exception $e) {
        echo "✗ ERREUR: " . $e->getMessage() . "\n";
        $errors++;
    }

    // Pause pour ne pas saturer l'API IA
    if (!$isDryRun && !empty(CLAUDE_API_KEY)) {
        usleep(500000); // 500ms
    }
}

// ── Résumé ─────────────────────────────────────────────────────────────────────
echo "\n--- Summary ---\n";
echo "Succès : $success / $total\n";
echo "Erreurs: $errors / $total\n";
echo "Terminé : " . date('Y-m-d H:i:s') . "\n\n";

exit($errors > 0 ? 1 : 0);
