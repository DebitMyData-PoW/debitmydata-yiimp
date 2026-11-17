<?php
/*
 * The payments table of /site/payments (loaded every 30 seconds by site/payments.php). "Add to
 * balance" gives a user's failed payouts back to their balance, to be paid again.
 */

$coin_id = getiparam('id');
$saveSort = $coin_id ? 'false' : 'true';

$sqlFilter = $coin_id ? "AND coinid={$coin_id}" : "";
$limit = $coin_id ? '' : 'LIMIT 100';

$data = dbolist("SELECT coinid, userid, SUM(amount) AS immature FROM earnings WHERE status=0 $sqlFilter GROUP BY coinid, userid");
$immature = array();
if (!empty($data)) foreach ($data as $row) {
	$immkey = $row['coinid']."-".$row['userid'];
	$immature[$immkey] = $row['immature'];
}

$data = dbolist("SELECT account_id, SUM(amount) AS failed FROM payouts WHERE tx IS NULL AND completed=0 GROUP BY account_id");
$failed = array();
if (!empty($data)) foreach ($data as $row) {
	$uid = $row['account_id'];
	$failed[$uid] = $row['failed'];
}

$list = getdbolist('db_accounts', "is_locked != 1 $sqlFilter AND (".
	"balance > 0 OR last_earning > (UNIX_TIMESTAMP()-60*60) OR id IN (SELECT DISTINCT account_id FROM payouts WHERE tx IS NULL)".
	") ORDER BY last_earning DESC $limit");

$users = count($list).' '.(count($list) == 1 ? 'user' : 'users');
if (count($list) == 20) $users .= " ($limit)";

echo '<div class="dmd-toolbar">'.dmd_admin_search('Search payments...').'<p class="dmd-small">'.$users.'</p></div>';

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';

dmd_admin_table('maintable', "{
	textExtraction: {
		2: function(node, table, n) { return $(node).attr('data'); }
	},
	widgets: ['zebra','filter','Storage','saveSort'],
	widgetOptions: {
		saveSort: {$saveSort},
		filter_saveFilters: {$saveSort},
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
<th data-sorter="numeric">Last block</th>
<th data-sorter="currency" class="r">Next payout</th>
<th data-sorter="currency" class="r">Immature</th>
<th data-sorter="currency" class="r">Failed</th>
<th data-sorter="false" class="r">Actions</th>
</tr>
</thead><tbody>
end;

$total = 0.; $totalimmat = 0.; $totalfailed = 0.;

foreach($list as $user)
{
	$coin = getdbo('db_coins', $user->coinid);
	$d = datetoa2($user->last_earning);

	$address = substr($user->username, 0, 10).'...';

	echo '<tr class="ssrow">';

	if($coin) {
		$coinbalance = $coin->balance ? bitcoinvaluetoa($coin->balance) : '';
		echo '<td data-label="Coin">'.dmd_admin_coin_cell($coin, '/site/coin?id='.$coin->id).'</td>';
		$immkey = "{$coin->id}-{$user->id}";
	} else {
		$coinbalance = '-';
		echo '<td data-label="Coin"></td>';
		$immkey = "0-{$user->id}";
	}

	echo '<td data-label="Address" class="dmd-first"><a class="dmd-addr" href="/mywallet/?address='.$user->username.'" title="'.$user->username.'">'.$address.'</a></td>';
	echo '<td data-label="Last block" data="'.$user->last_earning.'">'.($d ? "<span>$d ago</span>" : '').'</td>';

	$balance = $user->balance ? bitcoinvaluetoa($user->balance) : '';
	$total += (double) $user->balance;
	echo '<td data-label="Next payout" class="r dmd-num">'.$balance.'</td>';

	$immbalance = arraySafeVal($immature, $immkey, 0);
	$totalimmat += (double) $immbalance;
	$immbalance = $immbalance ? bitcoinvaluetoa($immbalance) : '';
	echo '<td data-label="Immature" class="r">'.$immbalance.'</td>';

	$failbalance = arraySafeVal($failed, $user->id, 0);
	$totalfailed += (double) $failbalance;
	$failbalance = $failbalance ? bitcoinvaluetoa($failbalance) : '';
	echo '<td data-label="Failed" class="r dmd-warn">'.$failbalance.'</td>';

	echo '<td data-label="Actions" class="r">';
	if ($failbalance != '')
		echo '<a class="dmd-act" href="/site/cancelUserPayment?id='.$user->id.'" title="Give the failed payouts back to the balance">Add to balance</a>';
	echo '</td>';

	echo "</tr>";
}

if (!count($list))
	echo '<tr><td colspan="7" class="dmd-empty-row">Nothing to pay.</td></tr>';

echo '</tbody></table></div></section>';

if ($coin_id) {
	$coin = getdbo('db_coins', $coin_id);
	$symbol = $coin->symbol;

	echo '<div class="dmd-totals">';
	echo '<section class="dmd-card"><div class="dmd-card-head"><h2>Totals</h2></div><dl class="dmd-kv">';
	echo '<div><dt>Balances</dt><dd>'.bitcoinvaluetoa($total)." $symbol</dd></div>";
	echo '<div><dt>Immature</dt><dd>'.bitcoinvaluetoa($totalimmat)." $symbol</dd></div>";
	if ($totalfailed)
		echo '<div class="dmd-warn"><dt>Failed</dt><dd>'.bitcoinvaluetoa($totalfailed)." $symbol</dd></div>";
	echo '</dl>';
	if ($totalfailed)
		echo '<p><a class="dmd-button dmd-ghost dmd-sm" href="/site/cancelUsersPayment?id='.$coin_id.'" title="Add to balance all failed payouts">Reset all failed</a></p>';
	echo '</section>';
	echo '</div>';
}
