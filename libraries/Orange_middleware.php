<?php

class Orange_middleware {
	protected static $requests = [];
	protected static $responds = [];

	public static function on_request() {
		self::$requests = func_get_args();
	}

	public static function on_responds() {
		self::$responds = func_get_args();
	}

	public static function requests() {
		return self::$requests;
	}

	public static function responds() {
		return self::$responds;
	}

}