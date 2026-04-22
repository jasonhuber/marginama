<?php
declare(strict_types=1);

// GET /search — text box + tag filter across one user's reviews + critiques.
// Semantic search if OPENAI_API_KEY is set; substring fallback otherwise.

require_once __DIR__ . '/../tags.php';
require_once __DIR__ . '/../search.php';

$user = require_session();

$q = trim((string) ($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 500, 'UTF-8');

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
$backlog = embedding_backlog_for_user($user['id']);

$reviewIdFilter = null;
if ($activeTags) {
    $reviewIdFilter = review_ids_with_all_tags($user['id'], $activeTags);
}

$hits = [];
$mode = 'none';
$quotaBlocked = false;
$quotaUsed = null;
$error = null;

if ($q !== '' || ($reviewIdFilter !== null && $reviewIdFilter)) {
    if ($q === '' && $reviewIdFilter !== null) {
        // Tag-only view: list critiques in those reviews, newest first.
        $place = implode(',', array_fill(0, count($reviewIdFilter), '?'));
        $stmt = db()->prepare(
            "SELECT c.id AS critique_id, c.review_id, c.timestamp_sec, c.note,
                    r.video_title, r.video_url, r.provider
             FROM video_critiques c
             JOIN video_reviews r ON r.id = c.review_id
             WHERE r.user_id = ? AND r.id IN ($place)
             ORDER BY c.updated_at DESC
             LIMIT 40"
        );
        $stmt->execute(array_merge([$user['id']], $reviewIdFilter));
        foreach ($stmt->fetchAll() as $row) {
            $hits[] = [
                'type'          => 'critique',
                'score'         => 0.0,
                'review_id'     => $row['review_id'],
                'critique_id'   => $row['critique_id'],
                'review_title'  => $row['video_title'],
                'review_url'    => $row['video_url'],
                'provider'      => $row['provider'],
                'note'          => $row['note'],
                'timestamp_sec' => (int) $row['timestamp_sec'],
            ];
        }
        $mode = 'tags-only';
    } elseif (search_enabled()) {
        $used = semantic_search_count_last_30d($user['id']);
        $quotaUsed = $used;
        if ($used >= SEARCH_QUOTA_PER_MONTH) {
            $quotaBlocked = true;
            $hits = substring_search_for_user($user['id'], $q, $reviewIdFilter);
            $mode = 'substring';
        } else {
            try {
                $queryVec = embed_one($q);
                $hits = semantic_search_for_user($user['id'], $queryVec, $reviewIdFilter);
                track_event('search.semantic', null, [
                    'len'      => mb_strlen($q, 'UTF-8'),
                    'tags'     => count($activeTags),
                    'hitCount' => count($hits),
                ], $user['id']);
                $mode = 'semantic';
            } catch (Throwable $e) {
                $error = 'Semantic search is unavailable right now; showing plain matches.';
                $hits = substring_search_for_user($user['id'], $q, $reviewIdFilter);
                $mode = 'substring';
            }
        }
    } else {
        $hits = substring_search_for_user($user['id'], $q, $reviewIdFilter);
        $mode = 'substring';
    }
}

function search_url_with(array $overrides, string $q, array $tags): string {
    $tags = $overrides['tags'] ?? $tags;
    $q    = $overrides['q']    ?? $q;
    $parts = [];
    if ($q !== '') $parts[] = 'q=' . urlencode($q);
    foreach ($tags as $t) $parts[] = 'tag[]=' . urlencode($t);
    return '/search' . ($parts ? '?' . implode('&', $parts) : '');
}

$title = 'Search';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Search your reviews</h1>
        <p class="muted">
          <?php if (search_enabled()): ?>
            Semantic search across titles + notes. Type what you mean, not just keywords.
          <?php else: ?>
            Plain substring search across titles + notes.
            <em>Set <code>OPENAI_API_KEY</code> in <code>.env</code> to enable semantic search.</em>
          <?php endif; ?>
        </p>
      </div>
      <div class="actions">
        <a class="btn" href="/video-reviews">All reviews</a>
      </div>
    </div>

    <form method="get" action="/search" class="card" style="margin-bottom:1rem;">
      <div class="row">
        <input class="grow" type="search" name="q" value="<?= e($q) ?>"
               placeholder="e.g. fuzzy eye contact when Mia asks about budget"
               autofocus autocomplete="off" maxlength="500">
        <button type="submit" class="btn accent">Search</button>
      </div>
      <?php foreach ($activeTags as $t): ?>
        <input type="hidden" name="tag[]" value="<?= e($t) ?>">
      <?php endforeach; ?>

      <?php if ($allTags): ?>
        <div class="row" style="flex-wrap:wrap; gap:6px; margin-top:0.75rem;">
          <?php foreach ($allTags as $t):
            $isActive = in_array($t['name'], $activeTags, true);
            $nextTags = $isActive
              ? array_values(array_filter($activeTags, fn($n) => $n !== $t['name']))
              : array_merge($activeTags, [$t['name']]);
          ?>
            <a class="chip<?= $isActive ? ' ok' : '' ?>"
               href="<?= e(search_url_with(['tags' => $nextTags], $q, $activeTags)) ?>">
              <?= e($t['name']) ?> <span class="muted"><?= (int) $t['use_count'] ?></span>
            </a>
          <?php endforeach; ?>
          <?php if ($activeTags): ?>
            <a class="chip" href="<?= e(search_url_with(['tags' => []], $q, $activeTags)) ?>">clear tags ✕</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>

    <?php if ($error): ?>
      <p class="muted" style="margin-bottom:1rem; color:var(--fg-2);"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($quotaBlocked): ?>
      <div class="card" style="margin-bottom:1rem;">
        <p class="muted" style="margin:0;">
          You've used your monthly quota of <?= SEARCH_QUOTA_PER_MONTH ?> semantic searches
          (<?= (int) $quotaUsed ?> in the last 30 days). Falling back to plain substring matches.
        </p>
      </div>
    <?php endif; ?>

    <?php
      $indexed = isset($_GET['indexed']) ? (int) $_GET['indexed'] : 0;
      $errCount = isset($_GET['errors']) ? (int) $_GET['errors'] : 0;
    ?>
    <?php if ($indexed > 0 || $errCount > 0): ?>
      <div class="card" style="margin-bottom:1rem;">
        <p class="muted" style="margin:0;">
          Indexed <?= $indexed ?> item<?= $indexed === 1 ? '' : 's' ?><?= $errCount ? ", $errCount error" . ($errCount === 1 ? '' : 's') : '' ?>.
        </p>
      </div>
    <?php endif; ?>

    <?php if (search_enabled() && ($backlog['reviewsMissing'] + $backlog['critiquesMissing']) > 0): ?>
      <div class="card" style="margin-bottom:1rem;">
        <form method="post" action="/search/backfill" class="row">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <span class="grow muted">
            <?= (int) $backlog['reviewsMissing'] ?> review<?= $backlog['reviewsMissing'] === 1 ? '' : 's' ?>
            and <?= (int) $backlog['critiquesMissing'] ?> note<?= $backlog['critiquesMissing'] === 1 ? '' : 's' ?>
            not yet indexed. New notes index automatically; run backfill for older ones.
          </span>
          <button type="submit" class="btn sm">Run backfill</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($q === '' && !$activeTags): ?>
      <p class="muted">Enter a query or pick a tag above.</p>
    <?php elseif (!$hits): ?>
      <p class="muted">No matches.</p>
    <?php else: ?>
      <div class="reviews-list">
        <?php foreach ($hits as $h):
          if ($h['type'] === 'critique') {
            $href = '/video-reviews/' . $h['review_id'];
            $deep = deep_link_at_time((string) $h['review_url'], (string) $h['provider'], (int) $h['timestamp_sec']);
            $ts   = format_timestamp((int) $h['timestamp_sec']);
          } else {
            $href = '/video-reviews/' . $h['review_id'];
            $deep = null;
            $ts   = null;
          }
        ?>
          <a class="review-card" href="<?= e($href) ?>" style="text-decoration:none;color:inherit;">
            <span class="provider-badge" aria-hidden="true"><?= icon('capture') ?></span>
            <div class="meta">
              <h3><span><?= e($h['review_title'] ?: $h['review_url']) ?></span></h3>
              <?php if ($h['note']): ?>
                <div class="sub" style="white-space:normal; line-height:1.45;"><?= e(mb_substr((string) $h['note'], 0, 240, 'UTF-8')) ?><?= mb_strlen((string) $h['note'], 'UTF-8') > 240 ? '…' : '' ?></div>
              <?php else: ?>
                <div class="sub"><?= e(provider_label((string) $h['provider'])) ?> · review title match</div>
              <?php endif; ?>
            </div>
            <?php if ($ts): ?>
              <span class="count-pill"><?= e($ts) ?></span>
            <?php endif; ?>
            <span class="stamp"><?= $mode === 'semantic' ? number_format($h['score'], 2) : '' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
