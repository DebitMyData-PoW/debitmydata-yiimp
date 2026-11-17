<?php

function yaamp_get_algos()
{
	/* Toggle Site Algos Here */
	return array(
		'a5a',
		'aergo',
		'allium',
		'argon2',
		'argon2d-crds',
		'argon2d-dyn',
		'argon2m',
		'argon2d-uis',
		'bastion',
		'bcd',
		'bitcore',
		'blake',
		'blake2b',
		'blake2s',
		'bmw512',
		'blakecoin',
		'c11',
		'curvehash',
		'decred',
		'deep',
		'dmd-gr',
		'exosis',
		'equihash',
		'groestl', // dmd-gr -m 256 (deprecated)
		'geek',
		'gr',
		'fortune',
		'mike',
		'heavyhash',
		'hex',
		'hmq1725',
		'hsr',
		'keccak',
		'keccakc',
		'lbk3',
		'lbry',
		'luffa',
		'lyra2',
		'lyra2v2',
		'lyra2v3',
		'lyra2vc0ban',
		'lyra2z',
		'lyra2zz',
		'lyra2z330',
		'm7m',
		'myr-gr',
		'neoscrypt',
		'nist5',
		'penta',
		'phi',
		'phi2',
		'phi1612',
		'polytimos',
		'quark',
		'qubit',
		'rainforest',
		'randomx',
		'rinhash',
		'scrypt',
		'scryptn',
		'sha256',
		'sha256csm',
		'sha256q',
		'sha256t',
		'sib',
		'skein',
		'skein2',
		'skunk',
		'sonoa',
		'timetravel',
		'tribus',
		'vanilla',
		'veltor',
		'velvet',
		'vitalium',
		'whirlpool',
		'x11',
		'x11evo',
		'x11kvs',
		'x12',
		'x13',
		'x14',
		'x15',
		'x16r',
		'x16rv2',
		'x16rt',
		'x16s',
		'x17',
		'x20r',
		'x21s',
		'x22i',
		'x25x',
		'xevan',
		'yescrypt',
		'yescryptR8',
		'yescryptR16',
		'yescryptR32',
		'yespower',
		'yespowerR8',
		'yespowerR16',
		'yespowerR24',
		'yespowerR32',
		'yespowerTIDE',
		'yespowerLTNCG',
		'zr5',
		'kawpow',
	);
}

// Used for graphs and 24h profit
// GH/s for fast algos like sha256
function yaamp_algo_mBTC_factor($algo)
{
	switch($algo) {
	case 'scryptn':
	case 'sha256':
               return 1000000000;
	case 'sha256t':
	case 'script':
		return 1000000000;
	case 'gr':
		return 0.001;
	case 'fortune':
		return 0.001;
	case 'mike':
		return 0.001;
	case 'sha256q':
	case 'blake':
	case 'blakecoin':
	case 'blake2s':
	       return 1000000;
	case 'blake2b':
	case 'decred':
	case 'keccak':
	case 'keccakc':
	case 'lbry':
	case 'vanilla':
		return 1000;
	default:
		return 1;
	}
}

// mBTC coef per algo
function yaamp_get_algo_norm($algo)
{
	global $configAlgoNormCoef;
	if (isset($configAlgoNormCoef[$algo]))
		return (float) $configAlgoNormCoef[$algo];

	$a = array(
		'argon2'	=> 1.0,
		'argon2d-crds'	=> 1.0,
		'argon2d-dyn'	=> 1.0,
		'argon2m'	=> 1.0,
		'argon2d-uis'	=> 1.0,
		'blake'		=> 1.0,
		'curvehash'	=> 1.0,
		'gr'		=> 0.001,
		'fortune'	=> 0.001,
		'mike'		=> 0.001,
		'keccak'	=> 1.0,
		'lyra2'		=> 1.0,
		'lyra2v2'	=> 1.0,
		'lyra2v3'	=> 1.0,
		'myr-gr'	=> 1.0,
		'neoscrypt'	=> 1.0,
		'nist5'		=> 1.0,
		'quark'		=> 1.0,
		'qubit'		=> 1.0,
		'scrypt'	=> 1.0,
		'scryptn'	=> 1.0,
		'sha256'	=> 1.0,
		'sha256csm'	=> 1.0,
		'skein'		=> 1.0,
		'skein2'	=> 1.0,
		'velvet'	=> 1.0,
		'whirlpool'	=> 1.0,
		'x11'		=> 1.0,
		'x13'		=> 1.0,
		'yescrypt'	=> 1.0,
		'yescryptR8'	=> 1.0,
		'yescryptR16'	=> 1.0,
		'yescryptR32'	=> 1.0,
		'zr5'		=> 1.0,
		'kawpow'	=> 1.0,
	);

	if(!isset($a[$algo]))
		return 1.0;

	return $a[$algo];
}

