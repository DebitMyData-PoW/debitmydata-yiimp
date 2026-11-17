<?php

// Functions commonly used in admin pages

/*
 * The admin pages in the pool's DebitMyData look (css/dmd-pool.css, "Admin pages"): a title, then
 * the admin's links as tabs. The pool's pages are always there; a coin's pages (wallet, settings,
 * blocks, peers, console) come under them while a coin is open.
 */

// Small inline icons (24 x 24, drawn with the text colour), as on the rest of the pool.
function dmd_admin_icon($name)
{
	$paths = array(
		'coins' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
		'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><path d="M16 14h.01"/>',
		'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'cube' => '<path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/>',
		'peers' => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M12 7.5v4M12 11.5l-5.5 5.5M12 11.5l5.5 5.5"/>',
		'console' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M13 15h4"/>',
		'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
		'out' => '<path d="M7 17L17 7M9 7h8v8"/>',
		'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
		'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>',
		'cpu' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v4M15 1v4M9 19v4M15 19v4M1 9h4M1 15h4M19 9h4M19 15h4"/>',
		'earn' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
		'pickaxe' => '<path d="M14.5 5.5l4 4M3 21l11-11"/><path d="M5 7.5c4-4.5 10-5.5 15-2.5-3-2-8.5-1.5-11.5 1.5M16.5 19c4.5-4 5.5-10 2.5-15 2 3 1.5 8.5-1.5 11.5"/>',
		'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
		'plus' => '<path d="M12 5v14M5 12h14"/>',
		'back' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
		'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
		'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
		'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
		'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v9H5v-9"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
	);
	return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
		. 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

// Which admin page is open: the tab to show as the current one.
function dmd_admin_current()
{
	$controller = controller()->id;
	$action = strtolower(controller()->action->id);
	if ($controller == 'coin') return 'coins';
	if ($controller == 'explorer') return 'blocks';
	$tabs = array(
		'coin' => 'wallet', 'update' => 'settings', 'peers' => 'peers', 'console' => 'console',
		'user' => 'users', 'users' => 'users', 'worker' => 'workers', 'workers' => 'workers',
		'earning' => 'earnings', 'payments' => 'payments', 'monsters' => 'monsters',
	);
	return isset($tabs[$action]) ? $tabs[$action] : '';
}

function dmd_admin_tab($key, $url, $label, $icon, $current)
{
	$on = ($key == $current) ? ' class="on" aria-current="page"' : '';
	return '<a href="' . CHtml::encode($url) . '"' . $on . '>' . dmd_admin_icon($icon) . $label . '</a>';
}

// The pool's admin pages (they were the "Users, Workers, Earnings..." line).
function getAdminSideBarLinks()
{
	$current = dmd_admin_current();
	$links = '<div class="dmd-admin-tabs" role="list">';
	$links .= '<span class="dmd-tabs-label">Pool</span>';
	$links .= dmd_admin_tab('coins', '/coin', 'Coins', 'coins', $current);
	$links .= dmd_admin_tab('users', '/site/user', 'Users', 'users', $current);
	$links .= dmd_admin_tab('workers', '/site/worker', 'Workers', 'cpu', $current);
	$links .= dmd_admin_tab('earnings', '/site/earning', 'Earnings', 'earn', $current);
	$links .= dmd_admin_tab('payments', '/site/payments', 'Payments', 'send', $current);
	$links .= dmd_admin_tab('monsters', '/site/monsters', 'Big miners', 'pickaxe', $current);
	$links .= '<button type="button" class="dmd-admin-logout" data-dmd-admin-logout aria-label="Log out" title="Log out: end the admin session on this browser">'
		. dmd_admin_icon('logout') . '<span>Log out</span></button>';
	$links .= '</div>';
	$links .= dmd_admin_logout_script();
	return $links;
}

// Log out: the admin session of this browser ends (/site/adminLogout), then the home page. (The
// portal's nginx password stays remembered by the browser until it is closed, as with any such
// password: making the browser forget it would make it ask for the password on logging out.)
function dmd_admin_logout_script()
{
	static $done = false;
	if ($done) return '';
	$done = true;
	return <<<'HTML'
<script>
document.addEventListener('click', function (event) {
	var button = event.target.closest ? event.target.closest('[data-dmd-admin-logout]') : null;
	if (!button) return;
	button.disabled = true;
	fetch('/site/adminLogout', { method: 'POST', credentials: 'same-origin' }).catch(function () {}).then(function () {
		location.href = '/';
	});
});
</script>
HTML;
}

// A coin's admin pages. Blocks, peers and console need the coin's wallet: they are left out when
// the page knows the wallet does not answer ($info === false).
function getAdminWalletLinks($coin, $info=NULL, $src='wallet')
{
	$current = dmd_admin_current();
	$img = !empty($coin->image) ? '<img src="' . CHtml::encode($coin->image) . '" alt="">' : '';
	$html = '<div class="dmd-admin-tabs" role="list">';
	$html .= '<span class="dmd-tabs-label">' . $img . CHtml::encode($coin->symbol) . '</span>';
	$html .= dmd_admin_tab('wallet', '/site/coin?id='.$coin->id, 'Wallet', 'wallet', $current);
	$html .= dmd_admin_tab('settings', '/site/update?id='.$coin->id, 'Settings', 'gear', $current);
	if ($info !== false) {
		$on = ($current == 'blocks') ? array('class'=>'on', 'aria-current'=>'page') : array();
		$html .= $coin->createExplorerLink(dmd_admin_icon('cube').'Blocks', array(), $on, true);
		$html .= dmd_admin_tab('peers', '/site/peers?id='.$coin->id, 'Peers', 'peers', $current);
		if (YAAMP_ADMIN_WEBCONSOLE)
			$html .= dmd_admin_tab('console', '/site/console?id='.$coin->id, 'Console', 'console', $current);
	}

	// the coin's own links, when they are set
	$external = array(
		'Forum' => $coin->link_bitcointalk,
		'Git' => $coin->link_github,
		'Site' => $coin->link_site,
		'Explorer' => $coin->link_explorer,
	);
	foreach ($external as $label => $url) {
		if (!empty($url))
			$html .= '<a href="' . CHtml::encode($url) . '" target="_blank" rel="noopener" title="' . $label . ' (opens a new tab)">'
				. $label . ' ' . dmd_admin_icon('out') . '</a>';
	}
	$html .= '</div>';
	return $html;
}

// The top of an admin page: "Admin", the page's title, the admin's links.
//   $title    the page's title
//   $coin     the coin the page is about, or null
//   $info     the coin wallet's getinfo, false if the wallet does not answer, null if unknown
//   $actions  html for the right side (buttons)
//   $sub      a line under the title
function dmd_admin_head($title, $coin=null, $info=null, $actions='', $sub='')
{
	$admin = controller()->admin;
	echo '<header class="dmd-admin-head">';
	echo '<div class="dmd-admin-title">';
	echo '<p class="dmd-eyebrow">' . ($admin ? 'Admin' : 'Block explorer') . '</p>';
	$img = ($coin && !empty($coin->image)) ? '<img src="' . CHtml::encode($coin->image) . '" alt="">' : '';
	echo '<h1>' . $img . $title . '</h1>';
	if ($sub !== '') echo '<p class="dmd-admin-sub">' . $sub . '</p>';
	echo '</div>';
	if ($actions !== '') echo '<div class="dmd-admin-actions">' . $actions . '</div>';
	echo '</header>';

	if (!$admin) return;
	echo '<nav class="dmd-admin-nav" aria-label="Admin">';
	echo getAdminSideBarLinks();
	if ($coin) echo getAdminWalletLinks($coin, $info);
	echo '</nav>';
}

// A sortable table (tablesorter), like showTableSorter() in ui/lib/libview.php, in the pool's
// table style. Rows show as one card each on phones: give each cell a data-label.
function dmd_admin_table($id, $options='', $class='')
{
	JavascriptReady("
		$('#{$id}').tablesorter({$options});
		$('.tablesorter-header').not('.sorter-false').css('cursor', 'pointer');"
	);
	echo "<table id='$id' class='dmd-table dmd-admin-table $class'>";
}

// The search box over a table (the table's filter widget reads it: filter_external '.search').
function dmd_admin_search($placeholder='Search...')
{
	return '<label class="dmd-search">' . dmd_admin_icon('search')
		. '<span class="visually-hidden">' . $placeholder . '</span>'
		. '<input class="search dmd-input" type="search" data-column="all" placeholder="' . $placeholder . '" autocomplete="off"></label>';
}

// A coin's logo and name in a table cell.
function dmd_admin_coin_cell($coin, $link='')
{
	if (!$coin) return '';
	$img = !empty($coin->image) ? '<img src="' . CHtml::encode($coin->image) . '" alt="">' : '';
	$name = CHtml::encode($coin->name) . ' <small>' . CHtml::encode($coin->symbol) . '</small>';
	if ($link !== '') $name = '<a href="' . CHtml::encode($link) . '">' . $name . '</a>';
	return '<span class="dmd-coin">' . $img . '<span>' . $name . '</span></span>';
}

/////////////////////////////////////////////////////////////////////////////////////////////

// Check if $IP is in $CIDR range
function ipCIDRCheck($IP, $CIDR)
{
	list($net, $mask) = explode('/', $CIDR);

	$ip_net = ip2long($net);
	$ip_mask = ~((1 << (32 - $mask)) - 1);

	$ip_ip = ip2long($IP);
	$ip_ip_net = $ip_ip & $ip_mask;

	return ($ip_ip_net === $ip_net);
}

function isAdminIP($ip)
{
	foreach(explode(',', YAAMP_ADMIN_IP) as $range)
	{
		if (strpos($range, '/')) {
			if(ipCIDRCheck($ip, $range) === true) return true;
		} else if ($range === $ip) {
			return true;
		}
	}
	return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////
