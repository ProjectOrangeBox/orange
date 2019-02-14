<?php
/**
 * Model_entity
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
abstract class Model_entity
{
	protected $_model_name = null;
	protected $save_columns = null;

	/**
	 * __construct
	 *
	 */
	public function __construct()
	{
		/* the model name should match the entity name */
		$this->_model_name = strtolower(substr(get_called_class(), 0, -7).'_model');
		
		log_message('info', 'Model_entity Class Initialized');
	}

	/**
	 * provide a save method to auto save (update) a entity back to the database
	 *
	 * @return mixed success
	 *
	 */
	public function save()
	{
		/* get the model */
		$model = ci()->{$this->_model_name};
		
		/* get the primary id */
		$primary_id = $model->get_primary_key();
		
		/* if save columns is set then only use those properties */
		if ($this->save_columns) {
			foreach ($this->save_columns as $col) {
				$data[$col] = $this->$col;
			}
		} else {
			/* use all public properties */
			$data = get_object_vars($this);
		}
		
		/* if the primary id is empty then insert the entity */
		if ($data[$primary_id] == null) {
			/* make sure the primary id is not set */
			unset($data[$primary_id]);
			
			/* insert the record and return the inserted record primary id */
			$success = $model->insert($data);
			
			/* if success is not false (fail) then set the primary_id to success - inserted record primary id */
			if ($success !== false) {
				$this->$primary_id = $success;
			}
		} else {
			/* else it's a update */
			$success = $model->update($data);
		}
		
		/* return success */
		return (bool)$success;
	}
} /* end class */