function getAlgoColors($algo)
{
	$a = array(
		'a5a'		=> '#f0f0f0',
		'aergo'		=> '#e0d0e0',
		'allium'	=> '#80a0d0',
		'argon2'	=> '#e0d0e0',
		'argon2d-crds'	=> '#e0d0e0',
		'argon2d-dyn'	=> '#e0d0e0',
		'argon2m'	=> '#e0d0e0',
		'argon2d-uis'	=> '#e0d0e0',
		'bastion'	=> '#e0b0b0',
		'bcd'		=> '#ffd880',
		'bitcore'	=> '#f790c0',
		'blake'		=> '#f0f0f0',
		'blake2b'	=> '#f2c81f',
		'blakecoin'	=> '#f0f0f0',
		'bmw512'	=> '#f0f0f0',
		'equihash'      => '#f0f0f0',
		'cuckoo'	=> '#d0a0a0',
		'c11'		=> '#a0a0d0',
		'curvehash'	=> '#d0a0a0',
		'decred'	=> '#f0f0f0',
		'deep'		=> '#e0ffff',
		'dmd-gr'	=> '#a0c0f0',
		'exosis'	=> '#49CCFE',
		'geek'		=> '#d0a0a0',
		'gr'		=> '#80a0d0',
		'fortune'	=> '#80a0d0',
		'mike'		=> '#d0f0a0',
		'groestl'	=> '#d0a0a0',
		'heavyhash'	=> '#d0a0a0',
		'hex'		=> '#c0f0c0',
		'hmq1725'	=> '#ffa0a0',
		'hsr'		=> '#aa70ff',
		'keccak'	=> '#c0f0c0',
		'keccakc'	=> '#c0f0c0',
		'lbk3'		=> '#809aef',
		'lbry'		=> '#b0d0e0',
		'luffa'		=> '#a0c0c0',
		'lyra2'		=> '#80a0f0',
		'lyra2v2'	=> '#80c0f0',
		'lyra2v3'       => '#80a0f0',
		'lyra2z330'	=> '#80b0f0',
		'lyra2vc0ban'   => '#80c0f0',
		'lyra2z'	=> '#80b0f0',
		'lyra2zz'	=> '#80b0f0',
		'm7m'		=> '#d0a0a0',
		'myr-gr'	=> '#a0c0f0',
		'neoscrypt'	=> '#a0d0f0',
		'nist5'		=> '#c0e0e0',
		'penta'		=> '#80c0c0',
		'phi'		=> '#a0a0e0',
		'phi2'		=> '#a0a0e0',
		'phi1612'	=> '#a0a0e0',
		'polytimos'	=> '#dedefe',
		'quark'		=> '#c0c0c0',
		'qubit'		=> '#d0a0f0',
		'rainforest'	=> '#d0f0a0',
		'randomx'    	=> '#d0f0a0',
		'renesis'	=> '#f0b0a0',
		'rinhash'    	=> '#d0f0a0',
		'scrypt'	=> '#c0c0e0',
		'scryptn'	=> '#d0d0d0',
		'sha256'	=> '#d0d0a0',
		'sha256csm'	=> '#d0d0b0',
		'sha256q'	=> '#9696dd',
		'sha256t'	=> '#d0d0f0',
		'sib'		=> '#a0a0c0',
		'skein'		=> '#80a0a0',
		'skein2'	=> '#c8a060',
		'skunk'		=> '#dedefe',
		'sonoa'		=> '#dedefe',
		'timetravel'	=> '#f0b0d0',
		'tribus'	=> '#c0d0d0',
		'vanilla'	=> '#f0f0f0',
		'velvet'	=> '#aac0cc',
		'vitalium'	=> '#f0b0a0',
		'whirlpool'	=> '#d0e0e0',
		'x11'		=> '#f0f0a0',
		'x11evo'	=> '#c0f0c0',
		'x11kvs'	=> '#f0f0a0',
		'x12'		=> '#ffe090',
		'x13'		=> '#ffd880',
		'x14'		=> '#f0c080',
		'x15'		=> '#f0b080',
		'x16r'		=> '#f0b080',
		'x16rv2'        => '#f0b080',
		'x16rt'		=> '#f0b080',
		'x16s'		=> '#f0b080',
		'x17'		=> '#f0b0a0',
		'x18'		=> '#f0b0a0',
		'x20r'		=> '#f0b0a0',
		'x21s'		=> '#f0b0a0',
		'x22i'		=> '#f0a090',
		'x25x'          => '#f0a090',
		'xevan'		=> '#f0b0a0',
		'yescrypt'	=> '#e0d0e0',
		'yescryptR8'	=> '#e0d0e0',
		'yescryptR16'	=> '#e2d0e2',
		'yescryptR32'	=> '#e2d0d2',
		'yespower' 	=> '#e2d0d2',
		'yespowerR8' 	=> '#e2d0d2',
		'yespowerR16' 	=> '#e2d0d2',
		'yespowerR24' 	=> '#e2d0d2',
		'yespowerR32' 	=> '#e2d0d2',
		'yespowerTIDE' 	=> '#e2d0d2',
		'yespowerLTNCG' 	=> '#e2d0d2',
		'zr5'		=> '#d0b0d0',
		'kawpow'        => '#d0bd00',

		'MN'		=> '#ffffff', // MasterNode Earnings
		'PoS'		=> '#ffffff'  // Stake
	);

	if(!isset($a[$algo]))
		return '#ffffff';

	return $a[$algo];
}

