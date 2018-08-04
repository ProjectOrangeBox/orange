<?php
/**
 * Validate_base
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
abstract class Validate_base {
	/**
	 * true values
	 */
	protected $true_array = [1, '1', 'y', 'on', 'yes', 't', 'true', true];

	/**
	 * false values
	 */
	protected $false_array = [0, '0', 'n', 'off', 'no', 'f', 'false', false];

	/**
	 * Error String
	 */
	protected $error_string = '';

	/**
	 * track if the combined cached configuration has been loaded
	 */
	protected $field_data;

	/**
	 * track if the combined cached configuration has been loaded
	 */
	protected $field;

	/**
	 * __construct
	 * Insert description here
	 *
	 * @param $field_data
	 * @param $error_string
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function __construct(&$field_data=null, &$error_string=null) {
		$this->field_data   = &$field_data;
		$this->error_string = &$error_string;

		log_message('info', 'Validate_base Class Initialized');
	}

	/**
	 * field
	 * Insert description here
	 *
	 * @param $field
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function field(&$field) {
		$this->field = &$field;

		return $this;
	}

	/**
	 * validate
	 * Insert description here
	 *
	 * @param $field
	 * @param $options
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function validate(&$field, $options) {}

	/**
	 * length
	 * Insert description here
	 *
	 * @param $length
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function length($length = null) {
		if (is_numeric($length)) {
			if ((int) $length > 0) {
				$this->field = substr($this->field, 0, $length);
			}
		}

		return $this;
	}

	/**
	 * trim
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function trim() {
		$this->field = trim($this->field);

		return $this;
	}

	/**
	 * human
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function human() {
		$this->field = preg_replace("/[^\\x20-\\x7E]/mi", '', $this->field);

		return $this;
	}

	/**
	 * human_plus
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function human_plus() {
		$this->field = preg_replace("/[^\\x20-\\x7E\\n\\t\\r]/mi", '', $this->field);

		return $this;
	}

	/**
	 * strip
	 * Insert description here
	 *
	 * @param $strip
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function strip($strip) {
		$this->field = str_replace(str_split($strip), '', $this->field);

		return $this;
	}

	/**
	 * is_bol
	 * Insert description here
	 *
	 * @param $field
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function is_bol($field) {
		return (in_array(strtolower($field), array_merge($this->true_array, $this->false_array), true)) ? true : false;
	}
} /* end class */
