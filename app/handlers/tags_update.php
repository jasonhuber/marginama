<?php
declare(strict_types=1);

// POST /video-reviews/:id/tags — replace the tag set on one review.
// Body: `tags` = comma-separated names (e.g. "alice, discovery, person:bob").

require_once __DIR__ . '/../tags.php';

/** @var array $params */
$user = require_session();
require_csrf();
$reviewId = $params[0];

$own = db()->prepare('SELECT id FROM video_reviews WHERE id = ? AND user_id = ?');
$own->execute([$reviewId, $user['id']]);
if (!$own->fetch()) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$raw = (string) ($_POST['tags'] ?? '');
$names = parse_tag_input($raw);
set_tags_for_review($user['id'], $reviewId, $names);

track_event('tags.update', null, ['reviewId' => $reviewId, 'count' => count($names)], $user['id']);

header('Location: /video-reviews/' . $reviewId);