function getAlgoPort($algo)
{
	$a = array(
	        'a5a'   	=> 8633,
		'aergo'		=> 3691,
		'allium'	=> 4443,
		'argon2'	=> 4235,
		'argon2d-crds'	=> 4238,
		'argon2d-dyn'	=> 4239,
		'argon2m'	=> 4234,
		'argon2d-uis'	=> 4240,
		'bastion'	=> 6433,
		'bcd'		=> 3643,
		'bitcore'	=> 3556,
		'blake'		=> 5733,
		'blake2b'	=> 5777,
		'blake2s'	=> 5766,
		'blakecoin'	=> 5743,
		'bmw512'	=> 5787,
		'c11'		=> 3573,
		'curvehash'	=> 3343,
		'decred'	=> 3252,
		'deep'		=> 3535,
		'dmd-gr'	=> 5333,
		'equihash' 	=> 8888,
		'exosis'	=> 3557,
		'geek'		=> 3692,
		'gr'		=> 7070,
		'fortune'	=> 3990,
		'mike'		=> 7079,
		'heavyhash'	=> 5136,
		'hex'		=> 5135,
		'hmq1725'	=> 3747,
		'honeycomb'	=> 7777,
		'hsr'		=> 7433,
		'keccak'	=> 5133,
		'keccakc'	=> 5134,
		'lbk3'		=> 5522,
		'lbry'		=> 3334,
		'luffa'		=> 5933,
		'lyra2'		=> 4432,
		'lyra2v2'	=> 4533,
		'lyra2v3'	=> 4433,
		'lyra2vc0ban'    => 4563,
		'lyra2z'	=> 4553,
		'lyra2z330'	=> 4555,
		'lyra2zz'	=> 4556,
		'm7m'		=> 6033,
		'myr-gr'	=> 5433,
		'neoscrypt'	=> 4233,
		'nist5'		=> 3833,
		'penta'		=> 5833,
		'phi'		=> 8333,
		'phi2'		=> 8332,
		'phi1612'	=> 8334,
		'polytimos'	=> 8463,
		'quark'		=> 4033,
		'qubit'		=> 4733,
		'renesis' 	=> 5252,
		'rainforest'	=> 7443,
		'randomx'  	=> 7444,
		'rinhash'  	=> 9554,
		'scrypt'	=> 3433,
		'scryptn'	=> 4333,
		'sha256'	=> 3333,
		'sha256csm'	=> 4473,
		'sha256q'	=> 3337,
		'sha256t'	=> 3339,
		'sib'		=> 5033,
		'skein'		=> 4933,
		'skein2'	=> 5233,
		'skunk'		=> 8433,
		'sonoa'		=> 8733,
		'timetravel'	=> 3555,
		'tribus'	=> 8533,
		'vanilla'	=> 5755,
		'veltor'	=> 5034,
		'velvet'	=> 6133,
		'vitalium'	=> 3233,
		'whirlpool'	=> 4133,
		'x11'		=> 3533,
		'x11evo'	=> 3553,
		'x11kvs'	=> 3536,
		'x12'		=> 3233,
		'x13'		=> 3633,
		'x14'		=> 3933,
		'x15'		=> 3733,
		'x16r'		=> 3636,
		'x16rv2'	=> 3637,
		'x16rt'		=> 7220,
		'x16s'		=> 3663,
		'x17'		=> 3737,
		'x20r'		=> 4300,
		'x21s'		=> 3224,
		'x22i'		=> 3223,
		'x25x'          => 5633,
		'xevan'		=> 3739,
		'yescrypt'	=> 6233,
		'yescryptR8'	=> 6353,
		'yescryptR16'	=> 6333,
		'yescryptR32'	=> 6343,
		'yespower'	=> 6234,
		'yespowerR8'	=> 6235,
		'yespowerR16'	=> 6236,
		'yespowerR24'	=> 6237,
		'yespowerR32'	=> 6238,
		'yespowerTIDE'	=> 6243,
		'yespowerLTNCG'	=> 6241,
		'zr5'		=> 5533,
		'kawpow'        => 3635,
		'randomx'	=> 8284,
		//'groestl'	=> 5333,
		// 5555 to 5683 reserved
	);

	global $configCustomPorts;
	if(isset($configCustomPorts[$algo]))
		return $configCustomPorts[$algo];

	if(!isset($a[$algo]))
		return 3033;

	return $a[$algo];
}

