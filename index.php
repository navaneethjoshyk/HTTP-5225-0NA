<?php

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';


$mode     = isset($_GET['mode']) ? $_GET['mode'] : 'pairs'; // 'pairs' | 'agg'
$platform = isset($_GET['platform']) ? trim($_GET['platform']) : '';
$min_age  = isset($_GET['min_age']) ? (int)$_GET['min_age'] : 0;
$max_age  = isset($_GET['max_age']) ? (int)$_GET['max_age'] : 0;
$order    = isset($_GET['order']) ? $_GET['order'] : 'platform';
$dir      = strtoupper(isset($_GET['dir']) ? $_GET['dir'] : 'ASC');
$limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit    = ($limit > 0 && $limit <= 500) ? $limit : 20;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset   = ($page - 1) * $limit;

/* ---------- dropdown options ---------- */
$platforms = getDistinctPlatforms($conn);

/* ---------- order whitelist ---------- */
$validOrder = ($mode === 'agg') ? validOrderColsAgg() : validOrderColsPairs();
$orderCol   = $validOrder[$order] ?? 'platform';
$dir        = ($dir === 'DESC') ? 'DESC' : 'ASC';

/* ---------- fetch data + counts (pagination) ---------- */
if ($mode === 'agg') {
    $total = countAgg($conn, $platform, $min_age, $max_age);
    $rows  = fetchAggPaged($conn, $platform, $min_age, $max_age, $orderCol, $dir, $limit, $offset);
} else {
    $total = countPairs($conn, $platform, $min_age, $max_age);
    $rows  = fetchPairsPaged($conn, $platform, $min_age, $max_age, $orderCol, $dir, $limit, $offset);
}
$pages = max(1, (int)ceil($total / $limit));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Insights — Mental Health & Social Media</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
<header class="appbar">
  <div class="brand">Insights<span class="dot">.</span></div>
  <nav class="tabs">
    <a href="?mode=pairs" class="<?= $mode==='pairs'?'active':'' ?>">Pairs (JOIN)</a>
    <a href="?mode=agg" class="<?= $mode==='agg'?'active':'' ?>">Aggregated (LEFT JOINs)</a>
  </nav>
</header>

