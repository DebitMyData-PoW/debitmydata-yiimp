<?php

class SiteController extends CommonController
{
	public $defaultAction='index';

	///////////////////////////////////////////////////
	// Security Note: You can rename this action as you
	// want, to customize the admin entrance url...
	//
	public function actionAdminPortal()
	{
		// Only the exact address that nginx protects with its password (location /site/AdminPortal)
		// may open the admin, after that password: the framework also answers other spellings of
		// this page (/site/adminportal, /Site/AdminPortal, /index.php/site/AdminPortal...) that
		// nginx's block does not cover, and the IP checks below always say yes on this pool.
		$path = parse_url(arraySafeVal($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH);
		if (($path !== '/site/AdminPortal' && $path !== '/site/AdminPortal/') || arraySafeVal($_SERVER, 'PHP_AUTH_USER', '') === '') {
			debuglog("admin portal refused: $path from " . arraySafeVal($_SERVER, 'REMOTE_ADDR'));
			throw new CHttpException(404, 'The requested page does not exist.');
		}

		$client_ip = arraySafeVal($_SERVER,'REMOTE_ADDR');
		$valid = isAdminIP($client_ip);

		if (arraySafeVal($_SERVER,'HTTP_X_FORWARDED_FOR','') != '') {
			debuglog("admin access attempt via IP spoofing!");
			$valid = true;
		}

		if ($valid)
			debuglog("admin connect from $client_ip");
		else
			debuglog("admin connect failure from $client_ip");

		user()->setState('yaamp_admin', $valid);

		$this->redirect("/coin");
	}

	// "Log out" in the admin pages' tabs (POST /site/adminLogout): the admin session of this browser
	// ends (core/functions/admin.php).
	public function actionAdminLogout()
	{
		header('Content-Type: application/json');
		header('Cache-Control: no-store');
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			http_response_code(405);
			echo json_encode(array('error' => 'POST only'));
			return;
		}
		if (user()->getState('yaamp_admin')) debuglog('admin logout from ' . arraySafeVal($_SERVER, 'REMOTE_ADDR'));
		user()->setState('yaamp_admin', false);
		echo json_encode(array('ok' => true));
	}

	/////////////////////////////////////////////////

	public function actionCreate()
	{
		if(!$this->admin) return;

		$coin = new db_coins;
		$coin->txmessage = true;
		$coin->created = time();
		$coin->index_avg = 1;
		$coin->difficulty = 1;
		$coin->installed = 1;
		$coin->visible = 1;

	//	$coin->deposit_minimum = 1;
		$coin->lastblock = '';

		if(isset($_POST['db_coins']))
		{
			$coin->setAttributes($_POST['db_coins'], false);
			if($coin->save())
				$this->redirect(array('admin'));
		}

		$this->render('coin_form', array('update'=>false, 'coin'=>$coin));
	}

	public function actionUpdate()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$txfee = $coin->txfee;

		if($coin && isset($_POST['db_coins']))
		{
			$coin->setScenario('update');
			$coin->setAttributes($_POST['db_coins'], false);

			if($coin->save())
			{
				if($txfee != $coin->txfee)
				{
					$remote = new WalletRPC($coin);
					$remote->settxfee($coin->txfee);
				}
				$this->redirect(array('coin', 'id'=>$coin->id));
			//	$this->goback();
			}
		}

