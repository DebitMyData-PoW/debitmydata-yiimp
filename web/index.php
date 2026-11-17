<?php

//define('YII_DEBUG', true);
// Every error, but not PHP's deprecation notices (old code still runs on PHP 8.3): the
// framework's error handler would end the page on them. Errors are logged, not shown to visitors.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 0);

require_once('/home/crypto-data/yiimp/site/configuration/serverconfig.php');
require_once('yaamp/defaultconfig.php');
require_once('yaamp/ui/app.php');

//$_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'];

// blacklist some search bots which ignore robots.txt (most in fact)
$isbot = false; $agent = arraySafeVal($_SERVER,'HTTP_USER_AGENT','');
if (strpos($agent, 'MJ12bot') || strpos($agent, 'DotBot') || strpos($agent, 'robot'))
	$isbot = true;
else if (strpos($agent, 'AhrefsBot') || strpos($agent, 'YandexBot') || strpos($agent, 'Googlebot'))
	$isbot = true;

if ($isbot) {
	$url = arraySafeVal($_SERVER,'REQUEST_URI');
	if (strpos($url, "explorer"))
		throw new CHttpException(403,"You are not wanted on this server. see robots.txt");
	die();
}

try
{
	$app->run();
}

catch(CException $e)
{
	// The visitor gets the status and a short message (not the exception, whose trace shows the
	// server's files); the details of a server error go to the framework's log.
	$status = ($e instanceof CHttpException) ? (int) $e->statusCode : 500;
	if (!headers_sent()) http_response_code($status);
	if ($status >= 500) Yii::log((string) $e, CLogger::LEVEL_ERROR, 'exception.' . get_class($e));
	$message = ($status == 404) ? 'The requested page does not exist.'
		: (($status < 500) ? $e->getMessage() : 'Something went wrong on our side. Please try again later.');
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $status . '</title></head><body><p>'
		. htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="/">Home</a></p></body></html>';
	die();
}

