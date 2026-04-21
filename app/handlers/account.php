<?php
declare(strict_types=1);

$user = require_session();
$pdo = db();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$error = null;
$success = null;

// ── Delete account (POST /settings/account/delete) ───────────────────────────
if ($method === 'POST' && $path === '/settings/account/delete') {
    require_csrf();
    $confirmEmail = trim((string) ($_POST['confirm_email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (strcasecmp($confirmEmail, $user['email']) !== 0) {
        $error = 'Email confirmation did not match your account email.';
    } else {
        $row = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $hash = (string) $row->fetchColumn();
        if (!$hash || !password_verify($password, $hash)) {
            $error = 'Password is incorrect.';
        } else {
            // Cascading delete: video_reviews, video_critiques, api_tokens
            // via FK ON DELETE CASCADE; events and suggestions via SET NULL
            // (they stay as anonymized aggregates for analytics continuity,
            // but the user_id link is severed so nothing identifiable remains).
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            sign_out();
            header('Location: /?deleted=1');
            exit;
        }
    }
}

// ── Update name (POST /settings/account) ─────────────────────────────────────
if ($method === 'POST' && $path === '/settings/account') {
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    if (strlen($name) > 255) {
        $error = 'Name must be 255 characters or fewer.';
    } else {
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')
            ->execute([$name ?: null, $user['id']]);
        $success = 'Name updated.';
        $user['name'] = $name ?: null;
    }
}

// ── Export all my data (GET /settings/account/export) ───────────────────────
if ($method === 'GET' && $path === '/settings/account/export') {
    $profile = $pdo->prepare(
        'SELECT id, email, name, created_at FROM users WHERE id = ?'
    );
    $profile->execute([$user['id']]);
    $profileRow = $profile->fetch();

    $reviews = $pdo->prepare(
        'SELECT id, video_url, video_title, provider, share_token, created_at, updated_at
         FROM video_reviews WHERE user_id = ? ORDER BY created_at ASC'
    );
    $reviews->execute([$user['id']]);
    $reviewRows = $reviews->fetchAll();

    if ($reviewRows) {
        $ids = array_column($reviewRows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $crit = $pdo->prepare(
            "SELECT id, review_id, timestamp_sec, note, created_at, updated_at
             FROM video_critiques WHERE review_id IN ($in) ORDER BY review_id, timestamp_sec ASC"
        );
        $crit->execute($ids);
        $critsByReview = [];
        foreach ($crit->fetchAll() as $c) {
            $critsByReview[$c['review_id']][] = $c;
        }
        foreach ($reviewRows as &$r) {
            $r['critiques'] = $critsByReview[$r['id']] ?? [];
        }
        unset($r);
    }

    $tokens = $pdo->prepare(
        'SELECT id, name, last_used_at, created_at FROM api_tokens WHERE user_id = ? ORDER BY created_at ASC'
    );
    $tokens->execute([$user['id']]);
    $tokenRows = $tokens->fetchAll();

    $sugg = $pdo->prepare(
        'SELECT id, kind, body, page_url, status, created_at FROM suggestions WHERE user_id = ? ORDER BY created_at ASC'
    );
    $sugg->execute([$user['id']]);
    $suggRows = $sugg->fetchAll();

    $events = $pdo->prepare(
        'SELECT id, type, path, meta, ip_trunc, created_at FROM events
         WHERE user_id = ? ORDER BY created_at ASC LIMIT 10000'
    );
    $events->execute([$user['id']]);
    $eventRows = $events->fetchAll();

    $export = [
        'exported_at' => gmdate('c'),
        'profile'     => $profileRow,
        'reviews'     => $reviewRows,
        'api_tokens'  => $tokenRows, // hashes deliberately omitted — tokens are secrets we don't retain in cleartext
        'suggestions' => $suggRows,
        'events'      => $eventRows,
    ];

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="marginama-' . $user['id'] . '.json"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Render settings page ─────────────────────────────────────────────────────

// Summary counts so the user knows what will go with the account.
$counts = [
    'reviews'     => (int) $pdo->query('SELECT COUNT(*) FROM video_reviews WHERE user_id = ' . (int) 0)->fetchColumn(), // placeholder, will replace
];
$c = $pdo->prepare('SELECT COUNT(*) FROM video_reviews WHERE user_id = ?');
$c->execute([$user['id']]);
$counts['reviews'] = (int) $c->fetchColumn();

$c = $pdo->prepare(
    'SELECT COUNT(*) FROM video_critiques c
     JOIN video_reviews r ON r.id = c.review_id WHERE r.user_id = ?'
);
$c->execute([$user['id']]);
$counts['critiques'] = (int) $c->fetchColumn();

$c = $pdo->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?');
$c->execute([$user['id']]);
$counts['tokens'] = (int) $c->fetchColumn();

$c = $pdo->prepare('SELECT COUNT(*) FROM events WHERE user_id = ?');
$c->execute([$user['id']]);
$counts['events'] = (int) $c->fetchColumn();

$title = 'Account';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Account</h1>
        <p class="muted">Your profile, your data, your exit.</p>
      </div>
    </div>

    <?php if ($error): ?><div class="error" style="margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success" style="margin-bottom:1rem;"><?= e($success) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:1.25rem;">
      <h3 style="margin:0 0 0.75rem;">Profile</h3>
      <form class="stack" method="post" action="/settings/account" style="max-width:none;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>Email
          <input type="email" value="<?= e($user['email']) ?>" disabled>
          <span class="hint">Email is locked — contact us to change it.</span>
        </label>
        <label>Name <span class="hint">optional, shown on shared review links</span>
          <input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" maxlength="255">
        </label>
        <div><button type="submit" class="btn">Save name</button></div>
      </form>
    </div>

    <div class="card" style="margin-bottom:1.25rem;">
      <h3 style="margin:0 0 0.5rem;">Your data, by the numbers</h3>
      <div class="row" style="gap:1.5rem; font-family:var(--font-mono); font-size:0.88rem; color:var(--fg-1); margin-bottom:1rem;">
        <span><?= number_format($counts['reviews']) ?> reviews</span>
        <span><?= number_format($counts['critiques']) ?> critiques</span>
        <span><?= number_format($counts['tokens']) ?> API tokens</span>
        <span><?= number_format($counts['events']) ?> analytics events</span>
      </div>
      <p class="muted" style="margin:0 0 1rem; font-size:0.9rem;">
        Export everything tied to your account as JSON. Machine-readable, portable, covers profile + reviews + critiques + API tokens (no secrets) + suggestions + events.
      </p>
      <a class="btn" href="/settings/account/export">Download my data (JSON)</a>
    </div>

    <div class="card" style="border-color:rgba(248,113,113,0.28); background:linear-gradient(180deg, rgba(248,113,113,0.05), transparent);">
      <h3 style="margin:0 0 0.5rem; color:var(--danger);">Delete my account</h3>
      <p class="muted" style="margin:0 0 1rem; font-size:0.94rem;">
        This is irreversible. Everything goes: your profile, all <?= (int) $counts['reviews'] ?> reviews, all <?= (int) $counts['critiques'] ?> critiques, all <?= (int) $counts['tokens'] ?> API tokens, all <?= (int) $counts['events'] ?> analytics rows (unlinked from you), any suggestions you submitted.
        Shared links you've created become invalid immediately.
      </p>
      <p class="muted" style="margin:0 0 1rem; font-size:0.94rem;">
        Download your data first if you want a copy.
      </p>
      <form class="stack" method="post" action="/settings/account/delete" style="max-width:none;" onsubmit="return confirm('Permanently delete your account and all associated data? This cannot be undone.');">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>Type your email to confirm
          <input type="email" name="confirm_email" required autocomplete="off" placeholder="<?= e($user['email']) ?>">
        </label>
        <label>Your current password
          <input type="password" name="password" required autocomplete="current-password">
        </label>
        <div><button type="submit" class="btn danger">Delete my account permanently</button></div>
      </form>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
