<?php
declare(strict_types=1);

require_once __DIR__ . '/../search.php';

/** @var array $params */
$user = require_session();
require_csrf();
$critiqueId = $params[0];
$note = trim((string) ($_POST['note'] ?? ''));

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

if ($note !== '' && strlen($note) <= 4000) {
    $upd = db()->prepare('UPDATE video_critiques SET note = ? WHERE id = ?');
    $upd->execute([$note, $critiqueId]);
    store_critique_embedding_best_effort(
        $critiqueId,
        $row['review_id'],
        $user['id'],
        ['note' => $note]
    );
}

header('Location: /video-reviews/' . $row['review_id']);
