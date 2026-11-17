<?php





// echo getAdminSideBarLinks();


//echo " - <a href='/site/coin?id={$coin->id}'>{$coin->name}</a><br/>";
// echo " <a href='/site/coin?id={$coin->id}'><i class='fa-duotone fa-square-arrow-left' style='font-size:100px'></i></a><br/>";


$this->widget('UniForm');

echo '<div class="dmd-admin dmd-coin-form">';
dmd_admin_head(CHtml::encode($coin->name).' <small>settings</small>', $coin);

echo CUFHtml::beginForm();
echo CUFHtml::errorSummary($coin);
echo CUFHtml::openTag('fieldset', array('class'=>'inlineLabels'));

InitMenuTabs('#tabs');

echo '<div id="tabs"><ul>'
	.'<li><a href="#tabs-1">'.dmd_admin_icon('coins').'General</a></li>'
	.'<li><a href="#tabs-2">'.dmd_admin_icon('gear').'Settings</a></li>'
	// .'<li><a href="#tabs-3">Exchange</a></li>'
	.'<li><a href="#tabs-4">'.dmd_admin_icon('cpu').'Daemon</a></li>'
	// .'<li><a href="#tabs-5">Links</a></li>'
	.'<li><a href="#tabs-6">'.dmd_admin_icon('console').'RPC</a></li>'
	.'</ul>';

echo '<div id="tabs-1">';

echo CUFHtml::openActiveCtrlHolder($coin, 'name', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'name', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'name', array('maxlength'=>50));
echo '<p class="formHint2">Required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'symbol', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'symbol', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'symbol', array('maxlength'=>50));
echo '<p class="formHint2">Required all upper case</p>';
echo CUFHtml::closeCtrlHolder();

/****************
echo CUFHtml::openActiveCtrlHolder($coin, 'symbol2', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'symbol2', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'symbol2', array('maxlength'=>50));
echo '<p class="formHint2">Set it if symbol is different</p>';
echo CUFHtml::closeCtrlHolder();
***************/

echo CUFHtml::openActiveCtrlHolder($coin, 'algo', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'algo', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'algo', array('maxlength'=>50));
echo '<p class="formHint2">Required all lower case</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'image', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'image', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'image', array('maxlength'=>50));
echo '<p class="formHint2">&nbsp;</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'payout_min', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'payout_min', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'payout_min', array('maxlength'=>50));
echo '<p class="formHint2">Pay users when they reach this amount</p>';
echo CUFHtml::closeCtrlHolder();

/*********************
echo CUFHtml::openActiveCtrlHolder($coin, 'payout_max', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'payout_max', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'payout_max', array('maxlength'=>50));
echo '<p class="formHint2">Maximum transaction amount</p>';
echo CUFHtml::closeCtrlHolder();
*********************/

echo CUFHtml::openActiveCtrlHolder($coin, 'txfee', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'txfee', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'txfee', array('maxlength'=>50,'readonly'=>'readonly'));
echo '<p class="formHint2">&nbsp;</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'block_height', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'block_height', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'block_height', array('readonly'=>'readonly'));
echo '<p class="formHint2">Current height</p>';
echo CUFHtml::closeCtrlHolder();

/*************************
echo CUFHtml::openActiveCtrlHolder($coin, 'target_height', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'target_height', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'target_height', array('maxlength'=>50));
echo '<p class="formHint2">Known height of the network</p>';
echo CUFHtml::closeCtrlHolder();
*************************/

echo CUFHtml::openActiveCtrlHolder($coin, 'powend_height', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'powend_height', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'powend_height', array('maxlength'=>50));
echo '<p class="formHint2">Height of the end of PoW mining</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'mature_blocks', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'mature_blocks', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'mature_blocks', array('maxlength'=>50));
echo '<p class="formHint2">Required block count to mature</p>';
echo CUFHtml::closeCtrlHolder();

/***********************
echo CUFHtml::openActiveCtrlHolder($coin, 'block_time', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'block_time', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'block_time', array('maxlength'=>50));
echo '<p class="formHint2">Average block time (sec)</p>';
echo CUFHtml::closeCtrlHolder();
*************************/

echo CUFHtml::openActiveCtrlHolder($coin, 'link_explorer', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'link_explorer', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'link_explorer', array('maxlength'=>50));
echo '<p class="formHint2">Link of the official Block explorer</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'errors', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'errors', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'errors', array('maxlength'=>200,'readonly'=>'readonly'));
echo '<p class="formHint2">&nbsp;</p>';
echo CUFHtml::closeCtrlHolder();

echo "</div>";

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div id="tabs-2">';