////////////////////////////////////////////////////////////////////////

function yaamp_fee($algo)
{
	$fee = controller()->memcache->get("yaamp_fee-$algo");
	if($fee && is_numeric($fee)) return (float) $fee;

/*	$norm = yaamp_get_algo_norm($algo);
	if($norm == 0) $norm = 1;

	$hashrate = getdbosql('db_hashrate', "algo=:algo order by time desc", array(':algo'=>$algo));
	if(!$hashrate || !$hashrate->difficulty) return 1;

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate_coinonly-$algo",
		"select sum(difficulty) * $target / $interval / 1000 from shares where valid and time>$delay and algo=:algo and jobid=0", array(':algo'=>$algo));

//	$fee = round(log($hashrate->hashrate * $norm / 1000000 / $hashrate->difficulty + 1), 1) + YAAMP_FEES_MINING;
//	$fee = round(log($rate * $norm / 2000000 / $hashrate->difficulty + 1), 1) + YAAMP_FEES_MINING;
*/
	$fee = YAAMP_FEES_MINING;

	// local fees config
	global $configFixedPoolFees;
	if (isset($configFixedPoolFees[$algo])) {
		$fee = (float) $configFixedPoolFees[$algo];
	}

	controller()->memcache->set("yaamp_fee-$algo", $fee);
	return $fee;
}

