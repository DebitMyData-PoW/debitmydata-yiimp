<?php
/*
 * The pool's home page, in the DebitMyData look: what the pool is, a box to open your wallet
 * page, the live figures (the pool's hashrate, its shared and solo parts and the network's under
 * it), the settings to put in a miner, the pool hashrate over the last 24 hours and the latest blocks found. The figures
 * refresh every 30 seconds (auto_refresh.js).
 * No payout countdown here: PPLNS pays in each block; a wallet still waiting for a balance
 * payout sees it on its own wallet page.
 */

JavascriptFile('/yaamp/ui/js/auto_refresh.js');

$algo = 'sha256';
$stratum = 'connect.debitmydata.io:8700';

// Keep in step with [PPLNS] fee and solo_fee in the DMD port's stratum config (dmd.sha.conf)
$fee_pool = '0.5%';
$fee_solo = '1%';

// "All blocks in the explorer": the pool's address (master_wallet) in the coin's explorer
// (block_explorer), both from the DMD coin's row in the coins table. The explorer is given
// /address/<address>.
$explorer_url = 'https://mempool.debitmydata.io/';
$explorer_coin = dmd_coin();
if ($explorer_coin) {
    $explorer_base = trim((string) $explorer_coin->block_explorer);
    if (preg_match('#^https?://[^\s"<>]+$#i', $explorer_base)) $explorer_url = $explorer_base;
    $explorer_wallet = trim((string) $explorer_coin->master_wallet);
    if (preg_match('/^[A-Za-z0-9]+$/', $explorer_wallet)) $explorer_url = rtrim($explorer_url, '/') . '/address/' . $explorer_wallet;
}

// "Notify me" on the latest blocks: anyone can get a notice for every block the pool finds, without
// an address (the bell's script in yaamp/ui/dmd-app.php does it). Only once notifications are set up.
require_once('/home/crypto-data/yiimp/site/web/yaamp/core/backend/push.php');
$notify_pool = push_config() && push_table();

// A coin's hashrate (counted as yaamp_coin_rate counts it), in two parts: shared (PPLNS, with the
// pool) and solo. Both from one query, kept 30 seconds like the other figures.
function dmd_home_rate_split($coin)
{
    if (!$coin->enable) return array(0, 0);
    if (!yaamp_has_solo_shares()) return array((double) yaamp_coin_rate($coin->id), 0);
    $id = intval($coin->id);
    $cache = controller()->memcache;
    $shared = $cache->get("home_rate_shared-$id");
    $solo = $cache->get("home_rate_solo-$id");
    if ($shared === false || $solo === false) {
        $target = yaamp_hashrate_constant($coin->algo);
        $interval = yaamp_hashrate_step();
        $rows = dbolist('SELECT solo, SUM(difficulty) AS diff FROM shares WHERE valid AND time > :since AND coinid = :coinid GROUP BY solo',
            array(':since' => time() - $interval, ':coinid' => $id));
        $shared = 0;
        $solo = 0;
        foreach ($rows as $row) {
            $rate = $row['diff'] * $target / $interval / 1000;
            if ($row['solo']) $solo += $rate; else $shared += $rate;
        }
        $cache->set("home_rate_shared-$id", $shared, 30);
        $cache->set("home_rate_solo-$id", $solo, 30);
    }
    return array((double) $shared, (double) $solo);
}

// "1.23 PH/s": a hashrate with its unit
function dmd_home_rate($hash)
{
    if ($hash > 0 && preg_match('/^\s*([0-9.,]+)\s*([A-Za-z]*)\s*$/', Itoa2($hash), $m)) return $m[1] . ' ' . $m[2] . 'H/s';
    return '0 H/s';
}

// Pool hashrate: the coins mined with SHA-256, added up, in its shared and solo parts (from the
// same shares, so the two parts always make the total)
$shared_hash = 0;
$solo_hash = 0;
$ttf_coin = null;
$ttf_split = array(0, 0);
foreach (getdbolist('db_coins', "enable=1 AND visible=1 AND algo=:algo", array(':algo' => $algo)) as $coin) {
    $split = dmd_home_rate_split($coin);
    $shared_hash += $split[0];
    $solo_hash += $split[1];
    if (!$ttf_coin || $split[0] + $split[1] > $ttf_split[0] + $ttf_split[1]) { $ttf_coin = $coin; $ttf_split = $split; }
}
$total_hash = $shared_hash + $solo_hash;
$split_sub = str_replace(' ', '&nbsp;', CHtml::encode(dmd_home_rate($shared_hash) . ' shared')) . ' · '
    . str_replace(' ', '&nbsp;', CHtml::encode(dmd_home_rate($solo_hash) . ' solo'));

