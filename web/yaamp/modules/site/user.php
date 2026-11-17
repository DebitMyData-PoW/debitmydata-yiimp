<?php
/*
 * Admin: the pool's users (/site/user): the accounts with a balance or a rig mining now
 * (site/user_results.php, refreshed every 30 seconds).
 */

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo '<div class="dmd-admin">';
dmd_admin_head('Users', null, null, '', 'Accounts with a balance or a rig mining now');

$symbol = getparam('symbol');

$coins = "<option value='all'>-all-</option>";
$list = getdbolist('db_coins', "enable AND (".
	"id IN (SELECT DISTINCT coinid FROM accounts WHERE balance>0.0001) ".
	"OR id IN (SELECT DISTINCT coinid from earnings) ) ORDER BY symbol");
foreach($list as $coin)
{
	if($coin->symbol == $symbol)
		$coins .= '<option value="'.$coin->symbol.'" selected>'.$coin->symbol.'</option>';
	else
		$coins .= '<option value="'.$coin->symbol.'">'.$coin->symbol.'</option>';
}

echo <<<end
<!--
<div align="right" style="margin-top: -14px; margin-bottom: -6px; margin-right: 140px;">
Select coin: <select id='coin_select'>$coins</select>&nbsp;
</div>
-->

<div id='main_results'><div class="dmd-card dmd-empty">Loading the users...</div></div>
</div>

<script>

$(function()
{
	$('#coin_select').change(function(event)
	{
		var symbol = $('#coin_select').val();
		window.location.href = '/site/user?symbol='+symbol;
	});

	main_refresh();
});

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
	var symbol = $('#coin_select').val();
	var url = "/site/user_results?symbol="+symbol;

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>
end;
