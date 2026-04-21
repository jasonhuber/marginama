<?php
declare(strict_types=1);

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $pw    = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([strtolower($email)]);
    $row = $stmt->fetch();
    if ($row && password_verify($pw, $row['password_hash'])) {
        sign_in_user($row['id']);
        header('Location: /video-reviews');
        exit;
    }
    $error = 'Email or password is incorrect.';
}

$title = 'Sign in';
ob_start(); ?>
<h1>Sign in</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="/signin">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>Email
    <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
  </label>
  <label>Password
    <input type="password" name="password" required>
  </label>
  <div class="row">
    <button type="submit" class="primary">Sign in</button>
    <a href="/signup">Need an account?</a>
  </div>
</form>
<?php $content = ob_get_clean();
$user = null;
require __DIR__ . '/../views/layout.php';
