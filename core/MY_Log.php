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
		/* defaults */
		$this->_log_path = APPPATH.'logs/';

		$this->reconfigure(load_config('config'));

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

	public function get_log_file() {
		$file = $this->_log_path.'log-'.date('Y-m-d').'.'.$this->_file_ext;

		return (file_exists($file)) ? file_get_contents($file) : '';
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

	pretty much a copy of CodeIgniter's Method.
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

		$result = file_put_contents($filepath, $message, FILE_APPEND | LOCK_EX);

		if (isset($newfile) && $newfile === true) 	{
			chmod($filepath, $this->_file_permissions);
		}

		return is_int($result);
	}

	protected function _convert_string_to_value($string) {

	}

	public function reconfigure($config,$value=null) {
		if ($value !== null) {
			$config = [$config=>$value];
		}

		if (isset($config['log_threshold'])) {
			$log_threshold = $config['log_threshold'];

			if (is_string($log_threshold)) {
				$log_threshold = strtoupper($log_threshold);
				
				if (strpos($log_threshold,',') !== false) {
					$log_threshold = explode(',',$log_threshold);
				} elseif ($log_threshold == 'ALL') {
					$log_threshold = 255;
				}
			}

			if (is_array($log_threshold)) {
				$int = 0;
				
				foreach ($log_threshold as $t) {
					$int += $this->psr_levels[strtoupper($t)];
				}

				$log_threshold = $int;
			}

			$this->_threshold = (int)$log_threshold;
			$this->_enabled = ($this->_threshold > 0);
		}

		isset(self::$func_overload) || self::$func_overload = (extension_loaded('mbstring') && ini_get('mbstring.func_overload'));

		if (isset($config['log_file_extension'])) {
			$this->_file_ext = (!empty($config['log_file_extension'])) 	? ltrim($config['log_file_extension'], '.') : 'php';
		}

		if (isset($config['log_path'])) {
			$this->_log_path = ($config['log_path'] !== '') ? $config['log_path'] : APPPATH.'logs/';

			file_exists($this->_log_path) || mkdir($this->_log_path, 0755, TRUE);

			if (!is_dir($this->_log_path) || !is_really_writable($this->_log_path)) {
				/* can't write */
				$this->_enabled = FALSE;
			}
		}

		if (!empty($config['log_date_format'])) 	{
			$this->_date_fmt = $config['log_date_format'];
		}

		if (!empty($config['log_file_permissions']) && is_int($config['log_file_permissions'])) 	{
			$this->_file_permissions = $config['log_file_permissions'];
		}

		if (isset($config['log_handler'])) {
			if ($config['log_handler'] == 'monolog' && class_exists('\Monolog\Logger',false)) {
				if (!$this->_monolog) {
					/*
					Create a instance of monolog for the bootstrapper
					Make the monolog "channel" "CodeIgniter"
					This is a local variable so the bootstrapper can attach stuff to it
					*/

					$monolog = new \Monolog\Logger('CodeIgniter');

					/*
					Find the monolog_bootstrap files
					This is NOT a standard Codeigniter config
					It includes PHP code which can use the $monolog object we just made
					*/
					if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/monolog.php')) {
						include APPPATH.'config/'.ENVIRONMENT.'/monolog.php';
					} elseif (file_exists(APPPATH.'config/monolog.php')) {
						include APPPATH.'config/monolog.php';
					}

					/* Attach the monolog instance to our class for later use */
					$this->_monolog = &$monolog;
				}
			}
		}

		return $this->_enabled;
	}

} /* End of Class */
