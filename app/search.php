<?php
declare(strict_types=1);

// Semantic search + embeddings glue.
//
// Embeddings are generated via OpenAI text-embedding-3-small (1536 dims) and
// stored as JSON arrays in MySQL. Similarity is computed in PHP at query time.
// For a single user's corpus (even thousands of notes) this is sub-100ms.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

const EMBEDDING_MODEL = 'text-embedding-3-small';
const EMBEDDING_DIMS  = 1536;

// Soft per-user monthly cap on semantic-search queries (counted from `events`).
// Tag filtering and substring match are never capped.
const SEARCH_QUOTA_PER_MONTH = 500;

// Batched inputs per OpenAI call during backfill. The API accepts up to 2048.
const EMBED_BATCH_SIZE = 96;

function search_enabled(): bool {
    return (env('OPENAI_API_KEY') ?? '') !== '';
}

/**
 * Call OpenAI's embeddings endpoint. Returns one float[] per input, in order.
 * Throws on any API or transport failure — callers decide whether to swallow.
 *
 * @param string[] $inputs
 * @return float[][]
 */
function embed_texts(array $inputs): array {
    $key = env('OPENAI_API_KEY');
    if (!$key) {
        throw new RuntimeException('OPENAI_API_KEY not configured');
    }
    if (!$inputs) {
        return [];
    }

    $payload = json_encode([
        'model' => EMBEDDING_MODEL,
        'input' => array_values($inputs),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('OpenAI transport error: ' . $err);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("OpenAI HTTP $status: " . substr((string) $body, 0, 500));
    }
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        throw new RuntimeException('OpenAI response malformed');
    }

    $out = [];
    foreach ($decoded['data'] as $row) {
        if (!isset($row['index'], $row['embedding']) || !is_array($row['embedding'])) {
            throw new RuntimeException('OpenAI embedding row malformed');
        }
        $out[(int) $row['index']] = array_map('floatval', $row['embedding']);
    }
    ksort($out);
    return array_values($out);
}

function embed_one(string $text): array {
    $result = embed_texts([$text]);
    return $result[0] ?? [];
}

/**
 * Cosine similarity between two normalized or unnormalized float arrays.
 * OpenAI embeddings are already unit-length, so dot product = cosine. But we
 * compute the full formula to stay correct if a non-OpenAI source ever lands.
 */
function cosine_similarity(array $a, array $b): float {
    $len = min(count($a), count($b));
    if ($len === 0) return 0.0;
    $dot = 0.0; $na = 0.0; $nb = 0.0;
    for ($i = 0; $i < $len; $i++) {
        $x = (float) $a[$i]; $y = (float) $b[$i];
        $dot += $x * $y;
        $na  += $x * $x;
        $nb  += $y * $y;
    }
    if ($na <= 0 || $nb <= 0) return 0.0;
    return $dot / (sqrt($na) * sqrt($nb));
}

/** Text used to represent a review for embedding — title is the strongest signal. */
function review_embed_source(array $review): string {
    $title = (string) ($review['video_title'] ?? '');
    return $title !== '' ? $title : (string) ($review['video_url'] ?? '');
}

function critique_embed_source(array $critique): string {
    return (string) ($critique['note'] ?? '');
}

/** Best-effort: store one embedding, swallow errors so writes never fail. */
function store_review_embedding_best_effort(string $reviewId, array $review): void {
    if (!search_enabled()) return;
    try {
        $text = review_embed_source($review);
        if ($text === '') return;
        $hash = hash('sha256', EMBEDDING_MODEL . "\n" . $text);
        $existing = db()->prepare('SELECT source_hash FROM review_embeddings WHERE review_id = ?');
        $existing->execute([$reviewId]);
        $row = $existing->fetch();
        if ($row && $row['source_hash'] === $hash) return;

        $vec = embed_one($text);
        if (!$vec) return;

        db()->prepare(
            'INSERT INTO review_embeddings (review_id, model, embedding, source_hash)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE model = VALUES(model),
                                      embedding = VALUES(embedding),
                                      source_hash = VALUES(source_hash),
                                      embedded_at = NOW()'
        )->execute([
            $reviewId,
            EMBEDDING_MODEL,
            json_encode($vec, JSON_UNESCAPED_SLASHES),
            $hash,
        ]);
    } catch (Throwable) {
        // Swallow — embeddings are best-effort. Backfill can catch up later.
    }
}

function store_critique_embedding_best_effort(string $critiqueId, string $reviewId, string $userId, array $critique): void {
    if (!search_enabled()) return;
    try {
        $text = critique_embed_source($critique);
        if ($text === '') return;
        $hash = hash('sha256', EMBEDDING_MODEL . "\n" . $text);
        $existing = db()->prepare('SELECT source_hash FROM critique_embeddings WHERE critique_id = ?');
        $existing->execute([$critiqueId]);
        $row = $existing->fetch();
        if ($row && $row['source_hash'] === $hash) return;

        $vec = embed_one($text);
        if (!$vec) return;

        db()->prepare(
            'INSERT INTO critique_embeddings (critique_id, review_id, user_id, model, embedding, source_hash)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE review_id = VALUES(review_id),
                                      user_id = VALUES(user_id),
                                      model = VALUES(model),
                                      embedding = VALUES(embedding),
                                      source_hash = VALUES(source_hash),
                                      embedded_at = NOW()'
        )->execute([
            $critiqueId,
            $reviewId,
            $userId,
            EMBEDDING_MODEL,
            json_encode($vec, JSON_UNESCAPED_SLASHES),
            $hash,
        ]);
    } catch (Throwable) {
        // Swallow.
    }
}

