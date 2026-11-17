<?php
/*
 * Admin: what the pool will pay (/site/payments, /site/payments?id=<coin> for one coin with its
 * totals): balances, immature earnings and failed payouts, refreshed every 30 seconds
 * (site/payments_results.php).
 */

$coin_id = getiparam('id');
$coin = null;
if ($coin_id) {
	$coin = getdbo('db_coins', $coin_id);
	$this->pageTitle = 'Payments - '.$coin->symbol;
}

echo '<div class="dmd-admin">';
dmd_admin_head('Payments', null, null, '', $coin ? 'Balances to pay in '.CHtml::encode($coin->name) : 'Balances to pay, immature earnings and failed payouts');

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

?>

<div id="main_results"><div class="dmd-card dmd-empty">Loading the payments...</div></div>
</div>

<script type="text/javascript">

var main_delay=30000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = '/site/payments_results?id=<?= $coin_id ?>';

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>

<?php

JavascriptReady("main_refresh();");
