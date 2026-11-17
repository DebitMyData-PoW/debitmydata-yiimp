<?php
/*
 * Wallet page: the blocks this address was paid in, newest first, with the block, when it was
 * found, whether it was shared by the pool or found by the address's solo miner, the address's reward and part
 * of the block, and where the reward is:
 *   Maturing n/251   the block needs 251 confirmations before its rewards can be paid
 *   In balance       confirmed; paid at the next balance payout
 *   Paid             paid out from the balance
 *   In block n/251   paid to the address inside the block itself, spendable once it matures
 *   Paid in block    paid inside the block, now spendable
 *   Orphaned / Invalid
 * The block opens in the coin's explorer when one is set (block_explorer in the coins table, or the
 * Settings field "link_explorer"): its coinbase transaction there, as on the home page. Without one,
 * in the pool's own explorer.
 * The 10/20/50 selector reloads the list with more blocks. Refreshed every 30 seconds by
 * mywallet/index.php (with the number of blocks chosen).
 */

$user = getuserparam(getparam('address'));
if(!$user || $user->is_locked) return;

$count = isset($_GET['count']) && is_numeric($_GET['count']) ? (int)$_GET['count'] : 5;
$count = max(1, min($count, 600));

$earnings = getdbolist('db_earnings', "userid={$user->id} ORDER BY create_time DESC LIMIT :count", array(':count' => $count));

$showrental = (bool) YAAMP_RENTAL;

$blocks = array();
$hashes = array();
foreach ($earnings as $earning) {
	$b = getdbo('db_blocks', $earning->blockid);
	if (!$b) continue;
	$blocks[$earning->blockid] = $b;
	if ($earning->coinid && !empty($b->blockhash)) $hashes[$earning->coinid][] = $b->blockhash;
}
// shared or solo, and which rewards were paid inside the block (coinbase payouts)
$kinds = array();
$paid_in_block = array();
foreach ($hashes as $coinid => $list) {
	$kinds += yaamp_block_kinds($coinid, $list);
	$paid_in_block += yaamp_user_coinbase_paid($user->id, $coinid, $list);
}

$fmt_eta = function ($t) { return sprintf('%dh %02dmin', intdiv($t, 3600), intdiv($t, 60) % 60); };

// the coin's own explorer (an http(s) address), or '' for the pool's explorer
$explorers = array();
$coin_explorer = function ($coin) use (&$explorers) {
	if (!isset($explorers[$coin->id])) {
		$explorers[$coin->id] = '';
		foreach (array($coin->block_explorer, $coin->link_explorer) as $e) {
			$e = trim((string) $e);
			if (preg_match('#^https?://[^\s"<>]+$#i', $e)) { $explorers[$coin->id] = rtrim($e, '/'); break; }
		}
	}
	return $explorers[$coin->id];
};
$cube = '<span class="dmd-cube"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
	.'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2l9 5v10l-9 5-9-5V7z"/>'
	.'<path d="M3 7l9 5 9-5M12 12v10"/></svg></span>';

echo '<div class="dmd-list-tools"><label for="count-select">Show</label><select id="count-select" class="dmd-select">';
foreach (array(5, 10, 20, 50, 100, 200, 300, 600) as $n)
	echo "<option value=\"$n\"".($count == $n ? ' selected' : '').">$n</option>";
echo '</select><span>blocks</span></div>';

