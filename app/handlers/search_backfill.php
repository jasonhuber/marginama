<?php
declare(strict_types=1);

// POST /search/backfill — embed the caller's unembedded reviews and critiques.
// Self-serve (no admin gate): each user can only backfill their own data.
// Bounded per-click by EMBED_BATCH_SIZE × maxBatches — click again for more.

require_once __DIR__ . '/../search.php';

$user = require_session();
require_csrf();

if (!search_enabled()) {
    header('Location: /search');
    exit;
}

// 3 × EMBED_BATCH_SIZE per click keeps the request under typical PHP timeouts
// even if OpenAI is slow — click again to continue.
$result = backfill_embeddings_for_user($user['id'], 3);
track_event('search.backfill', null, $result, $user['id']);

$q = [];
if (($result['reviewsDone'] ?? 0) || ($result['critiquesDone'] ?? 0)) {
    $q[] = 'indexed=' . (int) (($result['reviewsDone'] ?? 0) + ($result['critiquesDone'] ?? 0));
}
if (!empty($result['errors'])) {
    $q[] = 'errors=' . (int) $result['errors'];
}
header('Location: /search' . ($q ? '?' . implode('&', $q) : ''));
