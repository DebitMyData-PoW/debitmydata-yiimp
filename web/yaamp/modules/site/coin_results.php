<?php
/*
 * The live part of a coin's wallet page (site/coin.php loads it every 30 seconds): the wallet's
 * figures, its latest transactions and the bookmarked addresses.
 */

$coin = getdbo('db_coins', getiparam('id'));
if (!$coin) $this->goback();

$PoS = ($coin->rpcencoding == 'POS'); // or if 'stake' key is present in 'getinfo' method
$DCR = ($coin->rpcencoding == 'DCR' || $coin->getOfficialSymbol() == 'DCR');
$DGB = ($coin->rpcencoding == 'DGB' || $coin->getOfficialSymbol() == 'DGB');
$ETH = ($coin->rpcencoding == 'GETH');

$remote = new WalletRPC($coin);

$reserved1 = dboscalar("SELECT SUM(balance) FROM accounts WHERE coinid={$coin->id}");
$reserved1 = altcoinvaluetoa($reserved1);
$balance = altcoinvaluetoa($coin->balance);

$owed = dboscalar("SELECT SUM(E.amount) AS owed FROM earnings E ".
	"LEFT JOIN blocks B ON E.blockid = B.id ".
	"WHERE E.status!=2 AND E.coinid={$coin->id} "//."AND B.category NOT IN ('stake','generated')"
);
$owed_btc = bitcoinvaluetoa($owed*$coin->price);
$owed = altcoinvaluetoa($owed);

$symbol = $coin->symbol;
if (!empty($coin->symbol2)) $symbol = $coin->symbol2;
$sym = '<small>'.CHtml::encode($symbol).'</small>';

if (!function_exists('dmd_coin_stat')) {
function dmd_coin_stat($label, $value, $icon, $class='', $title='')
{
	$t = $title !== '' ? ' title="'.CHtml::encode($title).'"' : '';
	return '<li class="dmd-stat '.$class.'"'.$t.'><span class="dmd-icon">'.dmd_admin_icon($icon).'</span>'
		.'<b>'.$value.'</b><span class="dmd-label">'.$label.'</span></li>';
}
}

//////////////////////////////////////////////////////////////////////////////////////

$info = $remote->getinfo();

if (!empty($info)) {
	$stake = arraySafeVal($info, 'stake', '');
	if ($stake !== '') $PoS = true;
}

if ($DCR) {
	// Decred Tickets
	$stake = 0;
	$balances = $remote->getbalance('*',0);
	if (isset($balances["balances"])) {
		foreach ($balances["balances"] as $accb) {
			$stake += arraySafeVal($accb, 'lockedbytickets', 0);
		}
	}
	$stakeinfo = $remote->getstakeinfo();
	$ticketprice = arraySafeVal($stakeinfo,'difficulty');
	$tickets  = arraySafeVal($stakeinfo, 'live', 0);
	$tickets += arraySafeVal($stakeinfo, 'immature', 0);
}

$owned = dmd_coin_stat('Owned by the pool', number_format($coin->available, 4, '.', ',')." $sym", 'coins', 'dmd-stat-main');

if(!$info)
{
	echo '<ul class="dmd-stats">'.$owned
		.dmd_coin_stat('Price', bitcoinvaluetoa($coin->price), 'chart').'</ul>';
	echo '<p class="dmd-alert">The wallet does not answer: '.CHtml::encode('ERROR '.$remote->error).'</p>';
	return;
}

$errors = isset($info['errors'])? $info['errors']: '';
$balance = $remote->getbalance();
if (!is_numeric($balance)) $balance = 0;

$immature_balance = isset($info['immature_balance'])? $info['immature_balance']: '';

$txfee = isset($info['paytxfee'])? $info['paytxfee']: '';
$connections = isset($info['connections'])? CHtml::link($info['connections'],'/site/peers?id='.$coin->id): '';
$blocks = isset($info['blocks'])? $info['blocks']: '';

$remote = new WalletRPC($coin);
$diff = $remote->getdifficulty();
$diffPoS = arraySafeVal($diff,'proof-of-work');

echo '<ul class="dmd-stats">';
echo $owned;
echo dmd_coin_stat('Wallet balance', number_format($balance, 4, '.', ',')." $sym", 'wallet');
echo dmd_coin_stat('Owed to miners', number_format($owed, 4, '.', ',')." $sym", 'users');
echo dmd_coin_stat('Block reward', number_format($coin->reward, 4, '.', ',')." $sym", 'gift');
echo dmd_coin_stat('Blocks'.(!empty($errors) ? ' (see the warning)' : ''),
	is_numeric($blocks) ? number_format($blocks, 0, '.', ',') : $blocks, 'cube', !empty($errors) ? 'dmd-warn' : '', $errors);
