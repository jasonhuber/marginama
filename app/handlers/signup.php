<?php
declare(strict_types=1);

$error = null;
$email = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $name  = trim((string) ($_POST['name']  ?? ''));
    $pw    = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $id = ulid();
            $stmt = db()->prepare(
                'INSERT INTO users (id, email, password_hash, name) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$id, strtolower($email), password_hash($pw, PASSWORD_BCRYPT), $name ?: null]);
            sign_in_user($id);
            header('Location: /video-reviews');
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] ?? 0 === 1062) {
                $error = 'An account with that email already exists.';
            } else {
                $error = 'Could not create account.';
            }
        }
    }
}

$title = 'Sign up';
ob_start(); ?>
<h1>Create account</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="/signup">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>Email
    <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
  </label>
  <label>Name <span class="muted">(optional)</span>
    <input type="text" name="name" value="<?= e($name) ?>">
  </label>
  <label>Password <span class="muted">(8+ characters)</span>
    <input type="password" name="password" required minlength="8">
  </label>
  <div class="row">
    <button type="submit" class="primary">Sign up</button>
    <a href="/signin">Already have an account?</a>
  </div>
</form>
<?php $content = ob_get_clean();
$user = null;
require __DIR__ . '/../views/layout.php';
