<?php
// src/Auth/Auth.php — Authentification et gestion de session

class Auth {

    public static function init(): void {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        if (!empty($_SERVER['HTTPS'])) {
            ini_set('session.cookie_secure', '1');
        }
        session_name(SESSION_NAME);
        session_set_cookie_params(SESSION_LIFETIME);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Régénérer l'ID de session toutes les 30 min
        if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }

    public static function attempt(string $email, string $password): bool {
        $user = Database::fetchOne(
            'SELECT * FROM users WHERE email = ? AND actif = 1',
            [strtolower(trim($email))]
        );
        if (!$user || !password_verify($password, $user['password'])) {
            AuditLog::log(null, 'login_failed', 'user', null, "Email: $email");
            return false;
        }
        self::setUser($user);
        AuditLog::log($user['id'], 'login', 'user', $user['id'], 'Login successful');
        return true;
    }

    public static function setUser(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_nom']  = $user['nom'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_regen'] = time();
    }

    public static function logout(): void {
        if (self::check()) {
            AuditLog::log(self::id(), 'logout', 'user', self::id());
        }
        session_unset();
        session_destroy();
    }

    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [self::id()]);
    }

    public static function role(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            include ROOT_PATH . '/views/errors/403.php';
            exit;
        }
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────
    public static function csrfToken(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(): void {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
    }
}