echo dmd_coin_stat('Difficulty', number_format($PoS ? $diffPoS : $diff, 4, '.', ','), 'bolt');
echo dmd_coin_stat('Peers', $connections, 'peers');
if ($PoS) echo dmd_coin_stat('Stake', $stake, 'coins');
else if ($DCR) {
	echo dmd_coin_stat('Stake (tickets)', CHtml::link("$stake ($tickets)", '/site/tickets?id='.$coin->id), 'coins');
	echo dmd_coin_stat('Ticket price', CHtml::link($ticketprice, "https://dcrstats.com/", array('target'=>'_blank')), 'chart');
}
else echo dmd_coin_stat('Algo', CHtml::encode($coin->algo), 'cpu');
echo '</ul>';

if (!empty($errors))
	echo '<p class="dmd-alert">'.CHtml::encode($errors).'</p>';

$index = '';
if($coin->difficulty)
	$index = round($coin->reward * $coin->price / $coin->difficulty * 10000, 3);

//////////////////////////////////////////////////////////////////////////////////////

// last week
$list_since = arraySafeVal($_GET,'since',time()-(7*24*3600));
$list_since2 = arraySafeVal($_GET,'since',time()-(2*60*60));

$maxrows = (int) arraySafeVal($_GET,'rows', 50);
$maxrows = max($maxrows,  25);
$maxrows = min($maxrows, 250);

echo <<<end
<section class="dmd-card dmd-table-card" id="transactions">
<div class="dmd-card-head"><h2>Wallet transactions</h2></div>
<div class="dmd-scroll">
<table class="dmd-table dmd-admin-table">
<thead>
<tr>
<th>Status</th>
<th>Time</th>
<th class="r">Amount</th>
<th class="r">Block</th>
<th class="r">Difficulty</th>
<th class="r">Confirmations</th>
<th>Address</th>
<th>Tx</th>
</tr>
</thead><tbody>
end;

$account = '*';

$txs = $remote->listtransactions($account, $maxrows);

if (empty($txs)) {
	if (!empty($remote->error)) {
		echo '<tr><td colspan="8" class="dmd-warn">RPC Error: '.CHtml::encode($remote->error).'</td></tr>';
	}
	// retry...
	$txs = $remote->listtransactions($account, 200);
}

$txs_array = array(); $lastday = '';

if (!empty($txs)) {
	// to hide truncated days sums
	$tx = reset($txs);

	if (count($txs) == $maxrows && isset($tx['time']))
		$lastday = strftime('%F', $tx['time']);

	if (!empty($txs)) foreach($txs as $tx)
	{
		if (arraySafeVal($tx, 'time', $list_since2+1) > $list_since2)
			$txs_array[] = $tx;
	}

	krsort($txs_array);
}

$rows = 0;

