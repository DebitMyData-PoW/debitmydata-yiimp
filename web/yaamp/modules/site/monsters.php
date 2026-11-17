<?php
/*
 * Admin: the accounts to keep an eye on (/site/monsters): shares from a stratum that is gone, a
 * balance without blocks in the last 24 hours, the most rigs, the most shares, and the locked ones.
 */

echo '<div class="dmd-admin">';
dmd_admin_head('Big miners', null, null, '', 'Accounts to keep an eye on');

echo '<div class="dmd-toolbar">'.dmd_admin_search('Search miners...').'</div>';

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';

dmd_admin_table('maintable', "{
	headers: { 9: { sorter: false} },
	widgets: ['zebra','filter'],
	widgetOptions: {
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th>UID</th>
<th>Coin</th>
<th>Address</th>
<th>Last</th>
<th class="r">Blocks (24h)</th>
<th class="r">Balance</th>
<th class="r">Total paid</th>
<th class="r">Miners</th>
<th class="r">Shares</th>
<th>Why</th>
</tr>
</thead>
<tbody>
end;

function showUser($userid, $what)
{
	$user = getdbo('db_accounts', $userid);
	if(!$user) return;

	$d = datetoa2($user->last_earning);
	$balance = bitcoinvaluetoa($user->balance);
	$paid = dboscalar("select sum(amount) from payouts where account_id=$user->id");
	$paid = bitcoinvaluetoa($paid);

	$t = time()-24*60*60;

	$miner_count = getdbocount('db_workers', "userid=$user->id");
	$share_count = getdbocount('db_shares', "userid=$user->id");
	$block_count = getdbocount('db_blocks', "userid=$user->id and time>$t");

	$address = $user ? substr($user->username, 0, 10).'...' : '';

	$coin = getdbo('db_coins', $user->coinid);

	$why = array(
		'pid' => 'Shares from an old stratum',
		'blocks' => 'Balance, no block in 24h',
		'miners' => 'Most rigs',
		'shares' => 'Most shares',
		'locked' => 'Locked',
	);

	echo '<tr class="ssrow">';
	echo '<td data-label="UID" class="dmd-muted">'.$user->id.'</td>';
	echo '<td data-label="Coin">'.($coin ? dmd_admin_coin_cell($coin, '/site/coin?id='.$coin->id) : '').'</td>';
	echo '<td data-label="Address" class="dmd-first"><a class="dmd-addr" href="/mywallet/?address='.$user->username.'" title="'.$user->username.'">'.$address.'</a></td>';
	echo '<td data-label="Last">'.($d ? "<span>$d ago</span>" : '').'</td>';
	echo '<td data-label="Blocks (24h)" class="r">'.$block_count.'</td>';
	echo '<td data-label="Balance" class="r dmd-num">'.$balance.'</td>';
	echo '<td data-label="Total paid" class="r">'.$paid.'</td>';
	echo '<td data-label="Miners" class="r">'.$miner_count.'</td>';
	echo '<td data-label="Shares" class="r">'.$share_count.'</td>';
	echo '<td data-label="Why">'.($user->is_locked ? '<span class="dmd-badge solo">Locked</span>' : '<span class="dmd-small">'.$why[$what].'</span>').'</td>';
	echo "</tr>";
}

$t = time()-24*60*60;

$list = dbolist("select userid from shares where pid is null or pid not in (select pid from stratums) group by userid");
foreach($list as $item)
	showUser($item['userid'], 'pid');

$list = dbolist("select id from accounts where balance>0.001 and id not in (select distinct userid from blocks where userid is not null and time>$t)");
foreach($list as $item)
	showUser($item['id'], 'blocks');

$monsters = dbolist("SELECT COUNT(*) AS total, userid FROM workers GROUP BY userid ORDER BY total DESC LIMIT 5");
foreach($monsters as $item)
	showUser($item['userid'], 'miners');

$monsters = dbolist("SELECT COUNT(*) AS total, workerid FROM shares GROUP BY workerid ORDER BY total DESC LIMIT 5");
foreach($monsters as $item)
{
	$worker = getdbo('db_workers', $item['workerid']);
	if(!$worker) continue;

	showUser($worker->userid, 'shares');
}

$list = getdbolist('db_accounts', "is_locked");
foreach($list as $user)
	showUser($user->id, 'locked');

echo "</tbody></table></div></section>";
echo '</div>';
