<?php
declare(strict_types=1);

/**
 * Fire-and-forget email alert when a user submits feedback via /feedback.
 * Returns true on attempted send, false if disabled (no destination email).
 *
 * Deliverability note: for mail() to land reliably, FROM_EMAIL must be a
 * real mailbox on the sending domain (SPF/DKIM/DMARC). If not, expect spam
 * folders. The DB row always writes regardless of delivery.
 */
function send_suggestion_email(string $id, array $user, string $kind, string $body): bool {
    $to = env('SUGGESTIONS_ALERT_EMAIL');
    if (!$to) return false;

    $from = env('FROM_EMAIL', 'noreply@marginama.com') ?: 'noreply@marginama.com';
    $host = parse_url(app_url(), PHP_URL_HOST) ?? 'marginama.com';

    $who = $user['name']
        ? "{$user['name']} <{$user['email']}>"
        : (string) $user['email'];

    $subject = '[Marginama] ' . ucfirst($kind) . ' suggestion from ' . ($user['name'] ?: $user['email']);

    $lines = [
        'New suggestion on Marginama',
        '',
        'From:  ' . $who,
        'Kind:  ' . $kind,
        'ID:    ' . $id,
        'Time:  ' . gmdate('c'),
        '',
        '— Body ' . str_repeat('—', 64),
        $body,
        str_repeat('—', 72),
        '',
        'Admin view: https://' . $host . '/admin/suggestions',
    ];
    $message = implode("\n", $lines);

    // Sanitize From/Reply-To (RFC 5322 — single-line, no CRLF injection).
    $safeFrom = preg_replace('/[\r\n]+/', ' ', $from);
    $safeReply = preg_replace('/[\r\n]+/', ' ', (string) $user['email']);

    $headers = [
        'From: Marginama <' . $safeFrom . '>',
        'Reply-To: ' . $safeReply,
        'Content-Type: text/plain; charset=utf-8',
        'X-Mailer: Marginama',
    ];
    $ok = @mail($to, $subject, $message, implode("\r\n", $headers));
    return (bool) $ok;
}
