<?php
error_reporting(E_ALL);
ini_set("memory_limit", "32M");
ini_set("max_execution_time", "0");

define('DB_HOST',	$_ENV["DB_HOST"]);
define('DB_USER',	$_ENV["DB_USER"]);
define('DB_PASS',	$_ENV["DB_PASS"]);
define('DB_NAME',	$_ENV["DB_NAME"]);
define('HLXCE_WEB',	'/var/www/html');
define('HUD_URL',	'http://www.hlxcommunity.com');
define('OUTPUT_SIZE',	'medium');

define('DB_PREFIX',	'hlstats');
define('KILL_LIMIT',	10000);
define('DEBUG', 1);

// No need to change this unless you are on really low disk.
define('CACHE_DIR',	dirname(__FILE__) . '/cache');

