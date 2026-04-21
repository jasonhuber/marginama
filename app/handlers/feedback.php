<?php
declare(strict_types=1);

require_once __DIR__ . '/../suggestions.php';

$user = require_session();

$error = null;
$success = false;
$body = '';
$kind = 'other';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $kind = in_array($_POST['kind'] ?? '', ['bug', 'feature', 'praise', 'other'], true)
        ? $_POST['kind']
        : 'other';
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body === '' || strlen($body) > 8000) {
        $error = 'Feedback must be 1–8000 characters.';
    } else {
        $id = ulid();
        $pageUrl = $_SERVER['HTTP_REFERER'] ?? null;
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500) ?: null;
        db()->prepare(
            'INSERT INTO suggestions (id, user_id, kind, body, page_url, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$id, $user['id'], $kind, $body, $pageUrl, $ua]);
        send_suggestion_email($id, $user, $kind, $body);
        track_event('feedback.submit', null, ['kind' => $kind], $user['id']);
        $success = true;
        $body = '';
        $kind = 'other';
    }
}

$title = 'Send feedback';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Send feedback</h1>
        <p class="muted">Bug reports, feature requests, praise — everything lands in one inbox.</p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="card callout">
        <h3 style="margin:0 0 0.35rem; color:var(--accent);">Thanks — sent.</h3>
        <p class="muted" style="margin:0;">We read every one. You'll hear back if there's a question.</p>
        <p style="margin-top:1rem;"><a class="btn" href="/feedback">Send another</a> <a class="btn ghost" href="/video-reviews">Back to reviews</a></p>
      </div>
    <?php else: ?>
      <?php if ($error): ?><div class="error" style="margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>
      <form class="stack card" method="post" action="/feedback" style="max-width:none;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>What kind?
          <select name="kind" style="padding:0.55rem 0.85rem; background:var(--card); border:1px solid var(--border); border-radius:var(--r-md); color:var(--fg); font:inherit; font-size:0.95rem;">
            <option value="bug"     <?= $kind === 'bug'     ? 'selected' : '' ?>>Bug — something is broken</option>
            <option value="feature" <?= $kind === 'feature' ? 'selected' : '' ?>>Feature — something is missing</option>
            <option value="praise"  <?= $kind === 'praise'  ? 'selected' : '' ?>>Praise — something is great</option>
            <option value="other"   <?= $kind === 'other'   ? 'selected' : '' ?>>Other</option>
          </select>
        </label>
        <label>Details <span class="hint">(the more specific, the better)</span>
          <textarea name="body" required minlength="1" maxlength="8000" rows="8" placeholder="What happened, what you expected, a link or URL if it helps..."><?= e($body) ?></textarea>
        </label>
        <div class="row" style="justify-content:space-between;">
          <span class="muted" style="font-size:0.86rem;">Sending as <span class="mono"><?= e($user['email']) ?></span>.</span>
          <button type="submit" class="btn accent">Send feedback</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
