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

$providerLabels = [
    'youtube' => 'YouTube',
    'sybill'  => 'Sybill',
    'gdrive'  => 'Google Drive',
    'other'   => 'Video',
];

$title = 'Shared review';
$user = null;
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="share-banner">
      <span class="i"><?= icon('share') ?></span>
      <span>You're viewing a read-only shared review.</span>
    </div>

    <header class="review-head">
      <span class="provider-badge" aria-hidden="true"><?= icon('capture') ?></span>
      <div>
        <h1><?= e($review['video_title'] ?: '(untitled)') ?></h1>
        <div class="sub">
          <span><?= e($providerLabels[$review['provider']] ?? 'Video') ?></span>
          <span>·</span>
          <span>Reviewed by <?= e($review['reviewer_name'] ?: $review['reviewer_email']) ?></span>
          <span>·</span>
          <a href="<?= e($review['video_url']) ?>" target="_blank" rel="noopener">Open video ↗</a>
        </div>
      </div>
    </header>

    <div class="card">
      <?php if (!$critiques): ?>
        <p class="muted" style="margin:0;">No critiques on this review.</p>
      <?php else: ?>
        <div class="critiques">
          <?php foreach ($critiques as $c):
            $link = deep_link_at_time($review['video_url'], $review['provider'], (int) $c['timestamp_sec']);
          ?>
            <div class="critique">
              <div class="ts"><a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(format_timestamp((int) $c['timestamp_sec'])) ?></a></div>
              <div class="note" style="padding-top:0.4rem; white-space:pre-wrap; font-size:0.95rem;"><?= nl2br(e($c['note'])) ?></div>
              <span></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <p class="muted" style="text-align:center; margin-top:2rem; font-size:0.88rem;">
      Want your own? <a href="/signup">Create an account on Marginama</a>.
    </p>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
