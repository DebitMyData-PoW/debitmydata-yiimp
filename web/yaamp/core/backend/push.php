<?php
/*
 * Notifications of the pool app (web push), sent every 2 seconds by the notifications service
 * (dmd-pool-push.service: CronjobController::actionRunPush), or by the blocks job when that service
 * is not running (CronjobController::actionRunBlocks).
 *
 * Each device that turned notifications on (MywalletController::actionPush) has a row in
 * push_subscriptions with what it wants:
 *   1  blocks that pay its address    "2 blocks paid you: 23.10 DMD ... (shared)"; a solo block is told at once
 *   2  payouts                        "Payout sent: 1.20 DMD", "Rewards spendable: 34.65 DMD"
 *   4  every block the pool finds     "The pool found 3 blocks: 2 shared, 1 solo"
 * Shared: a pool block, paid to its miners (PPLNS, or PROP before it); solo: found by a solo miner
 * and paid to it.
 * A device turned on from the wallet page follows that address. Anyone can also turn them on from
 * the bell or the home page without an address: that device (userid 0) gets the pool's blocks only.
 * A notice goes out at the sender's next run (every 30 seconds at most), as one notice for everything
 * new of its kind; at most one of each kind per minute.
 *
 * The notices are encrypted for each device (RFC 8291, aes128gcm) and signed with the pool's key
 * (VAPID, RFC 8292), with PHP's openssl only. The keys: configuration/push.php gives the public
 * key and the path of the private key file, which only the user running the jobs can read.
 * Without that file, or without the table, nothing here runs.
 */

define('PUSH_CONFIG', '/home/crypto-data/yiimp/site/configuration/push.php');
define('PUSH_EVERY', 30);		// seconds between two runs of the sender
define('PUSH_GROUP', 60);		// one notice per kind per minute at most
define('PUSH_MAX_SENDS', 60);	// notices per run at most, and at most 20 seconds:
define('PUSH_MAX_TIME', 20);		// the blocks job must not wait
define('PUSH_MAX_FAILURES', 120);	// runs in a row a device's push service refuses (about an hour), then it is dropped
define('PUSH_MAX_POOL_ONLY', 5000);	// devices that follow no address (the pool's blocks only), at most

// The push services of the browsers: notices only go there.
function push_host_allowed($host)
{
	$host = strtolower((string) $host);
	if (defined('PUSH_TEST_HOST') && $host === PUSH_TEST_HOST) return true;
	foreach (array('fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com',
		'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com') as $known) {
		if ($host === $known || substr($host, -strlen('.' . $known)) === '.' . $known) return true;
	}
	return false;
}

function push_config()
{
	static $done = false;
	if (!$done) {
		$done = true;
		if (is_readable(PUSH_CONFIG)) include_once(PUSH_CONFIG);
	}
	return defined('PUSH_VAPID_PUBLIC') && PUSH_VAPID_PUBLIC !== '';
}

function push_table()
{
	static $has = null;
	if ($has === null) $has = (bool) dbolist("SHOW TABLES LIKE 'push_subscriptions'");
	return $has;
}

