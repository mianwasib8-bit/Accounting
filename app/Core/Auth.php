<?php
/**
 * Session Authentication
 */

declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $db = Database::getInstance();
        $user = $db->fetch(
            'SELECT * FROM users WHERE username = :u LIMIT 1',
            ['u' => $username]
        );

        if (!$user || !(int)$user['is_active']) {
            return false;
        }

        $valid = password_verify($password, $user['password_hash']);

        // Demo bootstrap: admin / admin123
        if (!$valid && $username === 'admin' && $password === 'admin123') {
            $valid = true;
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $db->query('UPDATE users SET password_hash = :h WHERE id = :id', [
                'h' => $hash,
                'id' => $user['id'],
            ]);
        }

        if (!$valid) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int)$user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'phone'     => $user['phone'],
            'role'      => $user['role'],
        ];

        $db->query('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);

        Audit::log((int)$user['id'], 'login', 'auth', (string)$user['id'], 'User logged in');

        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log(self::id(), 'logout', 'auth', (string)self::id(), 'User logged out');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login.php');
        }
    }

    public static function requireLoginApi(): void
    {
        if (!self::check()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    public static function updateSession(array $fields): void
    {
        if (!isset($_SESSION['user'])) {
            return;
        }
        foreach ($fields as $k => $v) {
            $_SESSION['user'][$k] = $v;
        }
    }
}
