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
<link rel="preload" as="font" type="font/woff2" href="/assets/fonts/inter-400.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="/assets/fonts/inter-500.woff2" crossorigin>
<link rel="stylesheet" href="/assets/app.css?v=<?= (int) $__cssV ?>">
</head>
<?php
// Page-view analytics. Only real HTML pages (through layout) count — API
// endpoints and asset requests never hit this. Admin browsing is skipped.
$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (!str_starts_with($__path, '/admin') && !str_starts_with($__path, '/api/')) {
    track_event('page_view', $__path);
}
?>
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
      <a href="/search">Search</a>
      <a href="/extension">Extension</a>
      <a href="/settings/api-tokens">Tokens</a>
      <a href="/settings/account">Account</a>
      <a href="/feedback">Feedback</a>
      <?php if (is_admin($user)): ?>
        <a href="/admin/analytics" style="color:var(--accent);">Analytics</a>
        <a href="/admin/suggestions" style="color:var(--accent);">Suggestions</a>
      <?php endif; ?>
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
      <a href="/extension">Extension</a>
      <?php if ($user): ?>
        <a href="/feedback">Feedback</a>
        <a href="/settings/account">Account</a>
      <?php endif; ?>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
      <a href="https://github.com/jasonhuber/marginama" target="_blank" rel="noopener">GitHub</a>
      <span class="muted">MIT · <?= (int) date('Y') ?></span>
    </div>
  </div>
</footer>
</body>
</html>
