<?php

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


//////////////////////////////////////////////////////////////////////////////////////


//////////////////////////////////////////////////////////////////////////////////////

// last week
$list_since = arraySafeVal($_GET,'since',time()-(7*24*3600));

// last hour
$list_since2 = arraySafeVal($_GET,'since',time()-(1*3600));

$maxrows = (int) arraySafeVal($_GET,'rows', 500);
$maxrows = max($maxrows, 200);
$maxrows = min($maxrows, 200);

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Payouts</div>";
echo "<div class='main-left-inner'>";

echo <<<end
<div id="transactions">
<table class="dataGrid5">
<thead class="">
<tr align='center' style='font-weight: normal;  color: #9699A1; font-family: interblack; font-size: 12px; font-variant: small-caps; text-transform: uppercase;'>
<th width='400'>Status</th>
<th width='100'>Time</th>
<th width='300'>Amount</th>
<!-- <th width='100'>Block</th> -->
<!-- <th width='100'>Difficulty</th> -->
<!-- <th width='100'>Confirm</th>  -->
<th width='300'>Address</th>
<th width='400'>Tx</th>
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
	$txs = $remote->listtransactions($account, 100);
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

  if ($category == 'send')

  {

echo '<tr class="ssrow">';

echo '<td align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">';

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


	$total = $coin->mature_blocks;
	$confirm = arraySafeVal($tx,'confirmations');
   	$percent = intval($confirm/$total * 100)."%";

	if ($category == 'generate') {
	echo '<span style="font-size: 9px;">Confirmed</span>';
	}
	else if ($category == 'send') {
	echo '<span style="font-size: 9px; color:#999999;">Payout</span>';
	}
	else if ($category == 'immature') {
	echo '<span>'.$percent.'</span>';
	}
echo '</td>';


//	echo '<td>'.arraySafeVal($tx,'confirmations').'</td>';
//	echo '<td title="'.$eta.'"><n style="font-size: 9px;">'.$category.'</n></td>';

//	echo '<td>'.$category.'</td>';
//	echo '<td title="'.$eta.'">'.$category.'</td>';





/**
	if (!isset($tx['time'])) {
		// martian wallets
		echo '<td colspan="8">'.json_encode($tx).'</td>';
		continue;
	}
*/

	$d = datetoa2($tx['time']);
	echo '<td align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 10px; font-variant: small-caps; text-transform: uppercase;">'.$d.'</td>';

//	echo '<td align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">'.$tx['amount'].'</td>';
	echo '<td align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 11px; font-variant: small-caps; text-transform: uppercase;">'.number_format($tx['amount']	, 2, '.', ',').'</td>';


//	if($block) {
//		echo '<td>'.$block['height'].'</td><td>'.round_difficulty($block['difficulty']).'</td>';
//	} else
//		echo '<td></td><td></td>';

//	echo '<td>'.arraySafeVal($tx,'confirmations').'</td>';

	echo '<td align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 11px;">';
	if(isset($tx['address']))
	{
		$address = $tx['address'];
		$address2 = substr($address, 0, 4).'...';
		$exists = dboscalar("SELECT count(*) AS nb FROM accounts WHERE username=:address", array(':address'=>$address));
	//	if ($exists)
	//		echo CHtml::link($address, '/?address='.$address);
	//	else if ($address == 'bMei9CDx2d8oh95ztRXuV1Zyzvck65zxAW')
	//	{
	//		echo 'Pool';
	//	}
	//	else
	//		echo $address2.'<br>';
		echo "<a href=https://pool.debitmydata.io?address=$address target='_blank'>$address2</a>";
	}
	echo '</td>';


	echo '<td align="center" class="row" style="font-weight: normal;  color: #999999; font-family: interblack; font-size: 11px;">';
	if(!empty($block)) {
		$txid = arraySafeVal($tx, 'txid');
		$label = substr($txid, 0, 4).'...';
		echo $coin->createExplorerLink($label, array('txid'=>$txid), array('target'=>'_blank'));
	}
	echo '</td>';


	echo '</tr>';

	$rows++;
	if ($rows >= $maxrows) break;
  }

}

echo '</tbody></table>';
echo '</div>';
echo '</div>';


$url = '/site/coin?id='.$coin->id.'&since='.(time()-31*24*3600).'&rows='.($maxrows*2);
$moreurl = CHtml::link('Click here to show more transactions...', $url);

// echo '<div class="loadfooter" style="margin-top: 4px;">'.$moreurl.'</div>';
echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////


echo '<br><br><br><br><br>';
echo '<br><br><br><br><br>';

echo '</tbody></table></div>';

