<?php

abstract class Middleware_base {
	protected $ci;

	public function __construct(&$ci) {
		$this->ci = &$ci;
	}

	public function request() {
	}

	public function responds($output) {
		return $output;
	}

	/* allow $this to work in middleware */
	public function __get($name) {
		return $this->ci->$name;
	}

}