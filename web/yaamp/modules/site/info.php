<?php

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");




echo <<<end
<script>
window.addEventListener("load", () => {
  const loader = document.querySelector(".loader");

  loader.classList.add("loader--hidden");s

  loader.addEventListener("transitionend", () => {
    document.body.removeChild(loader);
  //  loader.remove();
  });
});
</script>

<div class="loader"></div>
end;






echo <<<end
<script type="text/javascript">
function wallet_peers(id)
{
	var w = window.open("/explorer/peers?id=" + id, "peers",
		"width=400,height=600,location=no,menubar=no,resizable=yes,status=no,toolbar=no");
}
</script>


<!--
<div align="center"><audio id="music" src="/music/iknowyouwantit.mp3" width="120" height="50" autoplay loop controls hidden="true">
<p>If you are reading this, it is because your browser does not support the audio element.</p>
</audio>

<button onclick="playAudio()" type="button">Play</button>
<button onclick="pauseAudio()" type="button">Pause</button>

<button onclick="enableMute()" type="button">Mute</button>
<button onclick="disableMute()" type="button">Unmute</button>

<script>
var audio = document.getElementById("music");

function playAudio() { 
  audio.play();
} 

function pauseAudio() { 
  audio.pause();
} 

function enableMute() { 
  audio.muted = true;
} 

function disableMute() { 
  audio.muted = false;
} 

</script></div>
-->


<style type="text/css">
a.low { color: red; font-weight: normal; }
</style>

<br/>


<div class="main-left-box">
<div class="main-left-title">&nbsp;</div>
<div class="main-left-inner">
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid5',
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); },
		8: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<end

<thead>
<tr>
<!-- <th width="30" data-sorter=""></th>  -->
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Name</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Symbol</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Algo</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Version</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Height</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Network Difficulty</th>
<!-- <th>Connections</th> -->
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Pool Hash</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Network Hash</th>

<th data-sorter=""></th>
</tr>
</thead></body>
end;

if ($this->admin) 
{}



$list = getdbolist('db_coins', "enable and visible order by name");
foreach($list as $coin)
{
	if($coin->symbol == 'BTC') continue;
	if(!empty($coin->symbol2)) continue;

	$coin->version = formatWalletVersion($coin);

	//if (!$coin->network_hash)
		$coin->network_hash = controller()->memcache->get("yiimp-nethashrate-{$coin->symbol}");
	if (!$coin->network_hash) {
		$remote = new WalletRPC($coin);
		if ($remote)
			$info = $remote->getmininginfo();
		if (isset($info['networkhashps'])) {
			$coin->network_hash = $info['networkhashps'];
			controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $info['networkhashps'], 60);
		}
		else if (isset($info['netmhashps'])) {
			$coin->network_hash = floatval($info['netmhashps']) * 1e6;
			controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $coin->network_hash, 60);
		}
	}

	$difficulty = Itoa2($coin->difficulty, 8);
	$nethash_sfx = $coin->network_hash? strtoupper(Itoa2($coin->network_hash)).'H/s': '';

	echo '<tr class="ssrow">';
//	echo '<td><img src="'.$coin->image.'" width="35"></td>';

	echo '<td><b>'.$coin->createExplorerLink($coin->name).'</a></b></td>';
	echo '<td align="center" style="font-weight: normal;  color: #A9A9A9; font-family: interblack; font-size: 15px; font-variant: small-caps; text-transform: uppercase;"
>'.$coin->symbol.'</td>';

	echo '<td align="center" style="font-weight: normal;  color: #A9A9A9; font-family: interblack; font-size: 14px; font-variant: small-caps; text-transform: uppercase;"
>'.$coin->algo.'</td>';
	echo '<td align="center" style="font-weight: normal;  color: #A9A9A9; font-family: interblack; font-size: 14px; font-variant: small-caps; text-transform: uppercase;"
>'.$coin->version.'</td>';

	echo '<td align="center" style="font-weight: normal;  color: #A9A9A9; font-family: interblack; font-size: 14px; font-variant: small-caps; text-transform: uppercase;"
>'.$coin->block_height.'</td>';
	$diffnote = '';
	if ($coin->algo == 'equihash' || $coin->algo == 'quark') $diffnote = '*';
	echo '<td align="center" style="font-weight: normal;  color: #A9A9A9; font-family: interblack; font-size: 14px; font-variant: small-caps; text-transform: uppercase;"
 data="'.$coin->difficulty.'">'.$difficulty.$diffnote.'</td>';
	$cnx_class = (intval($coin->connections) > 3) ? '' : 'low';
	$peers_link = CHtml::link($coin->connections, "javascript:wallet_peers({$coin->id});", array('class'=>$cnx_class));
	// echo '<td>'.$peers_link.'</td>';
	$pool_hash = yaamp_coin_rate($coin->id);
	$pool_hash_sfx = $pool_hash ? Itoa2($pool_hash) . 'h/s' : '';
	echo "<td align='center' style='font-size: 15px; font-weight: ; color: #EEEEEE; font-family: interblack; font-variant: small-caps; text-transform: uppercase;'>$pool_hash_sfx</td>";
	echo '<td  align="center" style="font-weight: normal;  color: #EEEEEE; font-family: interblack; font-size: 17px; font-variant: small-caps; text-transform: uppercase;"
 data="'.$coin->network_hash.'">'.$nethash_sfx.'</td>';



