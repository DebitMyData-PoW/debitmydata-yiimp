<?php


echo <<<end
<script>
window.addEventListener("load", () => {
  const loader = document.querySelector(".loader");

  loader.classList.add("loader--hidden");

  loader.addEventListener("transitionend", () => {
    document.body.removeChild(loader);
  //  loader.remove();
  });
});
</script>

<div class="loader"></div>
end;

echo getAdminSideBarLinks();
?>

<div id='main_results'></div>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

$(function()
{
	main_refresh();
});

var main_delay=30000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = "/site/exchange_results";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>



