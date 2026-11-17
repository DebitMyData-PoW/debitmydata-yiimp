<?php
/*
 * Admin: a coin's wallet (/site/coin?id=): what the pool owns and owes in this coin, the wallet's
 * figures and its latest transactions (site/coin_results.php, refreshed every 30 seconds), and
 * "Send payouts" (pays the balances that are due now).
 */

$id = getiparam('id');
$coin = getdbo('db_coins', $id);
if (!$coin) {
	$this->goback();
}

$this->pageTitle = 'Wallet - '.(is_string($coin->symbol) ? $coin->symbol : '[INVALID SYMBOL]');

// force a refresh after 10mn to prevent memory leaks in chrome
app()->clientScript->registerMetaTag('600', null, 'refresh');

if (!empty($coin->algo) && $coin->algo != 'PoS')
	user()->setState('yaamp-algo', $coin->algo);

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

$sellamount = $coin->balance;

$maxrows = arraySafeVal($_REQUEST,'rows', 500);
$since = arraySafeVal($_REQUEST,'since', time() - (7*24*3600)); // one week
$since2 = arraySafeVal($_REQUEST,'since', time() - (2*60*60)); // 2 hours

app()->clientScript->registerCoreScript('jquery.ui'); // dialog

$payout = '<a class="dmd-button" href="/site/payuserscoin?id='.$coin->id.'" '
	.'onclick="return confirm(\'Send the payouts that are due now?\');">'.dmd_admin_icon('send').'Send payouts</a>';

echo '<div class="dmd-admin">';
dmd_admin_head(CHtml::encode($coin->name).' <small>wallet</small>', $coin, $info ? $info : false, $payout);

echo <<<END
<div id="main_results"><div class="dmd-card dmd-empty">Loading the wallet...</div></div>
</div>

<script type="text/javascript">

function uninstall_coin()
{
	if(!confirm("Uninstall this coin?"))
		return;

	window.location.href = '/site/uninstallcoin?id=$coin->id';
}

var main_delay=30000;
var main_timeout;

function main_refresh()
{
	var url = "/site/coin_results?id={$coin->id}&rows={$maxrows}&since={$since2}";

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

function showSellAmountDialog(marketname, address, marketid, bookmarkid)
{
	$("#dlgaddr").html(address);
	$("#sell-amount-dialog").dialog(
	{
		autoOpen: true,
		width: 400,
		height: 240,
		modal: true,
		title: 'Send {$coin->symbol} to '+marketname,

		buttons:
		{
			"Send / Sell": function()
			{
				amount = $('#input_sell_amount').val();
				if (marketid > 0)
					window.location.href = '/market/sellto?id='+marketid+'&amount='+amount;
				else
					window.location.href = '/site/bookmarkSend?id='+bookmarkid+'&amount='+amount;
			},
		}
	});
	return false;
}

</script>

<div id="sell-amount-dialog" style="display: none;">
<p>Address: <code id="dlgaddr">xxxxxxxxxxxx</code></p>
<p><label>Amount <input type="text" id="input_sell_amount" class="dmd-input" value="$sellamount"></label></p>
</div>

END;

JavascriptReady("main_refresh();");

if ($coin->watch) {
	$this->renderPartial('coin_market_graph', array('coin'=>$coin));
	JavascriptReady("$(window).resize(graph_resized);");
}
