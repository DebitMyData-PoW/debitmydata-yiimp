<?php
/*
 * Admin: a coin wallet's peers (/site/peers?id=): add a node, the connected nodes (remove one), and
 * the addnode= lines for a wallet's config file.
 */

if (!$coin) $this->goback();

$this->pageTitle = 'Peers - '.$coin->symbol;

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

echo '<div class="dmd-admin">';
dmd_admin_head(CHtml::encode($coin->name).' <small>peers</small>', $coin, $info ? $info : false);

//////////////////////////////////////////////////////////////////////////////////////

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<section class="dmd-card">
<div class="dmd-card-head"><h2>Add a node</h2></div>
<form class="dmd-inline-form" action="/site/peerAdd?id={$coin->id}" method="post">
<label class="visually-hidden" for="dmd-node">Node address</label>
<input id="dmd-node" type="text" name="node" class="dmd-input" placeholder="addr[:port]" autocomplete="off">
<button type="submit" class="dmd-button">Add node</button>
</form>
</section>
<section class="dmd-card dmd-table-card">
<div class="dmd-card-head"><h2>Connected nodes</h2></div>
<div class="dmd-scroll">
end;

dmd_admin_table('maintable', "{
	headers: {
		0:{sorter:'text'},
		1:{sorter:'text'},
		2:{sorter:'numeric'},
		3:{sorter:'numeric'},
		4:{sorter:false}
	},
	widgets: ['zebra','Storage','saveSort'],
	widgetOptions: {
		saveSort: true
	}
}");

echo <<<end
<thead>
<tr>
<th>Address</th>
<th>Version</th>
<th class="r">Height</th>
<!-- <th>Ping</th>
<th>Services</th>
<th>Since</th> -->
<th>Last</th>
<!-- <th>Rx / Tx (kB)</th> -->
<th class="r"></th>
</tr>
</thead><tbody>
end;

$addnode = array();
$version = '';
$localheight = arraySafeVal($info, 'blocks');

$list = $remote->getpeerinfo();

if(!empty($list))
foreach($list as $peer)
{
	echo '<tr class="ssrow">';

	$node = arraySafeVal($peer,'addr');
	echo '<td class="dmd-first" data-label="Address"><span class="dmd-addr">'.CHtml::encode($node).'</span></td>';

	$addnode[] = ($coin->rpcencoding=='DCR' ? 'addpeer=' : 'addnode=') . $node;

	$peerver = trim(arraySafeVal($peer,'subver'),'/');
	$version = max($version, $peerver);
	echo '<td data-label="Version">'.CHtml::encode($peerver).'</td>';

	$height = arraySafeVal($peer,'currentheight');
	$class = abs($height - $localheight) > 5 ? 'dmd-warn' : '';
	if (!$height) $height = arraySafeVal($peer,'synced_blocks');
	echo '<td data-label="Height" class="r '.$class.'"'.($class ? ' title="More than 5 blocks from this wallet"' : '').'>'.$height.'</td>';

//	echo '<td>'.arraySafeVal($peer,'pingtime','').'</td>';
//	echo '<td>'.arraySafeVal($peer,'services','').'</td>';

	$conntime = arraySafeVal($peer,'conntime',time());
	$startingheight = arraySafeVal($peer,'startingheight');
//	echo '<td>'.datetoa2($conntime)." ($startingheight)".'</td>';

	$lastrecv = arraySafeVal($peer,'lastrecv',time());
	$lastsend = arraySafeVal($peer,'lastsend',time());
	echo '<td data-label="Last"><span>'.datetoa2(max($lastrecv,$lastsend)).' ago</span></td>';

	$bytesrecv = round(arraySafeVal($peer,'bytesrecv')/1024.,1);
	$bytessent = round(arraySafeVal($peer,'bytessent')/1024.,1);
//	if ($bytesrecv+$bytessent)
//		echo '<td>'."$bytesrecv / $bytessent".'</td>';
//	else
//		echo '<td></td>';

	echo '<td class="r" data-label="">';
	$options = array('class'=>'dmd-act dmd-danger', 'title'=>'Disconnect from node');
	echo CHtml::link('Remove','/site/peerRemove?id='.$coin->id.'&node='.$node, $options);
	echo '</td>';

	echo '</tr>';
}
else
	echo '<tr><td colspan="5" class="dmd-empty-row">No node connected.</td></tr>';

echo '</tbody></table></div></section>';

echo '<section class="dmd-card">';
echo '<div class="dmd-card-head"><h2>Versions</h2></div>';
echo '<dl class="dmd-kv dmd-kv-wide">';
echo '<div><dt>This wallet</dt><dd>'.formatWalletVersion($coin).'</dd></div>';
echo '<div><dt>Latest of the nodes</dt><dd>'.CHtml::encode($version).'</dd></div>';
echo '</dl>';
echo '<p class="dmd-small">For a wallet\'s config file:</p>';
echo '<pre class="dmd-codebox">';
echo CHtml::encode(implode("\n",$addnode));
echo '</pre>';
echo '</section>';

echo '</div>';

//echo json_encode($list);