function take_yaamp_fee($v, $algo, $percent=-1)
{
	if ($percent == -1) $percent = yaamp_fee($algo);

	return $v - ($v * $percent / 100.0);
}

function yaamp_hashrate_constant($algo=null)
{
	return pow(2, 42);		// 0x400 00000000
}

function yaamp_hashrate_step()
{
	return 300;
}

function yaamp_profitability($coin)
{
	if(!$coin->difficulty) return 0;

	$btcmhd = 20116.56761169 / $coin->difficulty * $coin->reward * $coin->price;
	if(!$coin->auxpow && $coin->rpcencoding == 'POW')
	{
		$listaux = getdbolist('db_coins', "enable and visible and auto_ready and auxpow and algo='$coin->algo'");
		foreach($listaux as $aux)
		{
			if(!$aux->difficulty) continue;

			$btcmhdaux = 20116.56761169 / $aux->difficulty * $aux->reward * $aux->price;
			$btcmhd += $btcmhdaux;
		}
	}

	$algo_unit_factor = yaamp_algo_mBTC_factor($coin->algo);
	return $btcmhd * $algo_unit_factor;
}

function yaamp_convert_amount_user($coin, $amount, $user)
{
	$refcoin = getdbo('db_coins', $user->coinid);
	$value = 0.;
	if (YAAMP_ALLOW_EXCHANGE) {
		if(!$refcoin) $refcoin = getdbosql('db_coins', "symbol='BTC'");
		if(!$refcoin || $refcoin->price <= 0) return 0;
		$value = $amount * $coin->price / $refcoin->price;
	} else if ($coin->price && $refcoin && $refcoin->price > 0.) {
		$value = $amount * $coin->price / $refcoin->price;
	} else if ($coin->id == $user->coinid) {
		$value = $amount;
	}
	return $value;
}

function yaamp_convert_earnings_user($user, $status)
{
	$refcoin = getdbo('db_coins', $user->coinid);
	$value = 0.;
	if (YAAMP_ALLOW_EXCHANGE) {
		if(!$refcoin) $refcoin = getdbosql('db_coins', "symbol='BTC'");
		if(!$refcoin || $refcoin->price <= 0) return 0;
		$value = dboscalar("SELECT sum(amount*price) FROM earnings WHERE $status AND userid={$user->id}");
		$value = $value / $refcoin->price;
	} else if ($refcoin && $refcoin->price > 0.) {
		$value = dboscalar("SELECT sum(amount*price) FROM earnings WHERE $status AND userid={$user->id}");
		$value = $value / $refcoin->price;
	} else if ($user->coinid) {
		$value = dboscalar("SELECT sum(amount) FROM earnings WHERE $status AND userid={$user->id} AND coinid=".$user->coinid);
	}
	return $value;
}

//////////////////////////////credits to Alexg for PHP code changes//////////////////////////////////////////

function yaamp_pool_rate($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND algo=:algo", array(':algo'=>$algo));

	return $rate;
}

function yaamp_pool_rate_bad($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_pool_rate_bad-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE not valid AND time>$delay AND algo=:algo", array(':algo'=>$algo));
    $rate = $rate * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_pool_rate_bad-$algo", $rate, $t);
    }

	return $rate;
}

function yaamp_pool_rate_rentable($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_pool_rate_rentable-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE valid AND extranonce1 AND time>$delay AND algo=:algo", array(':algo'=>$algo));
    $rate = $rate * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_pool_rate_rentable-$algo", $rate, $t);
    }

	return $rate;
}

function yaamp_user_rate($userid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_user_rate-$userid-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo", array(':algo'=>$algo));
    $rate = $rate * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_user_rate-$userid-$algo", $rate, $t);
    }

	return $rate;
}

