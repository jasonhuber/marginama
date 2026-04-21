<?php
declare(strict_types=1);

/** @var array $params */
$user = require_session();
require_csrf();
$id = $params[0];
$action = $_POST['action'] ?? 'create';

$check = db()->prepare('SELECT id FROM video_reviews WHERE id = ? AND user_id = ?');
$check->execute([$id, $user['id']]);
if (!$check->fetch()) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

if ($action === 'revoke') {
    $stmt = db()->prepare('UPDATE video_reviews SET share_token = NULL WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
} else {
    $token = bin2hex(random_bytes(20));
    $stmt = db()->prepare('UPDATE video_reviews SET share_token = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$token, $id, $user['id']]);
}

header('Location: /video-reviews/' . $id);
