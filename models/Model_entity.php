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

	public function update() {
		if ($this->save_columns) {
			foreach ($this->save_columns as $col) {
				$data[$col] = $this->$col;
			}
		} else {
			$data = getPublicObjectVars($this);
		}

		/* update */
		return $this->{$this->entity_of_model_name}->update($data);
	}

	/*
	public function __get($name) {}
	public function __set($name, $value) {}
  */

} /* end class */