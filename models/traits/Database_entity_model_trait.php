<?php 

trait Database_entity_model_trait {
	protected $entity = null;

	public function Database_entity_model_trait__construct() {
		$this->entity = ucfirst(strtolower(substr(get_class(),0,-5)).'entity');
		
		/* load root the entity this does not need to be attached to the CI super global */
		require_once 'models/Model_entity.php';

		/* load supplied entity */
		require_once 'models/entities/'.$this->entity.'.php';

		/* override the models */
		$this->default_return_on_multi = [];
		$this->default_return_on_single = new $this->entity();
	}

	/* override the models */
	protected function _as_array($dbc) {
		$result = $this->default_return_on_multi;

		/* multiple records */
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
				$result = $dbc->custom_result_object($this->entity);
			}
		}
	
		return $result;
	}
	
	/* override the models */
	protected function _as_row($dbc) {
		$result = $this->default_return_on_single;
	
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
				$result = $dbc->custom_row_object(0, $this->entity);
			}
		}
		
		return $result;
	}

} /* end trait */
