<?php
/*
 * The pool's page layout, in the DebitMyData look (debitmydata.io, id.debitmydata.io): a white
 * top bar that stays on screen, the page, and a dark-red footer. The older style sheets still
 * load first, for the pages that use them (admin, explorer...); css/dmd-pool.css comes last and
 * gives every page the DebitMyData theme, light by default, dark when the visitor picks it with
 * the button in the top bar. The choice is kept in the pool's own "dmd-theme-pool" cookie (the
 * explorer, the website and id.debitmydata.io each remember their own choice).
 */

require('misc.php');
require_once('dmd-app.php');	// the pool as an app: manifest, "Get the app" window

// The page's title is the app's name on every page: browsers that add a page to the home screen
// take it as the app's name, so it is the same wherever the app is added from.
$pageTitle = 'DMD Pool';

echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
(function () {
  var root = document.documentElement, t = null;
  try {
    var m = document.cookie.match(/(?:^|;\\s*)dmd-theme-pool=(light|dark)/);
    t = m ? m[1] : localStorage.getItem("dmd-theme-pool");
  } catch (e) {}
  if (t !== "light" && t !== "dark") { t = "light"; }
  root.setAttribute("data-theme", t);
  root.style.colorScheme = t;
  // Chart colours for the current theme (the charts call this when they are drawn).
  window.dmdCharts = [];
  window.dmdChartTheme = function () {
    var dark = root.getAttribute("data-theme") === "dark";
    // The Aurora design colours (css/dmd-aurora.css).
    return { fore: dark ? "#8f8a9b" : "#6e6a78", grid: dark ? "#25222d" : "#f1eff4", tooltip: dark ? "dark" : "light",
      red: dark ? "#ff3348" : "#e0112b" };
  };
})();
</script>
<style>
/* The page colour from the very first frame: without it Firefox shows a blank white page for a
   moment on every load, while the stylesheets and scripts below are fetched. */
