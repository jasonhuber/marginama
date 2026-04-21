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
  <p class="muted">
    No reviews yet. Install the Marginama Chrome extension, open a YouTube /
    Sybill / Google Drive video, and add your first note. It will appear here.
  </p>
  <p><a href="/settings/api-tokens">Get an API token for the extension →</a></p>
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
