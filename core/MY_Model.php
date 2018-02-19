<?php
/**
 * MY_Model
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
 * libraries: errors, validate
 * models:
 * helpers:
 * functions:
 *
 */
class MY_Model extends CI_Model {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $rules     = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $rule_sets = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $object = null;

	/**
	 * object
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
	public function object() {
		return $this->object;
	}

	/**
	 * rules
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
	public function rules() {
		return $this->rules;
	}

	/**
	 * rule
	 * Insert description here
	 *
	 * @param $name
	 * @param $second
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function rule($name, $second = null) {
		return ($second) ? $this->rules[$name][$second] : $this->rules[$name];
	}

	/**
	 * clear
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
	public function clear() {
		ci('errors')->clear();

		return $this;
	}

	/**
	 * validate
	 * Insert description here
	 *
	 * @param $data
	 * @param $rules
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function validate(&$data, $rules = true) {
		if ($rules === true) {
			$rules = $this->_string2array(array_keys($data));
		} elseif (is_string($rules)) {
			$rules = $this->_string2array(explode(',', $this->rule_sets[$rules]));
		}

		$this->only_columns_with_rules($data, $rules);

		if (count($rules)) {
			ci('validate')->multiple($rules, $data);
		}

		return !ci('errors')->has();
	}

	/**
	 * remove_columns
	 * Insert description here
	 *
	 * @param $data
	 * @param $columns
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function remove_columns(&$data, $columns) {
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		foreach ($columns as $attr) {
			if (is_object($row)) {
				unset($row->$attr);
			} else {
				unset($row[$attr]);
			}
		}

		return $this;
	}

	/**
	 * only_columns
	 * Insert description here
	 *
	 * @param $data
	 * @param $columns
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function only_columns(&$data, $columns = []) {
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		foreach ($data as $key => $value) {
			if (!in_array($key, $columns)) {
				unset($data[$key]);
			}
		}

		return $this;
	}

	/**
	 * only_columns_with_rules
	 * Insert description here
	 *
	 * @param $data
	 * @param $rules
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function only_columns_with_rules(&$data, $rules = null) {
		$rule_fields = [];
		$rules = ($rules) ? $rules : $this->rules;

		foreach ($rules as $key => $rule) {
			if (isset($rule['field'])) {
				$rule_fields[] = $rule['field'];
			}
		}

		$this->only_columns($data, $rule_fields);

		return $this;
	}

	/**
	 * _string2array
	 * Insert description here
	 *
	 * @param $fields
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function _string2array($fields) {
		$new_array = [];

		foreach ($fields as $field) {
			if (isset($this->rules[$field])) {
				$new_array[$field] = $this->rules[$field];
			}
		}

		return $new_array;
	}
} /* end class */
