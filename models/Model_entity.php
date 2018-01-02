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

class Model_entity {
	protected $_model_name = null;
	protected $save_columns = null;

	public function __construct() {
		$this->_model_name = strtolower(substr(get_called_class(),0,-7).'_model');
		log_message('info', 'Model_entity Class Initialized');
	}

	public function save() {
		$model = ci()->{$this->_model_name};
		$primary_id = $model->get_primary_key();

		if ($this->save_columns) {
			foreach ($this->save_columns as $col) {
				$data[$col] = $this->$col;
			}
		} else {
			$data = get_object_vars($this);
		}

		if ($data[$primary_id] == null) {

			unset($data[$primary_id]);
			$success = $model->insert($data);

			if ($success !== false) {
				$this->$primary_id = $success;
			}
		} else {
			$success = $model->update($data);
		}

		return (bool)$success;
	}

} /* end file */