html{background:#fff}
html[data-theme="dark"]{background:#0b0a0f}
</style>
<title>' . CHtml::encode($pageTitle) . '</title>
' . dmd_app_head() . '<meta name="description" content="The official mining pool of the DebitMyData Proof-of-Work chain: mine DMD with any SHA-256 ASIC, PPLNS payouts.">
<meta name="theme-color" content="#ffffff">
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<meta property="og:type" content="website">
<meta property="og:title" content="DebitMyData PoW Mining Pool">
<meta property="og:description" content="The official mining pool of the DebitMyData Proof-of-Work chain. Mine DMD with any SHA-256 ASIC.">
<meta property="og:image" content="https://pool.debitmydata.io/images/banner_02.png">
<meta property="og:url" content="https://pool.debitmydata.io/">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="DebitMyData PoW Mining Pool">
<meta name="twitter:image" content="https://pool.debitmydata.io/images/banner_02.png">
<link rel="preload" href="/yaamp/ui/fonts/inter-wght.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/yaamp/ui/fonts/sora-wght.woff2" as="font" type="font/woff2" crossorigin>
';

echo CHtml::cssFile("/extensions/jquery/themes/ui-lightness/jquery-ui.css");
echo CHtml::cssFile('/yaamp/ui/menu/assets/css/style.css');
echo CHtml::cssFile('/yaamp/ui/menu/assets/css/responsive.css');
echo CHtml::cssFile('/yaamp/ui/css/all.css');
echo CHtml::cssFile('/yaamp/ui/css/main.css');
echo CHtml::cssFile('/yaamp/ui/css/table.css');
echo CHtml::cssFile('/yaamp/ui/css/bootstrap.css');
echo CHtml::cssFile('/yaamp/ui/css/bootstrap-navbar-menu.css');
echo CHtml::cssFile('/yaamp/ui/css/animate.css');
echo CHtml::cssFile('/yaamp/ui/css/dmd-pool.css?v=20');
echo CHtml::cssFile('/yaamp/ui/css/dmd-aurora.css?v=1');
echo CHtml::scriptFile('https://cdn.jsdelivr.net/npm/apexcharts');
echo CHtml::scriptFile('/yaamp/ui/js/all.js');
echo CHtml::scriptFile('https://kit.fontawesome.com/9160e74506.js', array('crossorigin' => 'anonymous'));
echo CHtml::scriptFile('/yaamp/ui/npm/sweetalert2@11.js');
echo CHtml::scriptFile('/yaamp/ui/js/jquery.tablesorter.js');
echo CHtml::scriptFile('/yaamp/ui/js/powerglitch.js');
echo CHtml::scriptFile('/yaamp/ui/js/wow.min.js');

// (the app's manifest and icons are near the top of <head>, under the title: browsers only read the
// manifest inside <head>, so nothing that writes into the page may come before them)

$cs = app()->getClientScript();
$cs->registerCoreScript('jquery.ui');

echo "</head>\n<body class=\"page dmd\" id=\"top\">\n";

showPageHeader();
showPageContent($content);
showPageFooter();

echo "<a class=\"dmd-to-top\" href=\"#top\" aria-label=\"Back to top\" title=\"Back to top\">" . dmd_icon('up') . "</a>\n";
echo "<script>new WOW().init();</script>\n";
echo dmd_theme_script();
echo dmd_app_dialog();
echo "</body>\n</html>";
return;


// Small inline icons (24 x 24, drawn with the text colour).
function dmd_icon($name, $class = '')
{
    $paths = array(
        'moon' => '<path d="M20.5 14.2A8.5 8.5 0 1 1 9.8 3.5a6.8 6.8 0 0 0 10.7 10.7z"/>',
        'sun' => '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.2M12 19.8V22M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2 12h2.2M19.8 12H22M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/>',
        'up' => '<path d="M12 5l-7 7m7-7l7 7M12 5v14"/>',
        'out' => '<path d="M7 17L17 7M9 7h8v8"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
    );
    $cls = $class ? " class=\"$class\"" : '';
    return '<svg' . $cls . ' viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

// The light / dark switch: saves the choice (the pool's own cookie) and recolours the charts
// already on the page.
function dmd_theme_script()
{
    return <<<'JS'
<script>
(function () {
  var root = document.documentElement, button = document.getElementById("dmd-theme-toggle");
  function dark() { return root.getAttribute("data-theme") === "dark"; }
  function label() {
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", dark() ? "#0b0a0f" : "#ffffff");
    if (!button) return;
    button.setAttribute("aria-label", dark() ? "Switch to the light theme" : "Switch to the dark theme");
    button.title = dark() ? "Light theme" : "Dark theme";
  }
  function save(t) {
    try { localStorage.setItem("dmd-theme-pool", t); } catch (e) {}
    var secure = location.protocol === "https:" ? "; secure" : "";
    document.cookie = "dmd-theme-pool=" + t + "; path=/; max-age=31536000; samesite=lax" + secure;
  }
  function recolour() {
    var c = window.dmdChartTheme();
    window.dmdCharts = (window.dmdCharts || []).filter(function (chart) {
      if (!chart || !chart.el || !document.body.contains(chart.el)) return false;
      try {
        chart.updateOptions({ chart: { foreColor: c.fore }, grid: { borderColor: c.grid }, tooltip: { theme: c.tooltip } }, false, false);
      } catch (e) {}
      return true;
    });
  }
  label();
  if (button) {
    button.addEventListener("click", function () {
      var t = dark() ? "light" : "dark";
      root.setAttribute("data-theme", t);
      save(t);
      label();
      recolour();
    });
  }
})();
</script>

JS;
}

function dmd_link($url, $label, $class = '')
{
    $cls = $class ? " class=\"$class\"" : '';
    return "<a$cls href=\"" . CHtml::encode($url) . "\" target=\"_blank\" rel=\"noopener\">$label</a>";
}

function showPageHeader()
{
    $action = controller()->action->id;
    $controller = controller()->id;
    $wallet = user()->getState('yaamp-wallet');
    $address = isset($_GET['address']);

    $home = ($controller == 'site' && $action == 'index' && !$address);
    $walletPage = ($controller == 'mywallet' || $address);
    $walletUrl = '/mywallet/?address=' . urlencode((string) $wallet);

    echo '<header class="dmd-topbar"><div class="dmd-in">';
    echo '<a class="dmd-brand" href="/"><img src="/images/dmd-pickaxe-25.png" srcset="/images/dmd-pickaxe-25.png 1x, /images/dmd-pickaxe-50.png 2x, /images/dmd-pickaxe-75.png 3x" width="25" height="25" alt="">DebitMyData <span class="dmd-tag">Pool</span></a>';
    // On a phone the links fold into this button (a checkbox: no script needed).
    echo '<input type="checkbox" id="dmd-menu-toggle" class="dmd-menu-toggle" aria-label="Menu" aria-controls="dmd-menu">';
    echo '<label for="dmd-menu-toggle" class="dmd-menu-button" aria-hidden="true"><span></span></label>';
    echo '<button type="button" id="dmd-theme-toggle" class="dmd-theme-toggle" aria-label="Switch between the light and dark themes">'
        . dmd_icon('moon', 'dmd-moon') . dmd_icon('sun', 'dmd-sun') . '</button>';
    echo dmd_app_bell($walletUrl);	// the notifications bell and its list, at the right
    echo '<nav id="dmd-menu" class="dmd-nav" aria-label="Pool">';
    echo '<a href="/"' . ($home ? ' class="on" aria-current="page"' : '') . '>Home</a>';
    echo '<a href="' . CHtml::encode($walletUrl) . '"' . ($walletPage ? ' class="on" aria-current="page"' : '') . '>My wallet</a>';
    echo dmd_link('https://mempool.debitmydata.io/', 'Explorer ' . dmd_icon('out'));
    if (controller()->admin) {
        $admin = dmd_admin_current() !== '';	// any admin page (core/functions/admin.php)
        $dmd = dmd_coin();	// the admin opens on the DMD coin's page, or on the coins without it
        $adminUrl = $dmd ? '/site/coin?id=' . (int) $dmd->id : '/coin';
        echo '<a href="' . $adminUrl . '"' . ($admin ? ' class="on" aria-current="page"' : '') . '>Admin</a>';
    }
    echo dmd_link('https://debitmydata.io/#mining', 'How to mine ' . dmd_icon('out'), 'dmd-cta');
    echo '</nav></div></header>';

    if (controller()->admin) {
        if (isAdminIP($_SERVER['REMOTE_ADDR']) === false) {
            debuglog("admin {$_SERVER['REMOTE_ADDR']}");
        }
    }
}

function showPageFooter()
{
    $year = date('Y');
    $id = 'https://id.debitmydata.io/';
    echo '<footer class="dmd-footer" id="dmd-footer"><div class="dmd-in"><div class="dmd-cols">';
    echo '<div class="dmd-col"><p class="dmd-head">The pool</p><a href="/">Home</a>'
        . '<a href="/mywallet/?address=">My wallet</a>' . dmd_app_footer_link() . '</div>';
    // The three DMD explorers, named as on debitmydata.io and id.debitmydata.io.
    echo '<div class="dmd-col"><p class="dmd-head">Explorers</p>'
        . dmd_link('https://mempool.debitmydata.io/', 'Mempool explorer')
        . dmd_link('https://chain.debitmydata.io/', 'Lite explorer')
        . dmd_link('https://explorer.debitmydata.io/', 'Rich list &amp; API') . '</div>';
    echo '<div class="dmd-col"><p class="dmd-head">Mine DMD</p>'
        . dmd_link('https://debitmydata.io/#mining', 'How to mine')
        . dmd_link('https://debitmydata.io/#rent', 'Rent hashrate')
        . dmd_link('https://debitmydata.io/#wallets', 'Get a DMD wallet') . '</div>';
    echo '<div class="dmd-col"><p class="dmd-head">Get a DID-LLM</p>'
        . dmd_link($id, 'Get a DID-LLM')
        . dmd_link('https://api.debitmydata.io/docs/advanced', 'API docs') . '</div>';
    echo '<div class="dmd-col"><p class="dmd-head">DebitMyData</p>'
        . dmd_link('https://debitmydata.io/', 'debitmydata.io')
        . dmd_link('https://debitmydata.com/', 'debitmydata.com')
        . dmd_link('https://agenticssocialspaces.debitmydata.com/', 'Agentic Spaces') . '</div>';
    echo '<div class="dmd-col"><p class="dmd-head">Community</p>'
        . dmd_link('https://discord.gg/PSK436rP4Y', 'Discord')
        . dmd_link('https://x.com/debitmydataPOW', 'X (Twitter)')
        . dmd_link('https://github.com/DebitMyData-PoW', 'GitHub')
        . '<a href="mailto:info@debitmydata.com">info@debitmydata.com</a></div>';
    echo '</div>';
    echo "<p class=\"dmd-legal\">© $year DebitMyData Pool · Pool URL: connect.debitmydata.io:8700 · PPLNS and solo</p>";
    echo '</div></footer>';
    // Some older pages leave tags open, which would put the footer inside the page: move it out.
    echo '<script>(function(){var f=document.getElementById("dmd-footer");'
        . 'if(f&&f.parentNode!==document.body){document.body.appendChild(f);}})();</script>';
}
