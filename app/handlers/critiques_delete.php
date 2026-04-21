<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();
require_csrf();
$critiqueId = $params[0];

// Check ownership via the join, delete in one query only if owned.
$stmt = db()->prepare(
    'SELECT c.id, c.review_id FROM video_critiques c
     JOIN video_reviews r ON r.id = c.review_id
     WHERE c.id = ? AND r.user_id = ?'
);
$stmt->execute([$critiqueId, $user['id']]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$del = db()->prepare('DELETE FROM video_critiques WHERE id = ?');
$del->execute([$critiqueId]);

header('Location: /video-reviews/' . $row['review_id']);
