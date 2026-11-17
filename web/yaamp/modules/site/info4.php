<?php


$id = getiparam('id');
$coin = getdbo('db_coins', $id);
if (!$coin) {
	$this->goback();
}

$this->pageTitle = 'Wallet - '.$coin->symbol;

// force a refresh after 10mn to prevent memory leaks in chrome
app()->clientScript->registerMetaTag('600', null, 'refresh');

if (!empty($coin->algo) && $coin->algo != 'PoS')
	user()->setState('yaamp-algo', $coin->algo);

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

$sellamount = $coin->balance;
//if ($info) $sellamount = floatval($sellamount) - arraySafeVal($info, "paytxfee") * 3;


$maxrows = arraySafeVal($_REQUEST,'rows', 500);
$since = arraySafeVal($_REQUEST,'since', time() - (7*24*3600)); // one week
$since2 = arraySafeVal($_REQUEST,'since', time() - (1*3600)); // one hour

echo '<div id="main_actions">';

app()->clientScript->registerCoreScript('jquery.ui'); // dialog

echo <<<END

</div>

<style type="text/css">
table.dataGrid a.red, table.dataGrid a.red:visited, a.red { color: darkred; }
div#main_actions {
	position: absolute; top: 60px; right: 16px; width: 280px; text-align: right;
}

div#markets {
	overflow-x: hidden; overflow-y: scroll; max-height: 156px;
}
div#transactions {
	overflow-x: hidden; overflow-y: hidden; min-height: 200px; max-height: 500px;
	margin-bottom: 8px;
}
div#sums {
	overflow-x: hidden; overflow-y: scroll; min-height: 250px; max-height: 600px;
	width: 380px; float: left; margin-top: 16px; margin-bottom: 8px; margin-right: 16px;
}

.page .footer { clear: both; width: auto; margin-top: 16px; }
tr.ssrow.bestmarket { background-color: #dfd; }
tr.ssrow.disabled { background-color: #fdd; color: darkred; }
tr.ssrow.orphan { color: darkred; }
</style>

<div id="main_results"></div>

<script type="text/javascript">

var main_delay=30000;
var main_timeout;

function main_refresh()
{
	var url = "/site/coin2_results?id={$id}&rows={$maxrows}&since2={$since2}";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

function main_ready(data)
{
	$('#main_results').html(data);
	$(window).trigger('resize'); // will draw graph
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}
</script>

<div id="sell-amount-dialog" style="display: none;">
<br>
Address: <span id="dlgaddr">xxxxxxxxxxxx</span><br><br>
Amount: <input type=text id="input_sell_amount" value="$sellamount">
<br>
</div>

END;

JavascriptReady("main_refresh();");


//////////////////////////////////////////////////////////////////////////////////////
