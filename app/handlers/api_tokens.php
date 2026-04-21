<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST' && preg_match('#^/settings/api-tokens/([A-Z0-9]{26})/delete/?$#', $path, $m)) {
    require_csrf();
    $stmt = db()->prepare('DELETE FROM api_tokens WHERE id = ? AND user_id = ?');
    $stmt->execute([$m[1], $user['id']]);
    header('Location: /settings/api-tokens');
    exit;
}

$newTokenPlain = null;
$error = null;

if ($method === 'POST') {
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '' || strlen($name) > 255) {
        $error = 'Give the token a name (1–255 chars).';
    } else {
        $plain = generate_api_token();
        $id = ulid();
        $stmt = db()->prepare(
            'INSERT INTO api_tokens (id, user_id, name, token_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, $user['id'], $name, hash_api_token($plain)]);
        $newTokenPlain = $plain;
    }
}

$stmt = db()->prepare(
    'SELECT id, name, last_used_at, created_at
     FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$user['id']]);
$tokens = $stmt->fetchAll();

$title = 'API tokens';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>API tokens</h1>
        <p class="muted">Tokens authorize the Marginama Chrome extension. Each one is hashed at rest and shown only once at creation.</p>
      </div>
    </div>

    <?php if ($error): ?><div class="error" style="margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>

    <?php if ($newTokenPlain): ?>
      <div class="card callout" style="margin-bottom:1.5rem;">
        <h3 style="margin:0 0 0.35rem;">New token created</h3>
        <p class="muted" style="margin:0 0 0.75rem;">Copy it now — it won't be shown again.</p>
        <div class="mono card" style="margin:0;padding:0.7rem 0.85rem;word-break:break-all;background:var(--bg-0);"><?= e($newTokenPlain) ?></div>
      </div>
    <?php endif; ?>

    <div class="card callout" style="margin-bottom:1.5rem;">
      <div class="row">
        <span class="i" style="color:var(--accent-ink);"><?= icon('export') ?></span>
        <span class="grow">
          <strong style="color:var(--accent-ink);">Don't have the extension yet?</strong>
          <span class="muted"> Download and load it as unpacked.</span>
        </span>
        <a class="btn sm" href="/extension">Install guide</a>
        <a class="btn accent sm" href="/extension.zip" download>Download <span class="mono">.zip</span></a>
      </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
      <h3 style="margin:0 0 0.75rem;">Create a token</h3>
      <form class="row" method="post" action="/settings/api-tokens">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input class="grow" type="text" name="name" placeholder='Token name (e.g. "Chrome — laptop")' required maxlength="255">
        <button type="submit" class="btn accent">Create token</button>
      </form>
    </div>

    <?php if ($tokens): ?>
      <h2 style="font-size:1.1rem; margin:1rem 0 0.75rem;">Active tokens</h2>
      <div class="reviews-list">
        <?php foreach ($tokens as $t): ?>
          <div class="review-card" style="grid-template-columns:auto 1fr auto;">
            <span class="provider-badge" aria-hidden="true" style="color:var(--accent);"><?= icon('secure') ?></span>
            <div class="meta">
              <h3><?= e($t['name']) ?></h3>
              <div class="sub">
                Last used <?= $t['last_used_at'] ? e($t['last_used_at']) : '<span class="muted">never</span>' ?>
                · Created <?= e($t['created_at']) ?>
              </div>
            </div>
            <form method="post" action="/settings/api-tokens/<?= e($t['id']) ?>/delete" onsubmit="return confirm('Revoke this token?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <button type="submit" class="btn danger sm">Revoke</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
