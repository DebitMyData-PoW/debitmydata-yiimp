<?php

function WriteBoxHeader($title)
{
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>$title</div>";
	echo "<div class='main-left-inner'>";
}

WriteBoxHeader("Transactions");



$coin = getdbo('db_coins', getiparam('id'));
if (!$coin) $this->goback();
$PoS = ($coin->algo == 'PoS'); // or if 'stake' key is present in 'getinfo' method
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
$bookmarkAdd = CHtml::link('+', "/site/bookmarkAdd?id=".$coin->id, array('title'=>'Add a bookmark'));
$list = getdbolist('db_markets', "coinid={$coin->id} AND NOT deleted ORDER BY disabled, priority DESC, price DESC");
$bestmarket = getBestMarket($coin);
$errors = isset($info['errors'])? $info['errors']: '';
$balance = isset($info['balance'])? $info['balance']: '';
$txfee = isset($info['paytxfee'])? $info['paytxfee']: '';
$connections = isset($info['connections'])? CHtml::link($info['connections'],'/site/peers?id='.$coin->id): '';
$blocks = isset($info['blocks'])? $info['blocks']: '';
$index = '';
if($coin->difficulty)
$list_since = arraySafeVal($_GET,'since',time()-(7*24*3600));
	$index = round($coin->reward * $coin->price / $coin->difficulty * 10000, 3);
$maxrows = (int) arraySafeVal($_GET,'rows', 500);
$maxrows = max($maxrows,  250);
$maxrows = min($maxrows, 2500);




echo <<<end
<div id="transactions">
<table class="dataGrid">
<thead class="">
<tr align='center' style='font-weight: normal;  color: #9699A1; font-family: interblack; font-size: 12px; font-variant: small-caps; text-transform: uppercase;'>
<th width='100'>Status</th>
<th width='100'>Time</th>
<th width='100'>Amount</th>
<th width='100'>Block</th>
<th width='100'>Difficulty</th>
<!-- <th width='100'>Confirm</th>  -->
<th width='100'>Address</th>
<th width='100'>Tx</th>
</tr>
</thead><tbody>
end;

$account = '*';

$txs = $remote->listtransactions($account, $maxrows);

if (empty($txs)) {
	if (!empty($remote->error)) {
		echo "<b>RPC Error: {$remote->error}</b><p/>";
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
		if (arraySafeVal($tx, 'time', $list_since+1) > $list_since)
			$txs_array[] = $tx;
	}

	krsort($txs_array);
}

$rows = 0;


foreach($txs_array as $tx)
{

echo '<tr class="ssrow"  align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">';

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





	$total = 100;
	$confirm = arraySafeVal($tx,'confirmations');
   	$percent = intval($confirm/$total * 100)."%";

	if ($category == 'generate') {
	echo '<td><n style="font-size: 9px;">Confirmed</n></td>';
	}
	else if ($category == 'send') {
	echo '<td><n style="font-size: 9px; color:#BC9600;">Payout</n></td>';
	}
	else if ($category == 'immature') {
	echo '<td>'.$percent.'</td>';
//	echo '<td>'.arraySafeVal($tx,'confirmations').'</td>';
//	echo '<td title="'.$eta.'"><n style="font-size: 9px;">'.$category.'</n></td>';
	}

//	echo '<td>'.$category.'</td>';
//	echo '<td title="'.$eta.'">'.$category.'</td>';






	if (!isset($tx['time'])) {
		// martian wallets
		echo '<td colspan="8">'.json_encode($tx).'</td>';
		continue;
	}

	$d = datetoa2($tx['time']);
	echo '<td>'.$d.'</td>';
	echo '<td>'.$tx['amount'].'</td>';

	if($block) {
		echo '<td>'.$block['height'].'</td><td>'.round_difficulty($block['difficulty']).'</td>';
	} else
		echo '<td></td><td></td>';

//	echo '<td>'.arraySafeVal($tx,'confirmations').'</td>';

	echo '<td style="font-size: 10px;">';
	if(isset($tx['address']))
	{
		$address = $tx['address'];
		$exists = dboscalar("SELECT count(*) AS nb FROM accounts WHERE username=:address", array(':address'=>$address));
		if ($exists)
			echo CHtml::link($address, '/?address='.$address);
		else if ($address == 'bMei9CDx2d8oh95ztRXuV1Zyzvck65zxAW')
		{
			echo 'Pool';
		}
		else
			echo $address.'<br>';
	}
	echo '</td>';


	echo '<td>';
	if(!empty($block)) {
		$txid = arraySafeVal($tx, 'txid');
		$label = substr($txid, 0, 7);
		echo $coin->createExplorerLink($label, array('txid'=>$txid), array('target'=>'_blank'));
	}
	echo '</td>';


	echo '</tr>';

	$rows++;
	if ($rows >= $maxrows) break;
}

echo '</tbody></table>';



//////////////////////////////////////////////////////////////////////////////////////
