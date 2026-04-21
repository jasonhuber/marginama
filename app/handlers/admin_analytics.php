<?php
declare(strict_types=1);

$user = require_admin();

$pdo = db();

// ── Top-line counters ────────────────────────────────────────────────────────
$total_users      = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_reviews    = (int) $pdo->query('SELECT COUNT(*) FROM video_reviews')->fetchColumn();
$total_critiques  = (int) $pdo->query('SELECT COUNT(*) FROM video_critiques')->fetchColumn();
$total_events     = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

$active_7d = (int) $pdo->query(
    'SELECT COUNT(DISTINCT user_id) FROM events
     WHERE user_id IS NOT NULL AND created_at >= (NOW() - INTERVAL 7 DAY)'
)->fetchColumn();

$sessions_7d = (int) $pdo->query(
    'SELECT COUNT(DISTINCT session_h) FROM events
     WHERE type = "page_view" AND created_at >= (NOW() - INTERVAL 7 DAY)'
)->fetchColumn();

$pageviews_7d = (int) $pdo->query(
    'SELECT COUNT(*) FROM events
     WHERE type = "page_view" AND created_at >= (NOW() - INTERVAL 7 DAY)'
)->fetchColumn();

$critiques_7d = (int) $pdo->query(
    'SELECT COUNT(*) FROM events
     WHERE type = "critique.create" AND created_at >= (NOW() - INTERVAL 7 DAY)'
)->fetchColumn();

// ── Daily activity: page_views + critique.create over the last 30 days ──────
$stmt = $pdo->query(
    'SELECT DATE(created_at) AS d,
            SUM(type = "page_view")       AS page_views,
            SUM(type = "critique.create") AS critiques,
            SUM(type LIKE "auth.%")       AS auth_events,
            COUNT(*)                      AS total
     FROM events
     WHERE created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC'
);
$raw = $stmt->fetchAll();
// Fill gaps so the chart is 30 uniform columns.
$daily = [];
$max_total = 0;
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $daily[$d] = ['page_views' => 0, 'critiques' => 0, 'auth_events' => 0, 'total' => 0];
}
foreach ($raw as $row) {
    if (isset($daily[$row['d']])) {
        $daily[$row['d']] = [
            'page_views'  => (int) $row['page_views'],
            'critiques'   => (int) $row['critiques'],
            'auth_events' => (int) $row['auth_events'],
            'total'       => (int) $row['total'],
        ];
        $max_total = max($max_total, (int) $row['total']);
    }
}

// ── Event type breakdown (last 30 days) ─────────────────────────────────────
$stmt = $pdo->query(
    'SELECT type, COUNT(*) AS c FROM events
     WHERE created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY type ORDER BY c DESC'
);
$type_breakdown = $stmt->fetchAll();
$type_total = array_sum(array_column($type_breakdown, 'c'));

// ── Per-user activity ───────────────────────────────────────────────────────
$stmt = $pdo->query(
    'SELECT u.id, u.email, u.name, u.created_at AS signed_up_at,
            (SELECT MAX(e.created_at) FROM events e WHERE e.user_id = u.id) AS last_active,
            (SELECT COUNT(*) FROM events e WHERE e.user_id = u.id) AS events,
            (SELECT COUNT(*) FROM events e WHERE e.user_id = u.id AND e.type = "page_view") AS page_views,
            (SELECT COUNT(*) FROM events e WHERE e.user_id = u.id AND e.type = "critique.create") AS critiques,
            (SELECT COUNT(DISTINCT DATE(e.created_at)) FROM events e
               WHERE e.user_id = u.id AND e.created_at >= (NOW() - INTERVAL 30 DAY)) AS active_days_30
     FROM users u
     ORDER BY last_active DESC, u.created_at DESC
     LIMIT 200'
);
$users = $stmt->fetchAll();

// ── Recent events (last 60) ─────────────────────────────────────────────────
$stmt = $pdo->query(
    'SELECT e.id, e.type, e.path, e.meta, e.ip_trunc, e.ua, e.created_at,
            u.email AS user_email, u.name AS user_name
     FROM events e
     LEFT JOIN users u ON u.id = e.user_id
     ORDER BY e.created_at DESC
     LIMIT 60'
);
$recent = $stmt->fetchAll();

