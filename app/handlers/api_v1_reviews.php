<?php
declare(strict_types=1);

// GET  /api/v1/video-reviews  — list caller's reviews
// POST /api/v1/video-reviews  — add critique (upserts the review)

require_once __DIR__ . '/../search.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$auth = require_bearer();
$userId = $auth['userId'];

if ($method === 'GET') {
    $stmt = db()->prepare(
        'SELECT r.id, r.video_url, r.video_title, r.provider, r.created_at, r.updated_at,
                (SELECT COUNT(*) FROM video_critiques c WHERE c.review_id = r.id) AS critique_count
         FROM video_reviews r
         WHERE r.user_id = ?
         ORDER BY r.updated_at DESC
         LIMIT 200'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $reviews = array_map(fn($r) => [
        'id'             => $r['id'],
        'videoUrl'       => $r['video_url'],
        'videoTitle'     => $r['video_title'],
        'provider'       => $r['provider'],
        'critiqueCount'  => (int) $r['critique_count'],
        'createdAt'      => $r['created_at'],
        'updatedAt'      => $r['updated_at'],
    ], $rows);
    json_response(200, ['reviews' => $reviews, 'count' => count($reviews)]);
}

if ($method === 'POST') {
    $body = json_body();
    $videoUrl    = $body['videoUrl']    ?? null;
    $videoTitle  = $body['videoTitle']  ?? null;
    $timestampSec = $body['timestampSec'] ?? null;
    $note        = $body['note']        ?? null;

    if (!is_string($videoUrl) || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        api_error(400, 'videoUrl must be a valid URL');
    }
    if (!is_numeric($timestampSec) || $timestampSec < 0 || $timestampSec > 36000) {
        api_error(400, 'timestampSec must be 0–36000');
    }
    if (!is_string($note) || trim($note) === '' || strlen($note) > 4000) {
        api_error(400, 'note must be 1–4000 characters');
    }
    if ($videoTitle !== null && (!is_string($videoTitle) || strlen($videoTitle) > 500)) {
        api_error(400, 'videoTitle must be ≤500 characters');
    }

    try {
        [$canonicalUrl, $provider] = canonicalize_video_url($videoUrl);
    } catch (InvalidArgumentException) {
        api_error(400, 'Could not parse videoUrl');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, video_title FROM video_reviews WHERE user_id = ? AND video_url = ? LIMIT 1'
        );
        $stmt->execute([$userId, $canonicalUrl]);
        $existing = $stmt->fetch();

        if ($existing) {
            $reviewId = $existing['id'];
            // Fill in title only if we didn't have one and now we do.
            if (!$existing['video_title'] && $videoTitle) {
                $pdo->prepare('UPDATE video_reviews SET video_title = ? WHERE id = ?')
                    ->execute([$videoTitle, $reviewId]);
            } else {
                $pdo->prepare('UPDATE video_reviews SET updated_at = NOW() WHERE id = ?')
                    ->execute([$reviewId]);
            }
        } else {
            $reviewId = ulid();
            $pdo->prepare(
                'INSERT INTO video_reviews (id, user_id, video_url, video_title, provider)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$reviewId, $userId, $canonicalUrl, $videoTitle, $provider]);
        }

        $critiqueId = ulid();
        $pdo->prepare(
            'INSERT INTO video_critiques (id, review_id, timestamp_sec, note)
             VALUES (?, ?, ?, ?)'
        )->execute([$critiqueId, $reviewId, (int) $timestampSec, trim($note)]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        api_error(500, 'Could not save critique');
    }

    track_event('critique.create', null, ['reviewId' => $reviewId, 'provider' => $provider], $userId);

    $rev = $pdo->prepare('SELECT id, video_url, video_title, provider FROM video_reviews WHERE id = ?');
    $rev->execute([$reviewId]);
    $review = $rev->fetch();
    $crt = $pdo->prepare('SELECT id, timestamp_sec, note, created_at FROM video_critiques WHERE id = ?');
    $crt->execute([$critiqueId]);
    $critique = $crt->fetch();

    store_review_embedding_best_effort($reviewId, $review);
    store_critique_embedding_best_effort($critiqueId, $reviewId, $userId, $critique);

    json_response(201, [
        'review' => [
            'id'         => $review['id'],
            'videoUrl'   => $review['video_url'],
            'videoTitle' => $review['video_title'],
            'provider'   => $review['provider'],
        ],
        'critique' => [
            'id'           => $critique['id'],
            'timestampSec' => (int) $critique['timestamp_sec'],
            'note'         => $critique['note'],
            'createdAt'    => $critique['created_at'],
        ],
    ]);
}

api_error(405, 'Method not allowed');
