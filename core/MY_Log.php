<?php
/**
* Orange Framework Extension
*
* This content is released under the MIT License (MIT)
*
* @package	CodeIgniter / Orange
* @author	Don Myers
* @license http://opensource.org/licenses/MIT MIT License
* @link	https://github.com/ProjectOrangeBox
*
* required
* core: config
* libraries:
* models:
* helpers:
*
*/
class MY_Log extends CI_Log {
	protected $_monolog = null; /* singleton reference to monolog */
	protected $_bitwise = false; /* Are we using CodeIgniter Mode or PSR3 Bitwise Mode? */

	protected $ci_levels = [
		'ERROR' => 1,
		'DEBUG' => 2,
		'INFO'  => 3,
		'ALL'   => 4,
	];
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

	/*
	Useful Configuration in the config.php File

	log_threshold - CodeIgniter 0-4 / PSR3 0-255
	log_path - file based logs path
	log_file_extension - file based logs file extension
	log_file_permissions - file based log permissions
	log_date_format - date format used by Formatters
	log_use_bitwise_psr - Should the threshold be a bitwise/monolog style or CodeIgniter style?
	log_handler - codeigniter | monolog
	*/
	public function __construct() {
		/*
		This function has multiple exit points
		because we try to bail as soon as possible
		if no logging is needed to keep it a little faster
		*/
		$config = &get_config();

		/* Is it even on? */
		if (is_numeric($config['log_threshold'])) {
			if ($config['log_threshold'] == 0) {
				/* Nope! */
				$this->_enabled = false;

				/* no need to stay around bail now */
				return;
			}
		}

		/* it's on therefore let the CodeIgniter parent setup */
		parent::__construct();

		/* Use CodeIgniter or PSR Threshold */
		$this->_bitwise = (bool)$config['log_use_bitwise_psr'];

		if ($config['log_handler'] == 'monolog' && class_exists('\Monolog\Logger',false)) {
			/*
			Create a instance of monolog for the bootstrapper
			Make the monolog "channel" "CodeIgniter"
			This is a local variable so the bootstrapper can attach stuff to it
			*/

			$monolog = new \Monolog\Logger('CodeIgniter');

			/* find the monolog_bootstrap file */
			if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/monolog.php')) {
				include APPPATH.'config/'.ENVIRONMENT.'/monolog.php';
			} elseif (file_exists(APPPATH.'config/monolog.php')) {
				include APPPATH.'config/monolog.php';
			}

			/* Attach the monolog instance to our class for later use */
			$this->_monolog = &$monolog;
		}

		$this->write_log('DEBUG', 'MY_Log initialized');
	}

	/**
	 * Write Log File
	 *
	 * Generally this function will be called using the global log_message() function
	 *
	 * @param	string	the error level: 'error','debug' or 'info'
	 * @param	string	the error message
	 * @return	bool
	 */
	public function write_log($level, $msg) {
		/*
		This function has multiple exit points
		because we try to bail as soon as possible
		if no logging is needed to keep it a little faster
		*/
		if ($this->_enabled === false) {
			return false;
		}

		/* normalize */
		$level = strtoupper($level);

		/*
		Are we even logging this level?
		Which mode?
		PSR Bitwise or CodeIgniter
		*/
		if ($this->_bitwise) {
			/* bitwise PSR 3 Mode */
			if ((!array_key_exists($level, $this->psr_levels)) || (!($this->_threshold & $this->psr_levels[$level]))) {
				return false;
			}
		} else {
			/* CodeIgniter Mode */
			if ((!isset($this->ci_levels[$level]) || ($this->ci_levels[$level] > $this->_threshold)) && !isset($this->threshold_array[$this->ci_levels[$level]])) {
				return false;
			}
		}

		/* logging level check passed - log something! */
		return ($this->_monolog) ? $this->monolog_write_log($level, $msg) : $this->ci_write_log($level, $msg);
	}

	protected function monolog_write_log($level, $msg) {
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

	/*
	overridden to allow all PSR3 log levels

	pretty much a copy of CodeIgniter's Method with the "top" removed.
	*/
	protected function ci_write_log($level, $msg) {
		$filepath = $this->_log_path.'log-'.date('Y-m-d').'.'.$this->_file_ext;
		$message = '';

		if (!file_exists($filepath)) {
			$newfile = true;
			/* Only add protection to php files */
			if ($this->_file_ext === 'php') {
				$message .= "<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>\n\n";
			}
		}

		if (!$fp = @fopen($filepath, 'ab')) {
			return false;
		}

		flock($fp, LOCK_EX);

		/* Instantiating DateTime with microseconds appended to initial date is needed for proper support of this format */
		if (strpos($this->_date_fmt, 'u') !== FALSE) {
			$microtime_full = microtime(true);
			$microtime_short = sprintf("%06d", ($microtime_full - floor($microtime_full)) * 1000000);
			$date = new DateTime(date('Y-m-d H:i:s.'.$microtime_short, $microtime_full));
			$date = $date->format($this->_date_fmt);
		} else {
			$date = date($this->_date_fmt);
		}

		$message .= $this->_format_line($level, $date, $msg);

		for ($written = 0, $length = self::strlen($message); $written < $length; $written += $result) 	{
			if (($result = fwrite($fp, self::substr($message, $written))) === false) {
				break;
			}
		}

		flock($fp, LOCK_UN);
		fclose($fp);

		if (isset($newfile) && $newfile === true) 	{
			chmod($filepath, $this->_file_permissions);
		}

		return is_int($result);
	}

} /* End of Class */
