<?php
declare(strict_types=1);

$user = current_user();
$title = 'Privacy';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Privacy</h1>
        <p class="muted">Last updated: 21 April 2026. Plain-English for humans, accurate for the GDPR.</p>
      </div>
    </div>

    <div class="card" style="line-height:1.7;">
      <h2 style="font-size:1.1rem; margin:0 0 0.5rem;">The short version</h2>
      <p>Marginama stores the email you sign up with, the notes you write, and a small analytics event for each page load or feature use. We don't sell anything. We don't send anything to third-party trackers. You can delete your account at any time and everything goes with it.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Who runs this</h2>
      <p>Marginama is operated by Jason Huber. Contact: <a href="mailto:jasonhuber@gmail.com">jasonhuber@gmail.com</a>. If you're in the EU/UK and you want to contact us about your data, use the same address.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">What we collect</h2>
      <p>When you create an account: <strong>email</strong>, an optional <strong>name</strong>, and a <strong>bcrypt hash</strong> of your password (we never see the plaintext).</p>
      <p>When you use the product: the <strong>video URLs</strong> you paste (never the video content itself — videos stay on YouTube, Vimeo, Loom, etc.), the <strong>titles</strong>, your <strong>critiques</strong>, the <strong>timestamps</strong> on those critiques, and any <strong>API tokens</strong> you mint for the Chrome extension (we store only a sha256 hash).</p>
      <p>For analytics, one row per page load or key action: the event type (<span class="mono">page_view</span>, <span class="mono">auth.signin</span>, <span class="mono">critique.create</span>, etc.), the path, a small JSON meta field, your <strong>truncated IP</strong> (IPv4 cut to /24, IPv6 cut to /48 — never the full address), your <strong>User-Agent</strong> string, the <strong>referer</strong>, and a <strong>sha256 hash</strong> of your session cookie so we can count unique sessions without storing the cookie value itself.</p>
      <p>When you submit feedback: the <strong>category</strong> (bug/feature/praise/other), your <strong>message</strong>, the <strong>page</strong> you were on when you sent it, and your <strong>User-Agent</strong>.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">What we don't collect</h2>
      <ul>
        <li>The video files themselves (they stay on the source platform).</li>
        <li>Your full IP address. It's truncated before storage.</li>
        <li>Your password. Only the bcrypt hash.</li>
        <li>The plaintext of your API tokens. Only the sha256 hash.</li>
        <li>Anything via third-party trackers or cookies. There are none.</li>
      </ul>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Cookies</h2>
      <p>One cookie: <span class="mono">marginama_sess</span>. It's how the server knows you're signed in. It's strictly necessary for the service to function — under the EU ePrivacy Directive, strictly-necessary cookies don't require consent. We don't set any other cookies, and we don't use any third-party cookies.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Why we collect it</h2>
      <p>Account data (email, password hash) is used for authentication. Content (reviews, critiques, share tokens) is used to deliver the feature you signed up for. Analytics events are used to understand how Marginama is being used so we can fix bugs and prioritize. Legal basis: performance of the contract with you (arts. 6(1)(b) GDPR) for account/content, and legitimate interest (6(1)(f)) for analytics of a narrow, first-party, privacy-preserving kind.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Who we share it with</h2>
      <p>Nobody, in the sense of "sold to" or "given to." Two sub-processors handle infrastructure on our behalf:</p>
      <ul>
        <li><strong>Hostinger</strong> (hostinger.com) — hosts the app server and the MySQL database. Data stays on their European/US infrastructure. See their privacy policy and DPA.</li>
        <li><strong>Cloudflare</strong> (cloudflare.com) — authoritative DNS only (grey-cloud mode). They resolve <span class="mono">marginama.com</span> to an IP; they do not see your traffic or content.</li>
      </ul>
      <p>We don't use analytics services, advertising networks, CRMs, feature flag services, CDNs that proxy your requests, or any other external processor.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">How long we keep it</h2>
      <p>Account, content, and tokens: as long as your account exists. Delete your account and everything is permanently removed within seconds (cascading database delete — there is no recoverable backup of the deleted rows on our end). Analytics events: up to 24 months, after which we may aggregate and purge raw rows.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Your rights</h2>
      <p>If GDPR, UK GDPR, or a similar law applies to you, you have the right to:</p>
      <ul>
        <li><strong>Access</strong> a copy of your data — <a href="/settings/account">Settings → Account</a> has a one-click "Download my data" that emits JSON with everything tied to your user id.</li>
        <li><strong>Delete</strong> your account and all associated data — same page, one button, cascading delete.</li>
        <li><strong>Rectify</strong> incorrect data — edit your name on the account page; for email changes contact us.</li>
        <li><strong>Port</strong> your data — the JSON export is machine-readable.</li>
        <li><strong>Object</strong> to analytics processing — contact us and we'll scrub your events rows.</li>
        <li><strong>Lodge a complaint</strong> with your local supervisory authority if you believe we've mishandled your data.</li>
      </ul>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Data breaches</h2>
      <p>In the unlikely event of a breach involving personal data, we will notify affected users by email within 72 hours of becoming aware of it, and notify supervisory authorities as required.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Changes to this policy</h2>
      <p>We'll post the new version on this page with an updated date. For material changes, we'll email signed-in users.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">Source code</h2>
      <p>Marginama's entire codebase is MIT-licensed and public at <a href="https://github.com/jasonhuber/marginama" target="_blank" rel="noopener">github.com/jasonhuber/marginama</a>. Every claim in this policy is verifiable against the source.</p>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
