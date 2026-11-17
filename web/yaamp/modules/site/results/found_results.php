<?php
/*
 * The latest blocks the pool found (home page, refreshed every 30 seconds): block, when, shared or
 * solo, reward, and how far the block is from maturity. Each block links to its transaction in the
 * explorer.
 */

// The pool shows maturity as "confirmations/251", as it always has.
$maturity = 251;

$count = getparam('count');
$count = $count ? (int) $count : 5;

$criteria = new CDbCriteria();
$criteria->condition = "t.category NOT IN ('stake','generated')";
$criteria->condition .= " AND IFNULL(coin.visible,1)=1";
$criteria->addCondition('coin.id != 179');
$criteria->addCondition('coin.id != 182');
$criteria->limit = $count;
$criteria->order = 't.time DESC';
$db_blocks = getdbolistWith('db_blocks', 'coin', $criteria);

// Shared (PPLNS, PROP) or solo, from what each block paid (yaamp_block_kinds)
$kinds = array();
$hashes = array();
foreach ($db_blocks as $db_block) {
    if ($db_block->coin_id && !empty($db_block->blockhash)) $hashes[$db_block->coin_id][] = $db_block->blockhash;
}
foreach ($hashes as $coinid => $list) {
    $kinds += yaamp_block_kinds($coinid, $list);
}

$cube = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/></svg>';

$rows = '';
foreach ($db_blocks as $db_block) {
    if (!$db_block->coin_id) {
        continue;    // rentals: not used on this pool
    }
    $coin = $db_block->coin ? $db_block->coin : getdbo('db_coins', $db_block->coin_id);
    if (!$coin) {
        continue;
    }

    $url = '';
    if (!empty($coin->link_explorer) && !empty($db_block->txhash)) {
        $url = CHtml::encode(rtrim($coin->link_explorer, '/') . '/tx/' . $db_block->txhash);
    }
    $height = number_format($db_block->height);
    $block = $url
        ? "<a class=\"dmd-height\" href=\"$url\" target=\"_blank\" rel=\"noopener\" title=\"Open in the explorer\"><span class=\"dmd-cube\">$cube</span>$height</a>"
        : "<span class=\"dmd-height\"><span class=\"dmd-cube\">$cube</span>$height</span>";

    $reward = $db_block->amount > 0
        ? '<span class="dmd-reward">' . number_format($db_block->amount) . ' <small>' . CHtml::encode($coin->symbol) . '</small></span>'
        : '—';

    $kind = isset($kinds[$db_block->blockhash]) ? $kinds[$db_block->blockhash] : 'prop';
    if ($kind == 'solo') {
        $type = '<span class="dmd-badge solo" title="Found by a solo miner and paid to its address in the block">Solo</span>';
    } else {
        $title = $kind == 'pplns' ? 'Shared by the pool\'s miners (PPLNS)' : 'Shared by the pool\'s miners';
        $type = '<span class="dmd-badge pool" title="' . $title . '">Shared</span>';
    }

    $confirm = (int) $db_block->confirmations;
    switch ($db_block->category) {
        case 'immature':
            $percent = max(2, min(100, round($confirm * 100 / $maturity)));
            $eta = '';
            if ($coin->block_time && $coin->mature_blocks) {
                $t = max(0, (int) ($coin->mature_blocks - $confirm) * $coin->block_time);
                $eta = sprintf(' · about %dh %02dmin left', intdiv($t, 3600), intdiv($t, 60) % 60);
            }
            $status = "<span class=\"dmd-status\" title=\"Confirming$eta\">$confirm/$maturity"
                . "<span class=\"dmd-bar\" aria-hidden=\"true\"><i style=\"width:$percent%\"></i></span></span>";
            break;
        case 'generate':
            $status = '<span class="dmd-badge done">Confirmed</span>';
            break;
        case 'orphan':
            $status = '<span class="dmd-badge orphan">Orphaned</span>';
            break;
        case 'new':
            $status = '<span class="dmd-badge new">New</span>';
            break;
        default:
            $status = '<span class="dmd-badge orphan">' . CHtml::encode(ucfirst($db_block->category)) . '</span>';
    }

    $rows .= '<tr><td>' . $block . '</td><td>' . datetoa2($db_block->time) . ' ago</td><td>' . $type . '</td>'
        . '<td class="r dmd-hide-phone">' . $reward . '</td><td class="r">' . $status . '</td></tr>';
}

if ($rows === '') {
    echo '<p class="dmd-empty">No blocks found yet.</p>';
    return;
}

echo '<table class="dmd-blocks-table"><thead><tr><th scope="col">Block</th><th scope="col">Found</th><th scope="col">Type</th>'
    . '<th scope="col" class="r dmd-hide-phone">Reward</th><th scope="col" class="r">Status</th></tr></thead><tbody>'
    . $rows . '</tbody></table>';
