<?php

$symbol = getparam('symbol');
$coin = null;
$algo = user()->getState('yaamp-algo');


$search = getparam('search'); // Retrieve the search term if any
$searchCondition = $search ? "AND username LIKE :search" : ""; // Add search condition only if search term exists
$users = getdbolist('db_accounts', "
    id IN (SELECT DISTINCT userid FROM workers) $searchCondition
", [':search' => "%$search%"]);

// Map coinid to symbol
$coins = getdbolist('db_coins', "");
$coinSymbols = array_column($coins, 'symbol', 'id');

// Step 2: Sort users by coin symbol without modifying the users array
usort($users, function ($a, $b) use ($coinSymbols) {
    $symbolA = $coinSymbols[$a['coinid']] ?? 'Unknown';
    $symbolB = $coinSymbols[$b['coinid']] ?? 'Unknown';
    return strcasecmp($symbolA, $symbolB); // Case-insensitive alphabetical sort
});



showTableSorter('dataGrid6', "{
    tableClass: 'dataGrid6',
    textExtraction: {
        4: function(node, table, cellIndex) { return $(node).attr('data'); },
        6: function(node, table, cellIndex) { return $(node).attr('data'); },
    },
    widgets: ['zebra', 'filter', 'Storage', 'saveSort'],
    widgetOptions: {
        saveSort: false,
        filter_saveFilters: false,
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: false,
        filter_ignoreCase: true
    }
}");

echo <<<END
<thead style="opacity:0%; position:fixed">
    <th align="center" data-sorter="numeric"></th>
    <th align="center" data-sorter="numeric"></th>
    <th align="center" data-sorter="numeric"></th>
</thead>
END;

$total_balance = 0;
$total_paid = 0;
$total_unsold = 0;

foreach ($users as $user) {
    $target = yaamp_hashrate_constant();
    $interval = yaamp_hashrate_step(); // 300 seconds
    $delay = time() - $interval;
    $user_rate = dboscalar("SELECT (SUM(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time > $delay AND userid = " . $user->id);
    $user_bad = yaamp_user_rate_bad($user->id);
    $pct_bad = $user_rate ? round($user_bad * 100 / $user_rate, 3) : 0;
    $balance = bitcoinvaluetoa($user->balance);
    $paid = dboscalar("SELECT SUM(amount) FROM payouts WHERE account_id = " . $user->id);
    $d = datetoa2($user->last_earning);
    $miner_count = getdbocount('db_workers', "userid = " . $user->id);
    $block_count = getdbocount('db_blocks', "userid = " . $user->id);
    $block_count_formatted = number_format($block_count, 0, '.', ',');
    $block_diff = ($paid && $block_count) ? round(dboscalar("SELECT SUM(difficulty) FROM blocks WHERE userid = " . $user->id) / $paid, 3) : '?';
    $paid = bitcoinvaluetoa($paid);
    $confirmed = yaamp_convert_earnings_user($user, "status = 1");
    $unconfirmed = yaamp_convert_earnings_user($user, "status = 0");
    $total_unsold = bitcoinvaluetoa($confirmed + $unconfirmed);
    $confirmed = $confirmed ? bitcoinvaluetoa($confirmed) : '';
    $unconfirmed = $unconfirmed ? bitcoinvaluetoa($unconfirmed) : '';
    $total_earned = bitcoinvaluetoa($total_unsold + $balance + $total_paid);
    $user_bad = Itoa2($user_bad);
    $imgopt = ['width' => '15', 'align' => 'center', 'style' => 'opacity:40%'];
    $imgopt2 = ['width' => '15', 'align' => 'center', 'style' => 'opacity:100%'];
    $miner = $user ? substr($user->username, 0, 4) : ' ';
    $miner3 = '..' . substr($user->username, -3);
    $miner_full = htmlspecialchars($user->username); // Use full wallet address
    $miner_truncated2 = $miner . $miner3;
    $miner_truncated = $user ? substr($user->username, 31, 34) : ' ';
    $miner2 = $user->username;

    if ($user->coinid != 196) {
        if ($coin && $user->coinid == $coin->id) {
            $coinimg2 = CHtml::image($coin->image, $coin->symbol, $imgopt2);
            $coinimg = CHtml::image($coin->image, $coin->symbol, $imgopt);
            $coinlink = CHtml::link($coin->symbol, '/explorer/' . $coin->symbol);
        } elseif ($user->coinid > 0) {
            $user_coin = getdbosql('db_coins', "id = :id", [':id' => $user->coinid]);
            if ($user_coin) {
                $coinimg = CHtml::image($user_coin->image, $user_coin->symbol, $imgopt);
                $coinimg2 = CHtml::image($user_coin->image, $user_coin->symbol, $imgopt2);
                $coinlink = CHtml::link($user_coin->symbol, '/explorer/' . $user_coin->symbol);
            }
        }
    }

    if ($user->coinid != 196) {
        echo '<tr class="ssrow" style="z-index:10; position:relative">';

/**
        echo '<td align="center" width="333" style="font-weight: normal; color: #9699A1; font-family: interblack; font-size: 25px; margin-right:5px">
                <a style="font-size:25px; color:#D9B915" href="https://' . YAAMP_SITE_URL . '/mywallet/?address=' . $user->username . '">
                    <img src="' . $user_coin->image . '" style="opacity:100%; width:25px; margin-bottom:-5px"></img> ' . $user_coin->symbol . '
                </a>
              </td>';
**/

	echo '<td style="display: none;" class="full-wallet">' . $miner_full . '</td>';
            echo '<td align="left" width="500" style="color: #9699A1; font-family: interblack; font-size: 25px">
                    <a style="font-size: 25px; text-transform:none" href="/mywallet/?address=' . $user->username . '" title="' . $miner_full . '">
                        <span width="50" class="fa-solid fa-user" style="margin-left: -10px; margin-right:5px; font-size: 25px; color: #9699A1;"></span>' . $miner_truncated2 . ' 
                        <i style="color:#9699A1; opacity:100%; font-size:15px; margin-left:3px; margin-right:-10px; margin-bottom:5px; vertical-align:center" class="fa-light fa-external-link"></i>
		    </a>
                  </td>';

        $user_rate_display = $user_rate > 0 ? Itoa2($user_rate) : '0.0 H';
        echo '<td width="500" align="left" style="font-weight: normal; color: #D9B915; font-family: interblack; font-size: 25px;" data="' . (0 + $user_rate) . '">
                <a style="font-size:25px; color:#D9B915" href="https://' . YAAMP_SITE_URL . '/mywallet/?address=' . $user->username . '">
                <span align="right" class="fa-solid fa-bolt fa-fade" style="margin-left: -10px; margin-right:5px; font-size: 25px; color: #D9B915;"></span>' . $user_rate_display . '
                </a>
              </td>';

        echo '</tr>';

        $total_balance += $user->balance;
        $total_paid += $paid;
    }
}

echo "</tbody>";
echo "</table>";
