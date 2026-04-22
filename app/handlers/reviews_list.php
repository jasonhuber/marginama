<?php
declare(strict_types=1);

require_once __DIR__ . '/../tags.php';

$user = require_session();

// Accept ?tag=foo (repeatable). Filter AND-ed: a review must carry all selected
// tags to appear. Unknown tag names narrow to zero results — that's fine.
$rawTagParams = [];
if (isset($_GET['tag'])) {
    $rawTagParams = is_array($_GET['tag']) ? $_GET['tag'] : [$_GET['tag']];
}
$activeTags = [];
foreach ($rawTagParams as $t) {
    if (!is_string($t)) continue;
    $n = normalize_tag_name($t);
    if ($n !== '' && !in_array($n, $activeTags, true)) {
        $activeTags[] = $n;
    }
}

$allTags = tags_for_user_with_counts($user['id']);

if ($activeTags) {
    $filteredIds = review_ids_with_all_tags($user['id'], $activeTags);
    if (!$filteredIds) {
        $reviews = [];
    } else {
        $place = implode(',', array_fill(0, count($filteredIds), '?'));
        $stmt = db()->prepare(
            "SELECT r.id, r.video_url, r.video_title, r.provider, r.updated_at,
                    (SELECT COUNT(*) FROM video_critiques c WHERE c.review_id = r.id) AS critique_count
             FROM video_reviews r
             WHERE r.user_id = ? AND r.id IN ($place)
             ORDER BY r.updated_at DESC"
        );
        $stmt->execute(array_merge([$user['id']], $filteredIds));
        $reviews = $stmt->fetchAll();
    }
} else {
    $stmt = db()->prepare(
        'SELECT r.id, r.video_url, r.video_title, r.provider, r.updated_at,
                (SELECT COUNT(*) FROM video_critiques c WHERE c.review_id = r.id) AS critique_count
         FROM video_reviews r
         WHERE r.user_id = ?
         ORDER BY r.updated_at DESC'
    );
    $stmt->execute([$user['id']]);
    $reviews = $stmt->fetchAll();
}

$reviewTags = tags_for_reviews(array_map(fn($r) => $r['id'], $reviews));

function reviews_list_url_with_tags(array $tags): string {
    if (!$tags) return '/video-reviews';
    $q = [];
    foreach ($tags as $t) $q[] = 'tag[]=' . urlencode($t);
    return '/video-reviews?' . implode('&', $q);
}

$title = 'Reviews';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Your reviews</h1>
        <p class="muted">
          <?= count($reviews) ?> <?= count($reviews) === 1 ? 'review' : 'reviews' ?>
          <?php if ($activeTags): ?>
            · filtered by
            <?php foreach ($activeTags as $i => $t): ?>
              <code><?= e($t) ?></code><?= $i < count($activeTags) - 1 ? ',' : '' ?>
            <?php endforeach; ?>
            · <a href="/video-reviews">clear</a>
          <?php endif; ?>
        </p>
      </div>
      <div class="actions">
        <a class="btn" href="/search">Search</a>
        <a class="btn" href="/extension"><?= icon('export', 'i') ?>Install extension</a>
        <a class="btn accent" href="/settings/api-tokens">Mint API token</a>
      </div>
    </div>

    <?php if ($allTags): ?>
      <div class="row" style="flex-wrap:wrap; gap:6px; margin-bottom:1rem;">
        <?php foreach ($allTags as $t):
          $isActive = in_array($t['name'], $activeTags, true);
          $nextTags = $isActive
            ? array_values(array_filter($activeTags, fn($n) => $n !== $t['name']))
            : array_merge($activeTags, [$t['name']]);
          $href = reviews_list_url_with_tags($nextTags);
        ?>
          <a class="chip<?= $isActive ? ' ok' : '' ?>" href="<?= e($href) ?>">
            <?= e($t['name']) ?> <span class="muted"><?= (int) $t['use_count'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$reviews && $activeTags): ?>
      <div class="card empty">
        <h2>No matches</h2>
        <p class="muted" style="margin:0.5rem 0 1.5rem;">No reviews carry all the selected tags.</p>
        <div class="row" style="justify-content:center;">
          <a class="btn" href="/video-reviews">Clear filters</a>
        </div>
      </div>
    <?php elseif (!$reviews): ?>
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
              <div class="sub">
                <?= e(provider_label($r['provider'])) ?>
                <?php $rt = $reviewTags[$r['id']] ?? []; if ($rt): ?>
                  <?php foreach ($rt as $t): ?>
                    · <span class="tag"><?= e($t['name']) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
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
