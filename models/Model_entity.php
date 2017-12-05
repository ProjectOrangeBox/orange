<?php
/**
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
 *
 */
 
class Model_entity {
	protected $entity_of_model_name = null; /* name of the "parent" model */
	protected $save_columns         = null; /* when saving only use these columns */

	public function __construct() {
		if (!$this->entity_of_model_name) {
			/* remove _entity add _model */
			$this->entity_of_model_name = strtolower(substr(get_called_class(), 0, -7) . '_model');
		}

		log_message('info', 'Model_entity Class Initialized');
	}

	public function save() {
		$model = ci()->{$this->entity_of_model_name};
		$primary_id = $model->get_primary_key();
	
		if ($this->save_columns) {
			foreach ($this->save_columns as $col) {
				$data[$col] = $this->$col;
			}
		} else {
			$data = get_object_vars($this);
		}

		if ($data[$primary_id] == null) {
			/* let's make sure it's removed then */
			unset($data[$primary_id]);
			
			$success = $model->insert($data);
			
			/* put it on this entity so they can update it on the next save call */
			if ($success !== false) {
				$this->$primary_id = $success;
			}			
		} else {
			$success = $model->update($data);
		}
		
		return $success;
	}

	/*
	public function __get($name) {}
	public function __set($name, $value) {}
  */

} /* end class */