<?php
/*
 * Block explorer: a coin's latest blocks (/explorer/<SYMBOL>), 20 a page, a search by height or
 * transaction, and the difficulty of the last 500 blocks.
 */

if (!$coin) $this->goback();

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");

$this->pageTitle = $coin->name." block explorer";

$start = (int) getiparam('start');

echo '<body oncontextmenu="return false;">';

echo <<<END
<script type="text/javascript">
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
});
</script>
END;

$multiAlgos = $coin->multialgos || versionToAlgo($coin, 0) !== false;

if (!$start || $start > $coin->block_height)
	$start = $coin->block_height;

echo '<div class="dmd-admin dmd-explorer">';
dmd_admin_head(CHtml::encode($coin->name).' <small>blocks</small>', $coin, null, '',
	'Height '.number_format($coin->block_height, 0, '.', ','));

echo '<section class="dmd-card dmd-table-card">';
echo '<div class="dmd-card-head"><h2>Blocks</h2></div>';
echo '<div class="dmd-scroll"><table class="dmd-table dmd-admin-table">';
echo "<thead>";
echo "<tr>";
echo "<th>Height</th>";
echo "<th>Time</th>";
echo "<th class='r'>Difficulty</th>";
if ($multiAlgos) echo "<th>Algo</th>";
echo "<th class='r'>Confirmations</th>";
echo "<th>Blockhash</th>";
echo "</tr>";
echo "</thead><tbody>";

$remote = new WalletRPC($coin);

$criteria = new CDbCriteria();
$criteria->condition = "t.category NOT IN ('stake','generated')";
$criteria->condition .= " AND IFNULL(coin.visible,1)=1"; // ifnull for rental
if(isset($r_algo)) {
	$criteria->addInCondition('t.algo', $r_algo);
}
$count = getparam('count');
$count = $count? $count: 1;

$criteria->limit = $count;
$criteria->order = 't.time DESC';
$db_blocks = getdbolistWith('db_blocks', 'coin', $criteria);

foreach($db_blocks as $db_block)
{
  for($i = $start; $i > max(1, $start-21); $i--)
  {
	$hash = $remote->getblockhash($i);
	$hash2 = $remote ? substr($remote->getblockhash($i), 0, 10).'...' : '';
	if(!$hash) continue;

	$block = $remote->getblock($hash);
	if(!$block) continue;

	$d = datetoa2($block['time']);
	$confirms = isset($block['confirmations'])? $block['confirmations']: '';
	$tx = count($block['tx']);
	$diff = $block['difficulty'];
	$diff2 = number_format($diff, 3, '.', ',');
	$algo = versionToAlgo($coin, $block['version']);

	$type = '';
	if (arraySafeval($block,'nonce',0) > 0) $type = 'PoW';
	else if (isset($block['auxpow'])) $type = 'Aux';
	else if (isset($block['mint']) || strstr(arraySafeVal($block,'flags',''), 'proof-of-stake')) $type = 'PoS';

	// nonce 256bits
	if ($type == '' && $coin->symbol=='ZEC') $type = 'PoW';

	echo '<tr class="ssrow">';
	echo '<td class="dmd-first" data-label="Height">'.$coin->createExplorerLink(dmd_admin_icon('cube').$i, array('height'=>$i), array('class'=>'dmd-height')).'</td>';
	echo '<td data-label="Time"><span>'.$d.' ago</span></td>';
	echo '<td data-label="Difficulty" class="r">'.$diff2.'</td>';
	if ($multiAlgos) echo '<td data-label="Algo">'.$algo.'</td>';
	echo '<td data-label="Confirmations" class="r">'.$confirms.'</td>';
	echo '<td data-label="Blockhash" title="'.$hash.'">';
	echo $coin->createExplorerLink($hash2, array('hash'=>$hash), array('class'=>'dmd-addr'));
	echo '</td>';
	echo "</tr>";
  }
}

echo "</tbody></table></div>";

$pager = '';
if ($start <= $coin->block_height - 20)
	$pager  = $coin->createExplorerLink('&larr; Newer', array('start'=>min($coin->block_height,$start+20)), array('class'=>'dmd-button dmd-ghost dmd-sm'));
if ($start != $coin->block_height)
	$pager .= $coin->createExplorerLink('Latest', array(), array('class'=>'dmd-button dmd-ghost dmd-sm'));
if ($start > 20)
	$pager .= $coin->createExplorerLink('Older &rarr;', array('start'=>max(1,$start-20)), array('class'=>'dmd-button dmd-ghost dmd-sm'));

echo '<div class="dmd-more-row"><div class="dmd-pager">'.$pager.'</div></div>';
echo '</section>';

$actionUrl = $coin->visible ? '/explorer/'.$coin->symbol : '/explorer/search?id='.$coin->id;

echo <<<end
<section class="dmd-card">
<div class="dmd-card-head"><h2>Search</h2></div>
<form class="dmd-inline-form" action="{$actionUrl}" method="POST">
<label class="visually-hidden" for="dmd-height">Block height</label>
<input id="dmd-height" type="text" name="height" class="dmd-input dmd-narrow" placeholder="Height" inputmode="numeric">
<label class="visually-hidden" for="dmd-txid">Transaction hash</label>
<input id="dmd-txid" type="text" name="txid" class="dmd-input" placeholder="Transaction hash" spellcheck="false">
<button type="submit" class="dmd-button">Search</button>
</form>
</section>
end;

if ($start != $coin->block_height) {
	echo '</div>';
	return;
}

echo <<<end
<section class="dmd-card dmd-chart">
<div class="dmd-card-head"><h2>Difficulty</h2><span class="dmd-small">The last 500 blocks</span></div>
<div id="diff_graph"></div>
</section>
</div>

<script type="text/javascript">

var last_graph_update = 0;

function graph_refresh()
{
	var now = Date.now()/1000;

	if (now < last_graph_update + 900) return;
	last_graph_update = now;

	var url = "/explorer/graph?id={$coin->id}";
	$.get(url, '', diff_graph_data);
}

function diff_graph_data_trace(data)
{
	 $('#diff_graph').html(data);
}

function diff_graph_data(data)
{
	$('#diff_graph').empty();
	data = JSON.parse(data);
	var data1 = data[0];
	if (!data1 || !data1.length) {
		$('#diff_graph').html('<div class="dmd-empty">No block to draw yet.</div>');
		return;
	}
	var c = window.dmdChartTheme();
	var options = {
		series: [{ name: 'Difficulty', data: data1 }],
		chart: { type: "area", height: 220, foreColor: c.fore, fontFamily: 'Inter, system-ui, sans-serif',
			toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
		colors: [c.red || '#e0112b'],
		stroke: { curve: "smooth", width: 2 },
		dataLabels: { enabled: false },
		markers: { size: 0, hover: { size: 5 } },
		xaxis: { type: "datetime", axisBorder: { show: false }, axisTicks: { show: false }, labels: { datetimeUTC: false } },
		yaxis: { decimalsInFloat: 2, labels: { formatter: function(val) { return val.toLocaleString(); } } },
		grid: { borderColor: c.grid, padding: { left: 8, right: 8 } },
		tooltip: { x: { format: "dd MMM yyyy HH:mm:ss" }, theme: c.tooltip },
		legend: { show: false },
		fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 100] } }
	};
	var chart = new ApexCharts(document.querySelector("#diff_graph"), options);
	chart.render();
	(window.dmdCharts = window.dmdCharts || []).push(chart);
}

</script>
end;

app()->clientScript->registerScript('graph',"
	graph_refresh();
", CClientScript::POS_READY);