function yaamp_user_rate_bad($userid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_user_rate_bad-$userid-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$diff = (double) controller()->memcache->get_database_scalar("yaamp_user_diff_avg-$userid-$algo",
		"SELECT avg(difficulty) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo", array(':algo'=>$algo));

	$rate = dboscalar("SELECT count(id) FROM shares WHERE valid!=1 AND time>$delay AND userid=$userid AND algo=:algo", array(':algo'=>$algo));
    $rate = $rate * $diff * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_user_rate_bad-$userid-$algo", $rate, $t);
    }

	return $rate;
}

function yaamp_worker_rate($workerid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_worker_rate-$workerid-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE valid AND time>$delay AND workerid=".$workerid);
    $rate = $rate * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_worker_rate-$workerid-$algo", $rate, $t);
    }

	return $rate;
}

function yaamp_worker_rate_bad($workerid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_worker_rate_bad-$workerid-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$diff = (double) controller()->memcache->get_database_scalar("yaamp_worker_diff_avg-$workerid-$algo",
		"SELECT avg(difficulty) FROM shares WHERE valid AND time>$delay AND workerid=".$workerid);

	$rate = dboscalar("SELECT count(id) FROM shares WHERE valid!=1 AND time>$delay AND workerid=".$workerid);

    $rate = $rate * $diff * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_worker_rate_bad-$workerid-$algo", $rate, $t);
    }

	return empty($rate)? 0: $rate;
}

function yaamp_worker_shares_bad($workerid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = (int) controller()->memcache->get_database_scalar("yaamp_worker_shares_bad-$workerid-$algo",
		"SELECT count(id) FROM shares WHERE valid!=1 AND time>$delay AND workerid=".$workerid);

	return $rate;
}

function yaamp_coin_rate($coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

    $rate = controller()->memcache->get("yaamp_coin_rate-$coinid");
    if($rate === false) {

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE valid AND time>$delay AND coinid=$coinid");
    $rate = $rate * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_coin_rate-$coinid", $rate, $t);
    }

	return $rate;
}

/////////////////////////////////////////////////////////////////////////////////////////////
// Solo mining (m=solo in the miner's password): solo shares are marked in the shares table
// (sql/2026-11-12-pplns-coinbase.sql) and each block records who it paid (coinbase_splits).

// The solo column and the coinbase_splits table are there.
function yaamp_has_solo_shares()
{
	static $ok = null;
	if ($ok !== null) return $ok;
	$v = controller()->memcache->get('yaamp_has_solo_shares');
	if ($v === false) {
		$v = (dboscalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() ".
			"AND table_name='shares' AND column_name='solo'") &&
			dboscalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() ".
			"AND table_name='coinbase_splits'")) ? 1 : 0;
		controller()->memcache->set('yaamp_has_solo_shares', $v, 300);
	}
	$ok = (bool) $v;
	return $ok;
}

// Does this account still have money waiting for a balance payout (the payout job, every
// YAAMP_PAYMENTS_FREQ)? Rewards paid in the block itself (PPLNS, solo) are cleared at once
// (earnings status 2) and never wait. What waits: earnings paid the normal way that are maturing
// or matured (status 0 or 1): blocks found with PROP before the switch, or a PPLNS part under the
// stratum's min_output; and a balance big enough for the payout job (over its lowest minimum,
// Sunday evening's: backend/payment.php). The wallet page shows the payout countdown and the
// payouts link only then.
function yaamp_user_payout_waiting($user)
{
	if (!$user || !$user->id) return false;

	$coin = $user->coinid ? getdbo('db_coins', $user->coinid) : null;
	$txfee = $coin ? floatval($coin->txfee) : 0;
	$min = max(floatval(YAAMP_PAYMENTS_MINI), $coin ? floatval($coin->payout_min) : 0, $txfee);
	if (floatval($user->balance) > max($min / 10, $txfee)) return true;

	$waiting = controller()->memcache->get_database_scalar("wallet_payout_waiting-{$user->id}",
		"SELECT COUNT(*) FROM earnings WHERE userid=".intval($user->id)." AND status IN (0,1)");
	return intval($waiting) > 0;
}

