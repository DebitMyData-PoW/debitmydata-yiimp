<?php
/*
 * Wallet page: the address's hashrate, its share of the pool, the time to find a block and its
 * rigs online, as figures like the home page's. Shares mined solo (m=solo) are left out of the
 * pool share on both sides; an address that only mines solo sees "Solo" and its own time to
 * find a block. Refreshed every 30 seconds by mywallet/index.php.
 */

$user = getuserparam(getparam('address'));
if(!$user) return;

$userid = intval($user->id);
$coinid = intval($user->coinid);
$coin = $coinid ? getdbo('db_coins', $coinid) : null;

if (!function_exists('dmd_stat_icon')) {
function dmd_stat_icon($name)
{
	$paths = array(
		'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		'pie' => '<path d="M21 12A9 9 0 1 1 12 3v9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
		'cpu' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v4M15 1v4M9 19v4M15 19v4M1 9h4M1 15h4M19 9h4M19 15h4"/>',
		'coins' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
		'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><path d="M16 14h.01"/>',
		'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
	);
	return '<span class="dmd-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
		. 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg></span>';
}
}

// "1.52 <small>PH/s</small>" from hashes per second
if (!function_exists('dmd_stat_rate')) {
function dmd_stat_rate($rate)
{
	if (!$rate) return '0 <small>H/s</small>';
	if (preg_match('/^\s*([0-9.,]+)\s*([A-Za-z]*)\s*$/', Itoa2($rate), $m))
		return CHtml::encode($m[1]) . ' <small>' . CHtml::encode($m[2]) . 'H/s</small>';
	return CHtml::encode(Itoa2($rate)) . ' <small>H/s</small>';
}
}

$algo = $coin ? $coin->algo : user()->getState('yaamp-algo');

$user_rate = yaamp_user_rate($userid, $algo);
$user_rate_bad = yaamp_user_rate_bad($userid, $algo);
$percent_bad = ($user_rate + $user_rate_bad) ? $user_rate_bad * 100 / ($user_rate + $user_rate_bad) : 0;
$rigs = getdbocount('db_workers', "userid=$userid AND algo=:algo", array(':algo' => $algo));

$share = null;		// percent of the pool's shares (solo left out on both sides)
$solo_only = false;
$solo_rate = 0;
$ttf = '';
$ttf_title = '';

if ($coin) {
	$user_shares = controller()->memcache->get_database_scalar("wallet_user_shares-$algo-$coinid-$userid",
		"SELECT SUM(difficulty) FROM shares WHERE valid AND userid=$userid AND coinid=$coinid AND algo=:algo", array(':algo' => $algo));
	$total_shares = controller()->memcache->get_database_scalar("wallet_coin_shares-$coinid",
		"SELECT SUM(difficulty) FROM shares WHERE valid AND coinid=$coinid AND algo=:algo", array(':algo' => $algo));

	$user_solo_shares = 0;
	if (yaamp_has_solo_shares()) {
		$user_solo_shares = controller()->memcache->get_database_scalar("wallet_user_solo_shares-$algo-$coinid-$userid",
			"SELECT SUM(difficulty) FROM shares WHERE valid AND solo=1 AND userid=$userid AND coinid=$coinid AND algo=:algo", array(':algo' => $algo));
		$solo_total = controller()->memcache->get_database_scalar("wallet_coin_solo_shares-$coinid",
			"SELECT SUM(difficulty) FROM shares WHERE valid AND solo=1 AND coinid=$coinid AND algo=:algo", array(':algo' => $algo));
		$user_shares = max(0, $user_shares - $user_solo_shares);
		$total_shares = max(0, $total_shares - $solo_total);
		$solo_rate = yaamp_user_rate_solo($userid, $coinid);
	}

	$solo_only = $user_solo_shares > 0 && $user_shares <= 0;
	if ($total_shares > 0) $share = $user_shares * 100 / $total_shares;
	else if ($user_shares <= 0) $share = 0;

	// time to find a block: the pool's, without solo miners; or the solo miner's own
	if ($solo_only && $solo_rate > 0) {
		$seconds = yaamp_ttf_seconds($coin->difficulty, $solo_rate);
		$ttf_title = 'Your own time to find a block, mining solo';
	} else {
		$seconds = $user_rate ? yaamp_ttf_seconds($coin->difficulty, yaamp_coin_rate_pool($coin->id)) : 0;
		$total_rate = yaamp_pool_rate();
		$pool_ttf = $total_rate ? sectoa2($coin->difficulty * 0x100000000 / $total_rate) : '';
		$ttf_title = 'The pool\'s time to find a block' . ($pool_ttf ? " ($pool_ttf at full pool speed, solo miners included)" : '');
	}
	$ttf = $seconds ? sectoa2($seconds) : '';
}

echo '<ul class="dmd-stats">';

// your hashrate
$sub = array();
if ($solo_rate > 0 && !$solo_only) $sub[] = Itoa2($solo_rate) . 'H/s solo';
if ($percent_bad >= 0.05) $sub[] = round($percent_bad, 1) . '% rejected';
echo '<li class="dmd-stat dmd-stat-main">' . dmd_stat_icon('bolt') . '<b>' . dmd_stat_rate($user_rate) . '</b>'
	. '<span class="dmd-label">Your hashrate</span>'
	. ($sub ? '<span class="dmd-sub">' . CHtml::encode(implode(' · ', $sub)) . '</span>' : '') . '</li>';

// your share of the pool
if ($solo_only)
	echo '<li class="dmd-stat" title="Mining solo (m=solo): the blocks you find are paid to you in full, minus the solo fee">'
		. dmd_stat_icon('pie') . '<b>Solo</b><span class="dmd-label">Your share</span><span class="dmd-sub">whole blocks, to you</span></li>';
else
	echo '<li class="dmd-stat" title="Your part of the pool\'s shares (solo shares left out)">' . dmd_stat_icon('pie')
		. '<b>' . ($share === null ? '—' : number_format($share, $share >= 10 ? 1 : 2, '.', ',') . ' <small>%</small>') . '</b>'
		. '<span class="dmd-label">Your share of the pool</span></li>';

// time to find a block
echo '<li class="dmd-stat" title="' . CHtml::encode($ttf_title) . '">' . dmd_stat_icon('clock')
	. '<b>' . ($ttf ? CHtml::encode($ttf) : '—') . '</b>'
	. '<span class="dmd-label">' . ($solo_only ? 'Your time to a block' : 'Pool time to a block') . '</span></li>';

// rigs online
echo '<li class="dmd-stat">' . dmd_stat_icon('cpu') . '<b>' . number_format($rigs) . '</b>'
	. '<span class="dmd-label">' . ($rigs == 1 ? 'Rig online' : 'Rigs online') . '</span></li>';

echo '</ul>';
