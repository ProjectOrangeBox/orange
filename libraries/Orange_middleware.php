<?php 

class Orange_middleware {
	protected static $middleware = [];

	public static function set() {
		self::$middleware = func_get_args();
	}
	
	public static function get() {
		return self::$middleware;
	}

}
