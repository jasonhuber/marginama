<?php
declare(strict_types=1);

$user = require_session();

$stmt = db()->prepare(
    'SELECT r.id, r.video_url, r.video_title, r.provider, r.updated_at,
            (SELECT COUNT(*) FROM video_critiques c WHERE c.review_id = r.id) AS critique_count
     FROM video_reviews r
     WHERE r.user_id = ?
     ORDER BY r.updated_at DESC'
);
$stmt->execute([$user['id']]);
$reviews = $stmt->fetchAll();

$title = 'Video reviews';
ob_start(); ?>
<h1>Video reviews</h1>
<?php if (!$reviews): ?>
  <div class="card empty">
    <h2 style="margin-top:0;">No reviews yet</h2>
    <p class="muted">
      Install the Marginama extension, open a YouTube / Sybill / Google Drive
      video, press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd>, type a note — it
      shows up here.
    </p>
    <p class="row">
      <a class="btn primary" href="/extension">Install the extension</a>
      <a class="btn" href="/settings/api-tokens">Mint an API token</a>
    </p>
  </div>
<?php else: ?>
  <table>
    <thead>
      <tr><th>Video</th><th>Notes</th><th>Updated</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($reviews as $r): ?>
      <tr>
        <td>
          <a href="/video-reviews/<?= e($r['id']) ?>">
            <?= e($r['video_title'] ?: $r['video_url']) ?>
          </a>
          <div class="muted mono"><?= e($r['provider']) ?></div>
        </td>
        <td><?= (int) $r['critique_count'] ?></td>
        <td class="muted"><?= e($r['updated_at']) ?></td>
        <td><a href="/video-reviews/<?= e($r['id']) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
