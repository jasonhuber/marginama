<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ── Sessions ─────────────────────────────────────────────────────────────────

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secret = env('SESSION_SECRET');
    if (!$secret || strlen($secret) < 16) {
        throw new RuntimeException('SESSION_SECRET must be set (32+ chars).');
    }
    session_name('marginama_sess');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_production(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Bind session to a secret-derived fingerprint — prevents trivial session
    // forgery even if the cookie value is guessed.
    $fp = hash_hmac('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '', $secret);
    if (!isset($_SESSION['fp'])) {
        $_SESSION['fp'] = $fp;
    } elseif ($_SESSION['fp'] !== $fp) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['fp'] = $fp;
    }
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, name, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_session(): array {
    $user = current_user();
    if (!$user) {
        header('Location: /signin');
        exit;
    }
    return $user;
}

function sign_in_user(string $userId): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function is_admin(?array $user): bool {
    if (!$user) return false;
    $adminEmail = env('ADMIN_EMAIL');
    if (!$adminEmail) return false;
    return strcasecmp((string) $user['email'], $adminEmail) === 0;
}

function require_admin(): array {
    $user = current_user();
    if (!is_admin($user)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found.\n";
        exit;
    }
    return $user;
}

function sign_out(): void {
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void {
    start_session();
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        echo 'CSRF token mismatch. Reload the page and try again.';
        exit;
    }
}

// ── API tokens (bearer auth for the extension) ───────────────────────────────

function hash_api_token(string $plaintext): string {
    return hash('sha256', $plaintext);
}

function generate_api_token(): string {
    return 'sk_' . bin2hex(random_bytes(20));
}

/**
 * Validate `Authorization: Bearer ...` (or `X-Api-Key:`). Returns owning user id.
 * Emits a JSON 401 and exits on failure.
 */
function require_bearer(): array {
    $provided = null;
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if (is_string($h) && stripos($h, 'Bearer ') === 0) {
        $provided = trim(substr($h, 7));
    }
    if (!$provided) {
        $x = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (is_string($x) && $x !== '') {
            $provided = trim($x);
        }
    }
    if (!$provided) {
        api_error(401, 'Missing API token');
    }
    $stmt = db()->prepare('SELECT id, user_id FROM api_tokens WHERE token_hash = ? LIMIT 1');
    $stmt->execute([hash_api_token($provided)]);
    $row = $stmt->fetch();
    if (!$row) {
        api_error(401, 'Invalid API token');
    }
    // Best-effort lastUsedAt update — failure here must not break the request.
    try {
        $upd = db()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?');
        $upd->execute([$row['id']]);
    } catch (Throwable) {
        // ignore
    }
    return ['userId' => $row['user_id'], 'tokenId' => $row['id']];
}

// ── JSON helpers (used by API handlers) ──────────────────────────────────────

function cors_headers(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Api-Key');
    header('Access-Control-Max-Age: 86400');
}

function json_response(int $status, array $body): void {
    http_response_code($status);
    cors_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(int $status, string $message): void {
    json_response($status, ['error' => ['message' => $message]]);
}

function json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_error(400, 'Request body must be JSON');
    }
    return $decoded;
}
