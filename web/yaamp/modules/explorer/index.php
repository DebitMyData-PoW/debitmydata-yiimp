<?php
/*
 * Block explorer without a coin (/explorer): the coins it can show.
 */

$coins = getdbolist('db_coins', ($this->admin ? "enable" : "enable AND visible")." ORDER BY name");

echo '<div class="dmd-admin dmd-explorer">';
dmd_admin_head('Block explorer', null, null, '', 'Choose a coin');

echo '<section class="dmd-card dmd-table-card"><div class="dmd-scroll">';
echo '<table class="dmd-table dmd-admin-table"><thead><tr><th>Coin</th><th class="r">Height</th></tr></thead><tbody>';
foreach ($coins as $coin) {
	echo '<tr class="ssrow">';
	echo '<td class="dmd-first" data-label="Coin">'.$coin->createExplorerLink(dmd_admin_coin_cell($coin), array(), array(), true).'</td>';
	echo '<td class="r" data-label="Height">'.($coin->block_height ? number_format($coin->block_height, 0, '.', ',') : '').'</td>';
	echo '</tr>';
}
if (empty($coins))
	echo '<tr><td colspan="2" class="dmd-empty-row">No coin to show.</td></tr>';
echo '</tbody></table></div></section>';
echo '</div>';
