<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();
$id = $params[0];

$stmt = db()->prepare(
    'SELECT id, video_url, video_title, provider, share_token, created_at, updated_at
     FROM video_reviews WHERE id = ? AND user_id = ?'
);
$stmt->execute([$id, $user['id']]);
$review = $stmt->fetch();
if (!$review) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$cst = db()->prepare(
    'SELECT id, timestamp_sec, note, created_at
     FROM video_critiques WHERE review_id = ? ORDER BY timestamp_sec ASC'
);
$cst->execute([$id]);
$critiques = $cst->fetchAll();

$shareUrl = $review['share_token']
    ? app_url() . '/share/video-review/' . $review['share_token']
    : null;

$title = $review['video_title'] ?: 'Review';
ob_start(); ?>
<p class="muted"><a href="/video-reviews">← All reviews</a></p>
<h1><?= e($review['video_title'] ?: '(untitled)') ?></h1>
<p class="muted">
  <span class="mono"><?= e($review['provider']) ?></span> ·
  <a href="<?= e($review['video_url']) ?>" target="_blank" rel="noopener">Open video ↗</a>
</p>

<div class="card">
  <form method="post" action="/video-reviews/<?= e($review['id']) ?>/share" class="row">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($shareUrl): ?>
      <input class="grow mono" type="text" readonly value="<?= e($shareUrl) ?>">
      <button type="submit" name="action" value="revoke">Revoke link</button>
    <?php else: ?>
      <span class="grow muted">Share a read-only link to this review.</span>
      <button type="submit" name="action" value="create" class="primary">Create share link</button>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="row">
    <h2 style="margin:0; flex:1;">Critiques</h2>
    <a class="btn" href="/video-reviews/<?= e($review['id']) ?>/export">Export JSON</a>
    <form method="post" action="/video-reviews/<?= e($review['id']) ?>/delete" onsubmit="return confirm('Delete this entire review and all critiques?')">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="danger">Delete review</button>
    </form>
  </div>
  <?php if (!$critiques): ?>
    <p class="muted">No critiques yet.</p>
  <?php else: ?>
    <?php foreach ($critiques as $c):
      $link = deep_link_at_time($review['video_url'], $review['provider'], (int) $c['timestamp_sec']);
    ?>
    <div class="critique">
      <div class="ts mono"><a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(format_timestamp((int) $c['timestamp_sec'])) ?></a></div>
      <form method="post" action="/video-reviews/critiques/<?= e($c['id']) ?>/edit" class="note">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <textarea name="note"><?= e($c['note']) ?></textarea>
        <div class="row" style="margin-top:0.5rem;">
          <button type="submit">Save</button>
          <span class="muted" style="flex:1;"></span>
        </div>
      </form>
      <form method="post" action="/video-reviews/critiques/<?= e($c['id']) ?>/delete" onsubmit="return confirm('Delete this note?')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="danger">✕</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
