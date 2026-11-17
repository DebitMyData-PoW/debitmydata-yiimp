<?php
/*
 * The users table of /site/user (loaded every 30 seconds by site/user.php).
 */

/////////////////////////////////////////////////////////////////////////////////////////////////

$symbol = getparam('symbol');
$coin = null;

if($symbol = 'all')
	$users = getdbolist('db_accounts', "balance>.001 OR id IN (SELECT DISTINCT userid FROM workers) ORDER BY last_earning DESC");
else
{
	$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
	if(!$coin) return;

	$users = getdbolist('db_accounts', "coinid={$coin->id} AND (balance>.001 OR id IN (SELECT DISTINCT userid FROM workers)) ORDER BY last_earning DESC");
}

$count = count($users);
echo '<div class="dmd-toolbar">'.dmd_admin_search('Search users...')
	.'<p class="dmd-small">'.$count.' '.($count == 1 ? 'user' : 'users').'</p></div>';

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';

dmd_admin_table('maintable', "{
	textExtraction: {
		3: function(node, table, cellIndex) { return $(node).attr('data'); },
		5: function(node, table, cellIndex) { return $(node).attr('data'); },
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
<th data-sorter="numeric">UID</th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="numeric">Last mined</th>
<th data-sorter="numeric" class="r">Workers</th>
<th data-sorter="numeric" class="r">Hashrate</th>
<th data-sorter="numeric" class="r">Bad</th>
<th data-sorter="numeric" class="r">Blocks</th>
<th data-sorter="numeric" class="r">Balance</th>
<th data-sorter="numeric" class="r">Total paid</th>
</tr>
</thead><tbody>
end;

$total_balance = 0;
$total_paid = 0;
$total_unsold = 0;

foreach($users as $user)
{
	$target = yaamp_hashrate_constant();
	$interval = yaamp_hashrate_step(); // 300 seconds
	$delay = time()-$interval;

	$user_rate = dboscalar("SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=".$user->id);
	$user_bad = yaamp_user_rate_bad($user->id);// dboscalar("SELECT (count(id) * $target / $interval / 1000) FROM shares WHERE valid=0 AND time>$delay AND userid=".$user->id);
	$pct_bad = $user_rate? round($user_bad*100/$user_rate, 3): 0;

	$balance = bitcoinvaluetoa($user->balance);
	$paid = dboscalar("SELECT sum(amount) FROM payouts WHERE account_id=".$user->id);
	$d = datetoa2($user->last_earning);

	$miner_count = getdbocount('db_workers', "userid=".$user->id);
	$block_count = getdbocount('db_blocks', "userid=".$user->id);
	$block_count1 = number_format($block_count, 0, '.', ',');;
	$block_diff = ($paid && $block_count) ? round(dboscalar("SELECT sum(difficulty) FROM blocks WHERE userid=".$user->id)/$paid, 3): '?';

	$paid = bitcoinvaluetoa($paid);
	$paid2 = number_format($paid, 6, '.', ',');
	$balance = bitcoinvaluetoa($user->balance);

	$total_paid = controller()->memcache->get_database_scalar("wallet_total_paid-$user->id",
		"select sum(amount) from payouts where account_id=$user->id");

	$confirmed = yaamp_convert_earnings_user($user, "status=1");
	$unconfirmed = yaamp_convert_earnings_user($user, "status=0");
	$total_unsold = bitcoinvaluetoa($confirmed + $unconfirmed);

	$confirmed = $confirmed? bitcoinvaluetoa($confirmed): '';
	$unconfirmed = $unconfirmed? bitcoinvaluetoa($unconfirmed): '';
	$total_earned = bitcoinvaluetoa($total_unsold + $balance + $total_paid);

	$user_bad = Itoa2($user_bad);

	$user_coin = $coin;
	if (!($coin && $user->coinid == $coin->id) && $user->coinid > 0)
		$user_coin = getdbosql('db_coins', "id=:id", array(':id'=>$user->coinid));
	$coincell = $user_coin ? dmd_admin_coin_cell($user_coin, '/explorer/'.$user_coin->symbol) : '';

	$miner = $user ? substr($user->username, 0, 10).'...' : '';

	echo '<tr class="ssrow">';
	echo '<td data-label="UID" class="dmd-muted">'.$user->id.'</td>';
	echo '<td data-label="Coin">'.$coincell.'</td>';
	echo '<td data-label="Address" class="dmd-first"><a class="dmd-addr" href="/mywallet/?address='.$user->username.'" title="'.$user->username.'">'.$miner.'</a></td>';
	echo '<td data-label="Last mined" data="'.$user->last_earning.'">'.($d ? "<span>$d ago</span>" : '').'</td>';
	echo '<td data-label="Workers" class="r">'.$miner_count.'</td>';
	echo '<td data-label="Hashrate" class="r dmd-num" data="'.(0+$user_rate).'">'.($user_rate ? Itoa2($user_rate).'H/s' : '').'</td>';
	echo '<td data-label="Bad" class="r">';
	if ($pct_bad) echo round($pct_bad,1)."&nbsp;%";
	echo '</td>';
	echo '<td data-label="Blocks" class="r">'.$block_count1.'</td>';
	echo '<td data-label="Balance" class="r dmd-num">'.$balance.'</td>';
	echo '<td data-label="Total paid" class="r">'.$paid2.'</td>';
	echo '</tr>';

	$total_balance += $user->balance;
	$total_paid += $paid;
}

if (!$count)
	echo '<tr><td colspan="10" class="dmd-empty-row">No user with a balance or a rig mining now.</td></tr>';

echo "</tbody>";
echo "</table></div></section>";

//echo "<p><a href='/site/bonususers'>1% bonus</a></p>";
