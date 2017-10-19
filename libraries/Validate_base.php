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
class Validate_base {
	protected $true_array  = [1, '1', 'y', 'on', 'yes', 't', 'true', true];
	protected $false_array = [0, '0', 'n', 'off', 'no', 'f', 'false', false];
	protected $error_string;
	protected $field_data;
	protected $field; /* reference to variable */

	/**
	 * wrapper to setup the validation base information
	 * @author Don Myers
	 * @param array &$field_data all of the fields being validated 
	 * this is helpful if you want to create a "the same as" or "different than" and you need to "look" at other columns
	 * in the validation set
	 * @param string &$error_string storage for the error message
	 */
	public function __construct(&$field_data, &$error_string) {
		$this->field_data   = &$field_data;
		$this->error_string = &$error_string;
	}

	/**
	 * field which needs to be validated
	 * @author Don Myers
	 * @param  string &$field reference to the field which needs to be validated
	 * @return $this chain-able
	 */
	public function field(&$field) {
		$this->field = &$field;

		/* chain-able */
		return $this;
	}

	/**
	 * wrapper
	 * @author Don Myers
	 * @param mixed &$field field need filtering
	 * @param string $options usually comma seperated list of options
	 */
	public function validate(&$field, $options) {}
	
	/**
	 * child callable method to shorten the fields content
	 * @author Don Myers
	 * @param  integer [$length = null] length to shorten the field
	 * @return $this chain-able
	 */
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

	/**
	 * child callable method to trim the fields content
	 * @author Don Myers
	 * @return $this chain-able
	 */
	public function trim() {
		$this->field = trim($this->field);

		/* chain-able */
		return $this;
	}

	/**
	 * child callable method to clean the input for visible character
	 * @author Don Myers
	 * @return $this chain-able
	 */
	public function human() {
		/* human characters only */
		$this->field = preg_replace("/[^\\x20-\\x7E]/mi", '', $this->field);

		/* chain-able */
		return $this;
	}

	/**
	 * child callable method to clean the input for visible character + return, line feed, tab
	 * @author Don Myers
	 * @return $this chain-able
	 */
	public function human_plus() {
		/* human,tab,linefeed,return */
		$this->field = preg_replace("/[^\\x20-\\x7E\\n\\t\\r]/mi", '', $this->field);

		/* chain-able */
		return $this;
	}

	/**
	 * child callable method to strip chracters passed
	 * @author Don Myers
	 * @param  string $strip chracters to strip from the field
	 * @return $this chain-able
	 */
	public function strip($strip) {
		$field = str_replace(str_split($strip), '', $field);

		/* chain-able */
		return $this;
	}

	/**
	 * child callable method convert a value into a boolean value
	 * @author Don Myers
	 * @param  string $field value to test
	 * @return bool
	 */
	public function is_bol($field) {
		return (in_array(strtolower($field), array_merge($this->true_array, $this->false_array), true)) ? true : false;
	}

} /* end class */