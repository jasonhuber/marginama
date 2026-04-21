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

$title = 'Reviews';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Your reviews</h1>
        <p class="muted"><?= count($reviews) ?> <?= count($reviews) === 1 ? 'review' : 'reviews' ?></p>
      </div>
      <div class="actions">
        <a class="btn" href="/extension"><?= icon('export', 'i') ?>Install extension</a>
        <a class="btn accent" href="/settings/api-tokens">Mint API token</a>
      </div>
    </div>

    <?php if (!$reviews): ?>
      <div class="card empty">
        <span class="i" style="width:56px;height:56px;border-radius:var(--r-lg);background:var(--card-2);color:var(--accent);border:1px solid var(--accent-line);margin-bottom:1rem;display:inline-flex;align-items:center;justify-content:center;">
          <?= icon('capture') ?>
        </span>
        <h2>No reviews yet</h2>
        <p class="muted" style="max-width:40ch;margin:0.5rem auto 1.5rem;">
          Install the Marginama extension, open a YouTube / Sybill / Google Drive
          video, press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd>, type a note — it
          shows up here.
        </p>
        <div class="row" style="justify-content:center;">
          <a class="btn accent" href="/extension">Install the extension</a>
          <a class="btn" href="/settings/api-tokens">Mint an API token</a>
        </div>
      </div>
    <?php else: ?>
      <div class="reviews-list">
        <?php foreach ($reviews as $r): ?>
          <a class="review-card" href="/video-reviews/<?= e($r['id']) ?>" style="text-decoration:none;color:inherit;">
            <span class="provider-badge" aria-hidden="true"><?= icon('capture') ?></span>
            <div class="meta">
              <h3><span><?= e($r['video_title'] ?: $r['video_url']) ?></span></h3>
              <div class="sub"><?= e(provider_label($r['provider'])) ?></div>
            </div>
            <span class="count-pill"><?= (int) $r['critique_count'] ?> <?= ((int) $r['critique_count']) === 1 ? 'note' : 'notes' ?></span>
            <span class="stamp"><?= e(substr($r['updated_at'], 0, 10)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