// Time to find a block (of the coin mined most here), at the current hashrate: shared (PPLNS:
// the shared hashrate, solo miners left out) and solo (all solo miners together)
$ttf_pool = 0;
$ttf_solo = 0;
if ($ttf_coin) {
    $ttf_pool = yaamp_ttf_seconds($ttf_coin->difficulty, $ttf_split[0]);
    $ttf_solo = yaamp_ttf_seconds($ttf_coin->difficulty, $ttf_split[1]);
}
// "3 hours", "5 days": the steps of sectoa2(), the number and its unit apart
function dmd_home_ttf($seconds)
{
    if (!($seconds > 0)) return '—';
    $d = 86400;
    foreach (array(array('year', 365 * $d, 365 * $d), array('month', 60 * $d, 30 * $d), array('week', 14 * $d, 7 * $d),
        array('day', 2 * $d, $d), array('hour', 7200, 3600), array('min', 120, 60), array('sec', 0, 1)) as $u) {
        if ($seconds < $u[1]) continue;
        $n = max(1, floor($seconds / $u[2]));
        return $n . ' <small>' . $u[0] . ($seconds / $u[2] >= 2 ? 's' : '') . '</small>';
    }
    return '—';
}
// The whole network's hashrate (the node of the coin mined most here), under the pool's
$network_hash = null;
if ($ttf_coin) {
    $network = yaamp_network_stats($ttf_coin);
    if (is_numeric($network['hashrate']) && $network['hashrate'] > 0) $network_hash = (double) $network['hashrate'];
}
$network_sub = '';
if ($network_hash) {
    $network_sub = 'Network ' . (preg_match('/^\s*([0-9.,]+)\s*([A-Za-z]*)\s*$/', Itoa2($network_hash), $m)
        ? $m[1] . ' ' . $m[2] . 'H/s' : Itoa2($network_hash) . 'H/s');
}
$network_title = '';
if ($network_hash && $total_hash > 0) {
    $part = $total_hash * 100 / $network_hash;
    $network_title = 'The pool has ' . number_format($part, $part >= 10 ? 1 : 2) . '% of the network';
}

$rate_value = '0';
$rate_unit = 'H/s';
if ($total_hash > 0 && preg_match('/^\s*([0-9.,]+)\s*([A-Za-z]*)\s*$/', Itoa2($total_hash), $m)) {
    $rate_value = $m[1];
    $rate_unit = $m[2] . 'H/s';
}

// Miners connected: different addresses, their workers, and how many of them mine solo
// (sent solo shares in the last 10 minutes)
$workers = getdbocount('db_workers', "algo=:algo", array(':algo' => $algo));
$miners = (int) controller()->memcache->get_database_scalar("home_miners-$algo",
    "SELECT COUNT(DISTINCT name) FROM workers WHERE algo=:algo", array(':algo' => $algo));
$solo_miners = 0;
if (yaamp_has_solo_shares()) {
    $solo_miners = (int) controller()->memcache->get_database_scalar("home_solo_miners-$algo",
        "SELECT COUNT(DISTINCT userid) FROM shares WHERE solo=1 AND valid AND algo=:algo AND time > UNIX_TIMESTAMP()-600",
        array(':algo' => $algo));
}
$miners_sub = number_format((int) $workers) . ((int) $workers == 1 ? ' worker' : ' workers')
    . ($solo_miners ? ' · ' . number_format($solo_miners) . ' solo' : '');