		$this->render('coin_form', array('update'=>true, 'coin'=>$coin));
	}

	/////////////////////////////////////////////////

	public function actionPeers()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_peers', array('coin'=>$coin));
	}

	public function actionPeerRemove()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$node = getparam('node');
		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding == 'DCR') {
				$res = $remote->node('disconnect', $node);
				if (!$res) $res = $remote->node('remove', $node);
				$remote->error = false; // ignore
			} else {
				$res = $remote->addnode($node, 'remove');
			}
			if (!$res && $remote->error) {
				user()->setFlash('error', "$node ".$remote->error);
			}
		}
		$this->goback();
	}

	public function actionPeerAdd()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$node = arraySafeVal($_POST, 'node');
		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding == 'DCR') {
				$remote->addnode($node, 'add');
				usleep(500*1000);
				$remote->node('connect', $node);
				sleep(1);
			} else {
				$res = $remote->addnode($node, 'add');
				if (!$res) {
					user()->setFlash('error', "$node ".$remote->error);
				} else {
					sleep(1);
				}
			}
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionTickets()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_tickets', array('coin'=>$coin));
	}

	public function actionTicketBuy()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$spendlimit = (double) arraySafeVal($_POST, 'spendlimit');
		$quantity  = (int) arraySafeVal($_POST, 'quantity');
		if ($coin && $spendlimit) {
			$remote = new WalletRPC($coin);
			if ($quantity <= 1)
				$res = $remote->purchaseticket($coin->account, $spendlimit);
			else
				$res = $remote->purchaseticket($coin->account, $spendlimit, 1, $coin->master_wallet, $quantity);
			if ($res === false)
				user()->setFlash('error', $remote->error);
			else
				user()->setFlash('message', is_string($res) ? "ticket txid: $res" : json_encode($res));
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionBookmarkAdd()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			$bookmark = new db_bookmarks;
			$bookmark->isNewRecord = true;
			$bookmark->idcoin = $coin->id;
			if (isset($_POST['db_bookmarks'])) {
				$bookmark->setAttributes($_POST['db_bookmarks'], false);
				if($bookmark->save())
					$this->redirect(array('/site/coin', 'id'=>$coin->id));
			}
			$this->render('bookmark', array('bookmark'=>$bookmark, 'coin'=>$coin));
		} else {
			$this->goback();
		}
	}

	public function actionBookmarkDel()
	{
		if(!$this->admin) return;
		$bookmark = getdbo('db_bookmarks', getiparam('id'));
		if ($bookmark) {
			$bookmark->delete();
		}
		$this->goback();
	}

	public function actionBookmarkEdit()
	{
		if(!$this->admin) return;
		$bookmark = getdbo('db_bookmarks', getiparam('id'));
		if($bookmark) {
			$coin = getdbo('db_coins', $bookmark->idcoin);
			if ($coin && isset($_POST['db_bookmarks'])) {
				$bookmark->setAttributes($_POST['db_bookmarks'], false);
				if($bookmark->save())
					$this->redirect(array('/site/coin', 'id'=>$coin->id));
			}
			$this->render('bookmark', array('bookmark'=>$bookmark, 'coin'=>$coin));
		} else {
			user()->setFlash('error', "invalid bookmark");
			$this->goback();
		}
	}

	public function actionBookmarkSend()
	{
		if(!$this->admin) return;

		$bookmark = getdbo('db_bookmarks', getiparam('id'));
		if($bookmark) {
			$coin = getdbo('db_coins', $bookmark->idcoin);
			$amount = getparam('amount');

			$remote = new WalletRPC($coin);

			$info = $remote->getinfo();
			if(!$info || !$info['balance']) return false;

			$deposit_info = $remote->validateaddress($bookmark->address);
			if(!$deposit_info || !isset($deposit_info['isvalid']) || !$deposit_info['isvalid']) {
				user()->setFlash('error', "invalid address for {$coin->name}, {$bookmark->address}");
				$this->redirect(array('site/coin', 'id'=>$coin->id));
			}

			$amount = min($amount, $info['balance'] - $info['paytxfee']);
			$amount = round($amount, 8);

			$tx = $remote->sendtoaddress($bookmark->address, $amount);
			if(!$tx) {
				debuglog("unable to send $amount {$coin->symbol} to bookmark {$bookmark->address}");
				debuglog($remote->error);
				user()->setFlash('error', $remote->error);
				$this->redirect(array('site/coin', 'id'=>$coin->id));
			} else {
				debuglog("sent $amount {$coin->symbol} to bookmark {$bookmark->address}");
				$bookmark->lastused = time();
				$bookmark->save();
				BackendUpdatePoolBalances($coin->id);
			}
		}

		$this->redirect(array('site/coin', 'id'=>$coin->id));
	}

	/////////////////////////////////////////////////

	public function actionConsole()
	{
		if(!$this->admin || !YAAMP_ADMIN_WEBCONSOLE) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_console', array(
			'coin'=>$coin,
			'query'=>arraySafeVal($_POST,'query'),
		));
	}

	/////////////////////////////////////////////////

	public function actionTriggers()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_triggers', array(
			'coin'=>$coin,
		));
	}

	public function actionTriggerEnable()
	{
		if(!$this->admin) return;
		$rule = getdbo('db_notifications', getiparam('id'));
		if ($rule) {
			$rule->enabled = (int) getiparam('en');
			$rule->save();
		}

		$this->goback();
	}

	public function actionTriggerReset()
	{
		if(!$this->admin) return;
		$rule = getdbo('db_notifications', getiparam('id'));
		if ($rule) {
			$rule->lasttriggered = 0;
			$rule->save();
		}

		$this->goback();
	}

	public function actionTriggerDel()
	{
		if(!$this->admin) return;
		$rule = getdbo('db_notifications', getiparam('id'));
		if ($rule) {
			$rule->delete();
		}

		$this->goback();
	}

	public function actionTriggerAdd()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$valid = true;
		$rule = new db_notifications;
		$rule->idcoin = $coin->id;
		$rule->notifytype = $_POST['notifytype'];
		$rule->conditiontype = $_POST['conditiontype'];
		$rule->conditionvalue = $_POST['conditionvalue'];
		$rule->notifycmd = $_POST['notifycmd'];
		$rule->description = $_POST['description'];
		$rule->enabled = 1;
		$rule->lastchecked = 0; // time
		$rule->lasttriggered = 0;

		$words = explode(' ', $rule->conditiontype);
		if (count($words) < 2) {
			user()->setFlash('error', "missing space in condition, sample : 'balance <' 5");
			$valid = false;
		}
		if ($valid) {
			$rule->save();
		}

		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionBotnets()
	{
		if(!$this->admin) return;

		$this->render('botnets');
	}

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		// the old wallet page is gone: /?address=... (YIIMP's usual wallet link) opens the wallet page
		if(isset($_GET['address']))
			$this->dmdWalletRedirect();
		else
			$this->render('index');
	}

	// To the wallet page (/mywallet/), with the address when it is a valid one.
	private function dmdWalletRedirect()
	{
		$address = (string) getparam('address');
		$url = '/mywallet/';
		if(preg_match('/^[A-Za-z0-9]{1,128}$/', $address)) $url .= '?address='.$address;
		$this->redirect($url);
	}

	/////////////////////////////////////////////////

	// the old test page is gone: /site/test opens the wallet page (with ?address= when given)
	public function actionTest()
	{
		$this->dmdWalletRedirect();
	}
	/////////////////////////////////////////////////

	public function actionApi()
	{
		$this->render('api');
	}

	public function actionBenchmarks()
	{
		$this->render('benchmarks');
	}

	public function actionDiff()
	{
		$this->render('diff');
	}

