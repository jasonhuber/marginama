<?php
declare(strict_types=1);

// GET/PATCH/DELETE /api/v1/video-reviews/:id

require_once __DIR__ . '/../search.php';

/** @var array $params */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$auth = require_bearer();
$userId = $auth['userId'];
$id = $params[0];

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT id, video_url, video_title, provider, created_at, updated_at
     FROM video_reviews WHERE id = ? AND user_id = ?'
);
$stmt->execute([$id, $userId]);
$review = $stmt->fetch();
if (!$review) {
    api_error(404, 'Review not found');
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM video_reviews WHERE id = ?')->execute([$id]);
    json_response(200, ['ok' => true]);
}

if ($method === 'PATCH') {
    $body = json_body();
    $videoTitle = $body['videoTitle'] ?? null;
    if ($videoTitle !== null && (!is_string($videoTitle) || strlen($videoTitle) > 500)) {
        api_error(400, 'videoTitle must be ≤500 characters');
    }
    if ($videoTitle === null) {
        api_error(400, 'No updatable fields provided');
    }
    $pdo->prepare('UPDATE video_reviews SET video_title = ? WHERE id = ?')
        ->execute([$videoTitle, $id]);
    $review['video_title'] = $videoTitle;

    store_review_embedding_best_effort($id, $review);
}

// GET and post-PATCH response: include critiques.
$cst = $pdo->prepare(
    'SELECT id, timestamp_sec, note, created_at, updated_at
     FROM video_critiques WHERE review_id = ? ORDER BY timestamp_sec ASC'
);
$cst->execute([$id]);
$critiques = array_map(fn($c) => [
    'id'           => $c['id'],
    'timestampSec' => (int) $c['timestamp_sec'],
    'note'         => $c['note'],
    'createdAt'    => $c['created_at'],
    'updatedAt'    => $c['updated_at'],
], $cst->fetchAll());

json_response(200, [
    'review' => [
        'id'         => $review['id'],
        'videoUrl'   => $review['video_url'],
        'videoTitle' => $review['video_title'],
        'provider'   => $review['provider'],
        'createdAt'  => $review['created_at'],
        'updatedAt'  => $review['updated_at'],
        'critiques'  => $critiques,
    ],
]);
