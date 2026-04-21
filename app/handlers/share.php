<?php
declare(strict_types=1);

/** @var array $params */
$token = $params[0];

$stmt = db()->prepare(
    'SELECT r.id, r.video_url, r.video_title, r.provider, r.updated_at,
            u.name AS reviewer_name, u.email AS reviewer_email
     FROM video_reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.share_token = ?'
);
$stmt->execute([$token]);
$review = $stmt->fetch();
if (!$review) {
    http_response_code(404);
    echo 'Share link not found.';
    exit;
}

$cst = db()->prepare(
    'SELECT timestamp_sec, note FROM video_critiques WHERE review_id = ? ORDER BY timestamp_sec ASC'
);
$cst->execute([$review['id']]);
$critiques = $cst->fetchAll();

$title = 'Shared review';
$user = null;
ob_start(); ?>
<h1><?= e($review['video_title'] ?: '(untitled)') ?></h1>
<p class="muted">
  Review by <?= e($review['reviewer_name'] ?: $review['reviewer_email']) ?> ·
  <a href="<?= e($review['video_url']) ?>" target="_blank" rel="noopener">Open video ↗</a>
</p>
<div class="card">
  <?php if (!$critiques): ?>
    <p class="muted">No critiques on this review.</p>
  <?php else: ?>
    <?php foreach ($critiques as $c):
      $link = deep_link_at_time($review['video_url'], $review['provider'], (int) $c['timestamp_sec']);
    ?>
    <div class="critique">
      <div class="ts mono"><a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(format_timestamp((int) $c['timestamp_sec'])) ?></a></div>
      <div class="note"><?= nl2br(e($c['note'])) ?></div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
