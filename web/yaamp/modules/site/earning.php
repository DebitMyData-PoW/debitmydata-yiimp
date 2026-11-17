<?php
/*
 * Admin: the miners' earnings not paid yet (/site/earning, /site/earning?id=<coin> for one coin with
 * its totals), refreshed every 30 seconds (site/earning_results.php).
 */

$coin_id = getiparam('id');
$coin = null;
if ($coin_id) {
	$coin = getdbo('db_coins', $coin_id);
	$this->pageTitle = 'Earnings - '.$coin->symbol;
}

echo '<div class="dmd-admin">';
dmd_admin_head('Earnings', null, null, '', $coin ? 'Not paid yet, in '.CHtml::encode($coin->name) : 'The latest earnings not paid yet');

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

?>

<div id='main_results'><div class="dmd-card dmd-empty">Loading the earnings...</div></div>
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
	var url = '/site/earning_results?id=<?= $coin_id ?>';

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>

<?php

JavascriptReady("main_refresh();");
