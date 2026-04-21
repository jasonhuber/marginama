<?php
declare(strict_types=1);

/** @var array $params */
$user = require_admin();

// Path-based subactions: /admin/suggestions/{id}/status -> POST updates status.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST' && preg_match('#^/admin/suggestions/([A-Z0-9]{26})/status/?$#', $path, $m)) {
    require_csrf();
    $newStatus = $_POST['status'] ?? 'new';
    if (!in_array($newStatus, ['new', 'read', 'done', 'wontfix'], true)) {
        $newStatus = 'new';
    }
    db()->prepare('UPDATE suggestions SET status = ? WHERE id = ?')->execute([$newStatus, $m[1]]);
    header('Location: /admin/suggestions');
    exit;
}

$filter = $_GET['status'] ?? 'all';
$validFilters = ['all', 'new', 'read', 'done', 'wontfix'];
if (!in_array($filter, $validFilters, true)) $filter = 'all';

$sql = 'SELECT s.id, s.kind, s.body, s.page_url, s.user_agent, s.status, s.created_at,
               u.email AS user_email, u.name AS user_name
        FROM suggestions s
        LEFT JOIN users u ON u.id = s.user_id';
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE s.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY s.created_at DESC LIMIT 500';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = db()->query(
    "SELECT status, COUNT(*) AS c FROM suggestions GROUP BY status"
)->fetchAll();
$countByStatus = array_fill_keys($validFilters, 0);
$countByStatus['all'] = 0;
foreach ($counts as $c) {
    $countByStatus[$c['status']] = (int) $c['c'];
    $countByStatus['all'] += (int) $c['c'];
}

$kindLabels = ['bug' => 'Bug', 'feature' => 'Feature', 'praise' => 'Praise', 'other' => 'Other'];
$kindColors = [
    'bug'     => 'var(--danger)',
    'feature' => 'var(--accent)',
    'praise'  => 'var(--success)',
    'other'   => 'var(--fg-2)',
];

$title = 'Admin — Suggestions';
ob_start(); ?>
<section class="block tight">
  <div class="container">
    <div class="page-head">
      <div>
        <h1>Suggestions</h1>
        <p class="muted"><?= (int) $countByStatus['all'] ?> total · <?= (int) $countByStatus['new'] ?> new</p>
      </div>
      <div class="actions">
        <?php foreach ($validFilters as $f): ?>
          <a class="btn sm<?= $filter === $f ? ' accent' : '' ?>" href="/admin/suggestions<?= $f === 'all' ? '' : '?status=' . e($f) ?>">
            <?= e(ucfirst($f)) ?> <span class="mono" style="opacity:0.7;"><?= (int) $countByStatus[$f] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!$rows): ?>
      <div class="card empty">
        <h2>Nothing here.</h2>
        <p class="muted">No suggestions match this filter.</p>
      </div>
    <?php else: ?>
      <div class="reviews-list">
        <?php foreach ($rows as $r):
          $color = $kindColors[$r['kind']] ?? 'var(--fg-2)';
        ?>
          <div class="card" style="padding:1rem 1.25rem;">
            <div class="row" style="align-items:flex-start; gap:1rem; margin-bottom:0.5rem;">
              <div class="grow">
                <div class="row" style="gap:0.4rem; margin-bottom:0.3rem;">
                  <span class="count-pill" style="color:<?= $color ?>; border-color:currentColor;"><?= e($kindLabels[$r['kind']] ?? 'Other') ?></span>
                  <span class="count-pill"><?= e($r['status']) ?></span>
                  <span class="stamp"><?= e($r['created_at']) ?></span>
                </div>
                <div class="sub mono" style="font-size:0.82rem; color:var(--dim);">
                  <?= e($r['user_name'] ?: $r['user_email'] ?: '—') ?><?= $r['user_name'] && $r['user_email'] ? ' <' . e($r['user_email']) . '>' : '' ?>
                </div>
              </div>
              <form method="post" action="/admin/suggestions/<?= e($r['id']) ?>/status">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <select name="status" onchange="this.form.submit()" style="padding:0.3rem 0.55rem; background:var(--card); border:1px solid var(--border); border-radius:var(--r-sm); color:var(--fg); font:inherit; font-size:0.82rem;">
                  <?php foreach (['new', 'read', 'done', 'wontfix'] as $s): ?>
                    <option value="<?= $s ?>"<?= $r['status'] === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
            <div style="white-space:pre-wrap; color:var(--fg); font-size:0.95rem; line-height:1.55; margin:0 0 0.5rem;"><?= e($r['body']) ?></div>
            <?php if ($r['page_url']): ?>
              <div class="muted mono" style="font-size:0.78rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">on <a href="<?= e($r['page_url']) ?>" target="_blank" rel="noopener"><?= e($r['page_url']) ?></a></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
