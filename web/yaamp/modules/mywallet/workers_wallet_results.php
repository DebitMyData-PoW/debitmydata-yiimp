<?php
/*
 * Wallet page: the address's rigs, one row each (on a phone, one card each): hashrate and its
 * part of the address's hashrate, difficulty, shares, rejected shares, blocks, miner software,
 * password, and the proxy the rig connects through with its ping.
 *
 * Included by MywalletController: once with the page (inside a card), and again by
 * actionWorkers_wallet_results when the 10/20/50 selector changes ($workers_wallet_ajax set):
 * then only the list is sent, and the page puts it in place of the old one.
 */

$address = getparam('address');
if (empty($address)) return;

// Set by MywalletController::actionWorkers_wallet_results when the selector reloads the list.
$workers_ajax = !empty($workers_wallet_ajax);

$count = isset($_GET['count']) && is_numeric($_GET['count']) ? (int)$_GET['count'] : 10;
$count = max(1, min($count, 200));

function get_ip_location_cached($ip) {
	static $cache = [];

	if (isset($cache[$ip])) return $cache[$ip];

	$url = "http://ip-api.com/json/" . urlencode($ip);
	$response = @file_get_contents($url);

	if ($response) {
		$data = json_decode($response, true);
		if ($data && $data['status'] == 'success') {
			// the answer comes over plain http: shown as text, never as HTML
			$location = CHtml::encode($data['city'] . ', ' . $data['countryCode']);
			$cache[$ip] = $location;
			return $location;
		}
	}

	$cache[$ip] = 'Unknown';
	return 'Unknown';
}

// The DDoS-protection proxies miners connect through.
$x4b_proxy_locations = [
	'108.181.135.250' => 'Ashburn, VA 🇺🇸',
	'108.181.108.252' => 'Los Angeles, CA 🇺🇸',
	'108.181.126.6'   => 'Amsterdam, NL 🇳🇱',
	'108.181.67.47'   => 'London, UK 🇬🇧',
	'108.181.101.52'  => 'Dallas, TX 🇺🇸',
	'108.181.77.67'   => 'Tokyo, JP 🇯🇵',
	'108.181.136.175' => 'São Paulo, BR 🇧🇷',
	'108.181.65.53'   => 'Sydney, AU 🇦🇺',
	'108.181.140.52'  => 'Chicago, IL 🇺🇸',
];

function get_worker_ping($ip) {
	// rigs behind the same proxy share its IP: ping each IP once per page
	static $cache = [];
	if (array_key_exists($ip, $cache)) return $cache[$ip];

	$ping = shell_exec("ping -c 1 -W 1 ".escapeshellarg($ip)); // 1 ping attempt, 1s timeout
	$cache[$ip] = preg_match('/time=([\d\.]+) ms/', (string) $ping, $matches) ? floatval($matches[1]) : null;
	return $cache[$ip];
}

$workers = getdbolist('db_workers', "name=:address ORDER BY name LIMIT :count", array(':address'=>$address, ':count'=>$count));

$total_rate = 0.0;
foreach($workers as $worker)
	$total_rate += yaamp_worker_rate($worker->id);

if (!$workers_ajax) {
	echo '<section class="dmd-card dmd-rigs" aria-labelledby="dmd-rigs-title"><div class="dmd-card-head">'
		. '<h2 id="dmd-rigs-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
		. 'stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/>'
		. '<path d="M9 1v4M15 1v4M9 19v4M15 19v4M1 9h4M1 15h4M19 9h4M19 15h4"/></svg>Your rigs</h2></div>';
	// the selector and the list sit in their own block, which the selector refills
	echo "<div id='workers_wallet_results'>";
}

echo '<div class="dmd-list-tools"><label for="workers-count-select">Show</label><select id="workers-count-select" class="dmd-select">';
foreach (array(10, 20, 50, 100, 200) as $n)
	echo "<option value=\"$n\"".($count == $n ? ' selected' : '').">$n</option>";
echo '</select><span>rigs</span></div>';

