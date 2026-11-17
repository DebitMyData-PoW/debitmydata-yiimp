<?php
/*
 * The pool as an app (a web app people add to their phone or computer), loaded by main.php:
 *   dmd_app_head()         the manifest, the home-screen icon and the app's name, in <head>
 *   dmd_app_footer_link()  "Get the app" in the footer
 *   dmd_app_dialog()       the "Get the app" window and its script, at the end of <body>
 * The files it points to sit at the web root: /manifest.json, /sw.js (the service worker: it
 * shows a page when there is no network, and the pool's notifications), and /images/app/.
 *
 * The window works like id.debitmydata.io's: it offers itself on phones and tablets a moment after
 * a page opens, and on any device from the footer's "Get the app" (or a link ending in
 * #get-the-app). It is never offered by itself inside the app, nor when the browser says the
 * app is installed. Only "Not now" is remembered, in this browser, for 30 days.
 * When the browser offers its own one-tap install (Chrome, Edge, Samsung Internet: once the site
 * has had a tap and about 30 seconds of use on that device), the window shows the Install button,
 * even if it was already open with the steps.
 *
 * In the app, the first page opened shows a short launch screen: the icon and the app's name on
 * the dark of the dark theme (like the phone's own splash screen before it), until the page has
 * loaded (between 0.7 and 3 seconds).
 */

define('DMD_APP_NAME', 'DMD Pool');
define('DMD_APP_ICONS', 3);	// raised when the icons change, so browsers fetch the new ones

function dmd_app_head()
{
	$v = DMD_APP_ICONS;
	return "<link rel=\"manifest\" href=\"/manifest.json\">\n"
		. "<meta name=\"mobile-web-app-capable\" content=\"yes\">\n"
		. "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n"
		. "<meta name=\"apple-mobile-web-app-title\" content=\"" . DMD_APP_NAME . "\">\n"
		. "<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"default\">\n"
		. "<link rel=\"apple-touch-icon\" href=\"/images/app/apple-touch-icon.png?v=$v\">\n"
		. "<link rel=\"icon\" type=\"image/png\" sizes=\"192x192\" href=\"/images/app/icon-192.png?v=$v\">\n"
		. "<link rel=\"icon\" type=\"image/png\" sizes=\"512x512\" href=\"/images/app/icon-512.png?v=$v\">\n"
		. dmd_app_launch_screen();
}