// ── Top pages (last 30 days) ────────────────────────────────────────────────
$stmt = $pdo->query(
    'SELECT path, COUNT(*) AS c, COUNT(DISTINCT session_h) AS sess
     FROM events
     WHERE type = "page_view" AND created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY path ORDER BY c DESC LIMIT 15'
);
$top_pages = $stmt->fetchAll();

// Short-relative time formatter (for feed).
function ago(string $sql_ts): string {
    $t = strtotime($sql_ts . ' UTC');
    $d = max(1, time() - $t);
    if ($d < 60)       return $d . 's ago';
    if ($d < 3600)     return intdiv($d, 60) . 'm ago';
    if ($d < 86400)    return intdiv($d, 3600) . 'h ago';
    if ($d < 86400*30) return intdiv($d, 86400) . 'd ago';
    return date('M j', $t);
}

function event_kind(string $type): string {
    if ($type === 'page_view') return 'view';
    if (str_starts_with($type, 'auth.')) return 'auth';
    if (str_starts_with($type, 'critique.')) return 'critique';
    if (str_starts_with($type, 'share.')) return 'share';
    if (str_starts_with($type, 'token.')) return 'token';
    if (str_starts_with($type, 'feedback.')) return 'feedback';
    return 'other';
}

$title = 'Admin — Analytics';
$bodyClass = 'admin-analytics';
ob_start(); ?>
<style>
  .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 1.75rem; }
  .stat {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--r-lg); padding: 1rem 1.1rem;
  }
  .stat .label { font-family: var(--font-mono); font-size: 10.5px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--dim); margin-bottom: 0.45rem; }
  .stat .value { font-size: 1.7rem; font-weight: 600; color: var(--fg-0); letter-spacing: -0.01em; font-variant-numeric: tabular-nums; }
  .stat .sub { color: var(--muted); font-size: 0.78rem; margin-top: 0.25rem; font-family: var(--font-mono); }

  .chart {
    display: flex; align-items: flex-end;
    gap: 3px;
    height: 160px;
    padding: 1rem 1.25rem;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    margin-bottom: 0.5rem;
    overflow-x: auto;
  }
  .bar-col {
    flex: 1 1 0;
    min-width: 12px;
    display: flex; flex-direction: column; align-items: stretch; justify-content: flex-end;
    gap: 1px;
    position: relative;
    cursor: default;
  }
  .bar-col .bar-pv    { background: rgba(6,182,212,0.55); border-radius: 2px 2px 0 0; }
  .bar-col .bar-crit  { background: rgba(6,182,212,1); }
  .bar-col .bar-auth  { background: var(--fg-3); border-radius: 0 0 2px 2px; }
  .bar-col:hover::after {
    content: attr(data-tip);
    position: absolute; bottom: calc(100% + 4px); left: 50%;
    transform: translateX(-50%);
    padding: 0.35rem 0.55rem;
    background: var(--bg-soft); border: 1px solid var(--border);
    border-radius: var(--r-sm);
    font-family: var(--font-mono); font-size: 10.5px; color: var(--fg-0);
    white-space: pre; pointer-events: none; z-index: 10;
  }
  .chart-axis {
    display: flex;
    gap: 3px;
    padding: 0 1.25rem;
    font-family: var(--font-mono); font-size: 10px; color: var(--dim);
    margin-bottom: 1.75rem;
  }
  .chart-axis span { flex: 1 1 0; min-width: 12px; text-align: center; }
  .chart-legend { display: flex; gap: 1rem; font-family: var(--font-mono); font-size: 10.5px; color: var(--muted); margin-bottom: 0.75rem; }
  .chart-legend .sw { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 5px; vertical-align: -1px; }

  .section-title {
    font-size: 1rem; font-weight: 600; color: var(--fg-0);
    letter-spacing: -0.01em; margin: 2rem 0 0.75rem;
  }

  .event-kind {
    display: inline-flex; align-items: center; gap: 4px;
    font-family: var(--font-mono); font-size: 10.5px;
    padding: 1px 7px;
    border: 1px solid var(--border); border-radius: 4px;
    color: var(--muted); background: var(--card-2);
    text-transform: lowercase;
  }
  .event-kind.view     { color: var(--fg-2); }
  .event-kind.auth     { color: #fbbf24; border-color: rgba(251,191,36,0.4); }
  .event-kind.critique { color: var(--accent); border-color: var(--accent-line); }
  .event-kind.share    { color: #34d399; border-color: rgba(52,211,153,0.4); }
  .event-kind.token    { color: #c4b5fd; border-color: rgba(196,181,253,0.4); }
  .event-kind.feedback { color: #f87171; border-color: rgba(248,113,113,0.4); }

  table.data { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
  table.data th, table.data td { padding: 0.55rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border); font-variant-numeric: tabular-nums; }
  table.data th { font-family: var(--font-mono); font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--dim); font-weight: 500; }
  table.data td.email, table.data td.path, table.data td.mono { font-family: var(--font-mono); font-size: 12px; color: var(--fg-1); overflow: hidden; text-overflow: ellipsis; max-width: 320px; white-space: nowrap; }
  table.data tbody tr:hover { background: var(--card-2); }

  .breakdown { display: flex; flex-direction: column; gap: 0.4rem; }
  .breakdown .row {
    display: grid; grid-template-columns: 12rem 1fr auto; gap: 0.75rem; align-items: center;
    font-size: 0.88rem;
  }
  .breakdown .row .n { font-family: var(--font-mono); color: var(--fg-1); font-size: 12px; }
  .breakdown .row .bar { position: relative; height: 6px; background: var(--card-2); border: 1px solid var(--border); border-radius: 3px; overflow: hidden; }
  .breakdown .row .bar span { position: absolute; inset: 0 auto 0 0; background: var(--accent); opacity: 0.7; border-radius: 3px 0 0 3px; }
  .breakdown .row .label { font-family: var(--font-mono); font-size: 12px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<section class="block tight">
  <div class="container">
    <div class="page-head">
      <div>
        <h1>Analytics</h1>
        <p class="muted">Self-hosted. No third-party trackers. IPs truncated, sessions hashed.</p>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat"><div class="label">Users</div><div class="value"><?= number_format($total_users) ?></div><div class="sub"><?= number_format($active_7d) ?> active in 7d</div></div>
      <div class="stat"><div class="label">Reviews</div><div class="value"><?= number_format($total_reviews) ?></div><div class="sub"><?= number_format($total_critiques) ?> critiques total</div></div>
      <div class="stat"><div class="label">Page views 7d</div><div class="value"><?= number_format($pageviews_7d) ?></div><div class="sub"><?= number_format($sessions_7d) ?> unique sessions</div></div>
      <div class="stat"><div class="label">Critiques 7d</div><div class="value"><?= number_format($critiques_7d) ?></div><div class="sub">via extension + web</div></div>
      <div class="stat"><div class="label">Events total</div><div class="value"><?= number_format($total_events) ?></div><div class="sub">all time</div></div>
    </div>

    <h2 class="section-title">Activity — last 30 days</h2>
    <div class="chart-legend">
      <span><span class="sw" style="background: var(--fg-3);"></span>auth events</span>
      <span><span class="sw" style="background: rgba(6,182,212,0.55);"></span>page views</span>
      <span><span class="sw" style="background: var(--accent);"></span>critiques created</span>
    </div>
    <div class="chart">
      <?php foreach ($daily as $d => $row):
        $max = max($max_total, 1);
        $hPv   = $row['page_views']   / $max * 130;
        $hCrit = $row['critiques']    / $max * 130;
        $hAuth = $row['auth_events']  / $max * 130;
        $tip = date('D M j', strtotime($d))
          . "\npv:   {$row['page_views']}"
          . "\ncrit: {$row['critiques']}"
          . "\nauth: {$row['auth_events']}";
      ?>
        <div class="bar-col" data-tip="<?= e($tip) ?>" title="<?= e($tip) ?>">
          <?php if ($hPv   > 0): ?><div class="bar-pv"   style="height: <?= $hPv ?>px;"></div><?php endif; ?>
          <?php if ($hCrit > 0): ?><div class="bar-crit" style="height: <?= $hCrit ?>px;"></div><?php endif; ?>
          <?php if ($hAuth > 0): ?><div class="bar-auth" style="height: <?= $hAuth ?>px;"></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="chart-axis">
      <?php $i = 0; foreach ($daily as $d => $_): $label = ($i % 5 === 0) ? date('M j', strtotime($d)) : ''; ?>
        <span><?= e($label) ?></span>
      <?php $i++; endforeach; ?>
    </div>

    <div class="grid-2" style="gap:2rem; display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
      <div>
        <h2 class="section-title" style="margin-top:0;">Top event types (30d)</h2>
        <div class="breakdown">
          <?php foreach ($type_breakdown as $row):
            $pct = $type_total > 0 ? ((int) $row['c']) / $type_total * 100 : 0;
          ?>
            <div class="row">
              <span class="label"><?= e($row['type']) ?></span>
              <span class="bar"><span style="width:<?= number_format($pct, 1) ?>%"></span></span>
              <span class="n"><?= number_format((int) $row['c']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$type_breakdown): ?><p class="muted" style="font-size:0.88rem; margin:0;">No events yet.</p><?php endif; ?>
        </div>
      </div>

      <div>
        <h2 class="section-title" style="margin-top:0;">Top pages (30d)</h2>
        <table class="data">
          <thead><tr><th>Path</th><th style="text-align:right;">Views</th><th style="text-align:right;">Sess</th></tr></thead>
          <tbody>
          <?php foreach ($top_pages as $row): ?>
            <tr>
              <td class="path"><?= e($row['path'] ?: '(none)') ?></td>
              <td style="text-align:right;"><?= number_format((int) $row['c']) ?></td>
              <td style="text-align:right;"><?= number_format((int) $row['sess']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$top_pages): ?><tr><td colspan="3" style="color:var(--muted); font-size:0.88rem;">No page views yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h2 class="section-title">Users</h2>
    <table class="data">
      <thead>
        <tr>
          <th>User</th>
          <th>Signed up</th>
          <th>Last active</th>
          <th style="text-align:right;">Active days 30d</th>
          <th style="text-align:right;">Page views</th>
          <th style="text-align:right;">Critiques</th>
          <th style="text-align:right;">Total events</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="email"><?= e($u['email']) ?><?= $u['name'] ? ' <span class="muted">· ' . e($u['name']) . '</span>' : '' ?></td>
          <td class="mono"><?= e(substr($u['signed_up_at'], 0, 10)) ?></td>
          <td class="mono"><?= $u['last_active'] ? e(ago($u['last_active'])) : '<span class="muted">—</span>' ?></td>
          <td style="text-align:right;"><?= (int) $u['active_days_30'] ?></td>
          <td style="text-align:right;"><?= number_format((int) $u['page_views']) ?></td>
          <td style="text-align:right;"><?= number_format((int) $u['critiques']) ?></td>
          <td style="text-align:right;"><?= number_format((int) $u['events']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="7" style="color:var(--muted); font-size:0.88rem;">No users yet.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <h2 class="section-title">Recent events</h2>
    <table class="data">
      <thead>
        <tr>
          <th>When</th>
          <th>Type</th>
          <th>User</th>
          <th>Path / meta</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recent as $r):
        $kind = event_kind($r['type']);
      ?>
        <tr>
          <td class="mono" title="<?= e($r['created_at']) ?>"><?= e(ago($r['created_at'])) ?></td>
          <td><span class="event-kind <?= e($kind) ?>"><?= e($r['type']) ?></span></td>
          <td class="email"><?= $r['user_email'] ? e($r['user_email']) : '<span class="muted">—</span>' ?></td>
          <td class="path"><?= e($r['path'] ?: $r['meta'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="4" style="color:var(--muted); font-size:0.88rem;">Nothing yet.</td></tr><?php endif; ?>
      </tbody>
    </table>

  </div>
</section>
<?php $content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