if ($this->admin) 
{
	echo "<td>";
	if(!empty($coin->link_bitcointalk))
		echo CHtml::link('forum', $coin->link_bitcointalk, array('target'=>'_blank'));
echo "&nbsp;";
	if(!empty($coin->link_github))
		echo CHtml::link('github', $coin->link_github, array('target'=>'_blank'));
echo "&nbsp;";
	if(!empty($coin->link_site))
		echo CHtml::link('site', $coin->link_site, array('target'=>'_blank'));
echo "&nbsp;";
	if(!empty($coin->link_exchange))
		echo CHtml::link('chat', $coin->link_exchange, array('target'=>'_blank'));
echo "&nbsp;";
	if(!empty($coin->link_explorer))
		echo CHtml::link('explorer', $coin->link_explorer, array('target'=>'_blank'));

	//else if(!empty($coin->link_site))
	//	echo CHtml::link('site', $coin->link_site, array('target'=>'_blank'));
}
	echo "</td>";
	echo "</tr>";
}

echo <<<end
</tbody>
</table>

</td></tr></table>
</div></div>



<br>



<div class="main-left-box">
<div class="main-left-title">&nbsp;</div>
<div class="main-left-inner">
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid5',
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); },
		8: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<end
<thead>
<tr>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">P2P Port</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">RPC Port</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Block Reward</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Block Time</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Difficulty Time</th>
<th align="center" style="font-weight: normal;  color: #919191; font-family: interblack; font-size: 13px; font-variant: small-caps; text-transform: uppercase;">Block Maturity</th>
<th data-sorter=""></th>
</tr>
</thead><tbody>
end;

$list = getdbolist('db_coins', "enable and visible order by name");
foreach($list as $coin)
{
	if($coin->symbol == 'BTC') continue;
	if(!empty($coin->symbol2)) continue;

	$coin->version = formatWalletVersion($coin);

	//if (!$coin->network_hash)
		$coin->network_hash = controller()->memcache->get("yiimp-nethashrate-{$coin->symbol}");
	if (!$coin->network_hash) {
		$remote = new WalletRPC($coin);
		if ($remote)
			$info = $remote->getmininginfo();
		if (isset($info['networkhashps'])) {
			$coin->network_hash = $info['networkhashps'];
			controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $info['networkhashps'], 60);
		}
		else if (isset($info['netmhashps'])) {
			$coin->network_hash = floatval($info['netmhashps']) * 1e6;
			controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $coin->network_hash, 60);
		}
	}

	$difficulty = Itoa2($coin->difficulty, 3);
	$nethash_sfx = $coin->network_hash? strtoupper(Itoa2($coin->network_hash)).'H/s': '';

	echo '<tr class="ssrow">';
	// echo '<td><img src="'.$coin->image.'" width="80"></td>';

	echo '<td align=center style="font-size: 14px; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;">6886</td>';
	echo '<td align=center style="font-size: 14px; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;">6885</td>';

	echo '<td align=center style="font-size: 14px; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;">100 BYC</td>';
	echo '<td align=center style="font-size: 14px; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;">1 minute</td>';

	echo '<td align=center style="font-size: 14px; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;">1 minute</td>';
	$diffnote = '';
	if ($coin->algo == 'equihash' || $coin->algo == 'quark') $diffnote = '*';
	echo '<td align=center style="font-size: 14px; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;">100 blocks</td>';
	$cnx_class = (intval($coin->connections) > 3) ? '' : 'low';
	$peers_link = CHtml::link($coin->connections, "javascript:wallet_peers({$coin->id});", array('class'=>$cnx_class));

	// echo "<td align=center style='font-size: 0.75em; font-weight: ; font-family: interblack; color: #A9A9A9; font-variant: small-caps; text-transform: uppercase;'>";


	// elseif(!empty($coin->link_site))
	//	echo CHtml::link('site', $coin->link_site, array('target'=>'_blank'));

	echo "</td>";
	echo "</tr>";
}

echo <<<end
</tbody>
</table>
</div></div>





<br>


<div class="main-left-box">
<div class="main-left-title">&nbsp;</div>
<div style="text-align:center;" class="main-left-inner">
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid5',
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); },
		8: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<end
<thead>
<tr>
<th align=center style="font-size: 16px; font-weight:normal; font-family: interblack; color: #919191; font-variant: small-caps; text-transform: uppercase;">Win</th>
<th align=center style="font-size: 16px; font-weight:normal; font-family: interblack; color: #919191; font-variant: small-caps; text-transform: uppercase;">Mac</th>
<th align=center style="font-size: 16px; font-weight:normal; font-family: interblack; color: #919191; font-variant: small-caps; text-transform: uppercase;">Linux</th>
</tr>
</thead><tbody>
end;
	echo '<td><a href="https://github.com/FennecBlockchain/Fennec/releases/latest/download/Fennec-Win.zip" target="_blank"><img src="/images/windows.png" width="60"></a></td>';
	echo '<td><a href="https://github.com/FennecBlockchain/Fennec/releases/latest/download/Fennec-Mac.zip" target="_blank"><img src="/images/mac.png" width="60"></a></td>';
	echo '<td><a href="https://github.com/FennecBlockchain/Fennec/releases/latest/download/Fennec-Linux.tar.gz" target="_blank"><img src="/images/linux.png" width="60"></a></td>';


echo <<<end
</tbody>
</table>
</div></div>













<br><br>
<br><br><br><br><br><br>
<center><p><img src='/images/debitmydata8.png' width='256'></img></p></center>

<br><br><br><br><br><br><br>

<center>





end;