echo CUFHtml::openActiveCtrlHolder($coin, 'enable', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'enable', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'enable', array('maxlength'=>50));
echo '<p class="formHint2">Required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'auto_ready', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'auto_ready', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'auto_ready', array('maxlength'=>50));
echo '<p class="formHint2">Allowed to mine</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'visible', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'visible', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'visible', array('maxlength'=>50));
echo '<p class="formHint2">Visibility for the public</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'installed', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'installed', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'installed', array('maxlength'=>50));
echo '<p class="formHint2">Required to be visible in the Wallets board</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'no_explorer', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'no_explorer', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'no_explorer', array('maxlength'=>50));
echo '<p class="formHint2">Disable block explorer for the public</p>';
echo CUFHtml::closeCtrlHolder();

/*************************
echo CUFHtml::openActiveCtrlHolder($coin, 'watch', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'watch', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'watch', array('maxlength'=>50));
echo '<p class="formHint2">Track balance and markets history</p>';
echo CUFHtml::closeCtrlHolder();
************************/

echo CUFHtml::openActiveCtrlHolder($coin, 'auxpow', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'auxpow', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'auxpow', array('maxlength'=>50));
echo '<p class="formHint2">Enable this only for merged mining</p>';
echo CUFHtml::closeCtrlHolder();

/******************
echo CUFHtml::openActiveCtrlHolder($coin, 'max_miners', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'max_miners', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'max_miners', array('maxlength'=>50));
echo '<p class="formHint2">Miners allowed by the stratum</p>';
echo CUFHtml::closeCtrlHolder();
*********************/

echo CUFHtml::openActiveCtrlHolder($coin, 'max_shares', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'max_shares', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'max_shares', array('maxlength'=>50));
echo '<p class="formHint2">Auto restart stratum after this amount of shares</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'master_wallet', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'master_wallet', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'master_wallet', array('maxlength'=>50));
echo '<p class="formHint2">The pool wallet address</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'reward', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'reward', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'reward', array('maxlength'=>50));
echo '<p class="formHint2">PoW block value</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'reward_mul', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'reward_mul', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'reward_mul', array('maxlength'=>50));
echo '<p class="formHint2">Adjust the block reward if incorrect</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'charity_percent', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'charity_percent', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'charity_percent', array('maxlength'=>50));
echo '<p class="formHint2">Reward for foundation or dev fees, generally between 1 and 10 %</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'charity_address', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'charity_address', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'charity_address', array('maxlength'=>50));
echo '<p class="formHint2">Foundation address if "dev fees" are required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hasgetinfo', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'hasgetinfo', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'hasgetinfo', array('maxlength'=>50));
echo '<p class="formHint2">Enable if getinfo rpc method is present</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hassubmitblock', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'hassubmitblock', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'hassubmitblock', array('maxlength'=>50));
echo '<p class="formHint2">Enable if submitblock method is present</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'txmessage', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'txmessage', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'txmessage', array('maxlength'=>50));
echo '<p class="formHint2">Block template with a tx message</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hasmasternodes', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'hasmasternodes', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'hasmasternodes', array('maxlength'=>50));
echo '<p class="formHint2">Require "payee" and "payee_amount", or masternode object in getblocktemplate</p>';
echo CUFHtml::closeCtrlHolder();


echo CUFHtml::openActiveCtrlHolder($coin, 'usesegwit', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'usesegwit', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'usesegwit', array('maxlength'=>50));
echo '<p class="formHint2">&nbsp;</p>';
echo CUFHtml::closeCtrlHolder();


echo CUFHtml::openActiveCtrlHolder($coin, 'usemweb', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'usemweb', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'usemweb', array('maxlength'=>50));
echo '<p class="formHint2">&nbsp;</p>';
echo CUFHtml::closeCtrlHolder();


echo "</div>";

//////////////////////////////////////////////////////////////////////////////////////////


/****************************************

echo '<div id="tabs-3">';

echo CUFHtml::openActiveCtrlHolder($coin, 'dontsell');
echo CUFHtml::activeLabelEx($coin, 'dontsell');
echo CUFHtml::activeCheckBox($coin, 'dontsell');
echo '<p class="formHint2">Disable auto send to exchange</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'sellonbid');
echo CUFHtml::activeLabelEx($coin, 'sellonbid');
echo CUFHtml::activeCheckBox($coin, 'sellonbid');
echo '<p class="formHint2">Reduce the sell price on exchanges</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'market');
echo CUFHtml::activeLabelEx($coin, 'market');
echo CUFHtml::activeTextField($coin, 'market', array('maxlength'=>128));
echo '<p class="formHint2">Selected exchange</p>';
echo CUFHtml::closeCtrlHolder();

if (empty($coin->price) || empty($coin->market) || $coin->market == 'unknown') {

	echo CUFHtml::openActiveCtrlHolder($coin, 'price');
	echo CUFHtml::activeLabelEx($coin, 'price');
	echo CUFHtml::activeTextField($coin, 'price', array('maxlength'=>16));
	echo '<p class="formHint2">Manually set the BTC price if missing</p>';
	echo CUFHtml::closeCtrlHolder();

}

echo '</div>';

**************************************************/

