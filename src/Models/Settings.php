<?php
// src/Models/Settings.php — Paramètres globaux de l'application

class Settings {

    /** Cache en mémoire pour éviter N requêtes par requête HTTP */
    private static array $cache = [];

    /**
     * Lire un paramètre. Retourne $default si la clé n'existe pas.
     * Retourne aussi $default si la table settings n'existe pas encore (migration non exécutée).
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        try {
            $row = Database::fetchOne(
                'SELECT valeur FROM settings WHERE cle = ?', [$key]
            );
        } catch (Exception $e) {
            // Table absente (migration non exécutée) — retourner la valeur par défaut
            self::$cache[$key] = $default;
            return $default;
        }
        $value = $row ? $row['valeur'] : $default;
        self::$cache[$key] = $value;
        return $value;
    }

    /**
     * Écrire un paramètre (INSERT OR UPDATE).
     */
    public static function set(string $key, mixed $value, int $userId = null): void {
        $strValue = (string)$value;
        $existing = Database::fetchOne('SELECT cle FROM settings WHERE cle = ?', [$key]);
        if ($existing) {
            Database::execute(
                'UPDATE settings SET valeur = ?, updated_by = ?, updated_at = NOW() WHERE cle = ?',
                [$strValue, $userId, $key]
            );
        } else {
            Database::insert(
                'INSERT INTO settings (cle, valeur, updated_by) VALUES (?, ?, ?)',
                [$key, $strValue, $userId]
            );
        }
        self::$cache[$key] = $strValue;
    }

    /**
     * Raccourci : DLP actif ?
     */
    public static function dlpEnabled(): bool {
        return (bool)(int)self::get('dlp_enabled', '1');
    }

    /**
     * Sociétés dont les documents sont TOUJOURS sous DLP (quels que soient les réglages).
     */
    public static function dlpCompanies(): array {
        return ['COMPANY - C1', 'COMPANY - C2', 'COMPANY - C3'];
    }

    /**
     * Détermine si le DLP s'applique à un fichier donné pour un utilisateur standard.
     * Retourne true si :
     *   - Le DLP global est actif, OU
     *   - La métadonnée "société" du fichier correspond à une société sous DLP forcé.
     * Retourne toujours false pour un admin.
     */
    public static function isDlpEnforcedForFile(int $fileId): bool {
        if (Auth::isAdmin()) return false;

        // Vérifier si la société du fichier force le DLP
        $meta = Database::fetchOne(
            'SELECT societe FROM file_metadata WHERE file_id = ?', [$fileId]
        );
        if (!empty($meta['societe'])) {
            foreach (self::dlpCompanies() as $company) {
                if (stripos(trim($meta['societe']), trim($company)) !== false) {
                    return true; // DLP forcé indépendamment du switch global
                }
            }
        }

        // Sinon, appliquer selon le switch global
        return self::dlpEnabled();
    }

    /**
     * Raccourci : activer/désactiver le DLP.
     */
    public static function setDlp(bool $enabled, int $userId): void {
        self::set('dlp_enabled', $enabled ? '1' : '0', $userId);
        AuditLog::log(
            $userId,
            $enabled ? 'dlp_enabled' : 'dlp_disabled',
            'settings', null,
            'DLP filtering ' . ($enabled ? 'enabled' : 'disabled') . ' by admin'
        );
    }
}
