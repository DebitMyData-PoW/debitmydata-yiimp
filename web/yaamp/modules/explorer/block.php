<?php
/*
 * Block explorer: one block (/explorer/<SYMBOL>?height= or ?hash= or ?txid=): its details, its
 * transactions (click a transaction's hash for its raw data) and a search.
 */

if (!$coin) return;

$this->pageTitle = $coin->name." block explorer";

$txid = getparam('txid');
$q = getparam('q');
if (!empty($q) && ctype_xdigit($q)) $txid = $q;
elseif (empty($txid)) $txid = 'txid not set'; // prevent highlight

echo '<body oncontextmenu="return false;">';

echo <<<END
<script type="text/javascript">
function toggleRaw(el) {
	$(el).parents('tr').next('tr.raw').toggle();
}
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
	$('span.txid').bind('click', function(el) { toggleRaw(el.target); });
	$('span.txid:contains("{$txid}")').addClass('dmd-found');
});
</script>
END;

function colorizeJson($json)
{
	$json = str_replace('"', '&quot;', $json);
	// strings
	$res = preg_match_all("# &quot;([^&]+)&quot;([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$class = '';
		if (strlen($m) == 64 && ctype_xdigit($m)) $class = 'hash';
		if (strlen($m) == 34 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) == 35 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) > 160 && ctype_alnum($m)) $class = 'data';
		if ($class == '' && strlen($m) < 64 && ctype_xdigit($m)) $class = 'hexa';
		$json = str_replace(' &quot;'.$m."&quot;".$sfx, ' "<s class="'.$class.'">'.$m.'</s>"'.$sfx, $json);
	}
	// keys
	$res = preg_match_all("#&quot;([^&]+)&quot;:#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$json = str_replace('&quot;'.$m."&quot;", '"<s class="key">'.$m.'</s>"', $json);
	}
	// humanize timestamps like "blocktime": 1462359961,
	$res = preg_match_all("#: ([0-9]{10})([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$ts = intval($m);
		if ($ts > 1400000000 && $ts < 1600000000) {
			$sfx = $matches[2][$n];
			$date = strftime("<u>%Y-%m-%d %T %z</u>", $ts);
			$json = str_replace(' '.$m.$sfx, ' "'.$date.'"'.$sfx, $json);
		}
	}
	// numeric
	$res = preg_match_all("#: ([e\-\.0-9]+)([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$json = str_replace(' '.$m.$sfx, ' <i>'.$m.'</i>'.$sfx, $json);
	}

	$json = preg_replace('#\[\s+\]#', '[]', $json);

	return $json;
}

// Transaction scripts are shown in the raw data only
function simplifyscript($script)
{
	$script = preg_replace("/[0-9a-f]+ OP_DROP ?/","", $script);
	$script = preg_replace("/OP_NOP ?/","", $script);
	return trim($script);
}

//////////////////////////////////////////////////////////////////////////////////////////////////

$remote = new WalletRPC($coin);
$block = $remote->getblock($hash);
if(!$block) return;

$d = date('Y-m-d H:i:s', $block['time']);
$confirms = isset($block['confirmations'])? $block['confirmations']: '';
$txcount = count($block['tx']);

$difficulty = bitcoinvaluetoa($block['difficulty']);
$difficulty2 = number_format($difficulty, 8, '.', ',');

$version = dechex($block['version']);
$nonce = $block['nonce'];

echo '<div class="dmd-admin dmd-explorer">';
dmd_admin_head(CHtml::encode($coin->name).' <small>block '.$block['height'].'</small>', $coin, null, '',
	$coin->createExplorerLink('&larr; All blocks', array(), array('class'=>'dmd-more')));

function dmd_block_row($label, $value)
{
	echo '<div><dt>'.$label.'</dt><dd>'.$value.'</dd></div>';
}

