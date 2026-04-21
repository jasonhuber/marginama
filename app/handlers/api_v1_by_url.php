<?php
declare(strict_types=1);

// GET /api/v1/video-reviews/by-url?url=<videoUrl>
//
// The extension uses this to load (and populate its sidebar with) the caller's
// own review for the current video. Since Marginama has no workspace sharing,
// the response always contains 0 or 1 reviews. We keep the array-shaped
// response so the extension code stays unchanged.

$auth = require_bearer();
$userId = $auth['userId'];

$url = $_GET['url'] ?? null;
if (!is_string($url) || $url === '') {
    api_error(400, 'Missing `url` query parameter');
}
try {
    [$canonicalUrl, ] = canonicalize_video_url($url);
} catch (InvalidArgumentException) {
    api_error(400, 'Could not parse url');
}

$stmt = db()->prepare(
    'SELECT r.id, r.video_url, r.video_title, r.provider, r.created_at, r.updated_at,
            u.name AS reviewer_name, u.email AS reviewer_email
     FROM video_reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.user_id = ? AND r.video_url = ?
     LIMIT 1'
);
$stmt->execute([$userId, $canonicalUrl]);
$row = $stmt->fetch();

if (!$row) {
    json_response(200, ['reviews' => []]);
}

$cst = db()->prepare(
    'SELECT id, timestamp_sec, note, created_at, updated_at
     FROM video_critiques WHERE review_id = ? ORDER BY timestamp_sec ASC'
);
$cst->execute([$row['id']]);
$critiques = array_map(fn($c) => [
    'id'           => $c['id'],
    'timestampSec' => (int) $c['timestamp_sec'],
    'note'         => $c['note'],
    'createdAt'    => $c['created_at'],
    'updatedAt'    => $c['updated_at'],
], $cst->fetchAll());

json_response(200, ['reviews' => [[
    'id'         => $row['id'],
    'videoUrl'   => $row['video_url'],
    'videoTitle' => $row['video_title'],
    'provider'   => $row['provider'],
    'isMine'     => true,
    'reviewer'   => ['name' => $row['reviewer_name'], 'email' => $row['reviewer_email']],
    'critiques'  => $critiques,
    'createdAt'  => $row['created_at'],
    'updatedAt'  => $row['updated_at'],
]]]);