foreach($txs_array as $tx)
{
	$category = arraySafeVal($tx,'category');
	if ($category == 'spent') continue;

	$block = null;
	if(isset($tx['blockhash']))
		$block = $remote->getblock($tx['blockhash']);

	$eta = '';
	if ($category == 'immature') {
		if ($coin->block_time && $coin->mature_blocks) {
			$t = (int) ($coin->mature_blocks - arraySafeVal($tx,'confirmations',0)) * $coin->block_time;
			$eta = "ETA: ".sprintf('%dh %02dmn', ($t/3600), ($t/60)%60);
		}
	}

	if (!isset($tx['time'])) {
		// martian wallets
		echo '<tr class="ssrow"><td colspan="8" class="dmd-wrap"><code>'.CHtml::encode(json_encode($tx)).'</code></td></tr>';
		continue;
	}

	echo '<tr class="ssrow">';

	$total = $coin->mature_blocks;
	$confirm = arraySafeVal($tx,'confirmations');
	$pct = $total ? min(100, intval($confirm/$total * 100)) : 0;

	if ($category == 'generate')
		$status = '<span class="dmd-badge done">Confirmed</span>';
	else if ($category == 'send')
		$status = '<span class="dmd-badge new">Payout</span>';
	else if ($category == 'orphan')
		$status = '<span class="dmd-badge orphan">Orphan</span>';
	else if ($category == 'immature')
		$status = '<span class="dmd-status" title="'.CHtml::encode($eta).'">'.$pct.'%<span class="dmd-bar"><i style="width:'.$pct.'%"></i></span></span>';
	else
		$status = '<span class="dmd-badge pool">Received</span>';
	echo '<td data-label="Status">'.$status.'</td>';

	$d = datetoa2($tx['time']);
	echo '<td data-label="Time" class="dmd-nowrap"><span>'.$d.' ago</span></td>';
	echo '<td data-label="Amount" class="r dmd-num">'.number_format($tx['amount'], 3, '.', ',').'</td>';

	if($block) {
		echo '<td data-label="Block" class="r">'.$block['height'].'</td>';
		echo '<td data-label="Difficulty" class="r">'.number_format($block['difficulty'], 3, '.', ',').'</td>';
	} else
		echo '<td data-label="Block"></td><td data-label="Difficulty"></td>';

	echo '<td data-label="Confirmations" class="r">'.arraySafeVal($tx,'confirmations').'</td>';

	echo '<td data-label="Address">';
	if(isset($tx['address']))
	{
		$address = $tx['address'];
		$exists = dboscalar("SELECT count(*) AS nb FROM accounts WHERE username=:address", array(':address'=>$address));

		$finder = $address ? substr($address, 0, 64).'' : '';
		$finder2 = $address ? substr($address, 0, 10).'...' : '';

		if ($exists)
			echo '<a class="dmd-addr" href="/mywallet/?address='.CHtml::encode($finder).'" target="_blank" title="'.CHtml::encode($address).'">'.CHtml::encode($finder2).'</a>';
		else if ($address == $coin->master_wallet)
			echo '<span class="dmd-badge new">New block</span>';
		else
			echo '<span class="dmd-addr" title="'.CHtml::encode($address).'">'.CHtml::encode($finder2).'</span>';
	}
	echo '</td>';

	echo '<td data-label="Tx">';
	if(!empty($block)) {
		$txid = arraySafeVal($tx, 'txid');
		$label = substr($txid, 0, 10);

		if ($coin->id == 62) {
			echo '<a class="dmd-addr" href="'.$coin->link_explorer.'/transaction/'.$txid.'" target="_blank">'.$label.'</a>';
		} if ($coin->link_explorer == NULL) {
			echo $coin->createExplorerLink($label, array('txid'=>$txid), array('target'=>'_blank', 'class'=>'dmd-addr'));
		} else {
			echo '<a class="dmd-addr" href="'.$coin->link_explorer.'/tx/'.$txid.'" target="_blank">'.$label.'</a>';
		}
	}
	echo '</td>';

	echo '</tr>';

	$rows++;
	if ($rows >= $maxrows) break;
}

if (!$rows)
	echo '<tr><td colspan="8" class="dmd-empty-row">No transaction in the last 2 hours.</td></tr>';

echo '</tbody></table></div>';

$url = '/site/coin?id='.$coin->id.'&since='.(time()-31*24*3600).'&rows='.($maxrows*2);
echo '<div class="dmd-more-row"><a class="dmd-button dmd-ghost dmd-sm" href="'.$url.'">Show more transactions</a></div>';
echo '</section>';

//////////////////////////////////////////////////////////////////////////////////////

// bookmarked addresses, made for quick sends between wallets
$list = getdbolist('db_bookmarks', "idcoin={$coin->id} ORDER BY lastused DESC");

if (!empty($list))
{
	echo '<section class="dmd-card dmd-table-card">';
	echo '<div class="dmd-card-head"><h2>Bookmarks</h2></div>';
	echo '<div class="dmd-scroll"><table class="dmd-table dmd-admin-table">';
	echo '<thead><tr><th>Name</th><th>Address</th><th>Last sent</th><th class="r"></th></tr></thead><tbody>';

	foreach($list as $bookmark)
	{
		echo '<tr class="ssrow bookmark">';
		echo '<td class="dmd-first" data-label="Name"><b>'.$bookmark->label.'</b></td>';

		echo '<td data-label="Address">';
		if (!empty($bookmark->address))
			echo '<span class="dmd-addr">'.$bookmark->address.'</span>';
		echo '</td>';

		$sent = datetoa2($bookmark->lastused);
		echo '<td data-label="Last sent">'.(empty($sent) ? "" : "$sent ago").'</td>';

		echo '<td class="r" data-label=""><span class="dmd-acts">';
		if (!empty($bookmark->address)) {
			$name = CJavaScript::encode($bookmark->label);
			$addr = CJavaScript::encode($bookmark->address);
			echo CHtml::link(dmd_admin_icon('send').'Send', "javascript:;", array(
				'class'=>'dmd-act',
				'onclick'=>"return showSellAmountDialog($name, $addr, 0, {$bookmark->id});"
			));
		}
		echo '<a class="dmd-act" href="/site/bookmarkEdit?id='.$bookmark->id.'">Edit</a>';
		echo '<a class="dmd-act dmd-danger" href="/site/bookmarkDel?id='.$bookmark->id.'">Delete</a>';
		echo '</span></td>';

		echo "</tr>";
	}

	echo "</tbody></table></div></section>";
}
