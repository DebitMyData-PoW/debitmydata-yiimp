<?php
/*
 * Admin: a coin wallet's console (/site/console?id=): send one RPC command to the wallet and read
 * its answer. Addresses and hashes in the answer become links after 2 seconds.
 */

if (!$coin) $this->goback();

$this->pageTitle = 'Console - '.$coin->symbol;

$remote = new WalletRPC($coin);

$info = $remote->getinfo();

echo '<div class="dmd-admin dmd-console">';
dmd_admin_head(CHtml::encode($coin->name).' <small>console</small>', $coin, $info ? $info : false);

if (!$info) {
	echo '<p class="dmd-alert">The wallet does not answer: '.CHtml::encode($remote->error).'</p>';
	echo '</div>';
	return;
}

//////////////////////////////////////////////////////////////////////////////////////

function colorizeJson($json)
{
	$json = str_replace('"', '&quot;', $json);
	// strings
	$res = preg_match_all("# &quot;([^&]+)&quot;([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$class = '';
		if (strlen($m) == 64 && ctype_xdigit($m)) $class = 'hash';
		if (strlen($m) == 34 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) == 35 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) > 160 && ctype_alnum($m)) $class = 'data';
		if ($class == '' && strlen($m) < 64 && ctype_xdigit($m)) $class = 'hexa';
		$json = str_replace(' &quot;'.$m."&quot;".$sfx, ' "<s class="'.$class.'">'.$m.'</s>"'.$sfx, $json);
	}
	// keys
	$res = preg_match_all("#&quot;([^&]+)&quot;:#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$json = str_replace('&quot;'.$m."&quot;", '"<s class="key">'.$m.'</s>"', $json);
	}
	// humanize timestamps like "blocktime": 1462359961,
	$res = preg_match_all("#: ([0-9]{10})([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$ts = intval($m);
		if ($ts > 1400000000 && $ts < 1600000000) {
			$sfx = $matches[2][$n];
			$date = strftime("<u>%Y-%m-%d %T %z</u>", $ts);
			$json = str_replace(' '.$m.$sfx, ' "'.$date.'"'.$sfx, $json);
		}
	}
	// numeric
	$res = preg_match_all("#: ([e\-\.0-9]+)([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$json = str_replace(' '.$m.$sfx, ' <i>'.$m.'</i>'.$sfx, $json);
	}

	$json = preg_replace('#\[\s+\]#', '[]', $json);
	$json = str_replace('[', '<b>[</b>', $json);
	$json = str_replace(']', '<b>]</b>', $json);
	$json = str_replace('{', '<b>{</b>', $json);
	$json = str_replace('}', '<b>}</b>', $json);

	return $json;
}

//////////////////////////////////////////////////////////////////////////////////////

$last_query = htmlentities(trim($query));

echo <<<end
<script type="text/javascript">

var lazyLinks;
function main_json_links() {
	if (lazyLinks) clearTimeout(lazyLinks);
	jQuery('s.addr').each(function(n) {
		var el = $(this);
		var addr = el[0].innerText;
		var link = '<a href="/?address='+addr+'" target="_blank">' + addr + '</a>';
		el.html(link);
	});
	jQuery('s.hash').each(function(n) {
		var el = $(this);
		var hash = el[0].innerText;
		var link = '<a href="/explorer/search?SYM={$coin->symbol}&q='+hash+'" target="_blank">' + hash + '</a>';
		el.html(link);
	});
}

</script>

<section class="dmd-card">
<div class="dmd-card-head"><h2>Command</h2><span class="dmd-small">For example: getblockchaininfo, getpeerinfo, help</span></div>
<form class="dmd-inline-form" action="/site/console?id={$coin->id}" method="post">
<label class="visually-hidden" for="dmd-query">Command</label>
<input id="dmd-query" class="dmd-input" value="{$last_query}" type="text" name="query" placeholder="Query" autocomplete="off" spellcheck="false">
<button class="dmd-button" type="submit">Execute</button>
</form>
</section>
end;

$result = '';
if (!empty($query)) {
	$result = $remote->execute($query);
	if ($result === false) {
		$result = $remote->error;
	}
	debuglog("{$coin->symbol} CONSOLE {$query}");
}

echo '<section class="dmd-card">';
echo '<div class="dmd-card-head"><h2>Answer</h2></div>';

if (!empty($remote->error) && $remote->error != $result) {
	$err = $remote->error;
	echo '<p class="dmd-alert dmd-rpcerror">';
	echo is_string($err) ? htmlentities($err) : htmlentities(json_encode($err, 128));
	echo '</p>';
}

echo '<div class="dmd-codebox terminal">';
echo is_string($result) ? htmlentities($result) : colorizeJson(htmlentities(json_encode($result, 128)));
echo '</div>';
echo '</section>';
echo '</div>';

JavascriptReady("$('#dmd-query').focus();");
JavascriptReady("lazyLinks = setTimeout(main_json_links, 2000);");
