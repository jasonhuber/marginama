<?php
declare(strict_types=1);

// Tag helpers. Tags are per-user, freeform strings. Users can encode "kinds"
// by convention (e.g. "person:alice", "topic:discovery") — we don't enforce.

require_once __DIR__ . '/db.php';

/** Normalise a tag string: trim, collapse whitespace, lowercase. */
function normalize_tag_name(string $raw): string {
    $s = trim($raw);
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $s = mb_strtolower($s, 'UTF-8');
    return $s;
}

/**
 * Parse a comma-separated tag input into a list of normalized names.
 * Limits applied here so handlers don't each repeat them.
 *
 * @return string[]
 */
function parse_tag_input(string $raw, int $maxTags = 20, int $maxLen = 64): array {
    $out = [];
    foreach (explode(',', $raw) as $part) {
        $name = normalize_tag_name($part);
        if ($name === '' || mb_strlen($name, 'UTF-8') > $maxLen) continue;
        if (!in_array($name, $out, true)) {
            $out[] = $name;
        }
        if (count($out) >= $maxTags) break;
    }
    return $out;
}

/** Upsert tags by name for a user; return map of name => id. */
function upsert_tags_for_user(string $userId, array $names): array {
    if (!$names) return [];
    $pdo = db();

    $place = implode(',', array_fill(0, count($names), '?'));
    $existing = $pdo->prepare(
        "SELECT id, name FROM tags WHERE user_id = ? AND name IN ($place)"
    );
    $existing->execute(array_merge([$userId], $names));
    $byName = [];
    foreach ($existing->fetchAll() as $row) {
        $byName[$row['name']] = $row['id'];
    }

    foreach ($names as $n) {
        if (isset($byName[$n])) continue;
        $id = ulid();
        try {
            $pdo->prepare('INSERT INTO tags (id, user_id, name) VALUES (?, ?, ?)')
                ->execute([$id, $userId, $n]);
            $byName[$n] = $id;
        } catch (Throwable) {
            // Race: another request just inserted the same name — look it up.
            $lookup = $pdo->prepare('SELECT id FROM tags WHERE user_id = ? AND name = ?');
            $lookup->execute([$userId, $n]);
            $row = $lookup->fetch();
            if ($row) $byName[$n] = $row['id'];
        }
    }
    return $byName;
}

/** Replace the tag set for one review with the given list of names. */
function set_tags_for_review(string $userId, string $reviewId, array $names): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM review_tags WHERE review_id = ?')->execute([$reviewId]);
        $map = upsert_tags_for_user($userId, $names);
        if ($map) {
            $ins = $pdo->prepare('INSERT IGNORE INTO review_tags (review_id, tag_id) VALUES (?, ?)');
            foreach ($map as $tagId) {
                $ins->execute([$reviewId, $tagId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Tags on one review, ordered by name. */
function tags_for_review(string $reviewId): array {
    $stmt = db()->prepare(
        'SELECT t.id, t.name
         FROM tags t
         JOIN review_tags rt ON rt.tag_id = t.id
         WHERE rt.review_id = ?
         ORDER BY t.name ASC'
    );
    $stmt->execute([$reviewId]);
    return $stmt->fetchAll();
}

/** All of a user's tags with usage counts, most-used first. */
function tags_for_user_with_counts(string $userId): array {
    $stmt = db()->prepare(
        'SELECT t.id, t.name, COUNT(rt.review_id) AS use_count
         FROM tags t
         LEFT JOIN review_tags rt ON rt.tag_id = t.id
         WHERE t.user_id = ?
         GROUP BY t.id, t.name
         ORDER BY use_count DESC, t.name ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Review IDs for a user that carry ALL of the given tag names. */
function review_ids_with_all_tags(string $userId, array $tagNames): array {
    if (!$tagNames) return [];
    $place = implode(',', array_fill(0, count($tagNames), '?'));
    $stmt = db()->prepare(
        "SELECT rt.review_id
         FROM review_tags rt
         JOIN tags t ON t.id = rt.tag_id
         JOIN video_reviews r ON r.id = rt.review_id
         WHERE r.user_id = ? AND t.user_id = ? AND t.name IN ($place)
         GROUP BY rt.review_id
         HAVING COUNT(DISTINCT t.name) = ?"
    );
    $params = array_merge([$userId, $userId], $tagNames, [count($tagNames)]);
    $stmt->execute($params);
    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $ids[] = $row['review_id'];
    }
    return $ids;
}

/**
 * Fetch tags for a batch of reviews in one query (avoid N+1 on /video-reviews).
 * Returns review_id => [['id'=>..,'name'=>..], ...].
 */
function tags_for_reviews(array $reviewIds): array {
    if (!$reviewIds) return [];
    $place = implode(',', array_fill(0, count($reviewIds), '?'));
    $stmt = db()->prepare(
        "SELECT rt.review_id, t.id, t.name
         FROM review_tags rt
         JOIN tags t ON t.id = rt.tag_id
         WHERE rt.review_id IN ($place)
         ORDER BY t.name ASC"
    );
    $stmt->execute($reviewIds);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['review_id']][] = ['id' => $row['id'], 'name' => $row['name']];
    }
    return $out;
}
