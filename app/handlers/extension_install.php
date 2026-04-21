<?php
declare(strict_types=1);

$user = current_user();
$title = 'Install the extension';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="section-header" style="margin-bottom:2rem;">
      <span class="eyebrow"><span class="dot"></span>Browser extension</span>
      <h1 style="font-size:clamp(1.75rem,3vw + 0.5rem,2.5rem);">Install the Marginama extension</h1>
      <p>Capture time-stamped critiques from YouTube, Sybill, and Google Drive videos — the panel appears right on the video page.</p>
    </div>

    <div class="steps">
      <div class="step">
        <h3><span class="i" style="color:var(--accent); margin-right:0.35rem;"><?= icon('export') ?></span>Download</h3>
        <p>Get the zipped extension bundle. It's ~30&nbsp;KB and contains the manifest, background worker, content script, icons, and options page.</p>
        <p><a class="btn accent" href="/extension.zip" download><?= icon('export', 'i') ?>Download <span class="mono">.zip</span></a></p>
      </div>
      <div class="step">
        <h3><span class="i" style="color:var(--accent); margin-right:0.35rem;"><?= icon('selfhost') ?></span>Load unpacked</h3>
        <p>Extract the zip. Open <code>chrome://extensions</code> (or <code>brave://extensions</code>). Toggle <strong>Developer mode</strong> on. Click <strong>Load unpacked</strong> and pick the <code>marginama-extension</code> folder.</p>
      </div>
      <div class="step">
        <h3><span class="i" style="color:var(--accent); margin-right:0.35rem;"><?= icon('secure') ?></span>Connect</h3>
        <p>Click the extension's toolbar icon → <strong>Options</strong>. Paste an API token, click <strong>Save</strong>, then <strong>Test connection</strong>. You'll see your review count.</p>
        <p>
        <?php if ($user): ?>
          <a class="btn" href="/settings/api-tokens">Mint an API token →</a>
        <?php else: ?>
          <a class="btn accent" href="/signup">Create an account</a>
          <span class="muted"> then mint a token from your dashboard.</span>
        <?php endif; ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="block tight">
  <div class="container narrow">
    <div class="card elevated">
      <h3 style="margin-bottom:0.5rem;">Using it</h3>
      <p class="muted" style="margin:0 0 1rem;">
        Open any YouTube, Sybill, or Google Drive video page. The floating Marginama
        panel appears top-right. Press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd>
        (or <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>N</kbd>) to capture the current
        timestamp, type the critique, and click <strong>Add note</strong>. Notes
        appear on <a href="/video-reviews">/video-reviews</a> sorted by timestamp.
      </p>
      <p class="muted" style="margin:0; font-size:0.88rem;">
        The extension is MIT-licensed. Source on
        <a href="https://github.com/jasonhuber/marginama/tree/main/extension" target="_blank" rel="noopener">GitHub</a>.
      </p>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