$rows = '';
foreach($earnings as $earning)
{
	$block = isset($blocks[$earning->blockid]) ? $blocks[$earning->blockid] : null;
	if (!$block) {
		debuglog("missing block id {$earning->blockid}!");
		continue;
	}
	$coin = getdbo('db_coins', $earning->coinid);
	$ago = datetoa2($earning->create_time).' ago';
	$when = date('M d H:i', $earning->create_time);

	if (!$coin) {
		// rented hashrate, paid in BTC
		if (!$showrental) continue;
		$percent = $block->amount ? percentvaluetoa($earning->amount * 100 / $block->amount) : 0;
		$rows .= '<tr><td data-label="Block">—<span class="dmd-when" title="'.$when.'">'.$ago.'</span></td><td data-label="Type">'
			.'<span class="dmd-badge pool">Rental</span></td><td data-label="Your reward" class="r"><span class="dmd-amount"><span class="dmd-reward">'
			.number_format($earning->amount, 3, '.', ',').' <small>BTC</small></span><span class="dmd-part">'.$percent.'% of the block</span></span></td>'
			.'<td data-label="Status" class="r"><span class="dmd-badge orphan">Paid</span></td></tr>';
		continue;
	}

	$maturity = $coin->mature_blocks ? (int) $coin->mature_blocks : 251;
	$confirmations = (int) $block->confirmations;
	$percent = $block->amount ? $earning->amount * 100 / $block->amount : 0;
	$symbol = CHtml::encode($coin->symbol);

	$height = number_format($block->height, 0, '.', ',');
	$explorer = $coin_explorer($coin);
	if ($explorer && (!empty($block->txhash) || !empty($block->blockhash))) {
		$url = $explorer . (!empty($block->txhash) ? '/tx/' . $block->txhash : '/block/' . $block->blockhash);
		$link = '<a class="dmd-height" href="' . CHtml::encode($url) . '" target="_blank" rel="noopener" title="Open in the explorer">'
			. $cube . $height . '</a>';
	}
	else
		$link = $coin->createExplorerLink($cube . $height, array('height' => $block->height), array('target' => '_blank', 'class' => 'dmd-height'));

	$kind = isset($kinds[$block->blockhash]) ? $kinds[$block->blockhash] : 'prop';
	if ($kind == 'solo')
		$type = '<span class="dmd-badge solo" title="Found by your solo miner, paid to your address in the block">Solo</span>';
	else
		$type = '<span class="dmd-badge pool" title="'.($kind == 'pplns' ? 'Shared by the pool (PPLNS)' : 'Shared by the pool (PROP)').'">Shared</span>';

	// where the reward is
	$eta = '';
	if ($coin->block_time && $confirmations < $maturity)
		$eta = ' Spendable in about '.$fmt_eta(max(0, $maturity - $confirmations) * $coin->block_time).'.';

	if ($earning->status == 0)
		$status = '<span class="dmd-badge new" title="The block needs '.$maturity.' confirmations.'.$eta.'">Maturing '.$confirmations.'/'.$maturity.'</span>';
	else if ($earning->status == 1)
		$status = '<span class="dmd-badge pool" title="Confirmed, paid at the next balance payout">In balance</span>';
	else if ($earning->status == 2 && !empty($paid_in_block[$block->blockhash])) {
		if ($block->category == 'immature' || $block->category == 'new')
			$status = '<span class="dmd-badge new" title="Paid to your address in this block.'.$eta.'">In block '.$confirmations.'/'.$maturity.'</span>';
		else if ($block->category == 'orphan')
			$status = '<span class="dmd-badge orphan">Orphaned</span>';
		else
			$status = '<span class="dmd-badge done" title="Paid to your address in this block, now spendable">Paid in block</span>';
	}
	else if ($earning->status == 2)
		$status = '<span class="dmd-badge done" title="Paid out from your balance">Paid</span>';
	else if ($earning->status == -1)
		$status = '<span class="dmd-badge orphan">Invalid</span>';
	else
		$status = '';

	$rows .= '<tr><td data-label="Block">'.$link.'<span class="dmd-when" title="'.$when.'">'.$ago.'</span></td>'
		.'<td data-label="Type">'.$type.'</td>'
		.'<td data-label="Your reward" class="r"><span class="dmd-amount"><span class="dmd-reward">'.number_format($earning->amount, 3, '.', ',').' <small>'.$symbol.'</small></span>'
		.'<span class="dmd-part">'.number_format($percent, 2, '.', ',').'% of the block</span></span></td>'
		.'<td data-label="Status" class="r">'.$status.'</td></tr>';
}

if ($rows === '') {
	echo '<p class="dmd-empty">No block has paid this address yet.</p>';
} else {
	echo '<table class="dmd-table dmd-earn-table"><thead><tr><th scope="col">Block</th><th scope="col">Type</th>'
		.'<th scope="col" class="r">Your reward</th><th scope="col" class="r">Status</th></tr></thead><tbody>'.$rows.'</tbody></table>';
}

?>
<script>
// More or fewer blocks: the wallet page's refresh keeps the number chosen.
$('#count-select').on('change', function () {
	window.dmd_blocks_count = this.value;
	var url = '/site/user_earning_results2?address=' + encodeURIComponent(<?= json_encode($user->username, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>) + '&count=' + encodeURIComponent(this.value);
	$('#found_results').addClass('dmd-busy');
	$.get(url, '', function (data) { $('#found_results').removeClass('dmd-busy').html(data); });
});
</script>
