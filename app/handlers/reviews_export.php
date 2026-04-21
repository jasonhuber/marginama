<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();
$id = $params[0];

$stmt = db()->prepare(
    'SELECT id, video_url, video_title, provider, created_at, updated_at
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
$review['critiques'] = $cst->fetchAll();

$filename = 'marginama-review-' . $review['id'] . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
