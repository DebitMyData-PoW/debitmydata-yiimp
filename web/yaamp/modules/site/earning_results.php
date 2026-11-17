<?php
/*
 * The earnings table of /site/earning (loaded every 30 seconds by site/earning.php). "Clear" takes
 * an earning out while its block is immature; "Delete" deletes it.
 */

$coin_id = getiparam('id');
$saveSort = $coin_id ? 'false' : 'true';

$sqlFilter = $coin_id ? "AND coinid={$coin_id}": '';
$limit = $coin_id ? '' : 'LIMIT 20';

$earnings = getdbolist('db_earnings', "status!=2 $sqlFilter ORDER BY create_time DESC $limit");

$records = count($earnings).' '.(count($earnings) == 1 ? 'record' : 'records');
if (count($earnings) >= 20) $records .= " ($limit)";

echo '<div class="dmd-toolbar">'.dmd_admin_search('Search earnings...').'<p class="dmd-small">'.$records.'</p></div>';

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';

dmd_admin_table('maintable', "{
	widgets: ['zebra','filter','Storage','saveSort'],
	textExtraction: {
		4: function(node, table, n) { return $(node).attr('data'); },
		5: function(node, table, n) { return $(node).attr('data'); }
	},
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
<th data-sorter="currency" class="r">Quantity</th>
<th data-sorter="numeric" class="r">Block</th>
<th data-sorter="numeric">Status</th>
<th data-sorter="numeric">Time</th>
<th data-sorter="false" class="r">Actions</th>
</tr>
</thead><tbody>
end;

$total = 0.; $total_btc = 0.; $totalimmat = 0.; $totalfees = 0.; $totalstake = 0.;

foreach($earnings as $earning)
{
	$coin = getdbo('db_coins', $earning->coinid);
	if(!$coin) continue;

	$user = getdbo('db_accounts', $earning->userid);
	if(!$user) continue;

	$block = getdbo('db_blocks', $earning->blockid);
	if(!$block) continue;

	$username = $user->username;
	$username2 = substr($username, 0, 10).'...';

	$t1 = datetoa2($earning->create_time). ' ago';
	$t2 = datetoa2($earning->mature_time);
	if ($t2) $t2 = '+'.$t2;

	$category = $block->category;
	if ($category == 'immature') $badge = 'new';
	else if ($category == 'orphan') $badge = 'orphan';
	else $badge = 'done';

	echo '<tr class="ssrow">';
	echo '<td data-label="Coin">'.dmd_admin_coin_cell($coin, '/site/coin?id='.$coin->id).'</td>';
	echo '<td data-label="Address" class="dmd-first"><a class="dmd-addr" href="/mywallet/?address='.$user->username.'" title="'.$user->username.'">'.$username2.'</a></td>';
	echo '<td data-label="Quantity" class="r dmd-num">'.bitcoinvaluetoa($earning->amount).'</td>';
	echo '<td data-label="Block" class="r">'.$block->height.'</td>';
	echo '<td data-label="Status" data="'.$block->height.'"><span class="dmd-badge '.$badge.'">'.CHtml::encode($category).'</span> <span class="dmd-small">'.$block->confirmations.'</span></td>';
	echo '<td data-label="Time" data="'.$earning->create_time.'">'."<span>$t1 $t2</span></td>";

	echo '<td data-label="Actions" class="r"><span class="dmd-acts">';
	echo '<a class="dmd-act" href="/site/clearearning?id='.$earning->id.'" title="Take it out while the block is immature">Clear</a>';
	echo '<a class="dmd-act dmd-danger" href="/site/deleteearning?id='.$earning->id.'">Delete</a>';
	echo '</span></td>';

	echo "</tr>";

	if($block->category == 'immature') {
		$total += (double) $earning->amount;
		$total_btc += (double) $earning->amount * $earning->price;
		$totalimmat += (double) $earning->amount;
	}

	if($block->category == 'generate') {
		$total += (double) $earning->amount; // "Exchange" state
		$total_btc += (double) $earning->amount * $earning->price;
	}

	else if($block->category == 'stake' || $block->category == 'generated') {
		$totalstake += (double) $earning->amount;
	}
}

if (!count($earnings))
	echo '<tr><td colspan="7" class="dmd-empty-row">No earning waiting.</td></tr>';

echo '</tbody>';
echo '</table></div></section>';

if ($coin_id) {
	$coin = getdbo('db_coins', $coin_id);
	if (!$coin) exit;

	$symbol = $coin->symbol;
	$feepct = yaamp_fee($coin->algo);
	$totalfees = ($total / ((100 - $feepct) / 100.)) - $total;
	$cleared = dboscalar("SELECT SUM(balance) FROM accounts WHERE coinid={$coin->id}");
	$exchange = $total - $totalimmat;

	echo '<div class="dmd-totals">';

	echo '<section class="dmd-card"><div class="dmd-card-head"><h2>Owed</h2></div><dl class="dmd-kv">';
	echo '<div><dt>Immature</dt><dd>'.bitcoinvaluetoa($totalimmat)." $symbol</dd></div>";
	echo '<div><dt>Total owed</dt><dd>'.bitcoinvaluetoa($total)." $symbol</dd></div>";
	echo '<div><dt>Pool Fees '.round($feepct,1).'%</dt><dd>'.bitcoinvaluetoa($totalfees)." $symbol</dd></div>";
	if ($coin->rpcencoding == 'POS')
		echo '<div><dt>Stake</dt><dd>'.bitcoinvaluetoa($totalstake)." $symbol</dd></div>";
	echo '</dl></section>';

	echo '<section class="dmd-card"><div class="dmd-card-head"><h2>Wallet</h2></div><dl class="dmd-kv">';
	echo '<div><dt>Balance</dt><dd>'.bitcoinvaluetoa($coin->balance)." $symbol</dd></div>";
	echo '<div><dt>Cleared</dt><dd>'.bitcoinvaluetoa($cleared)." $symbol</dd></div>";
	echo '<div><dt title="Available = (Balance - Cleared - in exchange)">Available</dt>';
	echo '<dd>'.bitcoinvaluetoa($coin->balance - $exchange - $cleared)." $symbol</dd></div>";
	echo '</dl></section>';

	echo '</div>';
}
