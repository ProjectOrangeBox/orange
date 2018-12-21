<?php

class Session extends CI_Session {

	public function userdata($key = NULL,$remove = false) {
		$data = parent::userdata($key);

		if (is_string($key) && $remove) {
			$this->unset_userdata($key);
		}

		return $data;
	}

	public function tempdata($key = NULL,$remove = false) {
		$data = parent::tempdata($key);

		if (is_string($key) && $remove) {
			$this->unset_tempdata($key);
		}

		return $data;
	}

}
