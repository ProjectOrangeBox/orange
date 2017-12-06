<?php 

trait Database_split_head_model_trait {
	# protected $read_group     = null; /* read only database group */
	# protected $write_group     = null; /* write database group */

	protected $read_database  = null; /* read only database resource */
	protected $write_database = null; /* write database resource */
	
	public function Database_role_model_trait__construct() {
		if (isset($this->read_group)) {
			$this->read_database = $this->load->database($this->read_group, true);
		}

		if (isset($this->write_group)) {
			$this->write_database = $this->load->database($this->write_group, true);
		}

		$this->trait_events['before insert'][] = function() {
			$this->_switch_database('write');
		};
		$this->trait_events['before update'][] = function() {
			$this->_switch_database('write');
		};
		$this->trait_events['before delete'][] = function() {
			$this->_switch_database('write');
		};
		$this->trait_events['before select'][] = function() {
			$this->_switch_database('read');
		};
	}

	protected function _switch_database($which) {
		if ($which == 'read' && $this->read_database) {
			$this->_database = $this->read_database;
		} elseif ($which == 'write' && $this->write_database) {
			$this->_database = $this->write_database;
		}

		return $this;
	}

} /* end trait */
