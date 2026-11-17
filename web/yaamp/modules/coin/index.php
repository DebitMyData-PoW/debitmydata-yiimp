<?php
/*
 * Admin: the pool's coins (/coin), the admin's first page. Each coin: its wallet page, its settings,
 * and whether it is on. "Add a coin" opens /coin/create.
 */

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

$coins = getdbolist('db_coins', "1 ORDER BY enable DESC");

$total_active = 0;
$total_installed = 0;
foreach($coins as $coin) {
	if($coin->enable) $total_active++;
	if($coin->installed) $total_installed++;
}
$total = count($coins);

echo '<div class="dmd-admin">';
dmd_admin_head('Coins', null, null,
	'<a class="dmd-button" href="/coin/create">'.dmd_admin_icon('plus').'Add a coin</a>',
	"$total ".($total == 1 ? 'coin' : 'coins').", $total_active on");

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';

dmd_admin_table('maintable', "{
	widgets: ['zebra'],
	headers: { 4: { sorter: false } }
}");

echo <<<end
<thead><tr>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Algo</th>
<th data-sorter="digit">Height</th>
<th data-sorter="text">Status</th>
<th class="r">Pages</th>
</tr></thead>
<tbody>
end;

foreach($coins as $coin)
{
	$status = $coin->enable ? '<span class="dmd-badge done">On</span>' : '<span class="dmd-badge orphan">Off</span>';
	if (!$coin->enable && $coin->installed) $status .= ' <span class="dmd-small">installed</span>';

	echo '<tr class="ssrow">';
	echo '<td class="dmd-first" data-label="Coin">'.dmd_admin_coin_cell($coin, '/site/coin?id='.$coin->id).'</td>';
	echo '<td data-label="Algo">'.CHtml::encode($coin->algo).'</td>';
	echo '<td data-label="Height" class="dmd-num">'.($coin->block_height ? number_format($coin->block_height, 0, '.', ',') : '').'</td>';
	echo '<td data-label="Status">'.$status.'</td>';
	echo '<td data-label="Pages" class="r"><span class="dmd-acts">';
	echo '<a class="dmd-act" href="/site/coin?id='.$coin->id.'">'.dmd_admin_icon('wallet').'Wallet</a>';
	echo '<a class="dmd-act" href="/site/update?id='.$coin->id.'">'.dmd_admin_icon('gear').'Settings</a>';
	echo '</span></td>';
	echo "</tr>";
}

echo "</tbody></table></div>";
echo '<p class="dmd-count">'."$total coins, $total_installed installed, $total_active running".'</p>';
echo '</section>';
echo '</div>';
