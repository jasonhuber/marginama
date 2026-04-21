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
<section class="block tight">
  <div class="container narrow">
    <p class="muted" style="margin-bottom:1rem;"><a href="/video-reviews" style="color:var(--fg-2);">← All reviews</a></p>

    <header class="review-head">
      <span class="provider-badge" aria-hidden="true"><?= icon('capture') ?></span>
      <div>
        <h1><?= e($review['video_title'] ?: '(untitled)') ?></h1>
        <div class="sub">
          <span><?= e(provider_label($review['provider'])) ?></span>
          <span>·</span>
          <a href="<?= e($review['video_url']) ?>" target="_blank" rel="noopener">Open video ↗</a>
          <span>·</span>
          <span><?= count($critiques) ?> <?= count($critiques) === 1 ? 'note' : 'notes' ?></span>
        </div>
      </div>
    </header>

    <div class="card" style="margin-bottom:1.5rem;">
      <form method="post" action="/video-reviews/<?= e($review['id']) ?>/share" class="row">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($shareUrl): ?>
          <span class="i" style="color:var(--accent);"><?= icon('share') ?></span>
          <input class="grow mono" type="text" readonly value="<?= e($shareUrl) ?>" onclick="this.select()">
          <button type="submit" name="action" value="revoke" class="btn danger">Revoke link</button>
        <?php else: ?>
          <span class="i" style="color:var(--fg-3);"><?= icon('share') ?></span>
          <span class="grow muted">Share a read-only link to this review.</span>
          <button type="submit" name="action" value="create" class="btn primary">Create share link</button>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="row" style="margin-bottom:1rem;">
        <h2 style="margin:0; flex:1; font-size:1.15rem;">Critiques</h2>
        <a class="btn sm" href="/video-reviews/<?= e($review['id']) ?>/export"><?= icon('export', 'i') ?>Export JSON</a>
        <form method="post" action="/video-reviews/<?= e($review['id']) ?>/delete" onsubmit="return confirm('Delete this entire review and all critiques?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="btn danger sm">Delete review</button>
        </form>
      </div>
      <?php if (!$critiques): ?>
        <p class="muted" style="margin:0;">No critiques yet. Open the video with the extension running to add your first note.</p>
      <?php else: ?>
        <div class="critiques">
          <?php foreach ($critiques as $c):
            $link = deep_link_at_time($review['video_url'], $review['provider'], (int) $c['timestamp_sec']);
          ?>
            <div class="critique">
              <div class="ts"><a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(format_timestamp((int) $c['timestamp_sec'])) ?></a></div>
              <form method="post" action="/video-reviews/critiques/<?= e($c['id']) ?>/edit" class="note">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <textarea name="note"><?= e($c['note']) ?></textarea>
                <div class="saverow"><button type="submit" class="btn sm">Save edit</button></div>
              </form>
              <form method="post" action="/video-reviews/critiques/<?= e($c['id']) ?>/delete" onsubmit="return confirm('Delete this note?')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn ghost sm" aria-label="Delete note">✕</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
