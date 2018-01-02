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

class MY_Model extends CI_Model {
	protected $rules     = [];
	protected $rule_sets = [];

	protected $object = null;

	public function object() {
		return $this->object;
	}

	public function rules() {
		return $this->rules;
	}

	public function rule($name, $second = null) {
		return ($second) ? $this->rules[$name][$second] : $this->rules[$name];
	}

	public function clear() {
		errors::clear();

		return $this;
	}

	public function validate(&$data, $rules = true) {
		if ($rules === true) {
			$rules = $this->string2array(array_keys($data));
		} elseif (is_string($rules)) {
			$rules = $this->string2array(explode(',', $this->rule_sets[$rules]));
		}

		$this->only_columns_with_rules($data, $rules);

		if (count($rules)) {
			ci('validate')->multiple($rules, $data);
		}

		return !errors::has();
	}

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

	protected function only_columns(&$data, $columns = []) {
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		foreach ($data as $key => $value) {
			if (!in_array($key, $columns)) {
				unset($data[$key]);
			}
		}

		return $this;
	}

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

	protected function string2array($fields) {
		$new_array = [];

		foreach ($fields as $field) {

			if (isset($this->rules[$field])) {
				$new_array[$field] = $this->rules[$field];
			}
		}

		return $new_array;
	}

} /* end file */