if (!$workers) {
	echo '<p class="dmd-empty">No rig is connected with this address right now.</p>';
} else {
	echo '<table class="dmd-table dmd-rigs-table"><thead><tr>'
		. '<th scope="col">Rig</th><th scope="col" class="r">Hashrate</th><th scope="col" class="r">Share</th>'
		. '<th scope="col" class="r">Difficulty</th><th scope="col" class="r">Shares</th><th scope="col" class="r">Rejected</th>'
		. '<th scope="col" class="r">Blocks</th><th scope="col">Miner</th><th scope="col">Password</th>'
		. '<th scope="col">Proxy</th><th scope="col" class="r">Ping</th></tr></thead><tbody>';

	foreach($workers as $worker)
	{
		$user_rate = yaamp_worker_rate($worker->id);
		$percent = $total_rate ? (100.0 * $user_rate) / $total_rate : 0.0;
		$user_bad = yaamp_worker_rate_bad($worker->id);
		$pct_bad = ($user_rate+$user_bad)? round($user_bad*100/($user_rate+$user_bad), 1): 0;
		$user_rate_h = $user_rate ? Itoa2($user_rate).'H/s' : '—';

		$name = $worker->worker;
		if ($worker->userid) {
			$user = getdbo('db_accounts', $worker->userid);
			if ($user && empty($name)) $name = $user->login;
		}
		$name = $name === '' || $name === null ? '—' : $name;

		$difficulty = $worker->difficulty ? number_format($worker->difficulty, 0, '.', ',') : '—';

		$ping = get_worker_ping($worker->ip);
		$ping_display = $ping ? number_format($ping, 1).' ms' : '—';

		if (isset($x4b_proxy_locations[$worker->ip]))
			$location = CHtml::encode($x4b_proxy_locations[$worker->ip]);
		else
			$location = '🌍 '.get_ip_location_cached($worker->ip);

		// "cgminer/4.11.1;Antminer S19j Pro": the part after the last ";"
		$miner_version = $worker->version;
		if (strpos($miner_version, ';') !== false) {
			$parts = explode(';', $miner_version);
			$miner_version = end($parts);
		}

		$algo = getparam('algo');
		if (empty($algo)) $algo = $worker->algo;	// the worker's own algorithm when the page gives none

		$shares = dboscalar("SELECT COUNT(id) FROM shares WHERE workerid=:worker AND algo=:algo", array(
			':worker' => $worker->id, ':algo' => $algo));
		$worker_blocs = dboscalar("SELECT COUNT(id) FROM blocks WHERE workerid=:worker AND algo=:algo", array(
			':worker' => $worker->id, ':algo' => $algo));
		$user_blocs = dboscalar("SELECT COUNT(id) FROM blocks WHERE userid=:user AND algo=:algo", array(
			':user' => $worker->userid, ':algo' => $algo));

		// names, versions and passwords come cleaned by the stratum; encoded all the same
		echo '<tr>';
		echo '<td data-label="Rig" class="dmd-rig-name">'.CHtml::encode($name).'</td>';
		echo '<td data-label="Hashrate" class="r dmd-rig-rate" data-sort="'.(0 + $user_rate).'">'.CHtml::encode($user_rate_h).'</td>';
		echo '<td data-label="Share" class="r">'.number_format($percent, 1, '.', '').'%</td>';
		echo '<td data-label="Difficulty" class="r">'.$difficulty.'</td>';
		echo '<td data-label="Shares" class="r">'.number_format((int) $shares).'</td>';
		echo '<td data-label="Rejected" class="r">'.($pct_bad > 0 ? $pct_bad.'%' : '—').'</td>';
		echo '<td data-label="Blocks" class="r" title="Found by this rig / by this address">'.(int) $worker_blocs.' / '.(int) $user_blocs.'</td>';
		echo '<td data-label="Miner">'.CHtml::encode($miner_version).'</td>';
		echo '<td data-label="Password"><code>'.CHtml::encode($worker->password).'</code></td>';
		echo '<td data-label="Proxy" class="dmd-nowrap">'.$location.'</td>';
		echo '<td data-label="Ping" class="r">'.$ping_display.'</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

// a reload by the selector ends here; the page that asked keeps its script
if ($workers_ajax) return;

echo '</div></section>';

// written as JavaScript strings, so no character in the URL can break out of them
$js_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$address_js = json_encode((string) getparam('address'), $js_flags);
$algo_js = json_encode((string) getparam('algo'), $js_flags);

echo <<<END
<script>
function setupWorkersSelect() {
	const select = document.getElementById('workers-count-select');
	if (!select) return;
	select.addEventListener('change', function () {
		const box = document.getElementById('workers_wallet_results');
		const xhr = new XMLHttpRequest();
		xhr.open('GET', '/mywallet/workers_wallet_results?address=' + encodeURIComponent({$address_js})
			+ '&count=' + encodeURIComponent(this.value) + '&algo=' + encodeURIComponent({$algo_js}), true);
		box.classList.add('dmd-busy');
		xhr.onload = function () {
			box.classList.remove('dmd-busy');
			if (xhr.status === 200) {
				box.innerHTML = xhr.responseText;
				setupWorkersSelect();	// the new list has a new selector
			}
		};
		xhr.onerror = function () { box.classList.remove('dmd-busy'); };
		xhr.send();
	});
}
setupWorkersSelect();
</script>
END;
