<?php

class MywalletController extends CommonController
{
public function actionIndex()
{

    if (isset($_GET['address'])) {
        $wallet = $_GET['address'];
        $user = getuserparam($wallet); // validate it's a real wallet/user
        if ($user) {
            user()->setState('yaamp-wallet', $user->username);
        }
    }

    ob_start();
    include '/home/crypto-data/yiimp/site/web/yaamp/modules/mywallet/workers_wallet_results.php';
    $workersContent = ob_get_clean();

    $this->render('index', array('workersContent' => $workersContent));
}

// The Miners box's 10/20/50 selector reloads the list from here. Only addresses that
// have an account on the pool get an answer.
public function actionWorkers_wallet_results()
{
    if (!getuserparam(getparam('address'))) return;

    $workers_wallet_ajax = true;
    include '/home/crypto-data/yiimp/site/web/yaamp/modules/mywallet/workers_wallet_results.php';
}

// Notifications of the pool app: the wallet page's switches, the bell and the home page's button,
// and the service worker when the browser renews a subscription. POST /mywallet/push/json with a
// JSON body:
//   {action: "get",         endpoint}                              -> {on, wants, address}
//   {action: "subscribe",   address, subscription: {endpoint, keys: {p256dh, auth}}, wants}
//   {action: "subscribe",   subscription}   (no address: anyone)  -> the pool's blocks; a device that
//                                                                     follows an address adds them
//   {action: "update",      endpoint, wants}
//   {action: "unsubscribe", endpoint}
//   {action: "renew",       old, subscription}
//   {action: "test",        endpoint}                              -> a test notice within seconds
//   {action: "inbox",       endpoint}                              -> {on, address, wants, items}: the bell's list
// wants: 1 blocks that pay this address, 2 payouts, 4 every block the pool finds. 1 and 2 go together
// (one choice on the wallet page, "My rewards", since PPLNS pays in the block).
public function actionPush()
{
    require_once '/home/crypto-data/yiimp/site/web/yaamp/core/backend/push.php';
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $answer = function ($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
    };
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $answer(array('error' => 'POST only'), 405);
    if (!push_config() || !push_table()) return $answer(array('error' => 'Notifications are not set up on this pool'), 503);

    $in = json_decode(file_get_contents('php://input', false, null, 0, 8192), true);
    if (!is_array($in) || empty($in['action'])) return $answer(array('error' => 'bad request'), 400);

    // a subscription from the browser: only the push services' addresses are taken
    $read = function ($s) {
        if (!is_array($s) || empty($s['endpoint']) || empty($s['keys']['p256dh']) || empty($s['keys']['auth'])) return null;
        $endpoint = (string) $s['endpoint'];
        $u = parse_url($endpoint);
        if (strlen($endpoint) > 1000 || !$u || empty($u['host']) || !push_host_allowed($u['host'])) return null;
        if ($u['scheme'] !== 'https' && !(defined('PUSH_TEST_HOST') && $u['host'] === PUSH_TEST_HOST)) return null;
        $p256dh = (string) $s['keys']['p256dh'];
        $auth = (string) $s['keys']['auth'];
        if (!preg_match('/^[A-Za-z0-9_-]{80,100}$/', $p256dh) || !preg_match('/^[A-Za-z0-9_-]{16,32}$/', $auth)) return null;
        return array('endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth);
    };
    $wants = isset($in['wants']) ? ((int) $in['wants']) & 7 : 3;
    if ($wants & 3) $wants |= 3;
    $hash = function ($endpoint) { return hash('sha256', (string) $endpoint); };
    $row = function ($endpoint) use ($hash) {
        return dborow("SELECT * FROM push_subscriptions WHERE endpoint_hash=:h", array(':h' => $hash($endpoint)));
    };
    $owner = function ($r) { return $r && (int) $r['userid'] ? getdbo('db_accounts', (int) $r['userid']) : null; };

    switch ($in['action']) {
    case 'get':
        $r = empty($in['endpoint']) ? null : $row($in['endpoint']);
        $user = $owner($r);
        return $answer(array('on' => (bool) $r, 'wants' => $r ? (int) $r['wants'] : 0, 'address' => $user ? $user->username : null));

    case 'subscribe':
        $address = isset($in['address']) ? trim((string) $in['address']) : '';
        $sub = $read(isset($in['subscription']) ? $in['subscription'] : null);
        if (!$sub) return $answer(array('error' => 'bad request'), 400);
        $old = $row($sub['endpoint']);
        if ($address === '') {
            // anyone, without an address: the pool's blocks. A device that already follows an
            // address keeps it and gets the pool's blocks too.
            $user = $owner($old);
            if ($user) {
                $w = (int) $old['wants'] | 4;
                $c = ((int) $old['wants'] & 4) ? $old : push_cursors_now($user->id, $user->coinid);
                dborun("UPDATE push_subscriptions SET endpoint=:e, p256dh=:k, auth=:a, wants=:w, block_cursor=:bc, failures=0 WHERE id=:id",
                    array(':e' => $sub['endpoint'], ':k' => $sub['p256dh'], ':a' => $sub['auth'], ':w' => $w, ':bc' => (int) $c['block_cursor'],
                        ':id' => (int) $old['id']));
                return $answer(array('on' => true, 'wants' => $w, 'address' => $user->username));
            }
            if (!$old && dboscalar("SELECT COUNT(*) FROM push_subscriptions WHERE userid=0") >= PUSH_MAX_POOL_ONLY)
                return $answer(array('error' => 'Too many devices. Try again later.'), 429);
            $c = $old ? $old : push_cursors_now(0, 0);
            $p = array(':e' => $sub['endpoint'], ':h' => $hash($sub['endpoint']), ':k' => $sub['p256dh'], ':a' => $sub['auth'],
                ':t' => time(), ':bc' => (int) $c['block_cursor']);
            if ($old)
                dborun("UPDATE push_subscriptions SET userid=0, endpoint=:e, p256dh=:k, auth=:a, wants=4, block_cursor=:bc, failures=0, created=:t "
                    . "WHERE endpoint_hash=:h", $p);
            else
                dborun("INSERT INTO push_subscriptions (userid, endpoint, endpoint_hash, p256dh, auth, wants, created, block_cursor) "
                    . "VALUES (0, :e, :h, :k, :a, 4, :t, :bc)", $p);
            return $answer(array('on' => true, 'wants' => 4, 'address' => null));
        }
        $user = getuserparam($address);
        if (!$user || !$wants) return $answer(array('error' => 'bad request'), 400);
        if (!$old && dboscalar("SELECT COUNT(*) FROM push_subscriptions WHERE userid=" . (int) $user->id) >= 50)
            return $answer(array('error' => 'Too many devices for this address'), 429);
        // a device that starts (or switches) following an address is told from now on
        $c = (!$old || $old['userid'] != $user->id) ? push_cursors_now($user->id, $user->coinid) : $old;
        $p = array(':u' => (int) $user->id, ':e' => $sub['endpoint'], ':h' => $hash($sub['endpoint']), ':k' => $sub['p256dh'],
            ':a' => $sub['auth'], ':w' => $wants, ':t' => time(), ':ec' => (int) $c['earn_cursor'], ':pc' => (int) $c['payout_cursor'],
            ':bc' => (int) $c['block_cursor']);
        if ($old)
            dborun("UPDATE push_subscriptions SET userid=:u, endpoint=:e, p256dh=:k, auth=:a, wants=:w, earn_cursor=:ec, payout_cursor=:pc, "
                . "block_cursor=:bc, failures=0, created=:t WHERE endpoint_hash=:h", $p);
        else
            dborun("INSERT INTO push_subscriptions (userid, endpoint, endpoint_hash, p256dh, auth, wants, created, earn_cursor, payout_cursor, block_cursor) "
                . "VALUES (:u, :e, :h, :k, :a, :w, :t, :ec, :pc, :bc)", $p);
        return $answer(array('on' => true, 'wants' => $wants, 'address' => $user->username));

    case 'update':
        $r = empty($in['endpoint']) ? null : $row($in['endpoint']);
        if (!$r || !$wants) return $answer(array('error' => 'bad request'), 400);
        $user = $owner($r);
        if (!$user) $wants = 4;	// a device without an address: the pool's blocks only
        // a kind turned on now starts from now (not with everything since the device turned them on)
        $now = push_cursors_now($user ? $user->id : 0, $user ? $user->coinid : 0);
        $c = array();
        foreach (array(1 => 'earn_cursor', 2 => 'payout_cursor', 4 => 'block_cursor') as $bit => $k)
            $c[$k] = (($wants & $bit) && !((int) $r['wants'] & $bit)) ? $now[$k] : (int) $r[$k];
        dborun("UPDATE push_subscriptions SET wants=:w, earn_cursor=:ec, payout_cursor=:pc, block_cursor=:bc WHERE id=:id",
            array(':w' => $wants, ':ec' => $c['earn_cursor'], ':pc' => $c['payout_cursor'], ':bc' => $c['block_cursor'], ':id' => (int) $r['id']));
        return $answer(array('on' => true, 'wants' => $wants));

    case 'unsubscribe':
        if (!empty($in['endpoint']))
            dborun("DELETE FROM push_subscriptions WHERE endpoint_hash=:h", array(':h' => $hash($in['endpoint'])));
        return $answer(array('on' => false));

    case 'inbox':
        $r = empty($in['endpoint']) ? null : $row($in['endpoint']);
        if (!$r) return $answer(array('on' => false, 'address' => null, 'wants' => 0, 'items' => array()));
        $user = $owner($r);
        return $answer(array('on' => true, 'address' => $user ? $user->username : null, 'wants' => $user ? (int) $r['wants'] : 4,
            'items' => push_inbox($r)));

    case 'test':
        // the blocks job sends it at its next run (and logs what the push service answered)
        $r = empty($in['endpoint']) ? null : $row($in['endpoint']);
        if (!$r) return $answer(array('error' => 'Notifications are not on for this device.'), 404);
        if (!array_key_exists('test_at', $r)) return $answer(array('error' => 'Test notices are not set up on this pool.'), 503);
        if ((int) $r['test_at'] < time() - 60)
            dborun("UPDATE push_subscriptions SET test_at=:t WHERE id=:id", array(':t' => time(), ':id' => (int) $r['id']));
        return $answer(array('ok' => true));

    case 'renew':
        // the browser replaced a subscription: the new one takes the old one's place
        $sub = $read(isset($in['subscription']) ? $in['subscription'] : null);
        if (!$sub || empty($in['old']) || !$row($in['old'])) return $answer(array('error' => 'bad request'), 400);
        if ($hash($sub['endpoint']) !== $hash($in['old']))
            dborun("DELETE FROM push_subscriptions WHERE endpoint_hash=:h", array(':h' => $hash($sub['endpoint'])));
        dborun("UPDATE push_subscriptions SET endpoint=:e, endpoint_hash=:h, p256dh=:k, auth=:a, failures=0 WHERE endpoint_hash=:o",
            array(':e' => $sub['endpoint'], ':h' => $hash($sub['endpoint']), ':k' => $sub['p256dh'], ':a' => $sub['auth'], ':o' => $hash($in['old'])));
        return $answer(array('on' => true));
    }
    return $answer(array('error' => 'bad request'), 400);
}

// "Forget" on the wallet page (POST /mywallet/forget/json): this browser stops remembering the
// address it opened last (the "My wallet" link and the bell). The page turns off this device's
// notifications for the address itself, through /mywallet/push/json, before calling this.
public function actionForget()
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array('error' => 'POST only'));
        return;
    }
    user()->setState('yaamp-wallet', null);
    echo json_encode(array('ok' => true));
}

}