<main class="container">
  <!-- Filters -->
  <section class="panel filters">
    <form method="get" class="grid">
      <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>" />
      <div>
        <label>Platform</label>
        <select name="platform">
          <option value="">All</option>
          <?php foreach ($platforms as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= $platform===$p?'selected':'' ?>>
              <?= htmlspecialchars($p) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Min Age</label>
        <input type="number" name="min_age" value="<?= (int)$min_age ?>" min="0" />
      </div>
      <div>
        <label>Max Age</label>
        <input type="number" name="max_age" value="<?= (int)$max_age ?>" min="0" />
      </div>
      <div>
        <label>Order by</label>
        <select name="order">
          <?php foreach (array_keys($validOrder) as $k): ?>
            <option value="<?= htmlspecialchars($k) ?>" <?= $order===$k?'selected':'' ?>>
              <?= htmlspecialchars($k) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Dir</label>
        <select name="dir">
          <option <?= $dir==='ASC'?'selected':'' ?>>ASC</option>
          <option <?= $dir==='DESC'?'selected':'' ?>>DESC</option>
        </select>
      </div>
      <div>
        <label>Rows</label>
        <select name="limit">
          <?php foreach ([10,20,50,100] as $opt): ?>
            <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="actions">
        <button type="submit">Apply</button>
        <a class="btn" href="?mode=<?= htmlspecialchars($mode) ?>">Reset</a>
      </div>
    </form>
  </section>

  <!-- KPI cards -->
  <section class="kpis">
  <div class="kpi pretty">
    <div class="kpi-icon">📊</div>
    <div>
      <div class="kpi-label">Total Rows</div>
      <div class="kpi-value"><?= number_format($total) ?></div>
    </div>
  </div>

  <div class="kpi pretty">
    <div class="kpi-icon">📄</div>
    <div>
      <div class="kpi-label">Page</div>
      <div class="kpi-value"><?= $page ?> <span class="muted">/ <?= $pages ?></span></div>
    </div>
  </div>

  <div class="kpi pretty">
    <div class="kpi-icon">⚙️</div>
    <div>
      <div class="kpi-label">Mode</div>
      <div class="kpi-value" style="text-transform:uppercase;">
        <?= htmlspecialchars($mode) ?>
      </div>
    </div>
  </div>
</section>


  <!-- Data table -->
  <section class="panel">
    <div class="table-wrap">
      <table>
        <?php if ($mode==='pairs'): ?>
        <thead>
          <tr>
            <th>Platform</th>
            <th class="right">Male Age</th>
            <th class="right">Male Sleep</th>
            <th class="right">Male Stress</th>
            <th class="right">Male Happiness</th>
            <th class="right">Female Age</th>
            <th class="right">Female Sleep</th>
            <th class="right">Female Stress</th>
            <th class="right">Female Happiness</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="chip"><?= htmlspecialchars($r['platform']) ?></span></td>
            <td class="right"><?= htmlspecialchars($r['male_age']) ?></td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['male_sleep'],10) ?>%"></span></div>
              <div class="<?= clsSleep($r['male_sleep']) ?> mono"><?= htmlspecialchars($r['male_sleep']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['male_stress'],10) ?>%"></span></div>
              <div class="<?= clsStress($r['male_stress']) ?> mono"><?= htmlspecialchars($r['male_stress']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['male_happy'],10) ?>%"></span></div>
              <div class="<?= clsHappy($r['male_happy']) ?> mono"><?= htmlspecialchars($r['male_happy']) ?></div>
            </td>
            <td class="right"><?= htmlspecialchars($r['female_age']) ?></td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['female_sleep'],10) ?>%"></span></div>
              <div class="<?= clsSleep($r['female_sleep']) ?> mono"><?= htmlspecialchars($r['female_sleep']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['female_stress'],10) ?>%"></span></div>
              <div class="<?= clsStress($r['female_stress']) ?> mono"><?= htmlspecialchars($r['female_stress']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['female_happy'],10) ?>%"></span></div>
              <div class="<?= clsHappy($r['female_happy']) ?> mono"><?= htmlspecialchars($r['female_happy']) ?></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>

        <?php else: /* agg mode */ ?>
        <thead>
          <tr>
            <th>Platform</th>
            <th class="right">Avg Male Sleep</th>
            <th class="right">Avg Male Stress</th>
            <th class="right">Avg Male Happiness</th>
            <th class="right"># Male</th>
            <th class="right">Avg Female Sleep</th>
            <th class="right">Avg Female Stress</th>
            <th class="right">Avg Female Happiness</th>
            <th class="right"># Female</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="chip"><?= htmlspecialchars($r['platform']) ?></span></td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['avg_male_sleep'],10) ?>%"></span></div>
              <div class="<?= clsSleep($r['avg_male_sleep']) ?> mono"><?= htmlspecialchars($r['avg_male_sleep']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['avg_male_stress'],10) ?>%"></span></div>
              <div class="<?= clsStress($r['avg_male_stress']) ?> mono"><?= htmlspecialchars($r['avg_male_stress']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['avg_male_happy'],10) ?>%"></span></div>
              <div class="<?= clsHappy($r['avg_male_happy']) ?> mono"><?= htmlspecialchars($r['avg_male_happy']) ?></div>
            </td>
            <td class="right mono"><?= htmlspecialchars($r['count_male']) ?></td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['avg_female_sleep'],10) ?>%"></span></div>
              <div class="<?= clsSleep($r['avg_female_sleep']) ?> mono"><?= htmlspecialchars($r['avg_female_sleep']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['avg_female_stress'],10) ?>%"></span></div>
              <div class="<?= clsStress($r['avg_female_stress']) ?> mono"><?= htmlspecialchars($r['avg_female_stress']) ?></div>
            </td>
            <td class="right">
              <div class="bar"><span style="width:<?= percentBar($r['avg_female_happy'],10) ?>%"></span></div>
              <div class="<?= clsHappy($r['avg_female_happy']) ?> mono"><?= htmlspecialchars($r['avg_female_happy']) ?></div>
            </td>
            <td class="right mono"><?= htmlspecialchars($r['count_female']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" style="display:flex;gap:.5rem;justify-content:flex-end;align-items:center;margin-top:.6rem;">
      <?php
        $qs = $_GET;
        $qs['page'] = max(1, $page - 1);
        $prev = '?' . http_build_query($qs);
        $qs['page'] = min($pages, $page + 1);
        $next = '?' . http_build_query($qs);
      ?>
      <a class="btn <?= $page<=1?'disabled':'' ?>" href="<?= $page<=1?'#':$prev ?>" style="padding:.45rem .7rem;border:1px solid #e7e7e7;border-radius:.5rem;text-decoration:none;color:inherit;">‹ Prev</a>
      <span class="muted" style="color:#666;">Page <?= $page ?> of <?= $pages ?></span>
      <a class="btn <?= $page>=$pages?'disabled':'' ?>" href="<?= $page>=$pages?'#':$next ?>" style="padding:.45rem .7rem;border:1px solid #e7e7e7;border-radius:.5rem;text-decoration:none;color:inherit;">Next ›</a>
    </div>
  </section>

  <!-- Plain loop preview for rubric -->
  <section class="panel">
    <h2>Loop Preview</h2>
    <pre class="row-plain"><?php
$idx = $offset + 1;
if ($mode === 'pairs') {
    foreach ($rows as $r) {
        echo "Row {$idx} — {$r['platform']} | M:{$r['male_stress']} F:{$r['female_stress']}\n";
        $idx++;
    }
} else {
    foreach ($rows as $r) {
        echo "Row {$idx} — {$r['platform']} | avg M happy: {$r['avg_male_happy']} | avg F happy: {$r['avg_female_happy']}\n";
        $idx++;
    }
}
?></pre>
  </section>
</main>
</body>
</html>
