<?php
declare(strict_types=1);

$user = current_user();
$title = 'Terms';
ob_start(); ?>
<section class="block tight">
  <div class="container narrow">
    <div class="page-head">
      <div>
        <h1>Terms of service</h1>
        <p class="muted">Last updated: 21 April 2026.</p>
      </div>
    </div>

    <div class="card" style="line-height:1.7;">
      <h2 style="font-size:1.1rem; margin:0 0 0.5rem;">1. What Marginama is</h2>
      <p>Marginama is a web app and Chrome extension that lets you capture time-stamped critiques on videos and review them on a dashboard. These terms govern your use of the service at <a href="https://marginama.com">marginama.com</a> and the associated extension.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">2. Your account</h2>
      <p>You're responsible for keeping your password safe. You agree to provide a valid email. Only one person per account. We can suspend or delete accounts that are used to abuse the service — spam, automated scraping that degrades performance for other users, attempts to circumvent authentication, uploading illegal content, etc.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">3. Your content</h2>
      <p>The critiques you write belong to you. You keep all rights to your notes, reviews, and export data. You grant us a license to store and display that content back to you (and to anyone you intentionally share a review link with) solely for the purpose of running the service. We don't train models on your content, publish it, sell it, or use it for advertising.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">4. Videos belong to their hosts</h2>
      <p>Marginama doesn't store, re-host, download, or re-encode the videos you review. They stay on YouTube, Vimeo, Loom, Sybill, Google Drive, or wherever they originated. You are responsible for ensuring you have the right to view any video you attach critiques to.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">5. Acceptable use</h2>
      <p>You agree not to use Marginama to:</p>
      <ul>
        <li>Post content that is illegal, harassing, defamatory, or infringing.</li>
        <li>Attempt to access another user's data or bypass authentication.</li>
        <li>Reverse-engineer the API in ways that degrade service for others.</li>
        <li>Scrape, bulk-download, or systematically harvest data belonging to others.</li>
        <li>Upload malware, exploits, or any code meant to cause harm.</li>
      </ul>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">6. Availability</h2>
      <p>Marginama is provided "as is." We don't guarantee uptime, data durability, or fitness for any particular purpose. We back up the production database on a best-effort basis but you shouldn't treat Marginama as a system of record — export what you need from <a href="/settings/account">Settings → Account</a> periodically.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">7. No warranty</h2>
      <p>To the maximum extent permitted by law, Marginama is provided without warranty of any kind, express or implied. This includes warranties of merchantability, fitness for a particular purpose, and non-infringement.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">8. Limit of liability</h2>
      <p>To the extent permitted by law, we are not liable for indirect, incidental, special, consequential, or punitive damages arising out of your use of the service. Where local law forbids excluding liability (for example, gross negligence under certain EU jurisdictions), these limitations do not apply.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">9. Termination</h2>
      <p>You can delete your account at any time from <a href="/settings/account">Settings → Account</a>. We can terminate accounts that violate these terms. On termination, your content is permanently deleted.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">10. Open source</h2>
      <p>Marginama's source code is available under the MIT license at <a href="https://github.com/jasonhuber/marginama" target="_blank" rel="noopener">github.com/jasonhuber/marginama</a>. You can audit, fork, self-host, and modify the code per that license. These terms govern only your use of the hosted service at <a href="https://marginama.com">marginama.com</a>.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">11. Changes</h2>
      <p>We may update these terms. When we do, we'll update the date at the top and, for material changes, notify signed-in users by email. Continuing to use the service after the update means you accept the new terms.</p>

      <h2 style="font-size:1.1rem; margin:1.75rem 0 0.5rem;">12. Contact</h2>
      <p>Questions about these terms: <a href="mailto:jasonhuber@gmail.com">jasonhuber@gmail.com</a>.</p>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