// The network's hashrate and difficulty, asked to the coin's node (getnetworkhashps,
// getdifficulty) at most every 30 seconds, for every page (the home page, the wallet pages):
// array('hashrate'=>H/s or null, 'difficulty'=>number, array or null). A node that does not
// answer gives nulls (kept 30 seconds too, so the pages never wait on it).
function yaamp_network_stats($coin)
{
	if (!$coin || !$coin->id) return array('hashrate'=>null, 'difficulty'=>null);

	$cache = controller()->memcache;
	$cache_key = "wallet_network_stats-v1-".intval($coin->id);
	$can_cache = isset($cache->memcache) && function_exists('memcache_get') && function_exists('memcache_set');
	$network = $can_cache ? memcache_get($cache->memcache, $cache_key) : false;
	if (!is_array($network)) {
		$network = array('hashrate'=>null, 'difficulty'=>null);
		$remote = new WalletRPC($coin);
		try {
			$network['hashrate'] = $remote->getnetworkhashps();
		} catch (Exception $e) {
			// an unavailable node should not stop the page
		}
		try {
			$network['difficulty'] = $remote->getdifficulty();
		} catch (Exception $e) {
			// the pages fall back to the stored difficulty
		}
		if ($can_cache) memcache_set($cache->memcache, $cache_key, $network, 0, 30);
	}
	return $network;
}

// The hashrate that finds the pool's blocks: solo miners left out. The pool's TTF uses it.
function yaamp_coin_rate_pool($coinid)
{
	$coinid = intval($coinid);
	if (!yaamp_has_solo_shares()) return yaamp_coin_rate($coinid);

	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$rate = controller()->memcache->get("yaamp_coin_rate_pool-$coinid");
	if($rate === false) {
		$target = yaamp_hashrate_constant($coin->algo);
		$interval = yaamp_hashrate_step();
		$delay = time()-$interval;
		$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE valid AND solo=0 AND time>$delay AND coinid=$coinid");
		$rate = $rate * $target / $interval / 1000;
		controller()->memcache->set("yaamp_coin_rate_pool-$coinid", $rate, 30);
	}
	return $rate;
}

// A miner's solo hashrate on a coin, 0 if it mines with the pool. Its own TTF uses it.
function yaamp_user_rate_solo($userid, $coinid)
{
	$userid = intval($userid);
	$coinid = intval($coinid);
	if (!yaamp_has_solo_shares()) return 0;

	$coin = getdbo('db_coins', $coinid);
	if(!$coin) return 0;

	$rate = controller()->memcache->get("yaamp_user_rate_solo-$userid-$coinid");
	if($rate === false) {
		$target = yaamp_hashrate_constant($coin->algo);
		$interval = yaamp_hashrate_step();
		$delay = time()-$interval;
		$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE valid AND solo=1 AND time>$delay AND userid=$userid AND coinid=$coinid");
		$rate = $rate * $target / $interval / 1000;
		controller()->memcache->set("yaamp_user_rate_solo-$userid-$coinid", $rate, 30);
	}
	return $rate;
}

// Time to find a block at this hashrate, in seconds (0 if unknown).
function yaamp_ttf_seconds($difficulty, $rate)
{
	return ($rate > 0 && $difficulty > 0) ? $difficulty * 0x100000000 / $rate : 0;
}

// How each block was mined and paid, by block hash:
//   'solo'  found by a solo miner, paid to it in the block
//   'pplns' a pool block paid in the block (PPLNS, or the block that closed the PROP round)
//   'prop'  a pool block paid by the website (before coinbase payouts, or the normal way)
function yaamp_block_kinds($coinid, $hashes)
{
	$kinds = array();
	$hashes = array_values(array_filter(array_unique($hashes)));
	if (empty($hashes) || !yaamp_has_solo_shares()) return $kinds;

	$params = array(':coin' => intval($coinid));
	$in = array();
	foreach ($hashes as $i => $h) { $in[] = ":h$i"; $params[":h$i"] = $h; }
	$rows = dbolist("SELECT blockhash, MAX(mode) AS mode, MAX(in_coinbase) AS paid FROM coinbase_splits ".
		"WHERE coinid=:coin AND blockhash IN (".implode(',', $in).") GROUP BY blockhash", $params);
	foreach ($rows as $row) {
		if ($row['mode'] == 3) $kinds[$row['blockhash']] = 'solo';
		else if ($row['mode'] > 0) $kinds[$row['blockhash']] = 'pplns';
		else $kinds[$row['blockhash']] = 'prop';
	}
	return $kinds;
}

