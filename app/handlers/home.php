<?php
declare(strict_types=1);

$user = current_user();
if ($user) {
    header('Location: /video-reviews');
    exit;
}

$title = 'Marginama';
ob_start(); ?>
<h1>Marginama</h1>
<p>Capture time-stamped critiques from YouTube, Sybill, and Google Drive videos.</p>
<p class="row">
  <a class="btn primary" href="/signup">Create account</a>
  <a class="btn" href="/signin">Sign in</a>
</p>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
