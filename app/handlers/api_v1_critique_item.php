<?php
declare(strict_types=1);

// PATCH/DELETE /api/v1/video-reviews/critiques/:id

/** @var array $params */
$method = $_SERVER['REQUEST_METHOD'] ?? 'PATCH';
$auth = require_bearer();
$userId = $auth['userId'];
$id = $params[0];

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT c.id, c.review_id, c.timestamp_sec, c.note, c.created_at, c.updated_at
     FROM video_critiques c
     JOIN video_reviews r ON r.id = c.review_id
     WHERE c.id = ? AND r.user_id = ?'
);
$stmt->execute([$id, $userId]);
$critique = $stmt->fetch();
if (!$critique) {
    api_error(404, 'Critique not found');
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM video_critiques WHERE id = ?')->execute([$id]);
    json_response(200, ['ok' => true]);
}

if ($method === 'PATCH') {
    $body = json_body();
    $note = $body['note'] ?? null;
    $ts   = $body['timestampSec'] ?? null;

    $sets = [];
    $vals = [];
    if ($note !== null) {
        if (!is_string($note) || trim($note) === '' || strlen($note) > 4000) {
            api_error(400, 'note must be 1–4000 characters');
        }
        $sets[] = 'note = ?';
        $vals[] = trim($note);
    }
    if ($ts !== null) {
        if (!is_numeric($ts) || $ts < 0 || $ts > 36000) {
            api_error(400, 'timestampSec must be 0–36000');
        }
        $sets[] = 'timestamp_sec = ?';
        $vals[] = (int) $ts;
    }
    if (!$sets) {
        api_error(400, 'No updatable fields provided');
    }
    $vals[] = $id;
    $pdo->prepare('UPDATE video_critiques SET ' . implode(', ', $sets) . ' WHERE id = ?')
        ->execute($vals);

    $re = $pdo->prepare('SELECT id, timestamp_sec, note, created_at, updated_at FROM video_critiques WHERE id = ?');
    $re->execute([$id]);
    $critique = $re->fetch();

    json_response(200, ['critique' => [
        'id'           => $critique['id'],
        'timestampSec' => (int) $critique['timestamp_sec'],
        'note'         => $critique['note'],
        'createdAt'    => $critique['created_at'],
        'updatedAt'    => $critique['updated_at'],
    ]]);
}

api_error(405, 'Method not allowed');
