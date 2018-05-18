<?php
/*
log_threshold - 0-4 if in psr mode 0-255
log_path - file based logs path
log_file_extension - file based logs file extension
log_file_permissions - file based log permissions
log_date_format - date format used by Formatters
log_use_bitwise_psr - Should the threshold be a bitwise/monolog style or CodeIgniter style?
*/

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\BrowserConsoleHandler;

/* Are we using monolog? */
if (isset($monolog)) {
	//$monolog->pushHandler(new StreamHandler($config['log_path'].'/'.date('Y-m-d').'.'.$config['log_file_extension'], Logger::DEBUG));
	//$monolog->pushHandler(new BrowserConsoleHandler(Logger::DEBUG));
	$monolog->pushHandler(new RotatingFileHandler($config['log_path'].'/log.'.$config['log_file_extension'],7, Logger::DEBUG));
}
