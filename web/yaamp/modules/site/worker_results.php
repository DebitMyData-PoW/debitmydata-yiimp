<?php
/*
 * The workers table of /site/worker (loaded every 30 seconds by site/worker.php).
 */

if (isset($_GET['algo']))
	user()->setState('yaamp-algo', $_GET['algo']);

$algo = user()->getState('yaamp-algo', 'all');

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

$workers = getdbolist('db_workers', "algo=:algo order by name", array(':algo'=>$algo));

$count = count($workers);
echo '<div class="dmd-toolbar">'.dmd_admin_search('Search workers...')
	.'<p class="dmd-small">'.$count.' '.($count == 1 ? 'worker' : 'workers').' on '.CHtml::encode($algo).'</p></div>';

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';

dmd_admin_table('maintable', "{
	textExtraction: {
		5: function(node, table, n) { return $(node).attr('data'); }
	},
	widgets: ['zebra','filter','Storage','saveSort'],
	widgetOptions: {
		saveSort: true,
		filter_saveFilters: false,
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="text">Pass</th>
<th data-sorter="text">Client</th>
<th data-sorter="text">Version</th>
<th data-sorter="numeric" class="r">Hashrate</th>
<th data-sorter="numeric" class="r">Diff</th>
<th data-sorter="numeric" class="r">Shares</th>
<th data-sorter="numeric" class="r">Shares %</th>
<th data-sorter="numeric" class="r">Bad %</th>
<th data-sorter="numeric" class="r">Found</th>
<th data-sorter="text">Name</th>
<th data-sorter="text" class="r"></th>
</tr>
</thead>
<tbody>
end;

$total_rate = 0.0;
foreach($workers as $worker)
{
	$total_rate += yaamp_worker_rate($worker->id);
}

foreach($workers as $worker)
{
	$user_rate = yaamp_worker_rate($worker->id);
	$percent = 0.0;
	if ($total_rate) $percent = (100.0 * $user_rate) / $total_rate;
	$user_bad = yaamp_worker_rate_bad($worker->id);
	$pct_bad = ($user_rate+$user_bad)? round($user_bad*100/($user_rate+$user_bad), 3): 0;
	$user_rate_h = $user_rate ? Itoa2($user_rate).'H/s' : '-';

	$name = $worker->worker;
	$user = $coin = NULL;
	$coincell = ''; $coinsym = ''; $shares= '';
	$gift = 0;

	if ($worker->userid) {
		$user = getdbo('db_accounts', $worker->userid);
		if ($user) {
			$coin = getdbo('db_coins', $user->coinid);
			$coinsym = $coin->symbol;
			$coincell = dmd_admin_coin_cell($coin, '/site/coin?id='.$coin->id);
		}
		$name = empty($name) ? $user->login : $name;
		$gift = $user->donation;
	}

	$dns = !empty($worker->dns)? $worker->dns: $worker->ip;
	if(strlen($worker->dns) > 40)
		$dns = '...'.substr($worker->dns, strlen($worker->dns) - 40);

	echo '<tr class="ssrow">';
	echo '<td data-label="Coin">'.($coincell ? $coincell : '-').'</td>';
	echo '<td data-label="Address" class="dmd-first"><a class="dmd-addr" href="/mywallet/?address='.$worker->name.'">'.$worker->name.'</a></td>';
	echo '<td data-label="Pass">'.CHtml::encode($worker->password).'</td>';
	echo '<td data-label="Client" title="'.$worker->ip.'">'.$dns.'</td>';
	echo '<td data-label="Version">'.CHtml::encode($worker->version).'</td>';
	echo '<td data-label="Hashrate" class="r dmd-num" data="'.$user_rate.'">'.$user_rate_h.'</td>';
	echo '<td data-label="Diff" class="r">'.$worker->difficulty.'</td>';

	$shares = dboscalar("SELECT COUNT(id) as shares FROM shares WHERE workerid=:worker AND algo=:algo", array(
		':worker'=> $worker->id,
		':algo'=> $algo
	));
	echo '<td data-label="Shares" class="r">'.$shares.'</td>';

	$worker_blocs = dboscalar("SELECT COUNT(id) as blocs FROM blocks WHERE workerid=:worker AND algo=:algo", array(
		':worker'=> $worker->id,
		':algo'=> $algo
	));

	$user_blocs = dboscalar("SELECT COUNT(id) as blocs FROM blocks WHERE userid=:user AND algo=:algo
		AND time > (SELECT min(time) FROM workers WHERE algo=:algo)", array(
		':user'=> $worker->userid,
		':algo'=> $algo
	));

	echo '<td data-label="Shares %" class="r">'.number_format($percent,1,'.','').'%</td>';

	echo '<td data-label="Bad %" class="r'.($pct_bad > 50 ? ' dmd-warn' : '').'">';
	if ($user_bad > 0) echo "{$pct_bad}%";
	echo '</td>';

	echo '<td data-label="Found" class="r" title="This worker / this address, since the oldest worker connected">'.$worker_blocs.' / '.$user_blocs.'</td>';
	echo '<td data-label="Name">'.CHtml::encode($name).'</td>';
	echo '<td data-label="Donation" class="r">'.($gift ? "$gift&nbsp;%" : '').'</td>';

	echo '</tr>';
}

if (!$count)
	echo '<tr><td colspan="13" class="dmd-empty-row">No worker on this algo now.</td></tr>';

echo "</tbody></table></div></section>";
