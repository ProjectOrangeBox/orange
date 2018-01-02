<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class Validate_base {
	protected $true_array  = [1, '1', 'y', 'on', 'yes', 't', 'true', true];
	protected $false_array = [0, '0', 'n', 'off', 'no', 'f', 'false', false];
	protected $error_string;
	protected $field_data;
	protected $field;

	public function __construct(&$field_data, &$error_string) {
		$this->field_data   = &$field_data;
		$this->error_string = &$error_string;
	}

	public function field(&$field) {
		$this->field = &$field;

		return $this;
	}

	public function validate(&$field, $options) {}

	public function length($length = null) {
		if (is_numeric($length)) {
			if ((int) $length > 0) {
				$this->field = substr($this->field, 0, $length);
			}
		}

		return $this;
	}

	public function trim() {
		$this->field = trim($this->field);

		return $this;
	}

	public function human() {
		$this->field = preg_replace("/[^\\x20-\\x7E]/mi", '', $this->field);

		return $this;
	}

	public function human_plus() {
		$this->field = preg_replace("/[^\\x20-\\x7E\\n\\t\\r]/mi", '', $this->field);

		return $this;
	}

	public function strip($strip) {
		$field = str_replace(str_split($strip), '', $field);

		return $this;
	}

	public function is_bol($field) {
		return (in_array(strtolower($field), array_merge($this->true_array, $this->false_array), true)) ? true : false;
	}

} /* end file */