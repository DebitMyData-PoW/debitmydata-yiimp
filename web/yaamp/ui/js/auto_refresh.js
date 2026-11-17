var auto_delay = 30000; // Refresh interval: 30 seconds

$(function() {
	auto_page_refresh();
});

function auto_page_refresh() {
	page_refresh();  // Your function to refresh data

	setTimeout(auto_page_refresh, auto_delay);  // Keep refreshing every 30 seconds
}