function push_b64u_encode($bin) { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function push_b64u_decode($s) { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

// A P-256 public key (65 bytes, 04||X||Y) as a key openssl can use.
function push_public_key($point)
{
	$der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
	$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
	return openssl_pkey_get_public($pem);
}

function push_point($details)
{
	return "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
}

// The notice, encrypted for one device (RFC 8291 and RFC 8188, one record).
function push_encrypt($payload, $p256dh, $auth)
{
	$ua_public = push_b64u_decode($p256dh);
	$auth_secret = push_b64u_decode($auth);
	if (strlen($ua_public) != 65 || $ua_public[0] !== "\x04" || strlen($auth_secret) < 16) return false;

	$peer = push_public_key($ua_public);
	$local = openssl_pkey_new(array('curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC));
	if (!$peer || !$local) return false;
	$as_public = push_point(openssl_pkey_get_details($local));
	$shared = openssl_pkey_derive($peer, $local, 32);
	if ($shared === false || strlen($shared) != 32) return false;

	$ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $ua_public . $as_public, $auth_secret);
	$salt = random_bytes(16);
	$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
	$nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

	$tag = '';
	$cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
	if ($cipher === false) return false;
	return $salt . pack('N', 4096) . chr(65) . $as_public . $cipher . $tag;
}

// "vapid t=..., k=..." for the push service at $endpoint (RFC 8292).
function push_vapid($endpoint)
{
	static $key = null;
	if ($key === null) {
		$key = false;
		if (defined('PUSH_VAPID_PRIVATE_FILE') && is_readable(PUSH_VAPID_PRIVATE_FILE))
			$key = openssl_pkey_get_private(file_get_contents(PUSH_VAPID_PRIVATE_FILE));
	}
	if (!$key) return false;

	$u = parse_url($endpoint);
	$claims = array('aud' => $u['scheme'] . '://' . $u['host'], 'exp' => time() + 12 * 3600,
		'sub' => defined('PUSH_SUBJECT') ? PUSH_SUBJECT : 'https://pool.debitmydata.io');
	$data = push_b64u_encode('{"typ":"JWT","alg":"ES256"}') . '.' . push_b64u_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
	if (!openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256)) return false;

	// openssl gives the signature as DER (SEQUENCE of r and s); JWT wants r||s, 32 bytes each
	$offset = 3;
	if (ord($der[1]) & 0x80) $offset += ord($der[1]) & 0x7f;
	$parts = array();
	for ($i = 0; $i < 2; $i++) {
		$len = ord($der[$offset]);
		$int = substr($der, $offset + 1, $len);
		$parts[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
		$offset += $len + 2;
	}
	return 'vapid t=' . $data . '.' . push_b64u_encode($parts[0] . $parts[1]) . ', k=' . PUSH_VAPID_PUBLIC;
}

// Sends one notice. Returns the push service's HTTP code (0: not sent).
function push_deliver($sub, $message)
{
	$endpoint = $sub['endpoint'];
	$u = parse_url($endpoint);
	if (!$u || !isset($u['host']) || !push_host_allowed($u['host'])) return 0;
	if ($u['scheme'] !== 'https' && !(defined('PUSH_TEST_HOST') && $u['host'] === PUSH_TEST_HOST)) return 0;

	$body = push_encrypt(json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
	$vapid = push_vapid($endpoint);
	if ($body === false || $vapid === false) return 0;

	$headers = array('Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400',
		'Urgency: high', 'Authorization: ' . $vapid);	// high: a phone saving battery shows it at once
	if (!empty($message['tag'])) $headers[] = 'Topic: ' . preg_replace('/[^A-Za-z0-9_-]/', '', $message['tag']);

	$ch = curl_init($endpoint);
	curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
		CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP));
	$answer = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$GLOBALS['push_last_answer'] = $code . ($code ? '' : ' (' . curl_error($ch) . ')') . ' '
		. substr(preg_replace('/\s+/', ' ', (string) $answer), 0, 200);
	curl_close($ch);
	return $code;
}

// What the push service answered last, for the log: "201", "403 {...}", "0 (timeout)".
function push_answer()
{
	return isset($GLOBALS['push_last_answer']) ? trim($GLOBALS['push_last_answer']) : 'not sent';
}

function push_amount($v) { return number_format((double) $v, (double) $v >= 100 ? 2 : 3, '.', ','); }

function push_heights($heights)
{
	sort($heights);
	$a = number_format(reset($heights), 0, '.', ',');
	$b = number_format(end($heights), 0, '.', ',');
	return count($heights) == 1 ? "block $a" : "blocks {$a}–{$b}";
}

// Shared or solo, by block hash (missing while not known yet), for rows with blockhash and coin_id.
function push_block_kinds($rows)
{
	$kinds = array();
	if (!$rows || !function_exists('yaamp_block_kinds')) return $kinds;
	$by_coin = array();
	foreach ($rows as $r) $by_coin[(int) $r['coin_id']][] = $r['blockhash'];
	foreach ($by_coin as $coinid => $hashes)
		foreach (yaamp_block_kinds($coinid, $hashes) as $hash => $kind) $kinds[$hash] = $kind == 'solo' ? 'solo' : 'shared';
	return $kinds;
}

// "Block 146,613 (shared).", "Blocks 146,611–146,613: 2 shared, 1 solo."
function push_blocks_body($rows, $kinds)
{
	$heights = array(); $shared = 0; $solo = 0;
	foreach ($rows as $r) {
		$heights[] = (int) $r['height'];
		$kind = isset($kinds[$r['blockhash']]) ? $kinds[$r['blockhash']] : '';
		if ($kind == 'solo') $solo++; else if ($kind == 'shared') $shared++;
	}
	$text = ucfirst(push_heights($heights));
	$n = count($rows);
	if (!$shared && !$solo) return "$text.";
	if ($n == 1) return "$text (" . ($solo ? 'solo' : 'shared') . ").";
	if (!$solo && $shared == $n) return "$text (all shared).";
	if (!$shared && $solo == $n) return "$text (all solo).";
	$parts = array();
	if ($shared) $parts[] = "$shared shared";
	if ($solo) $parts[] = "$solo solo";
	return "$text: " . implode(', ', $parts) . '.';
}

// What one device should be told now. Moves its cursors; returns the notices.
function push_collect(&$sub, $now)
{
	$out = array();
	$wants = (int) $sub['wants'];
	$userid = (int) $sub['userid'];
	$user = null; $coin = null; $symbol = ''; $wallet = '/';
	if ($userid) {
		$user = getdbo('db_accounts', $userid);
		if (!$user) return $out;
		$coin = getdbo('db_coins', $user->coinid);
		$symbol = $coin ? $coin->symbol : '';
		$wallet = '/mywallet/?address=' . urlencode($user->username);
	}
	else $wants &= 4;	// a device that follows no address: the pool's blocks only

	// blocks that pay this address
	if ($wants & 1) {
		$rows = dbolist("SELECT e.id, e.amount, b.height, b.blockhash, b.coin_id FROM earnings e JOIN blocks b ON b.id=e.blockid "
			."WHERE e.userid=$userid AND e.id>".(int) $sub['earn_cursor']." AND e.status>=0 ORDER BY e.id LIMIT 500");
		if ($rows) {
			$solo = array();
			if (function_exists('yaamp_block_kinds') && $coin) {
				$kinds = yaamp_block_kinds($coin->id, array_map(function ($r) { return $r['blockhash']; }, $rows));
				foreach ($rows as $r) if (isset($kinds[$r['blockhash']]) && $kinds[$r['blockhash']] == 'solo') $solo[] = $r;
			}
			if ($solo || $now - $sub['earn_sent'] >= PUSH_GROUP) {
				foreach ($solo as $r)
					$out[] = array('title' => 'You found a block!', 'tag' => '',
						'body' => 'Block '.number_format($r['height'], 0, '.', ',').' pays you '.push_amount($r['amount'])." $symbol (solo).",
						'url' => $wallet);
				$pool = array_filter($rows, function ($r) use ($solo) { return !in_array($r, $solo, true); });
				if ($pool) {
					$sum = 0; $heights = array();
					foreach ($pool as $r) { $sum += $r['amount']; $heights[] = (int) $r['height']; }
					$n = count($pool);
					$out[] = array('title' => $n == 1 ? 'A block paid you' : "$n blocks paid you", 'tag' => 'earn',
						'body' => push_amount($sum)." $symbol from ".push_heights($heights).' (shared).', 'url' => $wallet);
				}
				$sub['earn_cursor'] = (int) end($rows)['id'];
				$sub['earn_sent'] = $now;
			}
		}
	}

	// payouts: from the balance, or rewards paid in a block that can now be spent. A payout the
	// payment job is still sending (no tx yet, less than an hour old) holds the list until it is
	// done; an older one without a tx failed, and the payment job pays it again under a new id.
	if ($wants & 2) {
		$rows = dbolist("SELECT id, amount, tx, memoid, completed, time FROM payouts WHERE account_id=$userid AND id>".(int) $sub['payout_cursor']
			." ORDER BY id LIMIT 500");
		$done = array(); $cursor = (int) $sub['payout_cursor'];
		foreach ($rows as $r) {
			if (empty($r['completed']) && empty($r['tx'])) {
				if ($now - (int) $r['time'] < 3600) break;
				$cursor = (int) $r['id'];
				continue;
			}
			$done[] = $r;
			$cursor = (int) $r['id'];
		}
		if ($done && $now - $sub['payout_sent'] >= PUSH_GROUP) {
			$sent = 0; $nsent = 0; $spendable = 0; $nspend = 0;
			foreach ($done as $r) {
				if ($r['memoid'] === 'coinbase') { $spendable += $r['amount']; $nspend++; }
				else { $sent += $r['amount']; $nsent++; }
			}
			$tx = '/site/tx?address=' . urlencode($user->username);
			if ($nsent) $out[] = array('title' => 'Payout sent', 'tag' => 'payout', 'url' => $tx,
				'body' => push_amount($sent)." $symbol sent to your address".($nsent > 1 ? " in $nsent payouts." : '.'));
			if ($nspend) $out[] = array('title' => 'Rewards spendable', 'tag' => 'spendable', 'url' => $tx,
				'body' => push_amount($spendable)." $symbol paid in ".($nspend == 1 ? 'a block' : "$nspend blocks").' can now be spent.');
			$sub['payout_cursor'] = $cursor;
			$sub['payout_sent'] = $now;
		}
		else if (!$done && $cursor > $sub['payout_cursor'])
			$sub['payout_cursor'] = $cursor;	// only failed payouts: passed over
	}

	// every block the pool finds (of the address's coin; all of them for a device without an address),
	// shared or solo. The stratum records how a block pays as it sends it: a block of the last 20
	// seconds that has no record yet waits for it (the service looks again 2 seconds later).
	if (($wants & 4) && ($coin || !$userid)) {
		$rows = dbolist("SELECT id, height, blockhash, coin_id, time FROM blocks WHERE ".($coin ? "coin_id=".(int) $coin->id." AND " : '')
			."id>".(int) $sub['block_cursor']." AND category!='orphan' ORDER BY id LIMIT 500");
		$kinds = push_block_kinds($rows);
		$wait = false;
		if (function_exists('yaamp_has_solo_shares') && yaamp_has_solo_shares())
			foreach ($rows as $r) if (!isset($kinds[$r['blockhash']]) && $now - (int) $r['time'] < 20) $wait = true;
		if ($rows && !$wait && $now - $sub['block_sent'] >= PUSH_GROUP) {
			$n = count($rows);
			$out[] = array('title' => $n == 1 ? 'The pool found a block' : "The pool found $n blocks", 'tag' => 'pool',
				'body' => push_blocks_body($rows, $kinds), 'url' => '/');
			$sub['block_cursor'] = (int) end($rows)['id'];
			$sub['block_sent'] = $now;
		}
	}
	return $out;
}

// Sends what is new. The blocks job calls it (at most every PUSH_EVERY seconds); the notifications
// service calls it every 2 seconds with $loop set: it then only looks further when something
// changed (a new earning, payout, block or block record, a payout sent, a test notice asked for), or every
// PUSH_EVERY seconds for the notices that wait for their minute.
function BackendPushSend($loop = false)
{
	static $last_mark = null, $last_full = 0;
	if (!push_config() || !push_table()) return;
	if (!defined('PUSH_VAPID_PRIVATE_FILE') || !is_readable(PUSH_VAPID_PRIVATE_FILE)) return;

	$now = time();
	if ($loop) {
		$mark = dborow("SELECT (SELECT IFNULL(MAX(id),0) FROM earnings) AS e, (SELECT IFNULL(MAX(id),0) FROM blocks) AS b, "
			."(SELECT CONCAT(IFNULL(MAX(id),0), '-', IFNULL(SUM(completed),0)) FROM (SELECT id, completed FROM payouts ORDER BY id DESC LIMIT 50) AS p) AS p, "
			."(SELECT COUNT(*) FROM push_subscriptions WHERE test_at>0) AS t"
			.((function_exists('yaamp_has_solo_shares') && yaamp_has_solo_shares()) ? ", (SELECT IFNULL(MAX(id),0) FROM coinbase_splits) AS s" : ''));
		$mark = json_encode($mark);
		if ($mark === $last_mark && $now - $last_full < PUSH_EVERY) return;
		$last_mark = $mark;
		$last_full = $now;
	}
	else {
		$cache = controller()->memcache;
		$last = (int) $cache->get('push_last_run');
		if ($last && $now - $last < PUSH_EVERY) return;
		$cache->set('push_last_run', $now, 600);
	}

	$sends = 0; $more = false;
	foreach (dbolist("SELECT * FROM push_subscriptions ORDER BY id") as $sub) {
		if (time() - $now >= PUSH_MAX_TIME) { $more = true; break; }	// the rest next run
		$before = $sub;
		$messages = push_collect($sub, $now);
		// a test notice asked for from the wallet page ("Send a test notice"), first
		$test = !empty($sub['test_at']);
		if ($test) {
			$user = getdbo('db_accounts', (int) $sub['userid']);
			array_unshift($messages, array('title' => 'Test notice', 'tag' => 'test', 'body' => 'Notifications work on this device.',
				'url' => $user ? '/mywallet/?address=' . urlencode($user->username) : '/'));
		}
		if ($messages && $sends && $sends + count($messages) > PUSH_MAX_SENDS) { $more = true; break; }
		$GLOBALS['push_last_answer'] = 'not sent';
		$failed = false; $gone = false;
		foreach ($messages as $m) {
			$code = push_deliver($sub, $m);
			$sends++;
			if ($code == 404 || $code == 410) { $gone = true; break; }
			if ($code < 200 || $code >= 300) { $failed = true; break; }
		}
		$host = parse_url($sub['endpoint'], PHP_URL_HOST);
		if ($test) {
			dborun("UPDATE push_subscriptions SET test_at=0 WHERE id=".(int) $sub['id']);
			debuglog("push: test notice to device {$sub['id']} ($host): " . push_answer());
		}
		else if (($failed || $gone) && (int) $before['failures'] % 20 == 0)
			debuglog("push: device {$sub['id']} ($host) not delivered: " . push_answer());
		// the browser dropped the subscription, or its push service kept refusing: the row goes
		if ($gone || ($failed && (int) $before['failures'] + 1 >= PUSH_MAX_FAILURES)) {
			dborun("DELETE FROM push_subscriptions WHERE id=".(int) $sub['id']);
			continue;
		}
		// not delivered: tried again next run (the cursors stay where they were)
		if ($failed) {
			dborun("UPDATE push_subscriptions SET failures=failures+1 WHERE id=".(int) $sub['id']);
			continue;
		}
		$changed = (int) $before['failures'] != 0;
		foreach (array('earn_cursor', 'payout_cursor', 'block_cursor', 'earn_sent', 'payout_sent', 'block_sent') as $k)
			if ((int) $sub[$k] != (int) $before[$k]) $changed = true;
		if ($changed)
			dborun("UPDATE push_subscriptions SET earn_cursor=:ec, payout_cursor=:pc, block_cursor=:bc, earn_sent=:es, payout_sent=:ps, block_sent=:bs, failures=0 WHERE id=:id",
				array(':ec' => (int) $sub['earn_cursor'], ':pc' => (int) $sub['payout_cursor'], ':bc' => (int) $sub['block_cursor'],
					':es' => (int) $sub['earn_sent'], ':ps' => (int) $sub['payout_sent'], ':bs' => (int) $sub['block_sent'],
					':id' => (int) $sub['id']));
	}
	if ($sends) debuglog("push: $sends notices sent");
	// stopped before the end (many devices): the service goes on with the rest 2 seconds later
	if ($more && $loop) $last_mark = null;
}

// The bell's list (the top bar): what this device was told about since it turned notifications on,
// within the last 3 days, newest first. Ids: e<earning>, p<payout>, b<block>; the browser keeps
// which ones were seen.
function push_inbox($sub, $limit = 30)
{
	$items = array();
	$wants = (int) $sub['wants'];
	$userid = (int) $sub['userid'];
	$coin = null; $symbol = '';
	if ($userid) {
		$user = getdbo('db_accounts', $userid);
		if (!$user) return $items;
		$coin = getdbo('db_coins', $user->coinid);
		$symbol = $coin ? $coin->symbol : '';
	}
	else $wants &= 4;	// no address: the pool's blocks only
	$since = max((int) $sub['created'], time() - 3 * 24 * 3600);
	$limit = (int) $limit;
	$height = function ($h) { return number_format((int) $h, 0, '.', ','); };

	if ($wants & 1) {
		$rows = dbolist("SELECT e.id, e.amount, e.create_time AS t, b.height, b.blockhash FROM earnings e JOIN blocks b ON b.id=e.blockid "
			."WHERE e.userid=$userid AND e.status>=0 AND e.create_time>=$since ORDER BY e.id DESC LIMIT $limit");
		$kinds = array();
		if ($rows && function_exists('yaamp_block_kinds') && $coin)
			$kinds = yaamp_block_kinds($coin->id, array_map(function ($r) { return $r['blockhash']; }, $rows));
		foreach ($rows as $r) {
			$solo = isset($kinds[$r['blockhash']]) && $kinds[$r['blockhash']] == 'solo';
			$items[] = array('id' => 'e' . $r['id'], 'time' => (int) $r['t'],
				'title' => $solo ? 'You found a block!' : 'A block paid you',
				'body' => $solo ? 'Block ' . $height($r['height']) . ' pays you ' . push_amount($r['amount']) . " $symbol (solo)."
					: push_amount($r['amount']) . " $symbol from block " . $height($r['height']) . ' (shared).');
		}
	}
	if ($wants & 2) {
		$rows = dbolist("SELECT id, amount, memoid, time AS t FROM payouts WHERE account_id=$userid AND completed=1 AND time>=$since "
			."ORDER BY id DESC LIMIT $limit");
		foreach ($rows as $r)
			$items[] = $r['memoid'] === 'coinbase'
				? array('id' => 'p' . $r['id'], 'time' => (int) $r['t'], 'title' => 'Rewards spendable',
					'body' => push_amount($r['amount']) . " $symbol paid in a block can now be spent.")
				: array('id' => 'p' . $r['id'], 'time' => (int) $r['t'], 'title' => 'Payout sent',
					'body' => push_amount($r['amount']) . " $symbol sent to your address.");
	}
	if (($wants & 4) && ($coin || !$userid)) {
		$rows = dbolist("SELECT id, height, blockhash, coin_id, time AS t FROM blocks WHERE " . ($coin ? "coin_id=" . (int) $coin->id . " AND " : '')
			. "category!='orphan' AND time>=$since ORDER BY id DESC LIMIT $limit");
		$kinds = push_block_kinds($rows);
		foreach ($rows as $r)
			$items[] = array('id' => 'b' . $r['id'], 'time' => (int) $r['t'], 'title' => 'The pool found a block',
				'body' => push_blocks_body(array($r), $kinds));
	}
	usort($items, function ($a, $b) { return $b['time'] - $a['time']; });
	return array_slice($items, 0, $limit);
}

// A device starts following an address (or the pool only, $userid 0), or turns a kind of notice
// on: its cursors start at the end of the lists, so it is only told about what happens from now on.
function push_cursors_now($userid, $coinid)
{
	$userid = (int) $userid;
	return array(
		'earn_cursor' => $userid ? (int) dboscalar("SELECT IFNULL(MAX(id),0) FROM earnings WHERE userid=$userid") : 0,
		'payout_cursor' => $userid ? (int) dboscalar("SELECT IFNULL(MAX(id),0) FROM payouts WHERE account_id=$userid") : 0,
		'block_cursor' => (int) dboscalar("SELECT IFNULL(MAX(id),0) FROM blocks" . ($coinid ? " WHERE coin_id=" . (int) $coinid : '')),
	);
}