//////////////////////////////////////////////////////////////////////////////////////////



/**
echo '<div id="tabs-1">';
echo CUFHtml::openActiveCtrlHolder($coin, 'name', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'name', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'name', array('maxlength'=>50));
echo '<p class="formHint2">Required</p>';
*/

echo '<div id="tabs-4">';

echo CUFHtml::openActiveCtrlHolder($coin, 'program', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'program', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'program', array('maxlength'=>50));
echo '<p class="formHint2">Daemon Name - I.e. bitcoind</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->program)){
echo CUFHtml::openActiveCtrlHolder($coin, 'conf_folder', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'conf_folder', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'conf_folder', array('readonly'=>'readonly', 'maxlength'=>50));
echo '<p class="formHint2">Field will automatically update on save.</p>';
echo CUFHtml::closeCtrlHolder();}

else
if(empty($coin->conf_folder)){
$program = substr($coin->program, 0, -1);
$coin->conf_folder = "/home/crypto-data/wallets/.$program";
echo CUFHtml::openActiveCtrlHolder($coin, 'conf_folder', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'conf_folder', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'conf_folder', array('readonly'=>'readonly', 'maxlength'=>50));
echo '<p class="formHint2">Field will automatically update on save.</p>';
echo CUFHtml::closeCtrlHolder();}

echo CUFHtml::openActiveCtrlHolder($coin, 'rpchost', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpchost', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'rpchost', array('maxlength'=>50));
echo '<p class="formHint2">I.e. 127.0.0.1 WireGuard use 10.0.0.x or Internal IP from host</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->rpcport))
	$coin->rpcport = $coin->id*10;

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcport', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpcport', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'rpcport', array('maxlength'=>50));
echo '<p class="formHint2">Real RPC port or AutoGenerated</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->rpcuser))
	$coin->rpcuser = 'yiimprpc';

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcuser', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpcuser', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'rpcuser', array('maxlength'=>50));
echo '<p class="formHint2">AutoGenerated</p>';
echo CUFHtml::closeCtrlHolder();

// generate a random password
if(empty($coin->rpcpasswd))
	$coin->rpcpasswd = preg_replace("|[^\w]|m",'',base64_encode(pack("H*",md5("".time().YAAMP_SITE_URL))));

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcpasswd', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpcpasswd', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'rpcpasswd', array('maxlength'=>50));
echo '<p class="formHint2">AutoGenerated</p>';
echo CUFHtml::closeCtrlHolder();

/*************
echo CUFHtml::openActiveCtrlHolder($coin, 'serveruser', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'serveruser', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'serveruser', array('maxlength'=>50));
echo '<p class="formHint2">Leave Blank</p>';
echo CUFHtml::closeCtrlHolder();
**************/

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcencoding', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpcencoding', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'rpcencoding', array('maxlength'=>50));
echo '<p class="formHint2">POW/POS</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'dedicatedport', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'dedicatedport', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'dedicatedport', array('maxlength'=>50));
echo '<p class="formHint2">Run addport to get Port Number, leave blank if not using dedicated coin ports.</p>';
echo CUFHtml::closeCtrlHolder();

/******
echo CUFHtml::openActiveCtrlHolder($coin, 'dedicatedport2', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'dedicatedport2', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'dedicatedport2', array('maxlength'=>50));
echo '<p class="formHint2">Second port for a second stratum with a different strating difficulty</p>';
echo CUFHtml::closeCtrlHolder();
********/


/***********************
echo CUFHtml::openActiveCtrlHolder($coin, 'rpccurl', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpccurl', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'rpccurl', array('maxlength'=>50));
echo '<p class="formHint2">Force the stratum to use curl for RPC</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcssl', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpcssl', array('maxlength'=>50));
echo CUFHtml::activeCheckBox($coin, 'rpcssl', array('maxlength'=>50));
echo '<p class="formHint2">Wallet RPC secured via SSL</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpccert', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'rpccert', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'rpccert', array('maxlength'=>50));
echo "<p class='formHint2'>Certificat file for RPC via SSL</p>";
echo CUFHtml::closeCtrlHolder();
******************************/


