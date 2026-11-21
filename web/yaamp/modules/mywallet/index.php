<?php
/*
 * The wallet page of one DMD address, in the DebitMyData look of the home page: the address, the
 * address's figures, its hashrate over 12 hours, the network, its rigs and the blocks it was paid
 * in. The figures, the chart and the blocks refresh every 30 seconds (auto_refresh.js calls
 * page_refresh()); the rigs load with the page (each load pings every rig) and reload when their
 * 10/20/50 selector changes.
 *
 * The next balance payout (top right) and the link to the matured rewards show only while the address
 * still has money waiting for a balance payout (yaamp_user_payout_waiting(): earnings from the
 * PROP blocks before PPLNS, or a PPLNS part too small for the block). Rewards paid in the block
 * show in "Blocks you were paid in".
 *
 * Parts (loaded by the scripts at the end):
 *   /site/wallet_miners_results1  your hashrate, share of the pool, time to find a block, rigs
 *   /site/wallet_results          unconfirmed, balance, paid in total
 *   /site/wallet_graphs_results   one chart box per algorithm; /site/graph_user_results its points
 *   /site/wallet_miners_results2  network hashrate, pool hashrate, network difficulty
 *   /site/user_earning_results2   the blocks this address was paid in
 *   $workersContent               the rigs (mywallet/workers_wallet_results.php, from the controller)
 */

JavascriptFile('/yaamp/ui/js/auto_refresh.js');

$address = getparam('address');
if (!empty($address) && preg_match('/[^A-Za-z0-9]/', $address)) {
	die;
}

$user = getuserparam($address);
if ($user) {
	user()->setState('yaamp-wallet', $user->username);

	// the admin pages show where an address was first opened from
	if (empty($user->hostaddr) && !$this->admin) {
		$user->hostaddr = $_SERVER['REMOTE_ADDR'];
		$user->save();
	}
}

