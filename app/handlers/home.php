<?php
declare(strict_types=1);

$user = current_user();
if ($user) {
    header('Location: /video-reviews');
    exit;
}

$deleted = isset($_GET['deleted']);

$title = 'Marginama — video review, done properly';
$bodyClass = 'page-home';
ob_start(); ?>
<?php if ($deleted): ?>
  <div class="container" style="padding-top:1.5rem;">
    <div class="success" style="margin:0;">Your account and all associated data have been permanently deleted.</div>
  </div>
<?php endif; ?>
<section class="hero container">
  <span class="eyebrow"><span class="dot"></span>Video review, done properly</span>
  <h1>Catch the moment, <span class="glow">keep the critique</span>.</h1>
  <p class="lede">
    Marginama is a Chrome extension and dashboard for sales coaches, content
    editors, and product teams who review video and need their notes tied to
    the exact second.
  </p>
  <div class="hero-ctas">
    <a class="btn accent large" href="/signup">Get started</a>
    <a class="btn large" href="/extension">Get the extension</a>
  </div>

  <div class="mock-wrap" aria-hidden="true">
    <div class="browser">
      <div class="browser-bar">
        <span class="tl-dots"><span></span><span></span><span></span></span>
        <span class="url-bar"><span class="lock"></span>marginama.com/video-reviews</span>
      </div>
      <div class="app">
        <aside class="sidebar">
          <div class="side-label">Library</div>
          <div class="side-item active"><span class="sq"></span>Reviews</div>
          <div class="side-item"><span class="sq"></span>Shared</div>
          <div class="side-item"><span class="sq"></span>Archive</div>
          <div class="side-divider"></div>
          <div class="side-label">Settings</div>
          <div class="side-item"><span class="sq"></span>API tokens</div>
          <div class="side-item"><span class="sq"></span>Extension</div>
        </aside>
        <div class="panel">
          <div class="panel-head">
            <div>
              <div class="panel-title">Reviews</div>
              <div class="panel-sub">38 items · updated just now</div>
            </div>
            <div class="panel-meta">
              <span class="chip ok"><span class="d"></span>extension live</span>
              <span class="chip">MIT · v0.2</span>
            </div>
          </div>
          <div class="review-list">
            <div class="review-row">
              <span class="ts-mock">00:14:22</span>
              <span class="review-note">Opening framing: pitch is too buried. <span class="muted">Move value prop above the setup.</span></span>
              <span class="tag">YouTube</span>
            </div>
            <div class="review-row">
              <span class="ts-mock">00:22:08</span>
              <span class="review-note">Rep paused 3s before the pricing reveal. <span class="muted">Rehearse the transition.</span></span>
              <span class="tag">Sybill</span>
            </div>
            <div class="review-row">
              <span class="ts-mock">00:31:45</span>
              <span class="review-note">Good open-ended question. <span class="muted">This is the discovery moment.</span></span>
              <span class="tag">Sybill</span>
            </div>
            <div class="review-row">
              <span class="ts-mock">00:47:02</span>
              <span class="review-note">Demo skipped auth. <span class="muted">Walk through it for enterprise buyers next time.</span></span>
              <span class="tag">Drive</span>
            </div>
            <div class="review-row">
              <span class="ts-mock">01:02:11</span>
              <span class="review-note">Great close. <span class="muted">Confirm next step was agreed on.</span></span>
              <span class="tag">YouTube</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="block" id="features">
  <div class="container">
    <div class="section-header">
      <span class="eyebrow"><span class="dot"></span>Features</span>
      <h2>Everything the review actually needs.</h2>
      <p>Capture, seek, share, export. No floor-tile of tabs, no lost timestamps, no SaaS lock-in.</p>
    </div>
    <div class="grid-features">
      <article class="feature">
        <span class="i"><?= icon('capture') ?></span>
        <h3>Capture in place</h3>
        <p>Hit a shortcut while the video is playing. The timestamp saves with your note — no pausing, no copy-paste, no tab-switching.</p>
      </article>
      <article class="feature">
        <span class="i"><?= icon('timeline') ?></span>
        <h3>Deep-link timestamps</h3>
        <p>Every note is a seekable link. Click 14:22 on the dashboard and the video jumps there. Reviews stop being a wall of text.</p>
      </article>
      <article class="feature">
        <span class="i"><?= icon('share') ?></span>
        <h3>Share read-only</h3>
        <p>Send a revocable link to a colleague, client, or rep. They see your notes and the video. They change nothing.</p>
      </article>
      <article class="feature">
        <span class="i"><?= icon('export') ?></span>
        <h3>Export as JSON</h3>
        <p>Your reviews belong to you. One click pulls a full review out as structured JSON. Pipe it into a CRM, an LMS, or your own tooling.</p>
      </article>
      <article class="feature">
        <span class="i"><?= icon('selfhost') ?></span>
        <h3>Self-host it</h3>
        <p>One PHP codebase and one MySQL database. Run it on your own infrastructure. Your feedback never leaves your network.</p>
      </article>
      <article class="feature">
        <span class="i"><?= icon('opensource') ?></span>
        <h3>Open source, MIT</h3>
        <p>The whole thing is MIT-licensed. Read the code, fork it, audit it, change it. No vendor sitting between you and your team's work.</p>
      </article>
    </div>
  </div>
