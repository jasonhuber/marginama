<?php
// Expects: $title (string), $content (string, already-escaped HTML), $user (?array)
/** @var string $title */
/** @var string $content */
/** @var ?array $user */
$user = $user ?? null;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · Marginama</title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site">
  <a class="brand" href="<?= $user ? '/video-reviews' : '/' ?>">Marginama</a>
  <nav>
  <?php if ($user): ?>
    <a href="/video-reviews">Video reviews</a>
    <a href="/extension">Extension</a>
    <a href="/settings/api-tokens">Settings</a>
    <form method="post" action="/signout">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit">Sign out</button>
    </form>
  <?php else: ?>
    <a href="/extension">Extension</a>
    <a href="/signin">Sign in</a>
    <a href="/signup">Sign up</a>
  <?php endif; ?>
  </nav>
</header>
<main>
<?= $content ?>
</main>
<footer class="site">
  <span class="muted">Marginama · <a href="https://github.com/jasonhuber/marginama" target="_blank" rel="noopener">source on GitHub</a></span>
</footer>
</body>
</html>
