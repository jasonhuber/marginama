<?php
declare(strict_types=1);

$user = current_user();
$title = 'Install the extension';
ob_start(); ?>
<h1>Install the Marginama extension</h1>
<p class="muted">
  Capture time-stamped critiques from YouTube, Sybill, and Google Drive videos —
  the panel appears right on the video page.
</p>

<div class="card">
  <h2 style="margin-top:0;">1. Download</h2>
  <p><a class="btn primary" href="/extension.zip" download>Download <code>marginama-extension.zip</code></a></p>
  <p class="muted">Unzip it anywhere on your computer.</p>
</div>

<div class="card">
  <h2 style="margin-top:0;">2. Load in your browser</h2>
  <ol>
    <li>Open <code>chrome://extensions</code> (or <code>brave://extensions</code>).</li>
    <li>Toggle <strong>Developer mode</strong> on (top-right).</li>
    <li>Click <strong>Load unpacked</strong>, pick the <code>marginama-extension</code> folder you just unzipped.</li>
  </ol>
</div>

<div class="card">
  <h2 style="margin-top:0;">3. Connect it to your account</h2>
  <ol>
    <li>Click the extension's toolbar icon → <strong>Options</strong>.</li>
    <li>Paste an API token and click <strong>Save</strong>, then <strong>Test connection</strong>.</li>
  </ol>
  <p>
    <?php if ($user): ?>
      <a class="btn" href="/settings/api-tokens">Mint an API token →</a>
    <?php else: ?>
      <a class="btn" href="/signup">Create an account →</a>
      <span class="muted"> you can mint a token once you're signed in.</span>
    <?php endif; ?>
  </p>
</div>

<h2>Using it</h2>
<p>
  Open any YouTube, Sybill, or Google Drive video page. The floating Marginama
  panel appears in the top-right. Press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd>
  (or <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>N</kbd>) to capture the current
  timestamp, type the critique, and <strong>Add note</strong>. Notes appear on
  <a href="/video-reviews">/video-reviews</a> sorted by timestamp.
</p>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