echo '<section class="dmd-card">';
echo '<div class="dmd-card-head"><h2>Block</h2></div>';
echo '<dl class="dmd-kv dmd-kv-wide">';
dmd_block_row('Coin', $coin->createExplorerLink($coin->name));
dmd_block_row('Blockhash', '<span class="dmd-hash">'.$hash.'</span>');
dmd_block_row('Confirmations', $confirms);
dmd_block_row('Height', $block['height']);
dmd_block_row('Time', $d.' ('.$block['time'].')');
dmd_block_row('Difficulty', $difficulty2);
dmd_block_row('Bits', '<span class="dmd-hash">'.$block['bits'].'</span>');
dmd_block_row('Nonce', '<span class="dmd-hash">'.$nonce.'</span>');
dmd_block_row('Version', '<span class="dmd-hash">'.$version.'</span>');
dmd_block_row('Size', $block['size'].' bytes');

if(isset($block['flags']))
	dmd_block_row('Flags', '<span class="dmd-hash">'.$block['flags'].'</span>');

if(isset($block['previousblockhash']) && $coin->algo == 'x16r')
	dmd_block_row('Hash order', '<span class="dmd-hash">'.substr($block['previousblockhash'], -16).'</span>');

if(isset($block['previousblockhash']))
	dmd_block_row('Previous hash', $coin->createExplorerLink($block['previousblockhash'], array('hash'=>$block['previousblockhash']), array('class'=>'dmd-hash')));

if(isset($block['nextblockhash']))
	dmd_block_row('Next hash', $coin->createExplorerLink($block['nextblockhash'], array('hash'=>$block['nextblockhash']), array('class'=>'dmd-hash')));

dmd_block_row('Merkle root', '<span class="dmd-hash">'.$block['merkleroot'].'</span>');
dmd_block_row('Transactions', $txcount);
echo '</dl>';

echo '<details class="dmd-raw"><summary>Raw block data</summary>';
echo '<div class="dmd-codebox json">'.colorizeJson(json_encode($block, 128)).'</div>';
echo '</details>';
echo '</section>';

echo <<<end
<section class="dmd-card dmd-table-card">
<div class="dmd-card-head"><h2>Transactions</h2><span class="dmd-small">Click a transaction for its raw data</span></div>
<div class="dmd-scroll">
<table class="dmd-table dmd-admin-table dmd-tx">
<thead>
<tr>
<th class="ntx">#</th>
<th>Transaction hash</th>
<th class="r">Size</th>
<th class="r">Value</th>
<th>From</th>
<th>To (amount)</th>
</tr>
</thead><tbody>
end;

$ntx = 0;
foreach($block['tx'] as $txhash)
{
	$ntx++;

	$tx = $remote->getrawtransaction($txhash, 1);
	if(!$tx && ($ntx == 1 || $txid == $txhash)) {
		// some transactions are not found directly with getrawtransaction
		$tx = $remote->gettransaction($txhash);
		if ($tx && isset($tx['hex'])) {
			$hex = $tx['hex'];
			$tx = $remote->decoderawtransaction($hex);
			$tx['hex'] = $hex;
		} else {
			continue;
		}
	}
	if(!$tx) continue;

	$valuetx = 0;
	foreach($tx['vout'] as $vout)
		$valuetx += $vout['value'];
	$valuetx2 = number_format($valuetx, 8, '.', ',');

	echo '<tr class="ssrow">';
	echo '<td class="ntx dmd-muted" data-label="#">'.$ntx.'</td>';
	echo '<td class="dmd-first" data-label="Transaction hash"><span class="txid monospace">'.$tx['txid'].'</span></td>';

	$size = (strlen($tx['hex'])/2);
	echo '<td class="r" data-label="Size">'.$size.'</td>';
	echo '<td class="r dmd-num" data-label="Value">'.$valuetx2.'</td>';

	echo '<td data-label="From">';
	$segwit = false;
	foreach($tx['vin'] as $vin) {
		if(isset($vin['coinbase']))
			echo '<span class="dmd-badge new">Generation</span>';
		if(isset($vin['txinwitness']))
			$segwit = true;
	}
	if($segwit)
		echo ' <span class="dmd-badge pool" title="segwit">segwit</span>';
	echo "</td>";

	echo '<td class="dmd-to" data-label="To (amount)">';

	$nvout = count($tx['vout']);;
	if ($nvout > 500) echo "Too much addresses to display ($nvout)";
	else
	foreach($tx['vout'] as $vout)
	{
		$value = $vout['value'];
		$value2 = number_format($value, 8, '.', ',');
		if ($value == 0) continue;

		if(isset($vout['scriptPubKey']['addresses'][0]))
			echo '<span>'.$vout['scriptPubKey']['addresses'][0]." <small>($value2)</small></span>";
		else
			echo "<span><small>($value)</small></span>";
	}

	echo "</td>";
	echo '</tr><tr class="raw" style="display:none;"><td colspan="6"><div class="dmd-codebox json">';
	unset($tx['hex']);
	echo ($nvout > 500) ? 'truncated' : colorizeJson(json_encode($tx, 128));
	echo '</div></td>';
	echo "</tr>";

	if ($ntx > 100) {
		echo '<tr class="ssrow"><td colspan="6" class="dmd-empty-row">Too much transations to display...</td></tr>';
		break;
	}
}

