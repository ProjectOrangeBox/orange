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
		$primary_key = $this->{$this->entity_of_model_name}->get_primary_key();

		if ($this->save_columns) {
			foreach ($this->save_columns as $col) {
				$data[$col] = $this->$col;
			}
		} else {
			$data = get_object_vars($this);
		}

		/* unset these */
		unset($data['entity_of_model_name']);
		unset($data['save_columns']);

		/* update or insert? */
		return (!empty($data[$primary_key])) ? $this->{$this->entity_of_model_name}->update($data) : $this->{$this->entity_of_model_name}->insert($data);
	}

	/*
	public function __get($name) {}
	public function __set($name, $value) {}
  */

} /* end class */