/****************
echo CUFHtml::openActiveCtrlHolder($coin, 'account', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'account', array('maxlength'=>50));
echo CUFHtml::activeTextField($coin, 'account', array('maxlength'=>50));
echo '<p class="formHint2">Leave Blank</p>';
echo CUFHtml::closeCtrlHolder();
*********************/

echo CUFHtml::openActiveCtrlHolder($coin, 'specifications', array('maxlength'=>50));
echo CUFHtml::activeLabelEx($coin, 'specifications', array('maxlength'=>50));
echo CUFHtml::activeTextArea($coin, 'specifications', array('maxlength'=>1048,'lines'=>35));
echo '<p class="formHint2">Max 35 limit</p>';
echo CUFHtml::closeCtrlHolder();


echo "</div>";


//////////////////////////////////////////////////////////////////////////////////////////



echo '<div id="tabs-6">';

if ($coin->id) {
	if(empty($coin->program)){
		echo '<div class="dmd-conf"><p>Configuration information will be displayed after Process Name is entered and saved. Please reload page once completed.</p></div>';
	}
	else {
		$program = substr($coin->program, 0, -1);
		$port = getAlgoPort($coin->algo);
		$dedport = $coin->dedicatedport;

		// the wallet's config file, for /home/crypto-data/wallets/.<program>/<program>.conf
		$conf  = "rpcuser={$coin->rpcuser}\n";
		$conf .= "rpcpassword={$coin->rpcpasswd}\n";
		$conf .= "rpcport={$coin->rpcport}\n";
		$conf .= "rpcthreads=64\n";
		$conf .= "rpcallowip=127.0.0.1\n";
		$conf .= "onlynet=ipv4\n";
		$conf .= "maxconnections=0\n";
		$conf .= "daemon=1\n";
		$conf .= "listen=0\n";
		$conf .= "server=1\n";
		$conf .= "txindex=1\n";
		$conf .= "gen=0\n";
		$conf .= "masternode=0\n";
		$conf .= "staking=0\n";
		$conf .= "settxfee=0.0001\n";
		$conf .= "fallbackfee=0.0001\n";
		$conf .= "deprecatedrpc=accounts\n";
		$conf .= "addresstype=legacy\n";
		$conf .= "\n";
		$conf .= "debug=tor\n";
		$conf .= "proxy=127.0.0.1:9050\n";
		$conf .= "\n";
		$conf .= "# ADDNODES:\n";
		$conf .= "addnode=zq52inopl6knxj7bjyapemfnsvuuqsjrh6oogqhb2hnav2dyb7sph5ad.onion:PORT\n";
		$conf .= "\n";
		$conf .= "alertnotify=echo %s | mail -s {$coin->name} alert! youremail@gmail.com\n";
		$conf .= "blocknotify=blocknotify 127.0.0.1:$dedport {$coin->id} %s\n";

		echo '<div class="dmd-conf">';
		echo '<p>The wallet\'s config file for this coin:</p>';
		echo '<pre class="dmd-codebox">'.CHtml::encode($conf).'</pre>';
		echo '<button type="button" class="dmd-button dmd-ghost dmd-sm" onclick="myFunctionCONFCONTENT()">'.dmd_admin_icon('copy').'Copy</button>';
		echo "<textarea id='myTextareaCONFCONTENT' class='dmd-copy-src' tabindex='-1' aria-hidden='true'>\n".CHtml::encode($conf)."</textarea>\n";
		echo '</div>';
	}
}

echo "</div>";



echo <<<END
<script>

////////////////////////////////////////////////////

function myFunctionCONFCONTENT() {
  var copyText = document.getElementById("myTextareaCONFCONTENT");
  copyText.select();
  copyText.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(copyText.value);

    Swal.fire({
	customClass : {
	  title: 'swal2-title',
	},
        title: 'Copied!',
        //text: copyText.value,
        icon: 'success',
	      iconColor: '#c80815',
        showCancelButton: false,
        confirmButtonText: 'Ok',
        showConfirmButton: false,
        cancelButtonText: 'Nein',
        timer: 1200,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("myFunctionCONFCONTENT").submit();
        }
    })

}

////////////////////////////////////////////////////



</script>
END;





//////////////////////////////////////////////////////////////////////////////////////////


echo "</div>";

echo CUFHtml::closeTag('fieldset');
showSubmitButton($update? 'Save': 'Create');
echo CUFHtml::endForm();
echo '</div>';