function dmd_wallet_icon($name)
{
	$paths = array(
		'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><path d="M16 14h.01"/>',
		'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
		'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
		'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'cube' => '<path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/>',
		'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
		'out' => '<path d="M7 17L17 7M9 7h8v8"/>',
		'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'forget' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
		'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
		. 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

$lookup = function ($label, $button) {
	return '<form class="dmd-lookup" action="/mywallet/" method="get" role="search" data-dmd-lookup>'
		. '<label for="dmd-address" class="visually-hidden">' . $label . '</label><div class="dmd-lookup-row">'
		. '<input class="dmd-input" id="dmd-address" name="address" type="text" placeholder="' . $label . '" autocomplete="off" '
		. 'autocapitalize="off" spellcheck="false" required pattern="[A-Za-z0-9]+" title="A DMD address: letters and numbers only">'
		. '<button class="dmd-button" type="submit">' . dmd_wallet_icon('wallet') . $button . '</button></div></form>';
};

// An address typed in this page's form and opened (not reached through a link): its wallet page
// may move this device's notifications to it (see "Notifications" below). The tab remembers the
// typed address for 2 minutes, and the next wallet page reads it once.
$lookup_js = <<<'JS'
document.addEventListener('submit', function (event) {
	var form = event.target;
	if (!form || !form.hasAttribute || !form.hasAttribute('data-dmd-lookup') || !form.elements.address) return;
	try {
		sessionStorage.setItem('dmd-wallet-typed', JSON.stringify({ a: form.elements.address.value.trim().toLowerCase(), t: Date.now() }));
	} catch (err) {}
});
JS;

if (!$user) {
	$notfound = empty($address)
		? 'Enter the DMD address you mine to: your hashrate, rigs, balance and payouts show here.'
		: 'The pool has no shares from this address yet. Check the address, or try again a few minutes after your miner starts.';
	echo '<div class="dmd-wallet-intro"><p class="dmd-eyebrow">Your mining</p><h1>Check your <span class="dmd-accent">wallet</span></h1>'
		. '<p>' . $notfound . '</p>' . $lookup('Your DMD address', 'Open my wallet') . '</div>';
	// nothing to refresh
	echo "<script>\nfunction page_refresh() {}\n" . $lookup_js . "</script>\n";
	return;
}

$username = $user->username;
$coin = getdbo('db_coins', $user->coinid);
$payout_waiting = yaamp_user_payout_waiting($user);
if ($payout_waiting) {
	$mining = getdbosql('db_mining');
	$payout_at = $mining->last_payout + YAAMP_PAYMENTS_FREQ;
	$payout_eta = max(0, $payout_at - time());
}
$address_html = CHtml::encode($username);
$address_short = CHtml::encode(strlen($username) > 22 ? substr($username, 0, 10) . '…' . substr($username, -8) : $username);
$address_js = json_encode($username, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$coin_image = $coin && $coin->image ? CHtml::encode($coin->image) : '';

// Notifications (part 3): only once the pool has its keys (configuration/push.php) and the table.
require_once('/home/crypto-data/yiimp/site/web/yaamp/core/backend/push.php');
$push_key = (push_config() && push_table()) ? PUSH_VAPID_PUBLIC : '';
?>
<div class="dmd-home dmd-wallet">

<section class="dmd-wallet-head" aria-labelledby="dmd-wallet-title">
<div class="dmd-wallet-who">
<p class="dmd-eyebrow">Your mining</p>
<h1 id="dmd-wallet-title">Your <span class="dmd-accent">wallet</span></h1>
<div class="dmd-wallet-address">
<code title="<?= $address_html ?>"><span class="dmd-addr-full"><?= $address_html ?></span><span class="dmd-addr-short"><?= $address_short ?></span></code>
<button class="dmd-copy" type="button" data-copy="<?= $address_html ?>" title="Copy the address"><?= dmd_wallet_icon('copy') ?><span>Copy</span></button>
<button class="dmd-forget" type="button" id="dmd-forget" title="Forget this address on this device"><?= dmd_wallet_icon('forget') ?><span>Forget</span></button>
</div>
<?php if ($push_key): ?>
<div class="dmd-follow-note" id="dmd-follow-note" role="status" hidden>
<span class="dmd-icon"><?= dmd_wallet_icon('bell') ?></span>
<p>Notifications on this device are for <a id="dmd-follow-other" href="/mywallet/"></a>, not for this address.</p>
<button class="dmd-button dmd-ghost dmd-sm" type="button" id="dmd-follow-switch"><?= dmd_wallet_icon('bell') ?>Switch them to this address</button>
</div>
<?php endif; ?>
</div>
<div class="dmd-wallet-side">
<?php if ($payout_waiting): ?>
<div class="dmd-wallet-payout" title="Your balance is paid at <?= CHtml::encode(date('H:i T', $payout_at)) ?>, once it is over the minimum payout. Rewards paid in a block arrive with the block.">
<span class="dmd-icon"><?= dmd_wallet_icon('clock') ?></span>
<span><span class="dmd-label">Next balance payout</span><b id="dmd-payout" data-eta="<?= (int) $payout_eta ?>">—</b></span>
</div>
<?php endif; ?>
<?= $lookup('Check another address', 'Open') ?>
</div>
</section>

<h2 class="visually-hidden">Your figures</h2>
<div id="main_miners_results1" aria-live="polite"><ul class="dmd-stats dmd-loading"><li class="dmd-stat"><span class="dmd-label">Loading…</span></li></ul></div>
<div id="main_wallet_results" aria-live="polite"></div>

<div class="dmd-two">
<section class="dmd-card dmd-chart" aria-labelledby="dmd-wchart-title">
<div class="dmd-card-head"><h2 id="dmd-wchart-title"><?= dmd_wallet_icon('chart') ?>Your hashrate, 12 hours</h2>
<span class="dmd-chart-now" id="dmd-wchart-now" hidden></span></div>
<div id="main_graphs_results"><p class="dmd-empty">Loading the chart…</p></div>
</section>
<section class="dmd-card" aria-labelledby="dmd-network-title">
<div class="dmd-card-head"><h2 id="dmd-network-title"><?= dmd_wallet_icon('globe') ?>The network</h2></div>
<div id="main_miners_results2" style="margin-top:35px"><p class="dmd-empty">Loading…</p></div>
</section>
</div>

<?= $workersContent ?>

<section class="dmd-card dmd-blocks" aria-labelledby="dmd-mined-title">
<div class="dmd-card-head"><h2 id="dmd-mined-title"><?= dmd_wallet_icon('cube') ?>Blocks you were paid in</h2></div>
<div id="found_results" aria-live="polite"><p class="dmd-empty">Loading the blocks…</p></div>
</section>

<?php if ($payout_waiting): ?>
<section class="dmd-card dmd-wallet-payouts" aria-labelledby="dmd-payouts-title">
<div class="dmd-card-head"><h2 id="dmd-payouts-title"><?= dmd_wallet_icon('send') ?>Matured rewards</h2></div>
<p>Your share of each block, once the block has matured and the coins can be spent (they are paid to your address inside the block), and any balance payouts with their transaction.</p>
<a class="dmd-button" href="/site/tx?address=<?= $address_html ?>"><?= dmd_wallet_icon('send') ?>See your matured rewards</a>
</section>
<?php endif; ?>

<?php if ($push_key): ?>
<section class="dmd-card dmd-notify" id="dmd-notify" aria-labelledby="dmd-notify-title">
<div class="dmd-card-head"><h2 id="dmd-notify-title"><?= dmd_wallet_icon('bell') ?>Notifications</h2>
<span class="dmd-badge orphan" id="dmd-notify-state">Off</span></div>
<p>A notice on this device as soon as something happens to this address, even when the app or the browser is closed. A newer notice of the same kind replaces the older one.</p>
<div class="dmd-switches">
<label class="dmd-switch"><input type="checkbox" value="3" checked><span><b>My rewards</b><small>When a block pays you (a solo block at once), and when that reward has matured and can be spent. Small payouts from the balance too.</small></span></label>
<label class="dmd-switch"><input type="checkbox" value="4"><span><b>Every block the pool finds</b><small>Off unless you want them all.</small></span></label>
</div>
<div class="dmd-notify-actions">
<button class="dmd-button" type="button" id="dmd-notify-on"><?= dmd_wallet_icon('bell') ?>Turn on notifications</button>
<button class="dmd-notify-off" type="button" id="dmd-notify-test" hidden>Send a test notice</button>
<button class="dmd-notify-off" type="button" id="dmd-notify-off" hidden>Turn off on this device</button>
</div>
<p class="dmd-small" id="dmd-notify-msg" aria-live="polite"></p>
</section>
<?php endif; ?>

</div>

<script>
var dmd_address = <?= $address_js ?>;
var dmd_addr_url = encodeURIComponent(dmd_address);

<?= $lookup_js ?>
// this page was opened from the address form (typed), not through a link
var dmd_typed = false;
try {
	var dmd_t = JSON.parse(sessionStorage.getItem('dmd-wallet-typed') || 'null');
	sessionStorage.removeItem('dmd-wallet-typed');
	dmd_typed = !!dmd_t && dmd_t.a === String(dmd_address).toLowerCase() && Date.now() - dmd_t.t < 120000;
} catch (err) {}

<?php if ($coin_image): ?>
// the coin's logo as the tab icon
$(function () {
	$('link[rel~="icon"]').remove();
	$('head').append('<link rel="icon" href="<?= $coin_image ?>">');
});
<?php endif; ?>

// auto_refresh.js calls this every 30 seconds.
function page_refresh()
{
	$.get('/site/wallet_miners_results1?address=' + dmd_addr_url, '', function (data) { $('#main_miners_results1').html(data); });
	$.get('/site/wallet_results?address=' + dmd_addr_url, '', function (data) { $('#main_wallet_results').html(data); });
	$.get('/site/wallet_miners_results2?address=' + dmd_addr_url, '', function (data) { $('#main_miners_results2').html(data); });
	$.get('/site/user_earning_results2?address=' + dmd_addr_url + '&count=' + (window.dmd_blocks_count || 5), '', function (data) { $('#found_results').html(data); });
	dmd_graphs_refresh();
}

// "88.9 TH/s" from hashes per second.
function dmd_rate(h)
{
	var units = ['H/s', 'kH/s', 'MH/s', 'GH/s', 'TH/s', 'PH/s', 'EH/s'];
	var i = 0;
	h = Number(h) || 0;
	while (h >= 1000 && i < units.length - 1) { h /= 1000; i++; }
	return (h >= 100 ? h.toFixed(0) : h.toFixed(h >= 10 ? 1 : 2)) + ' ' + units[i];
}

// The hashrate chart: one box per algorithm (normally only SHA-256), redrawn every 5 minutes.
var dmd_graphs = {};
var dmd_graphs_at = 0;
function dmd_graphs_refresh()
{
	var now = Date.now();
	if (now < dmd_graphs_at + 5 * 60 * 1000) return;
	dmd_graphs_at = now;
	$.get('/site/wallet_graphs_results?address=' + dmd_addr_url, '', function (data) {
		var box = document.getElementById('main_graphs_results');
		var algos = [];
		$('<div>').html(data).find('.graph_algo').each(function () { algos.push(this.id); });
		if (!algos.length) {
			box.innerHTML = '<p class="dmd-empty">No hashrate in the last hours.</p>';
			dmd_graphs = {};
			return;
		}
		if (!box.querySelector('.dmd-wchart')) {
			box.innerHTML = algos.map(function (a) { return '<div class="dmd-wchart" id="graph_results_' + a + '"></div>'; }).join('');
		}
		algos.forEach(function (algo) {
			$.get('/site/graph_user_results?address=' + dmd_addr_url + '&algo=' + encodeURIComponent(algo), '', function (points) {
				dmd_graph_draw(algo, points, algos.length > 1);
			});
		});
	});
}

// Points (site/results/graph_user_results.php), every 15 minutes:
// [[accepted: ["2026/11/12 03:30:00", H/s], ...], [their average, ...], [rejected, ...]]
function dmd_graph_draw(algo, data, named)
{
	var series = [[], [], []];
	try {
		var lists = typeof data === 'string' ? JSON.parse(data) : data;
		for (var s = 0; s < 3; s++) {
			(lists[s] || []).forEach(function (row) {
				var t = new Date(String(row[0]).replace(/-/g, '/')).getTime();
				var v = Number(row[1]);
				if (!isNaN(t) && isFinite(v)) series[s].push([t, v]);
			});
		}
	} catch (err) {
		series = [[], [], []];
	}
	var box = document.getElementById('graph_results_' + algo);
	if (!box) return;
	if (!series[0].length || typeof ApexCharts === 'undefined') {
		box.innerHTML = '<p class="dmd-empty">The chart is not available right now.</p>';
		return;
	}
	var last = series[0][series[0].length - 1][1];
	var now = document.getElementById('dmd-wchart-now');
	now.innerHTML = 'Now <b>' + dmd_rate(last) + '</b>';
	now.hidden = false;
	// the rejected line only when some shares were rejected
	var rejected = series[2].some(function (p) { return p[1] > 0; });
	var list = [{ name: 'Accepted', data: series[0] }, { name: 'Average', data: series[1] }];
	if (rejected) list.push({ name: 'Rejected', data: series[2] });
	// the rejected line came or went: the chart is drawn again with its colours in order
	if (dmd_graphs[algo] && dmd_graphs[algo].dmd_lines !== list.length) {
		var old = dmd_graphs[algo];
		if (window.dmdCharts) window.dmdCharts = window.dmdCharts.filter(function (c) { return c !== old; });
		old.destroy();
		delete dmd_graphs[algo];
	}
	if (dmd_graphs[algo]) {
		dmd_graphs[algo].updateSeries(list);
		return;
	}
	box.innerHTML = '';
	var look = window.dmdChartTheme ? window.dmdChartTheme() : { fore: '#7d8797', grid: '#f0f1f4', tooltip: 'light' };
	var options = {
		series: list,
		chart: {
			type: 'area', height: 230, fontFamily: 'Inter, system-ui, sans-serif', foreColor: look.fore,
			toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, parentHeightOffset: 0
		},
		colors: [look.red || '#e0112b', '#9aa2ae', '#e08a00'],
		stroke: { curve: 'smooth', width: [2, 1.5, 1.5], dashArray: [0, 4, 0] },
		fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: [0.28, 0, 0], opacityTo: [0.02, 0, 0], stops: [0, 100] } },
		dataLabels: { enabled: false },
		markers: { size: 0, hover: { size: 5 } },
		grid: { borderColor: look.grid, strokeDashArray: 4, padding: { left: 8, right: 8 } },
		xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'HH:mm' }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
		yaxis: { tickAmount: 4, min: 0, labels: { formatter: dmd_rate } },
		tooltip: { x: { format: 'dd MMM HH:mm' }, y: { formatter: dmd_rate }, theme: look.tooltip },
		legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '12px' }
	};
	// more than one algorithm: each chart says which
	if (named) options.title = { text: algo, style: { fontSize: '13px', fontWeight: 700 } };
	var chart = new ApexCharts(box, options);
	chart.dmd_lines = list.length;
	chart.render();
	dmd_graphs[algo] = chart;
	if (window.dmdCharts) window.dmdCharts.push(chart);
}

