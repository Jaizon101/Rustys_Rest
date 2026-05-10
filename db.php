<?php
// ============================================================
//  db.php — Database + Security + Session Helpers
//  Rusty's Rest and Lodging
//
//  Works on Railway (reads env vars set in Railway dashboard)
//  AND falls back to XAMPP defaults for local development.
// ============================================================

// ── Database credentials ──────────────────────────────────────
// Railway injects MYSQLHOST, MYSQLPORT, MYSQLDATABASE,
// MYSQLUSER, MYSQLPASSWORD automatically when you add a
// MySQL service and link it to your app.
// For local XAMPP, the getenv() calls return false/empty,
// so we fall back to 'localhost', 'root', '', etc.

define('DB_HOST',    getenv('MYSQLHOST')     ?: 'localhost');
define('DB_PORT',    getenv('MYSQLPORT')     ?: '3306');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: 'rustys_rest_db');
define('DB_USER',    getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── PDO Singleton ─────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT
              .";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Return a clean JSON error (never expose raw PDO message in prod)
            jsonResponse(false, 'Database connection failed. Please try again later.', [], 500);
        }
    }
    return $pdo;
}

// ── Session (hardened) ────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name']  ?? 'Guest',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? 'guest',
    ];
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $target = $redirect ?: ($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
        header('Location: login.php?redirect=' . urlencode($target));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin('admin.php');
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

// ── CSRF Protection ───────────────────────────────────────────
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    startSession();
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(false, 'Security check failed. Please refresh and try again.', [], 403);
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function csrfMeta(): string {
    return '<meta name="csrf-token" content="' . csrfToken() . '">';
}

// ── Input Sanitization ────────────────────────────────────────
function clean(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function cleanInt(mixed $v, int $default = 0): int {
    $r = filter_var($v, FILTER_VALIDATE_INT);
    return $r !== false ? (int)$r : $default;
}

function cleanFloat(mixed $v, float $default = 0.0): float {
    $r = filter_var($v, FILTER_VALIDATE_FLOAT);
    return $r !== false ? (float)$r : $default;
}

// ── JSON Response ─────────────────────────────────────────────
function jsonResponse(bool $ok, string $msg, array $data = [], int $code = 200): never {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}