</section>

<section class="block" id="how">
  <div class="container">
    <div class="section-header">
      <span class="eyebrow"><span class="dot"></span>How it works</span>
      <h2>Three steps to a searchable review.</h2>
    </div>
    <div class="steps">
      <div class="step">
        <h3>Install the extension</h3>
        <p>Download the extension, extract it, and load it unpacked in Chrome or Brave. Paste an API token once — it authorizes only your account and is hashed at rest on the server.</p>
      </div>
      <div class="step">
        <h3>Open a video, take notes</h3>
        <p>Visit YouTube, Sybill, or a Google Drive video. A floating panel appears. Press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd> to grab the current timestamp, type your critique, save.</p>
      </div>
      <div class="step">
        <h3>Review on the dashboard</h3>
        <p>Your notes land at marginama.com, sorted by video and time. Edit, delete, share a read-only link, or export the whole review as JSON.</p>
      </div>
    </div>
  </div>
</section>

<section class="block" id="trust">
  <div class="container narrow">
    <div class="section-header">
      <span class="eyebrow"><span class="dot"></span>Own your tools</span>
      <h2>Built for teams that own their tools.</h2>
    </div>
    <p class="lede" style="margin:0 auto; text-align:center;">
      Most feedback tools keep your critiques on their servers and charge you
      monthly for the privilege. Marginama is the other option — MIT-licensed
      and self-hostable on a single PHP and MySQL stack. Per-user API tokens
      are hashed at rest, so the extension authorizes without exposing
      passwords. Your reviews are yours.
    </p>
    <div class="row" style="justify-content:center; margin-top:1.75rem;">
      <a class="btn" href="https://github.com/jasonhuber/marginama" target="_blank" rel="noopener"><?= icon('opensource', 'i') ?>Read the source</a>
      <a class="btn ghost" href="/extension.zip" download>Download extension</a>
    </div>
  </div>
</section>

<section class="block" id="faq">
  <div class="container narrow">
    <div class="section-header">
      <span class="eyebrow"><span class="dot"></span>FAQ</span>
      <h2>Answers to the obvious questions.</h2>
    </div>
    <div class="faq">
      <details>
        <summary>Which video platforms are supported?</summary>
        <p>Out of the box, Marginama works on YouTube, Sybill, and Google Drive video. The extension detects the current timestamp directly from the player on those sites. Additional platforms can be added by editing the extension source — it's part of the open-source repository.</p>
      </details>
      <details>
        <summary>How does pricing work?</summary>
        <p>Marginama is MIT-licensed software. If you self-host, it's free — you pay for your own server and database. Hosted accounts on marginama.com run on a per-seat subscription. No storage tier, no note cap, no trial countdown.</p>
      </details>
      <details>
        <summary>Does Marginama store my video content?</summary>
        <p>No. Marginama only stores timestamps, your written notes, and metadata about the video page. The video itself stays on YouTube, Sybill, or Google Drive. Nothing is re-hosted, re-encoded, or downloaded.</p>
      </details>
      <details>
        <summary>How do I install and set it up?</summary>
        <p>Create an account, generate an API token from your dashboard, and load the extension unpacked in Chrome or Brave. Paste the token into the extension once. From then on, open any supported video and the capture panel is there.</p>
      </details>
      <details>
        <summary>Can my team share critiques with each other?</summary>
        <p>Yes. Every review can be published as a read-only share link. Anyone with the link can read the notes and click through to timestamps. Links are revocable at any time, so a project that ends stays ended.</p>
      </details>
      <details>
        <summary>What if the extension can't detect my video?</summary>
        <p>Some players (Gong, Zoom cloud recordings, enterprise DRM videos) keep the video element behind cross-origin iframes the extension can't reach. In that case, flip the <strong>Manual timer</strong> toggle in the sidebar. Click <strong>▶ Start</strong> the moment you hit play on the video and the sidebar runs its own stopwatch. Press <kbd>⌘</kbd>+<kbd>⇧</kbd>+<kbd>N</kbd> to capture the elapsed time with your note. The only tradeoff: clicking timestamps on the dashboard opens the video URL but can't jump you to that exact second — you seek manually.</p>
      </details>
      <details>
        <summary>My video platform isn't supported. What now?</summary>
        <p>Two options. One: use <strong>Manual timer</strong> mode — it works on any web page with any player. Two: upload the recording to YouTube as <em>unlisted</em> or <em>private</em> (no need to make it public), share the link with your reviewer, and Marginama works exactly like it would on any public YouTube video. The video stays gated by YouTube's access controls; only the link holders can watch.</p>
      </details>
    </div>
  </div>
</section>

<section class="block">
  <div class="container narrow">
    <div class="cta-strip">
      <h2>Stop scrubbing. Start reviewing.</h2>
      <p>Install the extension, point it at your next recording, and watch your notes line up with the tape.</p>
      <div class="row" style="justify-content:center;">
        <a class="btn accent large" href="/signup">Get started</a>
        <a class="btn large" href="/extension">Install the extension</a>
      </div>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
