<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Extension to CodeIgniter Log Class
 *
 * Handle general logging with optional Monolog library support
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @config config.log_threshold `0`
 * @config config.log_path `ROOTPATH.'/var/logs/'`
 * @config config.log_file_extension `log`
 * @config config.log_file_permissions `0644`
 * @config config.log_date_format `Y-m-d H:i:s.u`
 * @config config.log_use_bitwise_psr `true`
 * @config config.log_handler
 *
 * @method __call
 *
 */

class MY_Log extends \CI_Log
{
	/**
	 * Local reference to monolog object
	 *
	 * @var \Monolog\Logger
	 */
	protected $_monolog = null; /* singleton reference to monolog */

	/**
	 * Boolean whether we are using bitwise error levels
	 *
	 * @var Boolean
	 */
	protected $_bitwise = false; /* Are we using CodeIgniter Mode or PSR3 Bitwise Mode? */

	/**
	 * Local reference to logging configurations
	 *
	 * @var Array
	 */
	protected $config = [];

	/**
	 * String to PSR error levels
	 *
	 * @var Array
	 */
	protected $psr_levels = [
		'EMERGENCY' => 1,
		'ALERT'     => 2,
		'CRITICAL'  => 4,
		'ERROR'     => 8,
		'WARNING'   => 16,
		'NOTICE'    => 32,
		'INFO'      => 64,
		'DEBUG'     => 128,
	];

	/**
	 * String to RFC error levels
	 *
	 * @var Array
	 */
	protected $rfc_log_levels = [
		'DEBUG'     => 100,
		'INFO'      => 200,
		'NOTICE'    => 250,
		'WARNING'   => 300,
		'ERROR'     => 400,
		'CRITICAL'  => 500,
		'ALERT'     => 550,
		'EMERGENCY' => 600,
	];

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 */
	public function __construct()
	{
		$this->config = load_config('config');

		$this->bootstrap();

		$this->write_log('DEBUG', 'MY_Log initialized');
	}

	/**
	 *
	 * Allow the assigning of any configuration that starts with log_
	 *
	 * #### Example
	 * ```php
	 * ci('log')->log_threshold(255)
	 * ci('log')->log_path(APPPATH.'/logs')
	 * ```
	 * @access public
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @return MY_Log
	 *
	 */
	public function __call(string $name, array $arguments) : MY_Log
	{
		if (substr($name, 0, 4) == 'log_') {
			$this->config[$name] = $arguments[0];

			$this->bootstrap();
		}

		return $this;
	}

	/**
	 *
	 * Write to log file
	 * Generally this function will be called using the global log_message() function
	 * If configuration value log_use_bitwise_psr is true
	 * then you can also use all of the other psr error levels
	 *
	 * @access public
	 *
	 * @param $level error|debug|info
	 * @param $msg the error message
	 *
	 * @return bool
	 *
	 */
	public function write_log($level, $msg) : bool
	{
		/**
		 * This function has multiple exit points
		 * because we try to bail as soon as possible
		 * if no logging is needed to keep it a little faster
		 */
		if (!$this->_enabled) {
			return false;
		}

		/* normalize */
		$level = strtoupper($level);

		/* bitwise PSR 3 Mode */
		if ((!array_key_exists($level, $this->psr_levels)) || (!($this->_threshold & $this->psr_levels[$level]))) {
			return false;
		}

		/* logging level check passed - log something! */
		return ($this->_monolog) ? $this->monolog_write_log($level, $msg) : $this->ci_write_log($level, $msg);
	}

	/**
	 *
	 * Get the contents of the current log file
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function get_log_file() : string
	{
		$file = $this->build_log_file_path();

		return (file_exists($file)) ? file_get_contents($file) : '';
	}

	/**
	 *
	 * Test whether logging is enabled
	 *
	 * #### Example
	 * ```php
	 * ci('log')->is_enabled();
	 * ```
	 * @access public
	 *
	 * @return Bool
	 *
	 */
	public function is_enabled() : Bool
	{
		return $this->_enabled;
	}

	/**
	 *
	 * Handle writing to monolog
	 * if we are using it
	 *
	 * @access protected
	 *
	 * @param string $level
	 * @param string $msg
	 *
	 * @return bool
	 *
	 */
	protected function monolog_write_log(string $level, string $msg) : bool
	{
		/* route to monolog */
		switch ($level) {
		case 'EMERGENCY': // 1
			$this->_monolog->addEmergency($msg);
			break;
		case 'ALERT': // 2
			$this->_monolog->addAlert($msg);
			break;
		case 'CRITICAL': // 4
			$this->_monolog->addCritical($msg);
			break;
		case 'ERROR': // 8
			$this->_monolog->addError($msg);
			break;
		case 'WARNING': // 16
			$this->_monolog->addWarning($msg);
			break;
		case 'NOTICE': // 32
			$this->_monolog->addNotice($msg);
			break;
		case 'INFO': // 64
			$this->_monolog->addInfo($msg);
			break;
		case 'DEBUG': // 128
			$this->_monolog->addDebug($msg);
			break;
		}

		return true;
	}