/**
	public function actionMultialgo()
	{
		$this->render('multialgo');
	}
*/

/**
	public function actionMining()
	{
		$this->render('mining');
	}
*/
	public function actionInfo()
	{
		$this->render('info');
	}

	public function actionInfo4()
	{
		$this->renderPartialAlgoMemcached('info4');
	}

	// The public list of all miners is removed. Old links land on the home page
	// instead of an error, so they don't fill the log.
	public function actionMiners()
	{
		$this->redirect('/');
	}

	/////////////////////////////////

	protected function renderPartialAlgoMemcached($partial, $cachetime=15)
	{
		$algo = user()->getState('yaamp-algo');
		$memcache = controller()->memcache->memcache;
		$memkey = $algo.'_'.str_replace('/','_',$partial);
		$html = controller()->memcache->get($memkey);

		if (!empty($html)) {
			echo $html;
			return;
		}

		ob_start();
		ob_implicit_flush(false);
		$this->renderPartial($partial);
		$html = ob_get_clean();
		echo $html;

		controller()->memcache->set($memkey, $html, $cachetime, MEMCACHE_COMPRESSED);
	}

	// COINS START

	// COINS END

	public function actionInfo4_results()
	{
		$this->renderPartialAlgoMemcached('results/info4_results');
	}

	// Pool tab: graph algo pool hashrate for sha256 algo (json data)
	public function actionGraph_hashrate_sha256_results()
	{
		$this->renderPartialAlgoMemcached('results/graph_hashrate_sha256_results');
	}

	// Home page: the pool hashrate chart of a period (json): 5m, 15m, 4h, 24h, 7d, 1y or all
	public function actionGraph_pool_hashrate()
	{
		$range = getparam('range', '24h');
		if (!in_array($range, array('5m', '15m', '4h', '24h', '7d', '1y', 'all'), true)) $range = '24h';
		$memkey = 'sha256_graph_pool_hashrate_'.$range;
		$json = controller()->memcache->get($memkey);
		if (empty($json)) {
			ob_start();
			ob_implicit_flush(false);
			$this->renderPartial('results/graph_pool_hashrate_results', array('range' => $range));
			$json = ob_get_clean();
			controller()->memcache->set($memkey, $json, $range == '5m' ? 5 : 15, MEMCACHE_COMPRESSED);
		}
		header('Content-Type: application/json');
		header('Cache-Control: no-cache');
		echo $json;
	}

	// Pool tab: last 50 blocks
	public function actionFound_results()
	{
		$this->renderPartialAlgoMemcached('results/found_results');
	}

	public function actionWallet_results()
	{
		$this->renderPartial('results/wallet_results');
	}

	public function actionWallet_miners_results1()
	{
		$this->renderPartial('results/wallet_miners_results1');
	}

	public function actionWallet_miners_results2()
	{
		$this->renderPartial('results/wallet_miners_results2');
	}

	public function actionWallet_graphs_results()
	{
		$this->renderPartial('results/wallet_graphs_results');
	}

	public function actionUser_earning_results2()
	{
    	$this->renderPartial('results/user_earning_results');
	}

	public function actionGraph_user_results()
	{
		$this->renderPartial('results/graph_user_results');
	}

	public function actionGraph_assets_results()
	{
		$this->renderPartial('results/graph_assets_results');
	}

	public function actionGraph_negative_results()
	{
		$this->renderPartial('results/graph_negative_results');
	}

	public function actionGraph_profit_results()
	{
		$this->renderPartial('results/graph_profit_results');
	}

	/////////////////////////////////////////////////

	public function actionGraphMarketPrices()
	{
		if (!$this->admin) return;
		$this->renderPartial('results/graph_market_prices', array('id'=> getiparam('id')));
	}

	public function actionGraphMarketBalance()
	{
		if (!$this->admin) return;
		$this->renderPartial('results/graph_market_balance', array('id'=> getiparam('id')));
	}

	/////////////////////////////////////////////////

