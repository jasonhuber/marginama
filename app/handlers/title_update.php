<?php
declare(strict_types=1);

// POST /video-reviews/:id/title — rename one review.
// Body: `video_title` (0–500 chars). Empty clears it back to "(untitled)".

require_once __DIR__ . '/../search.php';

/** @var array $params */
$user = require_session();
require_csrf();
$reviewId = $params[0];

$stmt = db()->prepare(
    'SELECT id, video_url, video_title, provider, created_at, updated_at
     FROM video_reviews WHERE id = ? AND user_id = ?'
);
$stmt->execute([$reviewId, $user['id']]);
$review = $stmt->fetch();
if (!$review) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$raw = trim((string) ($_POST['video_title'] ?? ''));
if (strlen($raw) > 500) {
    $raw = substr($raw, 0, 500);
}
$new = $raw === '' ? null : $raw;

db()->prepare('UPDATE video_reviews SET video_title = ? WHERE id = ?')
    ->execute([$new, $reviewId]);

$review['video_title'] = $new;
store_review_embedding_best_effort($reviewId, $review);

header('Location: /video-reviews/' . $reviewId);
