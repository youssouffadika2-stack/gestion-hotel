<?php
// Noyau partagé : base de données, sécurité, outils
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);

// Réglages : variables d'environnement en production (Coolify), valeurs WAMP par défaut en local
function env_or(string $k, string $d): string { $v = getenv($k); return $v === false || $v === '' ? $d : $v; }
define('DB_HOST', env_or('DB_HOST', 'localhost'));
define('DB_NAME', env_or('DB_NAME', 'hotels'));
define('DB_USER', env_or('DB_USER', 'root'));
define('DB_PASS', env_or('DB_PASS', ''));
const UPLOAD_DIR = __DIR__ . '/../uploads/';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function money(float|string $n): string
{
    return number_format((float) $n, 0, ',', ' ') . ' FCFA';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Jeton CSRF invalide.');
    }
}

function flash(?string $msg = null, string $type = 'success'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// Enregistre une image envoyée ; retourne le nom du fichier, null si aucun fichier
function save_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Image invalide ou trop lourde (3 Mo max).');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime]) || !getimagesize($file['tmp_name'])) {
        throw new RuntimeException('Format non accepté (JPG, PNG ou WebP).');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        throw new RuntimeException("Échec de l'enregistrement de l'image.");
    }
    return $name;
}

function delete_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        @unlink(UPLOAD_DIR . $name);
    }
}

require_once __DIR__ . '/gestion.php';