/**
 * Backfill unembedded rows for one user. Returns counts. Designed to run
 * interactively from /admin/embed-backfill — bounded by $maxBatches so the
 * request doesn't time out on large corpora.
 *
 * @return array{reviews:int, critiques:int, errors:int}
 */
function backfill_embeddings_for_user(string $userId, int $maxBatches = 10): array {
    $reviewsDone = 0; $critiquesDone = 0; $errors = 0;
    if (!search_enabled()) return compact('reviewsDone','critiquesDone','errors');

    $pdo = db();

    // Reviews first (cheap — just titles).
    for ($b = 0; $b < $maxBatches; $b++) {
        $rows = $pdo->prepare(
            'SELECT r.id, r.video_url, r.video_title
             FROM video_reviews r
             LEFT JOIN review_embeddings e ON e.review_id = r.id
             WHERE r.user_id = ? AND e.review_id IS NULL
             LIMIT ?'
        );
        $rows->bindValue(1, $userId);
        $rows->bindValue(2, EMBED_BATCH_SIZE, PDO::PARAM_INT);
        $rows->execute();
        $batch = $rows->fetchAll();
        if (!$batch) break;

        $inputs = [];
        $meta = [];
        foreach ($batch as $r) {
            $text = review_embed_source($r);
            if ($text === '') continue;
            $inputs[] = $text;
            $meta[] = ['id' => $r['id'], 'text' => $text];
        }
        if (!$inputs) break;

        try {
            $vecs = embed_texts($inputs);
        } catch (Throwable) {
            $errors++;
            break;
        }
        foreach ($vecs as $i => $vec) {
            $info = $meta[$i];
            try {
                $pdo->prepare(
                    'INSERT INTO review_embeddings (review_id, model, embedding, source_hash)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE model = VALUES(model),
                                              embedding = VALUES(embedding),
                                              source_hash = VALUES(source_hash),
                                              embedded_at = NOW()'
                )->execute([
                    $info['id'],
                    EMBEDDING_MODEL,
                    json_encode($vec, JSON_UNESCAPED_SLASHES),
                    hash('sha256', EMBEDDING_MODEL . "\n" . $info['text']),
                ]);
                $reviewsDone++;
            } catch (Throwable) {
                $errors++;
            }
        }
    }

    // Critiques (the real corpus).
    for ($b = 0; $b < $maxBatches; $b++) {
        $rows = $pdo->prepare(
            'SELECT c.id, c.review_id, c.note
             FROM video_critiques c
             JOIN video_reviews r ON r.id = c.review_id
             LEFT JOIN critique_embeddings e ON e.critique_id = c.id
             WHERE r.user_id = ? AND e.critique_id IS NULL
             LIMIT ?'
        );
        $rows->bindValue(1, $userId);
        $rows->bindValue(2, EMBED_BATCH_SIZE, PDO::PARAM_INT);
        $rows->execute();
        $batch = $rows->fetchAll();
        if (!$batch) break;

        $inputs = [];
        $meta = [];
        foreach ($batch as $c) {
            $text = critique_embed_source($c);
            if ($text === '') continue;
            $inputs[] = $text;
            $meta[] = ['id' => $c['id'], 'review_id' => $c['review_id'], 'text' => $text];
        }
        if (!$inputs) break;

        try {
            $vecs = embed_texts($inputs);
        } catch (Throwable) {
            $errors++;
            break;
        }
        foreach ($vecs as $i => $vec) {
            $info = $meta[$i];
            try {
                $pdo->prepare(
                    'INSERT INTO critique_embeddings (critique_id, review_id, user_id, model, embedding, source_hash)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE review_id = VALUES(review_id),
                                              user_id = VALUES(user_id),
                                              model = VALUES(model),
                                              embedding = VALUES(embedding),
                                              source_hash = VALUES(source_hash),
                                              embedded_at = NOW()'
                )->execute([
                    $info['id'],
                    $info['review_id'],
                    $userId,
                    EMBEDDING_MODEL,
                    json_encode($vec, JSON_UNESCAPED_SLASHES),
                    hash('sha256', EMBEDDING_MODEL . "\n" . $info['text']),
                ]);
                $critiquesDone++;
            } catch (Throwable) {
                $errors++;
            }
        }
    }

    return [
        'reviewsDone'   => $reviewsDone,
        'critiquesDone' => $critiquesDone,
        'errors'        => $errors,
    ];
}

