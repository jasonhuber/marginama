<?php
declare(strict_types=1);

/**
 * Self-hosted analytics. One events table, one track_event() call.
 *
 * Privacy posture:
 *   - IPs truncated to /24 (IPv4) or /48 (IPv6) — never the full address.
 *   - Session cookie values are sha256-hashed before storage (16 hex chars)
 *     so repeat sessions can be counted without retaining identifiers.
 *   - User-Agent stored verbatim (capped at 500 chars) for device breakdown.
 *   - Analytics failures swallow silently — must never break a request.
 */

function analytics_truncate_ip(string $ip): string {
    if ($ip === '') return '';
    if (str_contains($ip, ':')) {
        // IPv6 → keep first 3 hex groups (/48).
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 3)) . '::';
    }
    // IPv4 → /24.
    $parts = explode('.', $ip);
    if (count($parts) === 4) {
        return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0";
    }
    return $ip;
}

/**
 * Record one row in `events`. Never throws; swallows DB errors by design.
 *
 * @param string      $type   Namespaced event type: 'page_view',
 *                            'auth.signin', 'critique.create', etc.
 * @param string|null $path   Request path (for page_view).
 * @param array       $meta   Small associative array, JSON-encoded.
 * @param string|null $userIdOverride For API paths where there's no session
 *                            (extension bearer calls) — pass userId explicitly.
 */
function track_event(
    string $type,
    ?string $path = null,
    array $meta = [],
    ?string $userIdOverride = null
): void {
    try {
        $userId = $userIdOverride;
        if ($userId === null) {
            // current_user() starts the session — only call if one's likely active.
            $hasSessionCookie = !empty($_COOKIE['marginama_sess']);
            if ($hasSessionCookie) {
                $u = current_user();
                $userId = $u['id'] ?? null;
            }
        }
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500) ?: null;
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ipTrunc = analytics_truncate_ip($ip) ?: null;
        $ref = $_SERVER['HTTP_REFERER'] ?? null;
        if ($ref !== null) $ref = substr($ref, 0, 1024);

        $sessionRaw = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionRaw = session_id() ?: null;
        } elseif (!empty($_COOKIE['marginama_sess'])) {
            $sessionRaw = (string) $_COOKIE['marginama_sess'];
        }
        $sessionH = $sessionRaw ? substr(hash('sha256', $sessionRaw), 0, 16) : null;

        $stmt = db()->prepare(
            'INSERT INTO events
               (id, user_id, type, path, meta, ip_trunc, ua, session_h, referer)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            ulid(),
            $userId,
            $type,
            $path,
            $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            $ipTrunc,
            $ua,
            $sessionH,
            $ref,
        ]);
    } catch (Throwable) {
        // Swallow — analytics must never bubble up and break the user's request.
    }
}