function dmd_home_icon($name)
{
    $paths = array(
        'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'cpu' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v4M15 1v4M9 19v4M15 19v4M1 9h4M1 15h4M19 9h4M19 15h4"/>',
        'timer' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9.5 2h5M19 6l-1.5 1.5"/>',
        'share' => '<circle cx="6" cy="12" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><path d="M8.6 10.7l6.8-3.4M8.6 13.3l6.8 3.4"/>',
        'server' => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
        'cube' => '<path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><path d="M16 14h.01"/>',
        'out' => '<path d="M7 17L17 7M9 7h8v8"/>',
    );
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

?>
<div class="dmd-home">

<section class="dmd-hero" aria-labelledby="dmd-title">
<div class="dmd-hero-text">
<p class="dmd-eyebrow">Official SHA-256 pool</p>
<h1 id="dmd-title">DebitMyData <span class="dmd-accent">mining pool</span></h1>
<p>Mine DMD, the coin of the DebitMyData Proof-of-Work chain, with any SHA-256 ASIC or
rented hashrate. Payouts are PPLNS, paid to your DMD address in each block the pool finds.
Or mine solo and keep the whole block.</p>
<ul class="dmd-perks"><li>SHA-256</li><li>PPLNS paid in the block</li><li>Solo mining</li><li>NiceHash compatible</li></ul>
<form class="dmd-lookup" action="/mywallet/" method="get" role="search">
<label for="dmd-address">Check your mining</label>
<div class="dmd-lookup-row">
<input class="dmd-input" id="dmd-address" name="address" type="text" placeholder="Your DMD address" autocomplete="off"
autocapitalize="off" spellcheck="false" required pattern="[A-Za-z0-9]+" title="A DMD address: letters and numbers only">
<button class="dmd-button" type="submit"><?= dmd_home_icon('wallet') ?>Open my wallet</button>
</div>
<p class="dmd-small">Your hashrate, workers, balance and payouts.</p>
</form>
</div>
</section>

<h2 class="visually-hidden">The pool right now</h2>
<ul class="dmd-stats">
<li class="dmd-stat"><span class="dmd-icon"><?= dmd_home_icon('bolt') ?></span>
<b><?= CHtml::encode($rate_value) ?> <small><?= CHtml::encode($rate_unit) ?></small></b><span class="dmd-label">Pool hashrate</span>
<span class="dmd-sub" title="Shared: mining with the pool (PPLNS). Solo: miners who keep their whole block."><?= $split_sub ?></span>
<?php if ($network_sub): ?><span class="dmd-sub"<?= $network_title ? ' title="' . CHtml::encode($network_title) . '"' : '' ?>><?= CHtml::encode($network_sub) ?></span><?php endif; ?></li>
<li class="dmd-stat"><span class="dmd-icon"><?= dmd_home_icon('cpu') ?></span>
<b><?= number_format($miners) ?></b><span class="dmd-label"><?= $miners == 1 ? 'Miner online' : 'Miners online' ?></span>
<span class="dmd-sub"><?= CHtml::encode($miners_sub) ?></span></li>
<li class="dmd-stat dmd-stat-ttf"><span class="dmd-icon"><?= dmd_home_icon('timer') ?></span>
<dl class="dmd-ttf">
<div title="Shared, with the pool (PPLNS): at the shared hashrate, solo miners left out"><dt>Shared</dt><dd><?= dmd_home_ttf($ttf_pool) ?></dd></div>
<div title="Solo: all solo miners together. Your own time, mining solo, is on your wallet page."><dt>Solo</dt><dd><?= dmd_home_ttf($ttf_solo) ?></dd></div>
</dl><span class="dmd-label">Time to find a block</span></li>
<li class="dmd-stat"><span class="dmd-icon"><?= dmd_home_icon('share') ?></span>
<b>PPLNS <small>+ solo</small></b><span class="dmd-label">Payout scheme</span></li>
</ul>

<div class="dmd-two">
<section class="dmd-card dmd-settings" aria-labelledby="dmd-settings-title">
<div class="dmd-card-head"><h2 id="dmd-settings-title"><?= dmd_home_icon('server') ?>Pool settings</h2></div>
<dl class="dmd-kv">
<div><dt>Pool URL</dt><dd class="dmd-stratum"><code id="dmd-stratum"><?= CHtml::encode($stratum) ?></code>
<button class="dmd-copy" type="button" data-copy="<?= CHtml::encode($stratum) ?>"><?= dmd_home_icon('copy') ?><span>Copy</span></button></dd></div>
<div><dt>Username</dt><dd>Your DMD address, optionally followed by a dot and a worker name, such as <code>.S19</code></dd></div>
<div><dt>Password</dt><dd>Just: <code>x</code>, or a starting difficulty such as <code>d=65536</code>. To mine solo, add
<code>m=solo</code>, such as <code>m=solo</code> or <code>d=65536,m=solo</code></dd></div>
<div><dt>Fees</dt><dd><?= CHtml::encode($fee_pool) ?> with the pool, <?= CHtml::encode($fee_solo) ?> solo</dd></div>
</dl>
<p class="dmd-small">Some miners want <code>stratum+tcp://</code> in front of the pool URL. Hashrate
rented from NiceHash starts automatically at NiceHash's required difficulty.</p>
<p class="dmd-small" style="margin-top:10px"><a class="dmd-more" href="https://debitmydata.io/#mining" target="_blank" rel="noopener">Step-by-step mining guide <?= dmd_home_icon('out') ?></a></p>
</section>

<section class="dmd-card dmd-chart" aria-labelledby="dmd-chart-title">
<div class="dmd-card-head"><h2 id="dmd-chart-title"><?= dmd_home_icon('chart') ?>Pool hashrate, 24 hours</h2>
<span class="dmd-chart-now" id="dmd-chart-now" hidden></span></div>
<div id="pool_hashrate_sha256_results"><p class="dmd-empty">Loading the chart…</p></div>
</section>
</div>

<section class="dmd-card dmd-blocks" aria-labelledby="dmd-blocks-title">
<div class="dmd-card-head"><h2 id="dmd-blocks-title"><?= dmd_home_icon('cube') ?>Latest blocks found</h2>
<?php if ($notify_pool): ?><button class="dmd-notify-pool" type="button" data-dmd-notify-pool aria-pressed="false" title="Get a notice for every block the pool finds"><?= dmd_home_icon('bell') ?><span>Notify me</span></button>
<?php endif; ?><a class="dmd-more" href="<?= CHtml::encode($explorer_url) ?>" target="_blank" rel="noopener">All blocks in the explorer <?= dmd_home_icon('out') ?></a></div>
<div id="found_results" aria-live="polite"><p class="dmd-empty">Loading the blocks…</p></div>
</section>

</div>

<script>
var global_algo = 'sha256';

// auto_refresh.js calls this every 30 seconds.
function page_refresh()
{
    found_refresh();
    pool_hashrate_sha256_refresh();
}

function found_refresh()
{
    $.get('/site/found_results', '', function (data) { $('#found_results').html(data); });
}

function pool_hashrate_sha256_refresh()
{
    $.get('/site/graph_hashrate_sha256_results', '', dmd_chart_update);
}

// "88.9 TH/s" from hashes per second.
function dmd_rate(h)
{
    var units = ['H/s', 'kH/s', 'MH/s', 'GH/s', 'TH/s', 'PH/s', 'EH/s'];
    var i = 0;
    while (h >= 1000 && i < units.length - 1) { h /= 1000; i++; }
    return (h >= 100 ? h.toFixed(0) : h.toFixed(h >= 10 ? 1 : 2)) + ' ' + units[i];
}

// The chart's points: [["2026/11/10 03:30:00", MH/s], ...], first series of the answer.
var dmd_chart = null;
function dmd_chart_update(data)
{
    var box = document.getElementById('pool_hashrate_sha256_results');
    var points = [];
    try {
        var rows = (typeof data === 'string' ? JSON.parse(data) : data)[0] || [];
        for (var i = 0; i < rows.length; i++) {
            var t = new Date(String(rows[i][0]).replace(/-/g, '/')).getTime();
            var v = Number(rows[i][1]) * 1e6;
            if (!isNaN(t) && isFinite(v)) points.push([t, v]);
        }
    } catch (err) {
        points = [];
    }
    if (!points.length || typeof ApexCharts === 'undefined') {
        if (!dmd_chart) box.innerHTML = '<p class="dmd-empty">The chart is not available right now.</p>';
        return;
    }
    var now = document.getElementById('dmd-chart-now');
    now.innerHTML = 'Now <b>' + dmd_rate(points[points.length - 1][1]) + '</b>';
    now.hidden = false;
    if (dmd_chart) {
        dmd_chart.updateSeries([{ name: 'Pool hashrate', data: points }]);
        return;
    }
    box.innerHTML = '';
    var look = window.dmdChartTheme ? window.dmdChartTheme() : { fore: '#7d8797', grid: '#f0f1f4', tooltip: 'light' };
    dmd_chart = new ApexCharts(box, {
        series: [{ name: 'Pool hashrate', data: points }],
        chart: {
            type: 'area', height: 230, fontFamily: 'Inter, system-ui, sans-serif', foreColor: look.fore,
            toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false },
            parentHeightOffset: 0
        },
        colors: [look.red || '#e0112b'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 100] } },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        grid: { borderColor: look.grid, strokeDashArray: 4, padding: { left: 8, right: 8 } },
        xaxis: {
            type: 'datetime', labels: { datetimeUTC: false, format: 'HH:mm' },
            axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false }
        },
        yaxis: { tickAmount: 4, min: 0, labels: { formatter: dmd_rate } },
        tooltip: { x: { format: 'dd MMM HH:mm' }, y: { formatter: dmd_rate }, theme: look.tooltip },
        legend: { show: false }
    });
    dmd_chart.render();
    if (window.dmdCharts) window.dmdCharts.push(dmd_chart);
}

// Copy the pool URL.
$(document).on('click', '.dmd-copy', function () {
    var button = this;
    var text = button.getAttribute('data-copy');
    function done() {
        button.classList.add('done');
        button.querySelector('span').textContent = 'Copied';
        setTimeout(function () {
            button.classList.remove('done');
            button.querySelector('span').textContent = 'Copy';
        }, 1600);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () {});
    } else {
        var field = document.createElement('textarea');
        field.value = text;
        document.body.appendChild(field);
        field.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        document.body.removeChild(field);
    }
});

// The page reloads every 15 minutes for fresh figures, unless an address is being typed.
setInterval(function () {
    var field = document.getElementById('dmd-address');
    if (field.value === '' && document.activeElement !== field) location.reload();
}, 15 * 60 * 1000);
</script>
