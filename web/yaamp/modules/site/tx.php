<?php
/*
 * One wallet's matured rewards (/site/tx?address=..., opened from the wallet page), in the
 * DebitMyData look. Since PPLNS, a miner's share is paid straight to its address inside the block
 * (coinbase): nothing is sent later, and a reward is listed here once its block has matured and
 * the coins can be spent. Rows "From your balance" are the older balance payouts (PROP blocks
 * before PPLNS, or a PPLNS part too small for the block). Shown: the total, how many rewards and
 * when the last one matured, then every row, newest first. Each transaction opens in the explorer
 * (or can be copied when the coin has no explorer). The page has the pool's top bar and footer
 * (SiteController renders it with the layout, ui/main.php).
 */

if (!function_exists('dmd_tx_icon')) {
    function dmd_tx_icon($name)
    {
        $paths = array(
            'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><path d="M16 14h.01"/>',
            'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
            'list' => '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1.5"/><circle cx="4.5" cy="12" r="1.5"/><circle cx="4.5" cy="18" r="1.5"/>',
            'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
            'out' => '<path d="M7 17L17 7M9 7h8v8"/>',
        );
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }

    // An amount with up to 8 decimals, without trailing zeros (at least 2 decimals).
    function dmd_tx_amount($value)
    {
        $text = rtrim(number_format((float) $value, 8, '.', ','), '0');
        list($whole, $decimals) = explode('.', $text);
        return $whole . '.' . str_pad($decimals, 2, '0');
    }

    // A transaction ID shortened for the table: its start and its end.
    function dmd_tx_short($txid, $head = 10, $tail = 8)
    {
        return strlen($txid) > $head + $tail + 4 ? substr($txid, 0, $head) . '…' . substr($txid, -$tail) : $txid;
    }

    // The transaction cell: a link to the explorer, or the ID with a copy button.
    function dmd_tx_cell($txid, $explorer, $head, $tail)
    {
        $short = CHtml::encode(dmd_tx_short($txid, $head, $tail));
        if ($txid === '') {
            return '<span class="dmd-small">—</span>';
        }
        if ($explorer !== '') {
            return '<a class="dmd-hash" href="' . CHtml::encode($explorer . '/tx/' . $txid) . '" target="_blank"'
                . ' rel="noopener" title="Open in the explorer">' . $short . dmd_tx_icon('out') . '</a>';
        }
        return '<span class="dmd-hash">' . $short . '</span> <button class="dmd-copy" type="button" data-copy="'
            . CHtml::encode($txid) . '">' . dmd_tx_icon('copy') . '<span>Copy</span></button>';
    }
}

$this->pageTitle = 'Matured rewards';

$address = (string) getparam('address');
$user = getuserparam($address);
$walletUrl = '/mywallet/?address=' . urlencode($address);

if (!$user) {
    echo '<div class="dmd-wallet-intro"><p class="dmd-eyebrow">Matured rewards</p>'
        . '<h1>Unknown <span class="dmd-accent">address</span></h1>'
        . '<p>The pool has no rewards for this address. Check it on <a href="' . CHtml::encode($walletUrl)
        . '">your wallet page</a>.</p></div>';
    return;
}

$coin = getdbo('db_coins', $user->coinid);
$symbol = $coin ? CHtml::encode($coin->symbol) : 'DMD';
$explorer = ($coin && !empty($coin->link_explorer)) ? rtrim($coin->link_explorer, '/') : '';
$username = CHtml::encode($user->username);

$payouts = getdbolist('db_payouts', 'account_id=' . (int) $user->id . ' ORDER BY time DESC');

$total = 0;
$rows = '';
foreach ($payouts as $payout) {
    $total += $payout->amount;
    $txid = (string) $payout->tx;
    $tx = dmd_tx_cell($txid, $explorer, 10, 8);
    $txPhone = dmd_tx_cell($txid, $explorer, 8, 6);
    // On a phone the transaction goes under the date (its own column is hidden there).
    $rows .= '<tr><td><span class="dmd-tx-date">' . date('Y-m-d H:i', $payout->time) . '</span>'
        . '<small>' . datetoa2($payout->time) . ' ago</small>'
        . '<span class="dmd-tx-phone">' . $txPhone . '</span></td>'
        . '<td class="dmd-hide-phone">' . $tx . '</td>'
        . '<td>' . ($payout->memoid == 'coinbase'
            ? '<span class="dmd-badge pool" title="Paid to your address inside the block, now matured: the coins can be spent">In the block</span>'
            : '<span class="dmd-badge orphan" title="Sent from your balance by the pool wallet">From your balance</span>') . '</td>'
        . '<td class="r"><span class="dmd-reward">' . dmd_tx_amount($payout->amount) . ' <small>' . $symbol . '</small></span></td></tr>';
}

