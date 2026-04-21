<?php
// Expects: $title (string), $content (string, already-escaped HTML), $user (?array)
/** @var string $title */
/** @var string $content */
/** @var ?array $user */
$user = $user ?? null;
$bodyClass = $bodyClass ?? '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= e($title) ?> · Marginama</title>
<meta name="description" content="Marginama captures time-stamped critiques on YouTube, Sybill, and Google Drive videos. Self-hostable, open source.">
<?php
$__favPath = __DIR__ . '/../../public_html/assets/favicon.svg';
$__favV = is_file($__favPath) ? filemtime($__favPath) : 1;
$__cssPath = __DIR__ . '/../../public_html/assets/app.css';
$__cssV = is_file($__cssPath) ? filemtime($__cssPath) : 1;
?>
<link rel="icon" href="/assets/favicon.svg?v=<?= (int) $__favV ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=<?= (int) $__cssV ?>">
</head>
<body<?= $bodyClass ? ' class="' . e($bodyClass) . '"' : '' ?>>
<header class="site">
  <div class="bar">
    <a class="brand" href="<?= $user ? '/video-reviews' : '/' ?>" aria-label="Marginama">
      <span class="mark" aria-hidden="true">§</span>
      <span>Marginama</span>
    </a>
    <nav aria-label="Primary">
    <?php if ($user): ?>
      <a href="/video-reviews">Reviews</a>
      <a href="/extension">Extension</a>
      <a href="/settings/api-tokens">Settings</a>
      <form method="post" action="/signout">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn ghost sm">Sign out</button>
      </form>
    <?php else: ?>
      <a href="/extension">Extension</a>
      <a href="/#features">Features</a>
      <a href="/#faq">FAQ</a>
      <a href="/signin">Sign in</a>
      <a class="btn accent sm" href="/signup" style="margin-left:0.5rem;">Get started</a>
    <?php endif; ?>
    </nav>
  </div>
</header>
<main>
<?= $content ?>
</main>
<footer class="site">
  <div class="inner">
    <div><span class="mono">Marginama</span> · <span class="muted">Time-stamped video critiques.</span></div>
    <div class="links">
      <a href="/extension">Install extension</a>
      <a href="https://github.com/jasonhuber/marginama" target="_blank" rel="noopener">GitHub</a>
      <span class="muted">MIT · <?= (int) date('Y') ?></span>
    </div>
  </div>
</footer>
</body>
</html>
