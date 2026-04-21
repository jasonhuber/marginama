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
<div class="auth-shell container">
  <div class="auth-form">
    <h1>Welcome back.</h1>
    <p class="lede">Sign in to get to your reviews.</p>
    <?php if ($error): ?><div class="error" style="margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>
    <form class="stack" method="post" action="/signin">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Email
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
      </label>
      <label>Password
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="btn accent large">Sign in</button>
      <p class="muted" style="margin:0;font-size:0.88rem;">Don't have an account? <a href="/signup">Create one</a>.</p>
    </form>
  </div>
  <aside class="auth-aside">
    <div class="panel-card">
      <span class="eyebrow" style="margin-bottom:1rem;"><span class="dot"></span>What's inside</span>
      <h3>Your timestamps, your reviews, your exports.</h3>
      <p>Open a review, click a timestamp, jump to the second. Share a read-only link. Export as JSON. Revoke access any time.</p>
      <div class="review-list" style="margin-top:1rem;">
        <div class="review-row"><span class="ts-mock">00:14:22</span><span class="review-note">Opening framing is too buried.</span><span class="tag">YouTube</span></div>
        <div class="review-row"><span class="ts-mock">00:47:02</span><span class="review-note">Demo skipped auth — walk through it.</span><span class="tag">Drive</span></div>
        <div class="review-row"><span class="ts-mock">01:02:11</span><span class="review-note">Great close. Confirm next step.</span><span class="tag">YouTube</span></div>
      </div>
    </div>
  </aside>
</div>
<?php $content = ob_get_clean();
$user = null;
require __DIR__ . '/../views/layout.php';
