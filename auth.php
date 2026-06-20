<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
    $pad = 4 - (strlen($data) % 4);
    return base64_decode(strtr($data, '-_', '+/') . ($pad < 4 ? str_repeat('=', $pad) : ''));
}

function create_jwt(array $payload): string {
    $header  = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $body    = b64url_encode(json_encode($payload));
    $sig     = b64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function verify_jwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $b, $s] = $parts;
    $expected = b64url_encode(hash_hmac('sha256', "$h.$b", JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return null;
    $payload = json_decode(b64url_decode($b), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && $payload['exp'] < time()) return null;
    return $payload;
}

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function get_auth_user(): ?array {
    session_init();

    // Session-based (web pages)
    if (!empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    // Bearer token (API / AJAX)
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') === 0) {
        $payload = verify_jwt(substr($auth, 7));
        if ($payload && isset($payload['userId'])) {
            return db_fetch(
                'SELECT id, email, name, role, restaurantId FROM User WHERE id = ? AND isActive = 1',
                [$payload['userId']]
            );
        }
    }

    return null;
}

function require_auth(bool $redirect = false): array {
    $user = get_auth_user();
    if (!$user) {
        if ($redirect) {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
        json_error(401, 'Not authenticated');
    }
    return $user;
}

function require_admin(bool $redirect = false): array {
    $user = require_auth($redirect);
    if (!in_array($user['role'], ['ADMIN', 'SUPERADMIN', 'MANAGER'])) {
        if ($redirect) {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
        json_error(403, 'Admin access required');
    }
    return $user;
}

function auth_login(string $email, string $password): array|false {
    $user = db_fetch('SELECT * FROM User WHERE email = ? AND isActive = 1', [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    session_init();
    $safe = [
        'id'           => $user['id'],
        'email'        => $user['email'],
        'name'         => $user['name'],
        'role'         => $user['role'],
        'restaurantId' => $user['restaurantId'] ?? null,
    ];
    $_SESSION['user'] = $safe;
    $token = create_jwt([
        'userId'       => $user['id'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'restaurantId' => $user['restaurantId'] ?? null,
        'exp'          => time() + 86400,
    ]);
    return ['user' => $safe, 'accessToken' => $token];
}

function auth_logout(): void {
    session_init();
    $_SESSION = [];
    session_destroy();
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_error(int $status, string $message): never {
    json_response(['success' => false, 'error' => $message], $status);
}

function json_ok(mixed $data = null, string $message = ''): never {
    $r = ['success' => true];
    if ($data !== null)    $r['data']    = $data;
    if ($message !== '')   $r['message'] = $message;
    json_response($r);
}
