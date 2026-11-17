<?php

$user = getuserparam(getparam('address'));
if (!$user) return;

$coinid = intval($user->coinid);
$coin = $coinid ? getdbo('db_coins', $coinid) : null;
if (!$coin) return;

$algo = $coin->algo;
$cache = controller()->memcache;



// Sum current YiiMP hashrate estimates for enabled, visible coins using this algorithm.
$pool_coins = getdbolist('db_coins', "enable=1 AND visible=1 AND algo=:algo", array(':algo'=>$algo));
$pool_hash = 0;
foreach ($pool_coins as $pool_coin) {
	$coin_hash = yaamp_coin_rate($pool_coin->id);
	if (is_numeric($coin_hash) && $coin_hash > 0) $pool_hash += $coin_hash;
}

// The node's figures, shared with the home page for 30 seconds (failed requests too).
$network = yaamp_network_stats($coin);

$network_hash = $network['hashrate'];
$network_diff = $network['difficulty'];
if (is_array($network_diff)) $network_diff = arraySafeVal($network_diff, 'proof-of-work', null);
if (!is_numeric($network_diff) || $network_diff <= 0) $network_diff = $coin->difficulty;

$network_hash_display = is_numeric($network_hash) && $network_hash >= 0 ? ($network_hash > 0 ? Itoa2($network_hash).'H/s' : '0 H/s') : '-';
$pool_hash_display = is_numeric($pool_hash) && $pool_hash >= 0 ? ($pool_hash > 0 ? Itoa2($pool_hash).'H/s' : '0 H/s') : '-';
$network_diff_display = is_numeric($network_diff) && $network_diff > 0 ? number_format($network_diff, 2, '.', ',') : '-';

// Pool's part of the network
$pool_part = (is_numeric($network_hash) && $network_hash > 0 && $pool_hash > 0) ? $pool_hash * 100 / $network_hash : null;

echo '<dl class="dmd-kv dmd-kv-wide">';
echo '<div><dt>Network hashrate</dt><dd><b>'.CHtml::encode($network_hash_display).'</b></dd></div>';
echo '<div><dt>Pool hashrate</dt><dd><b>'.CHtml::encode($pool_hash_display).'</b>'
	.($pool_part !== null ? ' <span class="dmd-muted">('.number_format($pool_part, $pool_part >= 10 ? 1 : 2).'% of the network)</span>' : '').'</dd></div>';
echo '<div><dt>Network difficulty</dt><dd><b>'.CHtml::encode($network_diff_display).'</b></dd></div>';
if ($coin->block_height)
	echo '<div><dt>Block height</dt><dd><b>'.number_format($coin->block_height, 0, '.', ',').'</b></dd></div>';
echo '</dl>';
