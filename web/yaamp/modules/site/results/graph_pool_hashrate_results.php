<?php

/* The pool hashrate chart of a period, as JSON: {"range": "24h", "points": [[ms, H/s], ...]}.
 *
 *  5m, 15m  a point every 10 or 30 seconds, from the shares: each is the hashrate of the 5 minutes
 *           before it, the way the pool's "now" figure is worked out (yaamp_pool_rate)
 *  4h       the same every 2 minutes, from the shares counted minute by minute
 *  24h      the pool's 15-minute records (hashrate table, kept a day)
 *  7d       its hourly records (hashstats)
 *  1y, all  the hourly records, a day at a time
 *
 * Every answer ends with the live figure, now (the chart's live dot).
 * SiteController::actionGraph_pool_hashrate() keeps each period's answer a few seconds. */

$algo = 'sha256';
if (!isset($range)) $range = '24h';
$now = time();
$window = yaamp_hashrate_step();	// 300 seconds
$per_diff = yaamp_hashrate_constant($algo) / $window / 1000;	// H/s from the difficulty of a window's shares
$points = array();

if ($range == '5m' || $range == '15m' || $range == '4h') {
	$spans = array('5m' => 300, '15m' => 900, '4h' => 4*3600);
	$steps = array('5m' => 10, '15m' => 30, '4h' => 120);
	$span = $spans[$range];
	$step = $steps[$range];
	$group = $range == '4h' ? 60 : 1;	// the shares added up by second, or by minute
	$from = $now - $span - $window;
	$rows = dbolist("SELECT FLOOR(time/$group) AS g, SUM(difficulty) AS d FROM shares
		WHERE valid AND algo=:algo AND time>:from GROUP BY g", array(':algo'=>$algo, ':from'=>$from));
	$sums = array();
	foreach ($rows as $row) $sums[intval($row['g'])] = (double) $row['d'];

	$first = $now - $span;
	$first -= $first % $step;
	for ($t = $first + $step; $t < $now; $t += $step) {
		// the shares of (t - 5 minutes, t]
		$total = 0;
		$last = intval(floor($t / $group));
		for ($g = $last - intval($window / $group) + ($group == 1 ? 1 : 0); $g <= $last - ($group == 1 ? 0 : 1); $g++) {
			if (isset($sums[$g])) $total += $sums[$g];
		}
		$points[] = array($t * 1000, round($total * $per_diff));
	}
}
else if ($range == '24h') {
	$rows = dbolist("SELECT time, hashrate FROM hashrate WHERE algo=:algo AND time>=:from ORDER BY time",
		array(':algo'=>$algo, ':from'=>$now - 24*60*60));
	foreach ($rows as $row) $points[] = array(intval($row['time']) * 1000, (double) $row['hashrate']);
}
else {
	$from = $range == '7d' ? $now - 7*24*60*60 : ($range == '1y' ? $now - 365*24*60*60 : 0);
	if ($range == '7d') {
		$rows = dbolist("SELECT time AS t, hashrate AS h FROM hashstats WHERE algo=:algo AND time>=:from ORDER BY time",
			array(':algo'=>$algo, ':from'=>$from));
	} else {
		$rows = dbolist("SELECT FLOOR(time/86400)*86400 AS t, AVG(hashrate) AS h FROM hashstats
			WHERE algo=:algo AND time>=:from GROUP BY t ORDER BY t", array(':algo'=>$algo, ':from'=>$from));
	}
	foreach ($rows as $row) $points[] = array(intval($row['t']) * 1000, round((double) $row['h']));
}

// now: the live figure
$points[] = array($now * 1000, (double) yaamp_pool_rate($algo));

echo json_encode(array('range' => $range, 'points' => $points));