if ($coin->rpcencoding == 'DCR' && isset($block['stx'])) {
	echo '<tr><th class="section" colspan="6">';
	echo 'Stake';
	echo '</th></tr>';

	$ntx = 0;
	foreach($block['stx'] as $txhash)
	{
		$ntx++;

		$stx = $remote->getrawtransaction($txhash, 1);
		if(!$stx) continue;

		$valuetx = 0;
		foreach($stx['vout'] as $vout)
			$valuetx += $vout['value'];

		echo '<tr class="ssrow">';
		echo '<td class="ntx dmd-muted" data-label="#">'.$ntx.'</td>';
		echo '<td class="dmd-first" data-label="Transaction hash"><span class="txid monospace">'.$stx['txid'].'</span></td>';

		$size = (strlen($stx['hex'])/2);
		echo '<td class="r" data-label="Size">'.$size.'</td>';
		echo '<td class="r dmd-num" data-label="Value">'.$valuetx.'</td>';

		echo '<td data-label="From">';
		if(isset($stx['vout'][0]['scriptPubKey']) && arraySafeVal($stx['vout'][0]['scriptPubKey'],'type') == 'stakesubmission')
			echo "Ticket";
		else foreach($stx['vin'] as $vin) {
			if (arraySafeVal($vin,'blockheight') > 0) {
				echo $coin->createExplorerLink($vin['blockheight'], array('height'=>$vin['blockheight']));
				echo '<br/>';
			}
		}
		echo "</td>";

		echo '<td class="dmd-to" data-label="To (amount)">';
		foreach($stx['vout'] as $vout)
		{
			$value = $vout['value'];
			$value2 =  number_format($value, 8, '.', ',');
			if ($value == 0) continue;

			if(isset($vout['scriptPubKey']['addresses'][0]))
				echo '<span>'.$vout['scriptPubKey']['addresses'][0]." <small>($value2)</small></span>";
			else
				echo "<span><small>($value2)</small></span>";
		}
		echo "</td>";

		echo '</tr><tr class="raw" style="display:none;"><td colspan="6"><div class="dmd-codebox json">';
		unset($stx['hex']);
		echo colorizeJson(json_encode($stx, 128));
		echo '</div></td>';
		echo '</tr>';
	}
}

echo '</tbody></table></div></section>';

$actionUrl = $coin->visible ? '/explorer/'.$coin->symbol : '/explorer/search?id='.$coin->id;

echo <<<end
<section class="dmd-card">
<div class="dmd-card-head"><h2>Search</h2></div>
<form class="dmd-inline-form" action="{$actionUrl}" method="POST">
<label class="visually-hidden" for="dmd-height">Block height</label>
<input id="dmd-height" type="text" name="height" class="dmd-input dmd-narrow" placeholder="Block height" inputmode="numeric">
<label class="visually-hidden" for="dmd-txid">Transaction hash</label>
<input id="dmd-txid" type="text" name="txid" class="dmd-input" placeholder="Tx hash" spellcheck="false">
<button type="submit" class="dmd-button">Search</button>
</form>
</section>
</div>
end;
