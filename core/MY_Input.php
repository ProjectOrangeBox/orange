<?php
/**
 * MY_Input
 * provides some helper methods for fetching input data and pre-processing it.
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class MY_Input extends CI_Input {
	/**
	 * contains the current POST or PUT or PATCH request data
	 *
	 * @var array
	 */
	protected $_request;

	public function __construct() {
		/* call the parent classes constructor */
		parent::__construct();

		/* load our array with the incoming data */
		$this->_request = (count($_POST) > 0) ? $_POST : $this->input_stream();

		log_message('info', 'MY_Input Class Initialized');
	}

	/**
	 * fetch post or put data
	 *
	 * @param string $index input parameter name
	 * @param mixed $default default value if empty
	 * @param boolean $xss_clean whether to apply XSS filtering
	 *
	 * @return mixed
	 *
	 * @examples request('name','nothing supplied',true) - returns the value of name or nothing supplied if not provided with XSS filter
	 * @examples request(null,null,true) - returns all POST items with XSS filter
	 * @examples request() - returns all POST items without XSS filter
	 */
	public function request($index = null, $default = null, $xss_clean = false) {
		log_message('debug', 'MY_Input::request::'.$index);

		/* pull the value from our array and process with our built in function */
		$value = $this->_fetch_from_array($this->_request, $index, $xss_clean);

		/* was anything returned? if no return the default */
		return ($value === null) ? $default : $value;
	}

	/**
	 * request_replace
	 * replace or add input data
	 *
	 * @param string $index input parameter name
	 * @param mixed $replace_value value
	 *
	 * @return $this
	 *
	 * @examples request_replace('name','Dr Pepper')
	 */
	public function request_replace($index = null, $replace_value = null) {
		$this->_request[$index] = $replace_value;

		return $this;
	}

	/**
	 * request_remap
	 * remap the input keys
	 *
	 * @param array $map associated array new key=>old key
	 * @param boolean $keep_current keep the current request data
	 *
	 * @return $this
	 *
	 * @examples
	 */
	public function request_remap($map = [],$keep_current = false) {
		$current_request = $this->_request;

		if (!$keep_current) {
			$this->_request = [];
		}

		foreach ((array)$map as $new_key => $old_key) {
			$this->_request[$new_key] = $current_request[$old_key];
		}

		return $this;
	}

	/**
	 * process a request with advanced options
	 * this makes it easier to pass this data into a model
	 * or to provide additional processing
	 *
	 * @param array $move associated array new key=>old key (move)
	 * @param array $copy associated array new key=>old key (copy)
	 * @param string $default_model the name of the default model (where separator isn't present)
	 * @param string $only_model_name only return this model
	 * @param string $separator if this is present then treat this as a model + field pair
	 * @param boolean $append_model should "_model" be append to the model names
	 * @param array $_request form index=>value associated array
	 *
	 * @return array
	 *
	 * @examples
	 */
	public function request_process($move=[],$copy=[],$default_model='#',$only_model_name=null,$separator='.',$append_model=false,$_request=[]) {
		$request = ($_request) ? $_request : $this->_request;

		$groups = [];

		/* first copy over any fields */
		foreach ((array)$copy as $new_key=>$old_key) {
			if (isset($request[$old_key])) {
				$request[$new_key] = $request[$old_key];
			}
		}
	
		


		/* now group them */
		foreach ($request as $index=>$value) {
			if (strpos($index,$separator) !== false) {
				if (is_array($move)) {
					if (isset($move[$index])) {
						$index = $move[$index];
					}
				}

				list($model,$field) = explode($separator,$index,2);

				if ($append_model) {
					$model = str_replace('_model','',$model).'_model';
				}

				if (is_array($value)) {
					foreach ($value as $idx=>$v) {
						$groups[$model][$idx][$field] = $v;
					}
				} else {
					$groups[$model][$field] = $value;
				}
			} else {
				$groups[$default_model][$index] = $value;
			}
		}

		if ($append_model && $only_model_name) {
			$only_model_name = str_replace('_model','',$only_model_name).'_model';
		}

		return ($only_model_name) ? $groups[$only_model_name] : $groups;
	}

	/**
	 * cookie
	 * treat cookie like request with default value
	 *
	 * @param string $index input parameter name
	 * @param string $default default value if empty
	 * @param boolean $xss_clean whether to apply XSS filtering
	 *
	 * @return mixed
	 *
	 * @examples cookies('username','unknown',true)- returns the value of name or nothing supplied if not provided with XSS filter
	 * @examples cookies(null,null,true) - returns all COOKIE items with XSS filter
	 * @examples cookies() - returns all COOKIE items without XSS filter
	 */
	public function cookie($index = null, $default = null, $xss_clean = false) {
		$value = $this->_fetch_from_array($_COOKIE, $index, $xss_clean);

		return ($value === null) ? $default : $value;
	}
} /* end class */