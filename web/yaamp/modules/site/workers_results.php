<?php

$user = getuserparam(getparam('address'));
if(!$user) return;
$algo = user()->getState('yaamp-algo', 'all');

$userid = intval($user->id);
$coinid = intval($user->coinid);
if ($coinid) {
	$coin = getdbo('db_coins', $coinid);
}

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");


////echo "<div class='main-right-box'>";
// echo "<div class='main-left-title'>";
////echo "<div class='main-left-inner'>";



$workers = getdbolist('db_workers', "userid=$user->id order by password");
if(count($workers))
{


echo <<<END
<table  class='dataGrid5'>
<thead>
<tr class="wow fadeInUp" data-wow-duration="0.5s">
<th data-sorter="" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Details</th>
<th data-sorter="text" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Worker</th>
<th data-sorter="text" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Pass</th>
<th data-sorter="numeric" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Diff</th>
<th data-sorter="text" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Hash</th>
<th data-sorter="text" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Shares</th>
<th data-sorter="numeric" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Bad</th>
<th data-sorter="numeric" align="center" width=400 style="font-weight: normal;  color: #9699A1; font-family: facetype; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">Found</th>
</tr>
</thead>
<tbody class="wow fadeInUp" data-wow-duration="0.6s">
END;

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
	$address = substr($worker->name, 0, 7).'...';

	if ($worker->userid) {
		$user = getdbo('db_accounts', $worker->userid);
		if ($user) {
			$coin = getdbo('db_coins', $user->coinid);
			$coinsym = $coin->symbol;
			$coinimg = CHtml::image($coin->image, $coin->symbol, array('width'=>'16', 'style'=>'opacity:50%;'));
			$coinlink = CHtml::link($coin->name, '/site/coin?id='.$coin->id);
		}
		$name = empty($name) ? $user->login : $name;
		$gift = $user->donation;
	}

	$dns = !empty($worker->dns)? $worker->dns: $worker->ip;
	if(strlen($worker->dns) > 40)
		$dns = '...'.substr($worker->dns, strlen($worker->dns) - 40);

	echo "<tr class='ssrow' align=center style='font-weight: normal;  color: #999999; font-family: interblack; font-size: 13px;'>";
	//echo '<td width="20">'.$coinimg.'</td>';
	//echo '<td>'.$coinlink.''.($coinsym ? '&nbsp;('.$coinsym.')':'-').'</td>';
	//echo "<td><a href='/?address=$worker->name'>$worker->name</a></td>";
//	echo "<td><a href='/?address=$worker->name'>$address</a></td>";
	echo "<td>$worker->version</td>";
	echo '<td>'.$name.'</td>';
	echo "<td>$worker->password</td>";
	echo "<td>$worker->difficulty</td>";
	//echo "<td title='$worker->ip'>$dns</td>";
	echo "<td data=\"$user_rate\">$user_rate_h</td>";

	$shares = dboscalar("SELECT COUNT(id) as shares FROM shares WHERE workerid=:worker AND algo=:algo", array(
		':worker'=> $worker->id,
		':algo'=> $algo
	));
	//echo "<td>$shares</td>";

	$worker_blocs = dboscalar("SELECT COUNT(id) as blocs FROM blocks WHERE workerid=:worker AND algo=:algo", array(
		':worker'=> $worker->id,
		':algo'=> $algo
	));
	$user_blocs = dboscalar("SELECT COUNT(id) as blocs FROM blocks WHERE userid=:user AND algo=:algo
		AND time > (SELECT min(time) FROM workers WHERE algo=:algo)", array(
		':user'=> $worker->userid,
		':algo'=> $algo
	));
	echo '<td>'.number_format($percent,1,'.','').'%</td>';

	echo "<td>";
	if ($user_bad > 0) {
		if ($pct_bad > 50)
			echo " {$pct_bad}%";
		else
			echo " {$pct_bad}%";
	}
	echo "</td>";
	echo '<td>'.$worker_blocs.' / '.$user_blocs.'</td>';
	//echo '<td>'.(isset($gift) && $gift ? "$gift&nbsp;%" : '').'</td>';
	echo '</tr>';
}

echo "</tbody></table>";
////echo "</div>";
////echo "</div>";
}
?>







