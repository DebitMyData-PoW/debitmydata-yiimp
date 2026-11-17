<?php

if (isset($_GET['algo']))
	user()->setState('yaamp-algo', $_GET['algo']);

$algo = user()->getState('yaamp-algo');

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<div align="center" style="margin-top: -20px; margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 280px; text-align=center; text-align: center; background-color:  rgba(21, 21, 21, 0.07); color: #b1b1b1;" placeholder="Search wallet..." />
</div>
<style type="text/css">
tr.ssrow.filtered { display: none; }
</style>
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); }
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
<th data-sorter="" width="20"></th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="text">Coin</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="text">Address</th>
<!-- <th data-sorter="text">Pass</th> -->
<!-- <th data-sorter="text">Client</th> -->
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="text">Version</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="numeric">Hashrate</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="numeric">Mining Difficulty</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="numeric">Shares</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="numeric">Bad shares</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="numeric">Hashrate percent</th>
<th align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;" data-sorter="numeric">Found Blocks</th>
<!-- <th data-sorter="text" width="30">Name</th> -->
<!-- <th data-sorter="text"></th> -->
</tr>
</thead><tbody>
end;

$workers = getdbolist('db_workers', "algo=:algo order by name", array(':algo'=>$algo));

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
	$user_rate_h = $user_rate ? Itoa2($user_rate).'H' : '-';

	$name = $worker->worker;
	$user = $coin = NULL;
	$coinimg = ''; $coinlink = ''; $coinsym = ''; $shares= '';
	if ($worker->userid) {
		$user = getdbo('db_accounts', $worker->userid);
		if ($user) {
			$coin = getdbo('db_coins', $user->coinid);
			$coinsym = $coin->symbol;
			$coinimg = CHtml::image($coin->image, $coin->symbol, array('width'=>'22'));
			$coinlink = CHtml::link($coin->name, '/site/coin?id='.$coin->id);
		}
		$name = empty($name) ? $user->login : $name;
		$gift = $user->donation;
	}

	$dns = !empty($worker->dns)? $worker->dns: $worker->ip;
	if(strlen($worker->dns) > 40)
		$dns = '...'.substr($worker->dns, strlen($worker->dns) - 40);

	echo "<tr class='ssrow'>";
	echo '<td width="30">'.$coinimg.'</td>';
	echo '<td align=center style="font-size: .8em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;">'.$coin->name.''.($coinsym ? '&nbsp;('.$coinsym.')':'-').'</td>';
	echo "<td align=center style='font-size: 1.1em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1;'><a href='/?address=$worker->name'>$worker->name</a></td>";
	// echo "<td>$worker->password</td>";
	// echo "<td title='$worker->ip'>$dns</td>";
	echo "<td align=center style='font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;'>$worker->version</td>";
	echo "<td align=center style='font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;' data=\"$user_rate\">$user_rate_h</td>";
	echo "<td align=center style='font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;'>$worker->difficulty</td>";

	$shares = dboscalar("SELECT COUNT(id) as shares FROM shares WHERE workerid=:worker AND algo=:algo", array(
		':worker'=> $worker->id,
		':algo'=> $algo
	));
	echo "<td align=center style='font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;'>$shares</td>";

	echo "<td align=center style='font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;'>";
	if ($user_bad > 0) {
		if ($pct_bad > 50)
			echo " {$pct_bad}%";
		else
			echo " {$pct_bad}%";
	}
	echo "</td>";

	$worker_blocs = dboscalar("SELECT COUNT(id) as blocs FROM blocks WHERE workerid=:worker AND algo=:algo", array(
		':worker'=> $worker->id,
		':algo'=> $algo
	));
	$user_blocs = dboscalar("SELECT COUNT(id) as blocs FROM blocks WHERE userid=:user AND algo=:algo
		AND time > (SELECT min(time) FROM workers WHERE algo=:algo)", array(
		':user'=> $worker->userid,
		':algo'=> $algo
	));
	echo '<td align=middle style="font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;">'.number_format($percent,1,'.','').'%</td>';

	echo '<td align=middle style="font-size: .9em; font-weight:normal; font-family: monospace, sans-recif; color: #b1b1b1; font-variant: small-caps; text-transform: uppercase;">'.$worker_blocs.' / '.$user_blocs.'</td>';
	// echo '<td>'.$name.'</td>';
	// echo '<td>'.(isset($gift) && $gift ? "$gift&nbsp;%" : '').'</td>';
	echo '</tr>';
}

echo "</tbody></table>";




