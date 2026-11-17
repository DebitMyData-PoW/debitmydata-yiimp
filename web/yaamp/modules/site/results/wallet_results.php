<?php
/*
 * Wallet page: what the address has earned and been paid, as figures like the home page's.
 *   Unconfirmed  earnings still maturing, plus rewards paid to the address in blocks that are
 *                still maturing (coinbase payouts never go through the balance)
 *   Balance      confirmed, paid at the next balance payout. Shown only while the address has a
 *                balance or earnings on their way to it (PROP blocks from before PPLNS, PPLNS parts
 *                too small for the block): since PPLNS, rewards are paid in each block, and an
 *                empty balance would only confuse
 *   Paid         everything paid so far, balance payouts and rewards paid in blocks
 * The link to the matured rewards shows only while money waits for a balance payout (as the wallet
 * page's countdown: yaamp_user_payout_waiting()).
 * Refreshed every 30 seconds by mywallet/index.php.
 */

$user = getuserparam(getparam('address'));
if(!$user) return;

$defaultalgo = user()->getState('yaamp-algo');

$refcoin = getdbo('db_coins', $user->coinid);
if(!$refcoin)
{
	if($user->coinid != null)
		echo '<p class="dmd-alert">This wallet address is not valid. You will not receive payments using this address.</p>';
	$refcoin = getdbosql('db_coins', "symbol='BTC'");
}
elseif (!YAAMP_ALLOW_EXCHANGE && $user->coinid == 666 && $defaultalgo != 'sha256')
{
	echo '<p class="dmd-alert">This pool does not convert/trade currencies. You will not receive payments using this BTC address.</p>';
	return;
}

if (!function_exists('dmd_stat_icon')) {
function dmd_stat_icon($name)
{
	$paths = array(
		'coins' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
		'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><path d="M16 14h.01"/>',
		'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
	);
	return '<span class="dmd-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
		. 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg></span>';
}
}

$symbol = $refcoin ? CHtml::encode($refcoin->symbol) : '';

$confirmed = yaamp_convert_earnings_user($user, "status=1");
$unconfirmed = yaamp_convert_earnings_user($user, "status=0");
$total_unsold = bitcoinvaluetoa($confirmed + $unconfirmed);

$balance = bitcoinvaluetoa($user->balance);

// Rewards paid to this address in blocks still maturing (coinbase payouts): they never go
// through the balance, so they are counted here until the block matures.
$in_blocks = 0;
if (yaamp_has_solo_shares()) {
	$in_blocks = (double) controller()->memcache->get_database_scalar("wallet_in_blocks-$user->id",
		"SELECT SUM(e.amount) FROM earnings e JOIN blocks b ON b.id=e.blockid ".
		"JOIN coinbase_splits c ON c.coinid=e.coinid AND c.blockhash=b.blockhash AND c.userid=e.userid AND c.in_coinbase=1 ".
		"WHERE e.userid=".intval($user->id)." AND e.status=2 AND b.category IN ('new','immature')");
}

$total_unpaid = bitcoinvaluetoa($total_unsold + $in_blocks);
$total_paid = bitcoinvaluetoa(controller()->memcache->get_database_scalar("wallet_total_paid-$user->id",
	"select sum(amount) from payouts where account_id=$user->id"));

$fmt = function ($v) { return number_format((double) $v, 3, '.', ','); };

// money still waiting for a balance payout (PROP blocks from before PPLNS, PPLNS parts too small for the block)
$payout_waiting = yaamp_user_payout_waiting($user);
if ($payout_waiting) $balance_sub = 'paid at the next balance payout';
elseif ((double) $user->balance > 0) $balance_sub = 'under the minimum payout';
else $balance_sub = 'earnings arrive once confirmed'; // shown only while some are on their way

// The balance card only while there is a balance, or earnings on their way to it.
$show_balance = $payout_waiting || (double) $user->balance > 0 || (double) $total_unsold > 0;

$unconfirmed_title = 'Earnings still maturing';
if ($in_blocks > 0) $unconfirmed_title .= ', including ' . $fmt($in_blocks) . " $symbol paid to your address in blocks still maturing";

echo '<ul class="dmd-stats ' . ($show_balance ? 'dmd-three' : 'dmd-pair') . '">';
echo '<li class="dmd-stat" title="' . $unconfirmed_title . '">' . dmd_stat_icon('coins')
	. '<b>' . $fmt($total_unpaid) . " <small>$symbol</small></b><span class=\"dmd-label\">Unconfirmed</span>"
	. ($in_blocks > 0 ? '<span class="dmd-sub">' . $fmt($in_blocks) . ' in blocks</span>' : '') . '</li>';
if ($show_balance)
	echo '<li class="dmd-stat" title="Confirmed, paid at the next balance payout">' . dmd_stat_icon('wallet')
		. '<b>' . $fmt($balance) . " <small>$symbol</small></b><span class=\"dmd-label\">Balance</span>"
		. '<span class="dmd-sub">' . $balance_sub . '</span></li>';
echo '<li class="dmd-stat" title="Balance payouts and rewards paid in blocks">' . dmd_stat_icon('send')
	. '<b>' . $fmt($total_paid) . " <small>$symbol</small></b><span class=\"dmd-label\">Paid in total</span>"
	. ($payout_waiting ? '<a class="dmd-sub" href="/site/tx?address=' . CHtml::encode($user->username) . '">See your matured rewards</a>' : '') . '</li>';
echo '</ul>';
