<?php
/*
 * Admin: the rigs mining now (/site/worker), for one algo (site/worker_results.php, refreshed every
 * 30 seconds).
 */

echo '<div class="dmd-admin">';
dmd_admin_head('Workers', null, null, '', 'The rigs connected to the pool now');

$algo = user()->getState('yaamp-algo');

$algos = yaamp_get_algos();
$algo_opts = '';
/**
foreach($algos as $a) {
	if($a == $algo)
		$algo_opts .= "<option value='$a' selected>$a</option>";
	else
		$algo_opts .= "<option value='$a'>$a</option>";
}
*/

if (!strstr($algo_opts, 'selected') && $this->admin) {
	$algo_opts = "<option value=\"$algo\" selected>$algo</option>" . $algo_opts;
}

echo <<<end
<div class="dmd-toolbar dmd-worker-algo">
<label class="dmd-field" for="algo_select">Algo <select id="algo_select" class="dmd-select">$algo_opts</select></label>
</div>
end;

?>

<div id='main_results'><div class="dmd-card dmd-empty">Loading the workers...</div></div>
</div>

<script>

$(function()
{
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
	var url = '/site/worker_results?algo=' + $('#algo_select').val();

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

$('#algo_select').bind('change', function() {
	main_refresh();
});

</script>