$count = count($payouts);
$last = $count ? datetoa2($payouts[0]->time) . ' ago' : '—';
$addressLink = $explorer !== ''
    ? ' · <a href="' . CHtml::encode($explorer . '/address/' . $user->username) . '" target="_blank" rel="noopener">'
        . 'The address in the explorer ' . dmd_tx_icon('out') . '</a>'
    : '';

echo '<div class="dmd-wallet-intro dmd-tx-intro">'
    . '<p class="dmd-eyebrow">My wallet</p>'
    . '<h1>Your matured <span class="dmd-accent">rewards</span></h1>'
    . '<p class="dmd-tx-address"><code>' . $username . '</code>'
    . '<button class="dmd-copy" type="button" data-copy="' . $username . '">' . dmd_tx_icon('copy') . '<span>Copy</span></button></p>'
    . '<p class="dmd-tx-links"><a class="dmd-tx-back" href="' . CHtml::encode($walletUrl) . '">Back to my wallet</a>'
    . $addressLink . '</p>'
    . '<p class="dmd-tx-note">Your share of each block is paid straight to your address, inside the block.'
    . ' It shows here once the block has matured and the coins can be spent.</p>'
    . '</div>';

echo '<ul class="dmd-stats dmd-three">'
    . '<li class="dmd-stat"><span class="dmd-icon">' . dmd_tx_icon('wallet') . '</span><b>' . dmd_tx_amount($total)
    . ' <small>' . $symbol . '</small></b><span class="dmd-label">Total earned</span></li>'
    . '<li class="dmd-stat"><span class="dmd-icon">' . dmd_tx_icon('send') . '</span><b>' . number_format($count)
    . '</b><span class="dmd-label">Matured rewards</span></li>'
    . '<li class="dmd-stat"><span class="dmd-icon">' . dmd_tx_icon('clock') . '</span><b>' . $last
    . '</b><span class="dmd-label">Last matured</span></li>'
    . '</ul>';

echo '<section class="dmd-card"><div class="dmd-card-head"><h2>' . dmd_tx_icon('list') . 'All matured rewards</h2></div>';
if ($rows === '') {
    echo '<p class="dmd-empty">Nothing has matured yet. Your share of a block is in your wallet from the block on,'
        . ' and shows here once it has matured.</p>';
} else {
    echo '<table class="dmd-blocks-table dmd-tx-table"><thead><tr><th scope="col">Date</th>'
        . '<th scope="col" class="dmd-hide-phone">Transaction</th><th scope="col">Paid</th><th scope="col" class="r">Amount</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '<tfoot><tr><td>Total</td><td class="dmd-hide-phone"></td><td></td><td class="r">' . dmd_tx_amount($total)
        . ' <small>' . $symbol . '</small></td></tr></tfoot></table>';
}
echo '</section>';
?>
<script>
// "Back to my wallet": the wallet page opens this page in its own small window, so going back
// closes that window and shows the wallet page again (no second wallet page). Opened any other
// way (a bookmark, a new tab), the link goes to the wallet page as usual.
(function () {
    var back = document.querySelector('.dmd-tx-back');
    if (!back) return;
    back.addEventListener('click', function (event) {
        var wallet = window.opener;
        if (!wallet || wallet.closed) return;
        event.preventDefault();
        try { wallet.focus(); } catch (err) {}
        window.close();
        // Some browsers keep a window open that they did not open themselves: then go there.
        setTimeout(function () { if (!window.closed) window.location.href = back.href; }, 300);
    });
})();

// Copy the address (or a transaction ID).
document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('.dmd-copy') : null;
    if (!button) return;
    var text = button.getAttribute('data-copy');
    var label = button.querySelector('span');
    function done() {
        button.classList.add('done');
        label.textContent = 'Copied';
        setTimeout(function () { button.classList.remove('done'); label.textContent = 'Copy'; }, 1600);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () {});
    } else {
        var field = document.createElement('textarea');
        field.value = text;
        document.body.appendChild(field);
        field.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        document.body.removeChild(field);
    }
});
</script>