/** How many embeddings remain to be built for this user. */
function embedding_backlog_for_user(string $userId): array {
    $pdo = db();
    $r = $pdo->prepare(
        'SELECT COUNT(*) FROM video_reviews r
         LEFT JOIN review_embeddings e ON e.review_id = r.id
         WHERE r.user_id = ? AND e.review_id IS NULL'
    );
    $r->execute([$userId]);
    $reviewsMissing = (int) $r->fetchColumn();

    $c = $pdo->prepare(
        'SELECT COUNT(*) FROM video_critiques c
         JOIN video_reviews r ON r.id = c.review_id
         LEFT JOIN critique_embeddings e ON e.critique_id = c.id
         WHERE r.user_id = ? AND e.critique_id IS NULL'
    );
    $c->execute([$userId]);
    $critiquesMissing = (int) $c->fetchColumn();

    return [
        'reviewsMissing'   => $reviewsMissing,
        'critiquesMissing' => $critiquesMissing,
    ];
}

/** Count the user's semantic-search events in the trailing 30 days. */
function semantic_search_count_last_30d(string $userId): int {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM events
         WHERE user_id = ? AND type = 'search.semantic'
           AND created_at >= (NOW() - INTERVAL 30 DAY)"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Rank the user's critiques + reviews by cosine similarity to the query.
 * Returns a list of hits sorted by score desc, at most $limit rows.
 *
 * Each hit has: type (critique|review), score, review_id, critique_id?,
 * review_title, review_url, provider, note?, timestamp_sec?.
 *
 * @return array<int, array<string, mixed>>
 */
function semantic_search_for_user(string $userId, array $queryVec, ?array $reviewIdFilter, int $limit = 40): array {
    if (!$queryVec) return [];
    $pdo = db();

    $params = [$userId];
    $where = 'r.user_id = ?';
    if ($reviewIdFilter !== null) {
        if (!$reviewIdFilter) return [];
        $place = implode(',', array_fill(0, count($reviewIdFilter), '?'));
        $where .= " AND r.id IN ($place)";
        $params = array_merge($params, $reviewIdFilter);
    }

    // Critiques.
    $stmt = $pdo->prepare(
        "SELECT c.id AS critique_id, c.review_id, c.timestamp_sec, c.note,
                r.video_title, r.video_url, r.provider,
                e.embedding
         FROM critique_embeddings e
         JOIN video_critiques c ON c.id = e.critique_id
         JOIN video_reviews r ON r.id = e.review_id
         WHERE $where"
    );
    $stmt->execute($params);
    $hits = [];
    while ($row = $stmt->fetch()) {
        $vec = json_decode((string) $row['embedding'], true);
        if (!is_array($vec)) continue;
        $hits[] = [
            'type'          => 'critique',
            'score'         => cosine_similarity($queryVec, $vec),
            'review_id'     => $row['review_id'],
            'critique_id'   => $row['critique_id'],
            'review_title'  => $row['video_title'],
            'review_url'    => $row['video_url'],
            'provider'      => $row['provider'],
            'note'          => $row['note'],
            'timestamp_sec' => (int) $row['timestamp_sec'],
        ];
    }

    // Review-level hits (title embedding) — useful when the user searches for
    // a video, not a note inside it.
    $stmt = $pdo->prepare(
        "SELECT r.id AS review_id, r.video_title, r.video_url, r.provider, e.embedding
         FROM review_embeddings e
         JOIN video_reviews r ON r.id = e.review_id
         WHERE $where"
    );
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        $vec = json_decode((string) $row['embedding'], true);
        if (!is_array($vec)) continue;
        $hits[] = [
            'type'          => 'review',
            'score'         => cosine_similarity($queryVec, $vec),
            'review_id'     => $row['review_id'],
            'critique_id'   => null,
            'review_title'  => $row['video_title'],
            'review_url'    => $row['video_url'],
            'provider'      => $row['provider'],
            'note'          => null,
            'timestamp_sec' => null,
        ];
    }

    usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($hits, 0, $limit);
}

/** Fallback substring match when OPENAI_API_KEY is not configured. */
function substring_search_for_user(string $userId, string $query, ?array $reviewIdFilter, int $limit = 40): array {
    $pdo = db();
    $like = '%' . $query . '%';

    $params = [$userId, $like, $like];
    $where = 'r.user_id = ? AND (c.note LIKE ? OR r.video_title LIKE ?)';
    if ($reviewIdFilter !== null) {
        if (!$reviewIdFilter) return [];
        $place = implode(',', array_fill(0, count($reviewIdFilter), '?'));
        $where .= " AND r.id IN ($place)";
        $params = array_merge($params, $reviewIdFilter);
    }
    $params[] = $limit;

    $stmt = $pdo->prepare(
        "SELECT c.id AS critique_id, c.review_id, c.timestamp_sec, c.note,
                r.video_title, r.video_url, r.provider
         FROM video_critiques c
         JOIN video_reviews r ON r.id = c.review_id
         WHERE $where
         ORDER BY c.updated_at DESC
         LIMIT ?"
    );
    foreach ($params as $i => $v) {
        $stmt->bindValue($i + 1, $v, $i === count($params) - 1 ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $hits = [];
    while ($row = $stmt->fetch()) {
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
    return $hits;
}
