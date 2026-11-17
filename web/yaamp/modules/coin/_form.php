<?php
/*
 * Admin: add a coin (/coin/create) or change its name, symbol, algo and image (/coin/update).
 * All the other settings are on the coin's settings page (site/coin_form.php).
 */

$this->widget('UniForm');

echo '<div class="dmd-admin dmd-coin-form">';
dmd_admin_head($update ? 'Edit '.CHtml::encode($coin->symbol) : 'Add a coin', $update ? $coin : null, null,
	'<a class="dmd-button dmd-ghost" href="/coin">'.dmd_admin_icon('back').'Coins</a>',
	$update ? '' : 'Name, symbol and algo are enough to start; the rest is on the coin\'s settings page.');

echo CUFHtml::beginForm();
echo CUFHtml::errorSummary($coin);
echo CUFHtml::openTag('fieldset', array('class'=>'inlineLabels'));
echo '<div class="dmd-card">';

if(!$coin->installed)
{
	echo CUFHtml::openActiveCtrlHolder($coin, 'name');
	echo CUFHtml::activeLabelEx($coin, 'name');
	echo CUFHtml::activeTextField($coin, 'name');
	echo "<p class='formHint2'>The coin's name, for example DebitMyData</p>";
	echo CUFHtml::closeCtrlHolder();

	echo CUFHtml::openActiveCtrlHolder($coin, 'symbol');
	echo CUFHtml::activeLabelEx($coin, 'symbol');
	echo CUFHtml::activeTextField($coin, 'symbol');
	echo "<p class='formHint2'>All upper case, for example DMD</p>";
	echo CUFHtml::closeCtrlHolder();

	echo CUFHtml::openActiveCtrlHolder($coin, 'algo');
	echo CUFHtml::activeLabelEx($coin, 'algo');
	echo CUFHtml::activeTextField($coin, 'algo');
	echo "<p class='formHint2'>All lower case, for example sha256</p>";
	echo CUFHtml::closeCtrlHolder();

	echo CUFHtml::openActiveCtrlHolder($coin, 'image');
	echo CUFHtml::activeLabelEx($coin,'image');
	echo CUFHtml::activeTextField($coin, 'image');
	echo "<p class='formHint2'>Address of the coin's logo, for example /images/coin-DMD.png</p>";
	echo CUFHtml::closeCtrlHolder();
}
else
	echo '<p class="dmd-small">This coin is installed: change it on its settings page.</p>';

/********************************
echo CUFHtml::openActiveCtrlHolder($coin, 'link_bitcointalk');
echo CUFHtml::activeLabelEx($coin, 'link_bitcointalk');
echo CUFHtml::activeTextField($coin, 'link_bitcointalk');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_github');
echo CUFHtml::activeLabelEx($coin, 'link_github');
echo CUFHtml::activeTextField($coin, 'link_github');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_site');
echo CUFHtml::activeLabelEx($coin, 'link_site');
echo CUFHtml::activeTextField($coin, 'link_site');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_exchange');
echo CUFHtml::activeLabelEx($coin, 'link_exchange');
echo CUFHtml::activeTextField($coin, 'link_exchange');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_explorer');
echo CUFHtml::activeLabelEx($coin, 'link_explorer');
echo CUFHtml::activeTextField($coin, 'link_explorer');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();
***********************************/

echo '</div>';
echo CUFHtml::closeTag('fieldset');
showSubmitButton($update? 'Save': 'Create');
echo CUFHtml::endForm();

echo '</div>';
