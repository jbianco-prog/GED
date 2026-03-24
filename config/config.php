<?php
// config/config.php — Configuration centrale de la GED

// ── Base de données ──────────────────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')     ?: '');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: '');
define('DB_USER',     getenv('DB_USER')     ?: '');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// ── Application ──────────────────────────────────────────────────────────────
define('APP_NAME',    'SharePoint Online');
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost');
define('APP_ENV',     getenv('APP_ENV') ?: 'production'); // production | development
define('APP_DEBUG',   APP_ENV === 'development');

// ── Chemins ──────────────────────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('TMP_PATH',     STORAGE_PATH . '/tmp');
define('PUBLIC_PATH',  ROOT_PATH . '/public');

// ── Upload ───────────────────────────────────────────────────────────────────
define('MAX_FILE_SIZE',  50 * 1024 * 1024); // 50 Mo
define('ALLOWED_EXTENSIONS', [
    'pdf','doc','docx','xls','xlsx','ppt','pptx',
    'txt','csv','json','xml',
    'jpg','jpeg','png','gif','webp','bmp',
    'zip','tar','gz','7z',
    'mp4','avi','mov','mkv',
    'mp3','wav','ogg',
    'odt','ods','odp'
]);
define('BLOCKED_EXTENSIONS', ['php','php3','php4','php5','phtml','exe','bat','cmd','sh','js','vbs','py','rb']);

// ── IA / Claude API ──────────────────────────────────────────────────────────
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: 'sk-ant-api03---API---KEY---HERE---);
define('CLAUDE_MODEL',     'claude-sonnet-4-20250514');
define('CLAUDE_MAX_TOKENS', 1024);
define('AI_TEXT_LIMIT',    4000); // Nb de caractères max envoyés à l'IA

// ── Sécurité ─────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'ged_session');
define('SESSION_LIFETIME', 7200); // 2h
define('CSRF_TOKEN_NAME',  '_csrf_token');

// ── Mots-clés sensibles ──────────────────────────────────────────────────────
define('SENSITIVE_KEYWORDS', [
'confidentiel', 'confidential', 'secret', 'private', 'ne pas diffuser', 'ne pas distribuer', 'usage interne', 'internal', 'restricted', 'restreint', 'sensible', 'sensitive', 'propriétaire', 'proprietary', 'privé', 'personal', 'classifié', 'classified', 'non public', 'not public', 'ne pas partager', 'do not share', 'do not distribute', 'disclosure prohibited', 'accès restreint', 'limited access', 'accès limité', 'reserved', 'réservé', 'privileged', 'work product', 'protected ', 'distribution limitée', 'circulation restreinte', 'document confidentiel', 'document réservé', 'do not forward', 'ne pas transférer', 'interne uniquement', 'COMPANY C1', 'COMPANY C2', 'COMPANY C3'
]);