	/**
	 *
	 * Build the CodeIgniter Log File Path
	 *
	 * @access protected
	 *
	 * @return string
	 *
	 */
	protected function build_log_file_path() : string
	{
		return rtrim($this->_log_path, '/').'/log-'.date('Y-m-d').'.'.$this->_file_ext;
	}

	/**
	 *
	 * Overridden to allow all PSR3 log levels if they are passed
	 * This should be tested before calling this mehtod
	 * Pretty much a copy of CodeIgniter's Method.
	 *
	 * @access protected
	 *
	 * @param string $level
	 * @param string $msg
	 *
	 * @return bool success
	 *
	 */
	protected function ci_write_log(string $level, string $msg) : bool
	{
		$filepath = $this->build_log_file_path();
		$message = '';

		if (!file_exists($filepath)) {
			$newfile = true;
			/* Only add protection to php files */
			if ($this->_file_ext === 'php') {
				$message .= "<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>\n\n";
			}
		}

		/* Instantiating DateTime with microseconds appended to initial date is needed for proper support of this format */
		if (strpos($this->_date_fmt, 'u') !== false) {
			$microtime_full = microtime(true);
			$microtime_short = sprintf("%06d", ($microtime_full - floor($microtime_full)) * 1000000);
			$date = new DateTime(date('Y-m-d H:i:s.'.$microtime_short, $microtime_full));
			$date = $date->format($this->_date_fmt);
		} else {
			$date = date($this->_date_fmt);
		}

		$message .= $this->_format_line($level, $date, $msg);

		$result = file_put_contents($filepath, $message, FILE_APPEND | LOCK_EX);

		if (isset($newfile) && $newfile === true) {
			chmod($filepath, $this->_file_permissions);
		}

		return is_int($result);
	}

	/**
	 *
	 * Bootstrap / reconfigure bootstrap after a configuration value change
	 *
	 * @access protected
	 *
	 */
	protected function bootstrap() : void
	{
		if (isset($this->config['log_threshold'])) {
			$log_threshold = $this->config['log_threshold'];

			/* if they sent in a string split it into a array */
			if (is_string($log_threshold)) {
				$log_threshold = explode(',', $log_threshold);
			}

			/* is the array empty? */
			if (is_array($log_threshold)) {
				if (count($log_threshold) == 0) {
					$log_threshold = 0;
				}
			}

			/* Is all in the array (uppercase or lowercase?) */
			if (is_array($log_threshold)) {
				if (array_search('all', $log_threshold) !== false) {
					$log_threshold = 255;
				}
			}

			/* build the bitwise integer */
			if (is_array($log_threshold)) {
				$int = 0;

				foreach ($log_threshold as $t) {
					$t = strtoupper($t);

					if (isset($this->psr_levels[$t])) {
						$int += $this->psr_levels[$t];
					}
				}

				$log_threshold = $int;
			}

			$this->_threshold = (int)$log_threshold;

			$this->_enabled = ($this->_threshold > 0);
		}

		isset(self::$func_overload) || self::$func_overload = (extension_loaded('mbstring') && ini_get('mbstring.func_overload'));

		if (isset($this->config['log_file_extension'])) {
			$this->_file_ext = (!empty($this->config['log_file_extension'])) 	? ltrim($this->config['log_file_extension'], '.') : 'php';
		}

		if (isset($this->config['log_path'])) {
			$this->_log_path = ($this->config['log_path'] !== '') ? $this->config['log_path'] : APPPATH.'logs/';

			file_exists($this->_log_path) || mkdir($this->_log_path, 0755, true);

			if (!is_dir($this->_log_path) || !is_really_writable($this->_log_path)) {
				/* can't write */
				$this->_enabled = false;
			}
		}

		if (!empty($this->config['log_date_format'])) {
			$this->_date_fmt = $this->config['log_date_format'];
		}

		if (!empty($this->config['log_file_permissions']) && is_int($this->config['log_file_permissions'])) {
			$this->_file_permissions = $this->config['log_file_permissions'];
		}

		if (isset($this->config['log_handler'])) {
			if ($this->config['log_handler'] == 'monolog' && class_exists('\Monolog\Logger', false)) {
				if (!$this->_monolog) {
					/**
					 * Create a instance of monolog for the bootstrapper
					 * Make the monolog "channel" "CodeIgniter"
					 * This is a local variable so the bootstrapper can attach stuff to it
					 */
					$monolog = new \Monolog\Logger('CodeIgniter');

					/**
					 * Find the monolog_bootstrap files
					 * This is NOT a standard Codeigniter config
					 * It includes PHP code which can use the $monolog object we just made
					 */
					if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/monolog.php')) {
						include APPPATH.'config/'.ENVIRONMENT.'/monolog.php';
					} elseif (file_exists(APPPATH.'config/monolog.php')) {
						include APPPATH.'config/monolog.php';
					}

					/**
					 * Attach the monolog instance to our class for later use
					 */
					$this->_monolog = &$monolog;
				}
			}
		}
	}
} /* End of Class */