/**
	public function actionAbout()
	{
		$this->render('about');
	}

	public function actionTerms()
	{
		$this->render('terms');
	}
*/
	/////////////////////////////////////////////////

	public function actionAdmin()
	{
		if(!$this->admin) return;
		$this->render('admin');
	}

	public function actionAdmin_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('admin_results');
	}

	/////////////////////////////////////////////////

	public function actionConnections()
	{
		if(!$this->admin) return;
		$this->render('connections');
	}

	public function actionConnections_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('connections_results');
	}

	/////////////////////////////////////////////////

	public function actionBlock()
	{
		$this->render('block');
	}

/**
	public function actionBlock_results()
	{
		$this->renderPartialAlgoMemcached('results/block_results');
	}
*/
	/////////////////////////////////////////////////

	public function actionEarning()
	{
		if(!$this->admin) return;
		$this->render('earning');
	}

	public function actionEarning_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('earning_results');
	}

	// called from the wallet
	public function actionClearearnings()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			BackendClearEarnings($coin->id);
		}
		$this->goback();
	}

	// called from the earnings page
	public function actionClearearning()
	{
		if(!$this->admin) return;
		$earning = getdbo('db_earnings', getiparam('id'));
		if ($earning && $earning->status == 0) {
			$earning->delete();
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionPayments()
	{
		if(!$this->admin) return;
		$this->render('payments');
	}

	public function actionPayments_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('payments_results');
	}

	public function actionPayments_results2()
	{
		$this->renderPartial('payments_results2');
	}

	public function actionCancelUserPayment()
	{
		if(!$this->admin) return;
		$user = getdbo('db_accounts', getiparam('id'));
		if ($user) {
			BackendUserCancelFailedPayment($user->id);
		}
		$this->goback();
	}

	public function actionCancelUsersPayment()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			$amount_failed = 0.0; $cnt = 0;
			$time = time() - (48 * 3600);
			$failed = getdbolist('db_payouts', "idcoin=:id AND IFNULL(tx,'') = '' AND time>$time", array(':id'=>$coin->id));
			if (!empty($failed)) {
				foreach ($failed as $payout) {
					$user = getdbo('db_accounts', $payout->account_id);
					if ($user) {
						$user->balance += floatval($payout->amount);
						if ($user->save()) {
							$amount_failed += floatval($payout->amount);
							$cnt++;
						}
					}
					$payout->delete();
				}
				user()->setFlash('message', "Restored $cnt failed txs to user balances, $amount_failed {$coin->symbol}");
			} else {
				user()->setFlash('message', 'No failed txs found');
			}
		} else {
			user()->setFlash('error', 'Invalid coin id!');
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionUser()
	{
		if(!$this->admin) return;
		$this->render('user');
	}

	// The list of all miners: admins only
	public function actionUsers()
	{
		if(!$this->admin) return;
		$this->render('users');
	}

	public function actionUser_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('user_results');
	}

	public function actionUsers_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('users_results');
	}

	/////////////////////////////////////////////////

	public function actionWorker()
	{
		if(!$this->admin) return;
		$this->render('worker');
	}

	public function actionWorker_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('worker_results');
	}

	public function actionWorker_results2()
	{
		$this->renderPartial('worker_results2');
	}

	public function actionWorkers_results()
	{
		$this->renderPartial('workers_results');
	}

	/////////////////////////////////////////////////

	public function actionVersion()
	{
		if(!$this->admin) return;
		$this->render('version');
	}

	public function actionVersion_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('version_results');
	}

	/////////////////////////////////////////////////

	public function actionCommon()
	{
		if(!$this->admin) return;
		$this->render('common');
	}

	public function actionCommon_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('common_results');
	}

	/////////////////////////////////////////////////

	public function actionBalances()
	{
		if(!$this->admin) return;
		$this->render('balances');
	}

	public function actionBalances_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('balances_results');
	}

	public function actionBalanceUpdate()
	{
		if(!$this->admin) return;
		$id = getiparam('market');
		$market = getdbo('db_markets', $id);
		if ($market) {
			exchange_update_market_by_id($id);
			$this->redirect(array('/site/balances', 'exch'=>$market->name));
		} else {
			$this->goback();
		}
	}

	/////////////////////////////////////////////////

	public function actionExchange()
	{
		if(!$this->admin) return;
		$this->render('exchange');
	}

	public function actionExchange_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('exchange_results');
	}

	/////////////////////////////////////////////////

	public function actionCoin()
	{
		if(!$this->admin) return;
		$this->render('coin');
	}

	public function actionCoin_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('coin_results');
	}

	public function actionCoin2_results()
	{
		$this->renderPartial('coin2_results');
	}

	public function actionCoin_results2()
	{
		$this->renderPartial('coin_results2');
	}

	public function actionMemcached()
	{
		if(!$this->admin) return;
		$this->render('memcached');
	}

	public function actionMonsters()
	{
		if(!$this->admin) return;
		$this->render('monsters');
	}

	public function actionEmptyMarkets()
	{
		if(!$this->admin) return;
		$this->render('emptymarkets');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionTx()
	{
		$this->render('tx');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionWorkers()
	{
		$this->renderPartial('workers');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionResetBlockchain()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$coin->action = 3;
		$coin->save();

		$this->redirect("/site/coin?id=$coin->id");
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionRestartCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 4;
		$coin->enable = false;
		$coin->auto_ready = false;
		$coin->installed = true;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionStartCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 1;
		$coin->enable = true;
		$coin->auto_ready = false;
		$coin->installed = true;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionStopCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 2;
		$coin->enable = false;
		$coin->auto_ready = false;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionMakeConfigfile()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 5;
		$coin->installed = true;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionSetauto()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->auto_ready = true;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionUnsetauto()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->auto_ready = false;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionBanUser()
	{
		if(!$this->admin) return;

		$user = getdbo('db_accounts', getiparam('id'));
		if($user) {
			$user->is_locked = true;
			$user->balance = 0;
			$user->save();
		}

		$this->goback();
	}

	public function actionBlockuser()
	{
		if(!$this->admin) return;

		$wallet = getparam('wallet');
		$user = getuserparam($wallet);
		if($user) {
			$user->is_locked = true;
			$user->save();
		}

		$this->goback();
	}

	public function actionUnblockuser()
	{
		if(!$this->admin) return;

		$wallet = getparam('wallet');
		$user = getuserparam($wallet);
		if($user) {
			$user->is_locked = false;
			$user->save();
		}

		$this->goback();
	}

	public function actionLoguser()
	{
		if(!$this->admin) return;

		$user = getdbo('db_accounts', getiparam('id'));
		if($user) {
			$user->logtraffic = getiparam('en');
			$user->save();
		}

		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	// called from the wallet
	public function actionPayuserscoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			BackendCoinPayments($coin);
		}
		$this->goback();
	}

	// called from the wallet
	public function actionCheckblocks()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			BackendBlockFind1($coin->id);
			BackendBlocksUpdate($coin->id);
			BackendBlockFind2($coin->id);
			BackendUpdatePoolBalances($coin->id);
		}
		$this->goback();
	}

	////

	// called from the wallet
	public function actionDeleteEarnings()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			dborun("DELETE FROM earnings WHERE coinid={$coin->id}");
		}
		$this->goback();
	}

	// called from the earnings page
	public function actionDeleteEarning()
	{
		if(!$this->admin) return;
		$earning = getdbo('db_earnings', getiparam('id'));
		if($earning) {
			$earning->delete();
		}
		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionDeleteExchange()
	{
		if(!$this->admin) return;
		$exchange = getdbo('db_exchange', getiparam('id'));
		if ($exchange) {
			$exchange->status = 'deleted';
			$exchange->price = 0;
			$exchange->receive_time = time();
			$exchange->save();
		}
		$this->goback();
	}

	public function actionClearMarket()
	{
		if(!$this->admin) return;
		$market = getdbo('db_markets', getiparam('id'));
		if($market) {
			$market->lastsent = null;
			$market->save();
		}
		$this->goback();
	}

	// called from the dashboard page
	public function actionClearorder()
	{
		if(!$this->admin) return;
		$order = getdbo('db_orders', getiparam('id'));
		if ($order) {
			$order->delete();
		}
		$this->goback();
	}

	public function actionCancelorder()
	{
		if(!$this->admin) return;
		$order = getdbo('db_orders', getiparam('id'));

		cancelExchangeOrder($order);

		$this->goback();
	}

	////////////////////////////////////////////////////////////////////////////////////////

	public function actionAlgo()
	{
		$algo = getalgoparam();
		$a = getdbosql('db_algos', "name=:name", array(':name'=>$algo));

		if($a)
			user()->setState('yaamp-algo', $a->name);
		else
			user()->setState('yaamp-algo', 'all');

		$route = getparam('r');
		if (!empty($route))
			$this->redirect($route);
		else
			$this->goback();
	}

	public function actionGomining()
	{
		$algo = getalgoparam();
		if ($algo == 'all') {
			return;
		}
		user()->setState('yaamp-algo', $algo);
		$this->redirect("/site/mining");
	}

	public function actionUpdatePrice()
	{
		if(!$this->admin) return;
		BackendPricesUpdate();
		$this->goback();
	}

	public function actionUninstallCoin()
	{
		if(!$this->admin) return;

		$coin = getdbo('db_coins', getiparam('id'));
		if($coin)
		{
		//	dborun("delete from blocks where coin_id=$coin->id");
			dborun("delete from exchange where coinid=$coin->id");
			dborun("delete from earnings where coinid=$coin->id");
		//	dborun("delete from markets where coinid=$coin->id");
			dborun("delete from orders where coinid=$coin->id");
			dborun("delete from shares where coinid=$coin->id");

			$coin->enable = false;
			$coin->installed = false;
			$coin->auto_ready = false;
			$coin->master_wallet = null;
			$coin->mint = 0;
			$coin->balance = 0;
			$coin->save();
		}

		$this->redirect("/site/admin");
	}

	public function actionRunExchange()
	{
		if(!$this->admin) return;

		$id = getiparam('id');
		$balance = getdbo('db_balances', $id);

		if($balance)
			runExchange($balance->name);

		$tm = round($this->elapsedTime(), 3);

		if ($balance)
			debuglog("runexchange done ({$balance->name}) {$tm} sec");
		else
			debuglog("runexchange failed (no id!)");

		$this->redirect("/coin");
	}

}
