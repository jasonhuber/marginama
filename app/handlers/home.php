<?php
declare(strict_types=1);

$user = current_user();
if ($user) {
    header('Location: /video-reviews');
    exit;
}

$title = 'Marginama';
ob_start(); ?>
<section class="hero">
  <h1>Time-stamped video critiques, in one place.</h1>
  <p class="lede">
    Marginama drops a floating panel onto YouTube, Sybill, and Google Drive videos.
    Click to capture the current timestamp, type a note, and it saves against your account —
    searchable, shareable, exportable.
  </p>
  <p class="cta-row">
    <a class="btn primary" href="/signup">Create account</a>
    <a class="btn" href="/signin">Sign in</a>
    <a class="btn ghost" href="/extension">Install the extension →</a>
  </p>
</section>

<section class="features">
  <div class="feature">
    <h3>Capture</h3>
    <p>Press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd> on any supported video page. The current
      timestamp and a note go straight into your review.</p>
  </div>
  <div class="feature">
    <h3>Review</h3>
    <p>Every critique links back to the exact second of the video. Click a timestamp to seek.</p>
  </div>
  <div class="feature">
    <h3>Share</h3>
    <p>Generate a read-only link for any review. Anyone with the link sees the notes — no account required.</p>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