// Which of a miner's blocks were paid to it in the block itself, by block hash.
function yaamp_user_coinbase_paid($userid, $coinid, $hashes)
{
	$paid = array();
	$hashes = array_values(array_filter(array_unique($hashes)));
	if (empty($hashes) || !yaamp_has_solo_shares()) return $paid;

	$params = array(':user' => intval($userid), ':coin' => intval($coinid));
	$in = array();
	foreach ($hashes as $i => $h) { $in[] = ":h$i"; $params[":h$i"] = $h; }
	$rows = dbolist("SELECT blockhash FROM coinbase_splits WHERE userid=:user AND coinid=:coin AND in_coinbase=1 ".
		"AND blockhash IN (".implode(',', $in).")", $params);
	foreach ($rows as $row) $paid[$row['blockhash']] = true;
	return $paid;
}

function yaamp_rented_rate($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

    $rate = controller()->memcache->get("yaamp_rented_rate-$algo");
    if($rate === false) {

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = dboscalar("SELECT sum(difficulty) FROM shares WHERE time>$delay AND algo=:algo AND jobid!=0 AND valid", array(':algo'=>$algo));
    $rate = $rate * $target / $interval / 1000;
    $t = 30;
    controller()->memcache->set("yaamp_rented_rate-$algo", $rate, $t);
    }

	return $rate;
}

function yaamp_job_rate($jobid)
{
    $job = getdbo('db_jobs', $jobid);
    if(!$job) return 0;

    $rate = controller()->memcache->get("yaamp_job_rate-$jobid");
    if($rate === false) {

        $target = yaamp_hashrate_constant($job->algo);
        $interval = yaamp_hashrate_step();
        $delay = time()-$interval;

        $rate = dboscalar("SELECT sum(difficulty) FROM jobsubmits WHERE valid AND time>$delay AND jobid=".$jobid);
        $rate = $rate * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_job_rate-$jobid", $rate, $t);
    }
 
    return $rate;
}

function yaamp_job_rate_bad($jobid)
{
    $job = getdbo('db_jobs', $jobid);
    if(!$job) return 0;

    $rate = controller()->memcache->get("yaamp_job_rate_bad-$jobid");
    if($rate === false) {

        $target = yaamp_hashrate_constant($job->algo);
        $interval = yaamp_hashrate_step();
        $delay = time()-$interval;

        $diff = (double) controller()->memcache->get_database_scalar("yaamp_job_diff_avg-$jobid",
            "SELECT avg(difficulty) FROM jobsubmits WHERE valid AND time>$delay AND jobid=".$jobid);

        $rate = dboscalar("SELECT count(id) FROM jobsubmits WHERE valid!=1 AND time>$delay AND jobid=".$jobid);
        $rate = $rate * $diff * $target / $interval / 1000;
        $t = 30;
        controller()->memcache->set("yaamp_job_rate_bad-$jobid", $rate, $t);
    }

    return $rate;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////

function yaamp_pool_rate_pow($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate_pow-$algo",
		"SELECT sum(shares.difficulty) * $target / $interval / 1000 FROM shares, coins
			WHERE shares.valid AND shares.time>$delay AND shares.algo=:algo AND
			shares.coinid=coins.id AND coins.rpcencoding='POW'", array(':algo'=>$algo));

	return $rate;
}

/////////////////////////////////////////////////////////////////////////////////////////////

function yaamp_renter_account($renter)
{
	if(YAAMP_PRODUCTION)
		return "renter-prod-$renter->id";
	else
		return "renter-dev-$renter->id";
}

/////////////////////////////////////////////////////////////////////////////////////////////