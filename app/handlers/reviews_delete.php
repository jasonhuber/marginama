<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();
require_csrf();
$id = $params[0];

$stmt = db()->prepare('DELETE FROM video_reviews WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
header('Location: /video-reviews');