// The app's launch screen: the icon and the name on the page's colour, from the very first frame
// (drawn by the <html> element itself, so nothing in the page has to load first), once per start.
function dmd_app_launch_screen()
{
	$v = DMD_APP_ICONS;
	$name = DMD_APP_NAME;
	return <<<HTML
<style>
html.dmd-launch::before{content:"";position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483646;
background:#18181d url("/images/app/icon-512.png?v=$v") no-repeat center calc(50% - 30px)/136px 136px}
html.dmd-launch::after{content:"$name";position:fixed;left:0;right:0;top:calc(50% + 52px);z-index:2147483647;
text-align:center;font:800 28px/1.2 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;letter-spacing:.01em;color:#ececf1}
html.dmd-launch.dmd-launch-out::before,html.dmd-launch.dmd-launch-out::after{opacity:0;transition:opacity .35s ease}
</style>
<script>
(function () {
	try {
		var app = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
		if (!app || sessionStorage.getItem("dmd-app-started")) return;
		sessionStorage.setItem("dmd-app-started", "1");
	} catch (e) { return; }
	var root = document.documentElement, start = Date.now(), done = false;
	root.classList.add("dmd-launch");
	function hide() {
		if (done) return;
		done = true;
		setTimeout(function () {
			root.classList.add("dmd-launch-out");
			setTimeout(function () { root.classList.remove("dmd-launch", "dmd-launch-out"); }, 400);
		}, Math.max(0, 700 - (Date.now() - start)));
	}
	if (document.readyState === "complete") hide(); else window.addEventListener("load", hide);
	setTimeout(hide, 3000);
})();
</script>

HTML;
}

// The bell in the top bar, at its right: grey when there is nothing new, red with a green dot when
// this device has notices not seen yet. A tap opens the list under it; tapping a notice marks it
// seen and takes it off the list. Anyone can turn notifications on from it, without an address:
// that device gets the pool's blocks (the wallet page's card follows an address). Shown only when
// the pool has its notification keys (configuration/push.php). The list comes from
// MywalletController::actionPush ("inbox").
function dmd_app_bell($wallet_url)
{
	if (!function_exists('push_config')) @include_once('/home/crypto-data/yiimp/site/web/yaamp/core/backend/push.php');
	if (!function_exists('push_config') || !push_config()) return '';
	// "Your wallet's rewards and payouts": the wallet's card, or the home page's address field
	// when this browser has not opened a wallet yet
	$href = CHtml::encode(preg_match('/address=$/', $wallet_url) ? '/#dmd-address' : $wallet_url . '#dmd-notify');
	return '<div class="dmd-bellbox" id="dmd-bellbox" data-key="' . CHtml::encode(PUSH_VAPID_PUBLIC) . '">'
		. '<button type="button" class="dmd-bell" id="dmd-bell" data-unread="0" aria-haspopup="true" aria-expanded="false" '
		. 'aria-controls="dmd-bell-panel" title="Notifications" aria-label="Notifications">'
		. '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
		. 'stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>'
		. '<path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg><span class="dmd-bell-dot" aria-hidden="true"></span></button>'
		. '<div class="dmd-bell-panel" id="dmd-bell-panel" role="region" aria-label="Notifications" hidden>'
		. '<div class="dmd-bell-head"><b>Notifications</b><button type="button" class="dmd-bell-all" hidden>Mark all as seen</button></div>'
		. '<ul class="dmd-bell-list"></ul><p class="dmd-bell-empty">Loading…</p>'
		. '<div class="dmd-bell-actions" hidden><button type="button" class="dmd-button dmd-bell-on">Turn on notifications</button></div>'
		. '<div class="dmd-bell-foot"><a class="dmd-bell-settings" href="' . $href . '">Notification settings</a>'
		. '<span class="dmd-bell-tools"><button type="button" class="dmd-bell-test" hidden>Send a test notice</button>'
		. '<button type="button" class="dmd-bell-off" hidden>Turn off</button></span></div></div></div>';
}

function dmd_app_footer_link()
{
	return '<a class="dmd-get-app" href="#get-the-app">' . dmd_icon('phone') . 'Get the app</a>';
}

function dmd_app_dialog()
{
	$v = DMD_APP_ICONS;
	$name = DMD_APP_NAME;
	$share = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">'
		. '<path d="M12 3v12M8 7l4-4 4 4M7 10H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-1" fill="none" '
		. 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	$add = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">'
		. '<rect x="4" y="4" width="16" height="16" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>'
		. '<path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

	return <<<HTML
<dialog id="dmd-install" class="dmd-install" aria-labelledby="dmd-install-title">
<div class="in">
<button type="button" id="dmd-install-close" class="dmd-install-close" aria-label="Close">&times;</button>
<img src="/images/app/icon-512.png?v=$v" alt="" width="112" height="112" loading="lazy" decoding="async">
<h2 id="dmd-install-title" tabindex="-1" autofocus>Get the $name app</h2>
<p id="dmd-install-how"></p>
<ol id="dmd-install-iphone" class="steps" hidden>
<li><span>Tap <b>Share</b></span>$share</li>
<li><span>Choose <b>Add to Home Screen</b></span>$add</li></ol>
<ol id="dmd-install-android" class="steps" hidden>
<li><span>Open the browser's menu <b>&#8942;</b></span></li>
<li><span>Tap <b>Install app</b> or <b>Add to Home screen</b></span></li></ol>
<p id="dmd-install-note" class="note" hidden>Already added it? Open it from your home screen.</p>
<div class="dmd-install-actions">
<button type="button" id="dmd-install-go" class="dmd-button dmd-install-go" hidden>Install the app</button>
<button type="button" id="dmd-install-later" class="dmd-install-later">Not now</button>
</div></div></dialog>
<script>
(function () {
	"use strict";
	var DECLINED = "dmd-pool-app-not-now", DECLINE_DAYS = 30;
	function $(s) { return document.querySelector(s); }

	// The service worker: a page when there is no network, and the pool's notifications.
	if ("serviceWorker" in navigator) {
		window.addEventListener("load", function () { navigator.serviceWorker.register("/sw.js").catch(function () {}); });
	}

	var box = $("#dmd-install");
	if (!box) return;
	var inApp = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
	if (inApp) document.documentElement.classList.add("dmd-in-app");
	var touch = window.matchMedia("(pointer: coarse)").matches;
	var iphone = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
	var device = iphone ? "iphone" : touch ? "android" : "computer";
	var canTell = "getInstalledRelatedApps" in navigator;
	var title = $("#dmd-install-title"), offerTitle = title.textContent;
	var how = $("#dmd-install-how"), go = $("#dmd-install-go"), later = $("#dmd-install-later");
	var prompt = null;	// the browser's own install dialog, when it offers one
	var installed = false, ready = false, closed = false;

	function declinedLately() {
		try {
			var at = Number(localStorage.getItem(DECLINED));
			return at > 0 && Date.now() - at < DECLINE_DAYS * 24 * 60 * 60 * 1000;
		} catch (e) { return false; }
	}
	function isInstalled() { return installed || inApp; }
	function fill() {
		var done = isInstalled();
		title.textContent = done ? (inApp ? "You're using the app" : "The app is already installed") : offerTitle;
		if (inApp) how.textContent = "This is the $name app.";
		else if (done) how.textContent = "Open $name from your " + (device === "computer" ? "computer's apps." : "home screen.");
		else if (prompt) how.textContent = "Install it: it opens full screen, like any app. Free, no app store.";
		else if (device === "computer") how.textContent = "In Chrome or Edge, click the install icon at the right of the address bar. In Safari on a Mac, choose File, then Add to Dock.";
		else how.textContent = "Add it to your home screen in two taps:";
		var byHand = !done && !prompt;
		$("#dmd-install-iphone").hidden = !(byHand && device === "iphone");
		$("#dmd-install-android").hidden = !(byHand && device === "android");
		$("#dmd-install-note").hidden = done || canTell;
		go.hidden = done || !prompt;
		later.textContent = done ? "OK" : "Not now";
	}
	function open() {
		fill();
		if (box.open) return;
		if (typeof box.showModal === "function") box.showModal(); else box.setAttribute("open", "");
	}
	function close() {
		closed = true;
		if (!box.open) return;
		if (typeof box.close === "function") box.close(); else box.removeAttribute("open");
	}
	// By itself: phones and tablets, a moment after the page opens, while nothing is being typed.
	function offer() {
		if (!ready || closed || !touch || isInstalled() || declinedLately()) return;
		var a = document.activeElement;
		if (a && (a.tagName === "INPUT" || a.tagName === "TEXTAREA")) return;
		open();
	}
	var check = (canTell ? navigator.getInstalledRelatedApps().then(function (apps) {
		return apps.some(function (app) { return app.platform === "webapp"; });
	}, function () { return false; }) : Promise.resolve(false)).then(function (yes) { installed = yes; });
	// Asked for: always opens, and forgets an earlier "Not now".
	function ask() {
		closed = false;
		try { localStorage.removeItem(DECLINED); } catch (e) {}
		check.then(open);
	}

	window.addEventListener("beforeinstallprompt", function (event) {
		event.preventDefault();
		prompt = event;
		if (box.open) fill(); else offer();
	});
	go.addEventListener("click", function () {
		if (!prompt) return;
		var asked = prompt;
		prompt = null;
		asked.prompt();
		asked.userChoice.then(function (choice) {
			if (choice.outcome === "accepted") installed = true;
			close();	// "Cancel" in the browser's dialog only closes ours
		});
	});
	window.addEventListener("appinstalled", function () { installed = true; close(); });
	// "Not now": the only answer remembered.
	later.addEventListener("click", function () {
		if (!isInstalled()) { try { localStorage.setItem(DECLINED, String(Date.now())); } catch (e) {} }
		close();
	});
	$("#dmd-install-close").addEventListener("click", close);
	box.addEventListener("cancel", function (event) { event.preventDefault(); close(); });	// Escape, or the phone's Back
	box.addEventListener("click", function (event) { if (event.target === box) close(); });	// a tap outside the window
	// The footer's "Get the app" (and any link to #get-the-app) opens it here.
	document.addEventListener("click", function (event) {
		var link = event.target.closest && event.target.closest('a[href$="#get-the-app"]');
		if (!link) return;
		event.preventDefault();
		ask();
	});
	if (location.hash === "#get-the-app") {
		history.replaceState(null, "", location.pathname + location.search);
		ask();
	}
	check.then(function () { setTimeout(function () { ready = true; offer(); }, 2500); });
})();

// The bell in the top bar and its list. Anyone can turn notifications on here, or with a button
// marked data-dmd-notify-pool (the home page's "Notify me"): without an address, the device gets a
// notice for every block the pool finds. The wallet page's card follows an address.
(function () {
	"use strict";
	var box = document.getElementById("dmd-bellbox");
	if (!box) return;
	var bell = document.getElementById("dmd-bell"), panel = document.getElementById("dmd-bell-panel");
	var list = panel.querySelector(".dmd-bell-list"), empty = panel.querySelector(".dmd-bell-empty");
	var all = panel.querySelector(".dmd-bell-all"), settings = panel.querySelector(".dmd-bell-settings");
	var actions = panel.querySelector(".dmd-bell-actions"), turnOn = panel.querySelector(".dmd-bell-on");
	var turnOff = panel.querySelector(".dmd-bell-off"), test = panel.querySelector(".dmd-bell-test");
	var KEY = box.getAttribute("data-key") || "", walletHref = settings.getAttribute("href");
	var SEEN = "dmd-pool-seen", items = [], on = false, loaded = false, address = null, wants = 0;
	var sub = null, note = "", busy = false, testing = false;
	var can = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
	var iphone = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
	var inApp = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
	var poolButtons = Array.prototype.slice.call(document.querySelectorAll("[data-dmd-notify-pool]"));

	function seen() { try { return JSON.parse(localStorage.getItem(SEEN) || "[]"); } catch (e) { return []; } }
	function keep(ids) { try { localStorage.setItem(SEEN, JSON.stringify(ids.slice(-500))); } catch (e) {} }
	function fresh() { var s = seen(); return items.filter(function (i) { return s.indexOf(i.id) < 0; }); }
	function blocked() { return can && Notification.permission === "denied"; }
	function poolOn() { return on && (wants & 4) > 0; }
	function ago(t) {
		var s = Math.max(0, Math.round(Date.now() / 1000 - t));
		if (s < 60) return "just now";
		if (s < 3600) return Math.floor(s / 60) + " min ago";
		if (s < 86400) return Math.floor(s / 3600) + " h ago";
		return new Date(t * 1000).toLocaleDateString(undefined, { month: "short", day: "numeric" });
	}
	function draw() {
		var news = fresh();
		bell.setAttribute("data-unread", news.length ? "1" : "0");
		bell.title = news.length ? news.length + (news.length > 1 ? " new notifications" : " new notification") : "Notifications";
		bell.setAttribute("aria-label", bell.title);
		list.textContent = "";
		news.forEach(function (item) {
			var li = document.createElement("li"), b = document.createElement("button");
			var head = document.createElement("span"), title = document.createElement("b"), when = document.createElement("time");
			var body = document.createElement("span");
			b.type = "button";
			b.title = "Mark as seen";
			title.textContent = item.title;
			when.textContent = ago(item.time);
			when.dateTime = new Date(item.time * 1000).toISOString();
			body.textContent = item.body;
			head.className = "dmd-bell-item-head";
			body.className = "dmd-bell-item-body";
			head.appendChild(title);
			head.appendChild(when);
			b.appendChild(head);
			b.appendChild(body);
			b.addEventListener("click", function (event) { event.stopPropagation(); keep(seen().concat([item.id])); draw(); });
			li.appendChild(b);
			list.appendChild(li);
		});
		empty.hidden = news.length > 0 && !note;
		if (note) empty.textContent = note;
		else if (!loaded) empty.textContent = "Loading…";
		else if (on) empty.textContent = "You're all caught up.";
		else if (!can && iphone && !inApp) empty.innerHTML = 'On an iPhone, notifications come through the app: <a href="#get-the-app">add it to your Home Screen</a>, open it, and turn them on there.';
		else if (!can) empty.textContent = "This browser cannot show notifications.";
		else if (blocked()) empty.textContent = "Notifications are blocked for this site in your browser settings.";
		else empty.textContent = "Get a notice on this device each time the pool finds a block, even when the site is closed.";
		all.hidden = news.length < 2;
		actions.hidden = !loaded || on || !can || blocked();
		turnOn.disabled = busy;
		// a device that follows a wallet turns them off, and sends a test notice, on the wallet's card
		turnOff.hidden = test.hidden = !on || !!address;
		turnOff.disabled = busy;
		test.disabled = busy || testing;
		if (!loaded || (on && address)) {
			settings.textContent = "Notification settings";
			if (address) settings.href = "/mywallet/?address=" + encodeURIComponent(address) + "#dmd-notify";
		}
		else {
			settings.textContent = "Your wallet's rewards and payouts";
			settings.href = walletHref;
		}
		poolButtons.forEach(function (b) {
			var label = b.querySelector("span") || b;
			label.textContent = poolOn() ? "Notifications on" : "Notify me";
			b.setAttribute("aria-pressed", poolOn() ? "true" : "false");
			b.title = poolOn() ? "This device gets a notice for every block the pool finds" : "Get a notice for every block the pool finds";
			b.disabled = busy;
		});
	}
	function post(data) {
		return fetch("/mywallet/push/json", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || "The pool did not answer."); return j; }); });
	}
	function load() {
		if (!can) { loaded = true; on = false; draw(); return; }
		navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (s) {
			sub = s;
			if (!s) return { on: false, items: [] };
			return post({ action: "inbox", endpoint: s.endpoint }).catch(function () { return { on: false, items: [] }; });
		}).then(function (r) {
			loaded = true;
			on = !!r.on;
			items = r.items || [];
			address = r.address || null;
			wants = r.wants || 0;
			draw();
		}).catch(function () { loaded = true; draw(); });
	}
	function changed() { document.dispatchEvent(new CustomEvent("dmd-notify-change", { detail: { from: "bell" } })); }
	function open(yes) {
		panel.hidden = !yes;
		bell.setAttribute("aria-expanded", yes ? "true" : "false");
		if (yes) draw();
	}

	// Turning them on here: the browser asks for permission, then the pool keeps this device. A
	// device that already follows a wallet gets the pool's blocks too.
	function key() {
		var s = KEY.replace(/-/g, "+").replace(/_/g, "/"); s += "===".slice((s.length + 3) % 4);
		var raw = atob(s), out = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
		return out;
	}
	function sameKey(s) {	// a subscription made with another key of the pool is made again
		var k = s.options && s.options.applicationServerKey;
		if (!k) return true;
		var a = new Uint8Array(k), b = key();
		if (a.length !== b.length) return false;
		for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
		return true;
	}
	function turnOnPool() {
		if (busy) return;
		busy = true;
		note = "Turning on…";
		draw();
		Notification.requestPermission().then(function (p) {
			if (p !== "granted") throw new Error(p === "denied" ? "Notifications are blocked for this site in your browser settings." : "Notifications were not allowed.");
			return navigator.serviceWorker.ready;
		}).then(function (reg) {
			return reg.pushManager.getSubscription().then(function (s) {
				if (s && !sameKey(s)) return s.unsubscribe().then(function () { return null; });
				return s;
			}).then(function (s) { return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key() }); });
		}).then(function (s) {
			sub = s;
			return post({ action: "subscribe", subscription: s.toJSON() });
		}).then(function (r) {
			note = "";
			on = true; address = r.address || null; wants = r.wants || 4;
			changed();
		}).catch(function (err) {
			note = (err && err.message) || "Something went wrong. Try again in a moment.";
			open(true);
		}).then(function () { busy = false; draw(); });
	}
	function turnOffPool() {
		if (busy || !sub) return;
		busy = true;
		draw();
		var s = sub;
		post({ action: "unsubscribe", endpoint: s.endpoint }).then(function () { return s.unsubscribe(); }).then(function () {
			sub = null; note = ""; on = false; items = []; wants = 0;
			changed();
		}).catch(function (err) { note = (err && err.message) || "Something went wrong. Try again in a moment."; })
			.then(function () { busy = false; draw(); });
	}

	// "Send a test notice": the service sends it within seconds; one every 10 seconds at most
	function sendTest() {
		if (!sub || testing) return;
		testing = true;
		draw();
		post({ action: "test", endpoint: sub.endpoint }).then(function () {
			note = "A test notice is on its way: it should arrive within a few seconds.";
		}).catch(function (err) {
			note = (err && err.message) || "Something went wrong. Try again in a moment.";
		}).then(function () {
			draw();
			setTimeout(function () { testing = false; note = ""; draw(); }, 10000);
		});
	}

	bell.addEventListener("click", function (event) { event.stopPropagation(); open(panel.hidden); });
	all.addEventListener("click", function (event) { event.stopPropagation(); keep(seen().concat(items.map(function (i) { return i.id; }))); draw(); });
	turnOn.addEventListener("click", function (event) { event.stopPropagation(); turnOnPool(); });
	turnOff.addEventListener("click", function (event) { event.stopPropagation(); turnOffPool(); });
	test.addEventListener("click", function (event) { event.stopPropagation(); sendTest(); });
	// "Notify me" on the page: turns them on at once; when they are on (or cannot be), the list
	// opens and says so
	poolButtons.forEach(function (b) {
		b.addEventListener("click", function (event) {
			event.preventDefault();
			event.stopPropagation();
			if (busy) return;
			if (poolOn() || !can || blocked()) open(true); else turnOnPool();
		});
	});
	document.addEventListener("click", function (event) { if (!panel.hidden && !box.contains(event.target)) open(false); });
	document.addEventListener("keydown", function (event) { if (event.key === "Escape" && !panel.hidden) { open(false); bell.focus(); } });

	// The footer link: the wallet page's card (on that page, it scrolls down to it), or the home
	// page's address field
	function show(card) {
		card.scrollIntoView({ behavior: "smooth", block: "start" });
		card.classList.remove("dmd-flash");
		void card.offsetWidth;
		card.classList.add("dmd-flash");
	}
	function field(input) {
		input.scrollIntoView({ behavior: "smooth", block: "center" });
		try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
	}
	settings.addEventListener("click", function (event) {
		var card = document.getElementById("dmd-notify"), input = document.getElementById("dmd-address");
		var toField = /#dmd-address$/.test(settings.getAttribute("href"));
		if (toField ? !input : !card) return;
		event.preventDefault();
		open(false);
		if (toField) field(input); else show(card);
	});
	if (location.hash === "#dmd-notify" || location.hash === "#dmd-address") {
		window.addEventListener("load", function () {
			var card = document.getElementById("dmd-notify"), input = document.getElementById("dmd-address");
			if (location.hash === "#dmd-notify" && card) setTimeout(function () { show(card); }, 600);
			if (location.hash === "#dmd-address" && input) setTimeout(function () { field(input); }, 300);
		});
	}

	// new notices: when one arrives (the service worker tells the page), when this device's
	// notifications change (here or on the wallet page), and every minute
	document.addEventListener("dmd-notify-change", load);
	if ("serviceWorker" in navigator) navigator.serviceWorker.addEventListener("message", function (event) {
		if (event.data && event.data.type === "dmd-pool-notice") load();
	});
	setInterval(load, 60000);
	load();
})();
</script>

HTML;
}