// The next balance payout (only while money waits for it), counted down every second; the page
// reloads once it has been paid, and the box goes when nothing waits any more.
(function () {
	var el = document.getElementById('dmd-payout');
	if (!el) return;
	var end = Date.now() + Number(el.getAttribute('data-eta')) * 1000;
	var reloading = false;
	function show() {
		var left = Math.round((end - Date.now()) / 1000);
		if (left <= 0) {
			el.textContent = 'Paying…';
			if (!reloading) { reloading = true; setTimeout(function () { location.reload(); }, 60000); }
		} else {
			var m = Math.floor(left / 60), s = left % 60;
			el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
		}
	}
	show();
	setInterval(show, 1000);
})();

<?php if ($push_key): ?>
// Notifications: this device follows this address (yaamp/core/backend/push.php sends them).
(function () {
	var KEY = <?= json_encode($push_key) ?>;
	var box = document.getElementById('dmd-notify');
	var state = document.getElementById('dmd-notify-state'), msg = document.getElementById('dmd-notify-msg');
	var on = document.getElementById('dmd-notify-on'), off = document.getElementById('dmd-notify-off'), test = document.getElementById('dmd-notify-test');
	var boxes = Array.prototype.slice.call(box.querySelectorAll('.dmd-switch input'));
	var note = document.getElementById('dmd-follow-note'), noteLink = document.getElementById('dmd-follow-other'), noteBtn = document.getElementById('dmd-follow-switch');
	var iphone = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	var inApp = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
	var sub = null;

	function wants() { return boxes.reduce(function (w, b) { return w | (b.checked ? Number(b.value) : 0); }, 0); }
	function tell(isOn, address) { document.dispatchEvent(new CustomEvent('dmd-notify-change', { detail: { on: isOn, address: address } })); }
	// "dm1qjufnnh…6dc5x5pk6ym3q": the start and the end of an address, as at the top of the page
	function shortAddr(a) { return a.length > 22 ? a.slice(0, 10) + '…' + a.slice(-8) : a; }
	function linkTo(a) {
		var el = document.createElement('a');
		el.href = '/mywallet/?address=' + encodeURIComponent(a);
		el.textContent = shortAddr(a);
		el.title = a;
		return el;
	}
	function say(parts) {
		msg.textContent = '';
		parts.forEach(function (x) { msg.appendChild(typeof x === 'string' ? document.createTextNode(x) : x); });
	}
	// this device follows another address: told at the top (with a link to that address's page and
	// a button to switch here) and in this box
	function other(a) {
		noteLink.href = '/mywallet/?address=' + encodeURIComponent(a);
		noteLink.textContent = shortAddr(a);
		noteLink.title = a;
		note.hidden = false;
		say(['This device gets the notifications of ', linkTo(a), '. Turn them on here to follow this address instead.']);
	}
	function show(isOn, text) {
		note.hidden = true;
		state.textContent = isOn ? 'On' : 'Off';
		state.className = 'dmd-badge ' + (isOn ? 'done' : 'orphan');
		on.hidden = isOn;
		off.hidden = !isOn;
		test.hidden = !isOn;
		msg.textContent = text || '';
	}
	function post(data) {
		return fetch('/mywallet/push/json', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'The pool did not answer.'); return j; }); });
	}
	function key() {
		var s = KEY.replace(/-/g, '+').replace(/_/g, '/'); s += '==='.slice((s.length + 3) % 4);
		var raw = atob(s), out = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
		return out;
	}
	// a subscription made with another key of the pool (the key was replaced) is made again
	function sameKey(s) {
		var k = s.options && s.options.applicationServerKey;
		if (!k) return true;
		var a = new Uint8Array(k), b = key();
		if (a.length !== b.length) return false;
		for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
		return true;
	}
	function fail(err) { msg.textContent = (err && err.message) || 'Something went wrong. Try again in a moment.'; }

	if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
		on.hidden = true;
		if (iphone && !inApp) msg.innerHTML = 'On an iPhone, notifications come through the app: <a href="#get-the-app">add it to your Home Screen</a>, open it, and turn them on there.';
		else msg.textContent = 'This browser cannot show notifications.';
		return;
	}
	if (Notification.permission === 'denied') msg.textContent = 'Notifications are blocked for this site in your browser settings.';

	// this device's notifications: when the page opens, and when the bell changes them.
	// A device that turned them on from the bell or the home page (the pool's blocks, no address)
	// follows this address too as soon as its wallet page opens, with every kind of notice on: the
	// visitor already said yes to notifications on this device.
	var join = true;	// only when the page opens, not after a choice made on this page
	function refresh() {
		return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (s) {
			sub = s;
			var blocked = Notification.permission === 'denied' ? 'Notifications are blocked for this site in your browser settings.' : '';
			var first = join;
			join = false;
			if (!s) { show(false, blocked); return; }
			return post({ action: 'get', endpoint: s.endpoint }).then(function (r) {
				if (!r.on) { show(false, blocked); return; }
				if (!r.address) {
					boxes.forEach(function (b) { b.checked = true; });
					if (first && Notification.permission === 'granted') {
						return post({ action: 'subscribe', address: dmd_address, subscription: s.toJSON(), wants: wants() }).then(function () {
							show(true, 'On for this address too, as notifications are on for this device. Choose what you get above.');
							tell(true, dmd_address);
						});
					}
					show(false, 'This device gets a notice for every block the pool finds. Turn on notifications here to also follow this address.');
					return;
				}
				boxes.forEach(function (b) { b.checked = (r.wants & Number(b.value)) > 0; });
				if (r.address === dmd_address) { show(true); return; }
				// another address. Typed in the address form and opened: the notifications move
				// here, with the same choices. Reached through a link: they stay with the other one.
				if (first && dmd_typed && Notification.permission === 'granted') {
					dmd_typed = false;
					var before = r.address;
					return post({ action: 'subscribe', address: dmd_address, subscription: s.toJSON(), wants: r.wants || wants() }).then(function () {
						show(true);
						say(['Notifications on this device now follow this address, instead of ', linkTo(before), '.']);
						tell(true, dmd_address);
					}, function () { show(false); other(before); });
				}
				show(false);
				other(r.address);
			});
		}).catch(function () {});
	}
	refresh();
	document.addEventListener('dmd-notify-change', function (event) { if (event.detail && event.detail.from === 'bell') refresh(); });

	// For "Forget": does this device get the notifications of this address? (asked again, the page
	// may be old) -> {s, wants} or null. Then drop them: a device that also gets every block the
	// pool finds keeps those (without an address); otherwise its notifications are turned off.
	window.dmdNotifyFollowing = function () {
		return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (s) {
			if (!s) return null;
			return post({ action: 'get', endpoint: s.endpoint }).then(function (r) {
				return r.on && r.address === dmd_address ? { s: s, wants: r.wants } : null;
			});
		}).catch(function () { return null; });
	};
	window.dmdNotifyDrop = function (f) {
		return post({ action: 'unsubscribe', endpoint: f.s.endpoint }).then(function () {
			if (f.wants & 4) return post({ action: 'subscribe', address: '', subscription: f.s.toJSON() });
			return f.s.unsubscribe();
		}).then(function () { tell((f.wants & 4) > 0, null); });
	};

	function turnOn(doneText) {
		if (!wants()) { msg.textContent = 'Choose at least one kind of notice.'; return; }
		on.disabled = noteBtn.disabled = true;
		msg.textContent = 'Turning on…';
		Notification.requestPermission().then(function (p) {
			if (p !== 'granted') throw new Error(p === 'denied' ? 'Notifications are blocked for this site in your browser settings.' : 'Notifications were not allowed.');
			return navigator.serviceWorker.ready;
		}).then(function (reg) {
			return reg.pushManager.getSubscription().then(function (s) {
				if (s && !sameKey(s)) return s.unsubscribe().then(function () { return null; });
				return s;
			}).then(function (s) { return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key() }); });
		}).then(function (s) {
			sub = s;
			return post({ action: 'subscribe', address: dmd_address, subscription: s.toJSON(), wants: wants() });
		}).then(function () {
			show(true, doneText);
			tell(true, dmd_address);
		}).catch(fail).then(function () { on.disabled = noteBtn.disabled = false; });
	}
	on.addEventListener('click', function () { turnOn('Done. You will get a notice on this device.'); });
	noteBtn.addEventListener('click', function () { turnOn('Switched: notifications on this device now follow this address.'); });

	test.addEventListener('click', function () {
		if (!sub) return;
		test.disabled = true;
		post({ action: 'test', endpoint: sub.endpoint }).then(function () {
			msg.textContent = 'A test notice is on its way: it should arrive within a few seconds.';
		}).catch(fail).then(function () { setTimeout(function () { test.disabled = false; }, 10000); });
	});

	off.addEventListener('click', function () {
		if (!sub) { show(false); return; }
		var endpoint = sub.endpoint;
		post({ action: 'unsubscribe', endpoint: endpoint }).then(function () { return sub.unsubscribe(); })
			.then(function () { sub = null; show(false, 'Notifications are off on this device.'); tell(false, null); }).catch(fail);
	});

	boxes.forEach(function (b) {
		b.addEventListener('change', function () {
			if (on.hidden === false || !sub) return;	// not on yet: the choice is sent when turning on
			if (!wants()) { off.click(); return; }
			post({ action: 'update', endpoint: sub.endpoint, wants: wants() }).then(function () { msg.textContent = 'Saved.'; }).catch(fail);
		});
	});
})();
<?php endif; ?>

// Forget: this browser stops remembering this address (the "My wallet" link, the bell), and this
// device stops getting its notifications. Then the home page.
(function () {
	var button = document.getElementById('dmd-forget');
	button.addEventListener('click', function () {
		var ask = window.dmdNotifyFollowing ? window.dmdNotifyFollowing() : Promise.resolve(null);
		button.disabled = true;
		ask.then(function (f) {
			var text = 'Forget this address on this device?\n\nThis browser stops remembering it'
				+ (f ? ', and this device stops getting its notifications' + ((f.wants & 4) ? ' (it keeps the notices of every block the pool finds)' : '') : '')
				+ '. You can open it again any time.';
			if (!window.confirm(text)) { button.disabled = false; return; }
			return (f ? window.dmdNotifyDrop(f) : Promise.resolve()).catch(function () {}).then(function () {
				return fetch('/mywallet/forget/json', { method: 'POST', credentials: 'same-origin' });
			}).then(function () {
				try { sessionStorage.removeItem('dmd-wallet-typed'); } catch (err) {}
				location.href = '/';
			}, function () { button.disabled = false; });
		});
	});
})();

// Copy the address.
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
</script>
