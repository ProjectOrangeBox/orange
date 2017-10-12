<?php
/**
 * Orange Framework validation rule
 *
 * This content is released under the MIT License (MIT)
 *
 * @package	CodeIgniter / Orange
 * @author	Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link	https://github.com/ProjectOrangeBox
 *
 */
class Filter_base {
	protected $true_array  = [1, '1', 'y', 'on', 'yes', 't', 'true', true];
	protected $false_array = [0, '0', 'n', 'off', 'no', 'f', 'false', false];
	protected $field_data;
	protected $field; /* reference to variable */

	public function __construct(&$field_data) {
		$this->field_data   = &$field_data;
	}

	public function field(&$field) {
		$this->field = &$field;

		/* chain-able */
		return $this;
	}

	public function length($length = null) {
		/* Did they send in a number for the max length? */
		if (is_numeric($length)) {
			if ((int) $length > 0) {
				/* $field modified by reference */
				$this->field = substr($this->field, 0, $length);
			}
		}

		/* chain-able */
		return $this;
	}

	public function trim() {
		$this->field = trim($this->field);

		/* chain-able */
		return $this;
	}

	public function human() {
		/* human characters only */
		$this->field = preg_replace("/[^\\x20-\\x7E]/mi", '', $this->field);

		/* chain-able */
		return $this;
	}

	public function human_plus() {
		/* human,tab,linefeed,return */
		$this->field = preg_replace("/[^\\x20-\\x7E\\n\\t\\r]/mi", '', $this->field);

		/* chain-able */
		return $this;
	}

	public function strip($strip) {
		$this->field = str_replace(str_split($strip), '', $this->field);

		/* chain-able */
		return $this;
	}

	/* is this a boolean value (in general terms) */
	public function is_bol($field) {
		return (in_array(strtolower($field), array_merge($this->true_array, $this->false_array), true)) ? true : false;
	}

} /* end class */