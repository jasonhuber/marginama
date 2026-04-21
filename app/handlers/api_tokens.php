<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Delete action: /settings/api-tokens/{id}/delete
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
<h1>API tokens</h1>
<p class="muted">
  The Marginama Chrome extension authenticates with a bearer token. Create one here
  and paste it into the extension's Options page. Tokens are shown only once.
</p>
<div class="card callout">
  <div class="row">
    <span class="grow">
      <strong>Don't have the extension yet?</strong>
      <span class="muted"> Download and load it as unpacked.</span>
    </span>
    <a class="btn" href="/extension">Install guide</a>
    <a class="btn primary" href="/extension.zip" download>Download .zip</a>
  </div>
</div>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<?php if ($newTokenPlain): ?>
  <div class="success">
    <strong>New token created.</strong> Copy it now — it won't be shown again.
    <div class="mono card" style="margin-top:0.5rem; word-break:break-all;"><?= e($newTokenPlain) ?></div>
  </div>
<?php endif; ?>

<form class="row" method="post" action="/settings/api-tokens">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input class="grow" type="text" name="name" placeholder="Token name (e.g. &quot;Chrome — laptop&quot;)" required maxlength="255">
  <button type="submit" class="primary">Create token</button>
</form>

<?php if ($tokens): ?>
<h2>Active tokens</h2>
<table>
  <thead><tr><th>Name</th><th>Last used</th><th>Created</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($tokens as $t): ?>
    <tr>
      <td><?= e($t['name']) ?></td>
      <td class="muted"><?= e($t['last_used_at'] ?: '—') ?></td>
      <td class="muted"><?= e($t['created_at']) ?></td>
      <td>
        <form method="post" action="/settings/api-tokens/<?= e($t['id']) ?>/delete" onsubmit="return confirm('Revoke this token?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="danger">Revoke</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
