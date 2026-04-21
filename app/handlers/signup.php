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
            if (($e->errorInfo[1] ?? 0) === 1062) {
                $error = 'An account with that email already exists.';
            } else {
                $error = 'Could not create account.';
            }
        }
    }
}

$title = 'Sign up';
ob_start(); ?>
<div class="auth-shell container">
  <div class="auth-form">
    <h1>Create your account</h1>
    <p class="lede">Ninety seconds and a shortcut — you'll be capturing timestamped critiques on your next video.</p>
    <?php if ($error): ?><div class="error" style="margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>
    <form class="stack" method="post" action="/signup">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Email
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
      </label>
      <label>Name <span class="hint">optional</span>
        <input type="text" name="name" value="<?= e($name) ?>" autocomplete="name">
      </label>
      <label>Password <span class="hint">8+ characters</span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>
      <button type="submit" class="btn accent large">Create account</button>
      <p class="muted" style="margin:0;font-size:0.88rem;">Already have an account? <a href="/signin">Sign in</a>.</p>
    </form>
  </div>
  <aside class="auth-aside">
    <div class="panel">
      <span class="eyebrow" style="margin-bottom:1rem;"><span class="dot"></span>What you get</span>
      <h3>Time-stamped notes, one click away.</h3>
      <p>The extension drops a floating panel onto YouTube, Sybill, and Google Drive video pages. Press the shortcut, type the note, move on.</p>
      <div class="mini-illo"><?= icon('hero-illustration') ?></div>
    </div>
  </aside>
</div>
<?php $content = ob_get_clean();
$user = null;
require __DIR__ . '/../views/layout.php';
