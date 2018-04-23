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
	protected $_request = [];

	public function __construct() {
		$this->_raw_input_stream = file_get_contents('php://input');
		$this->_request = $this->http_parse_query($this->_raw_input_stream);

		/* call the parent classes constructor */
		parent::__construct();

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
	 * process a request with advanced options
	 * this makes it easier to pass the returned array into a models
	 * or something else for further processing
	 *
	 * @param array $copy associated array new index=>old index (copy) - preformed first
	 * @param array $move associated array new index=>old index (move) - preformed second
	 * @param array $remove array indexes - preformed last
	 * @param string $default_model the name of the default model (where separator isn't present)
	 * @param string $only_index only return this model
	 * @param string $separator if this is present then treat each array index as a model + field pair
	 * @param boolean $append_model should "_model" be append to the array indexes
	 * @param mixed $_request associated array index=>value - or - TRUE to use client input
	 *
	 * @return array
	 *
	 * if $_request is true the input is processed and placed back into client input
	 * if $_request is false the input is processed then a array is returned
	 * if $_request is an array then a array is returned
	 *
	 * it is possible to append [] (brackets) after the field name in the
	 * copy or move array in order to copy or move the value to the output of a array if needed
	 * $copy = ['roles.parent_id[]'=>'id'];
	 * This would copy the id to each role index
	 *
	 */
	public function request_remap($copy=[],$move=[],$remove=[],$default_model='#',$only_index=null,$separator='.',$append_model=false,$_request=[]) {
		/* use form request or what was sent in? */
		if (is_bool($_request)) {
			$request = $this->_request;
		} elseif (is_array($_request)) {
			$request = $_request;
		} else {
			throw new Exception('Request Process input not an array or boolean.');
		}

		$append_model = ($append_model === true) ? '_model' : $append_model;

		/* storage for new form groups */
		$groups = [];

		/* first copy over any fields */
		foreach ((array)$copy as $new_index=>$old_index) {
			if (isset($request[$old_index])) {
				$request[$new_index] = $request[$old_index];
			}
		}

		/* then move any fields */
		foreach ((array)$move as $new_index=>$old_index) {
			if (isset($request[$old_index])) {
				$request[$new_index] = $request[$old_index];
				unset($request[$old_index]);
			}
		}

		/* finally remove any fields */
		foreach ((array)$remove as $index) {
			if (isset($request[$index])) {
				unset($request[$index]);
			}
		}

		/* now create model groups based on the indexes */
		foreach ($request as $index=>$value) {
			if (strpos($index,$separator) !== false) {
				list($model,$field) = explode($separator,$index,2);

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

		/* now process the array elements */
		foreach ($groups as $key=>$value) {
			if (is_array($value)) {
				foreach ($value as $k=>$v) {
					if (substr($k,-2) == '[]') {
						$kk = substr($k,0,-2);
						unset($groups[$key][$k]);
						foreach ($groups[$key] as $aa=>$bb) {
							$groups[$key][$aa][$kk] = $v;
						}
					}
				}
			}
		}

		/* add _model if necessary */
		if ($append_model) {
			foreach ($groups as $model_name=>$value) {
				if (substr($model_name,-strlen($append_model)) != $append_model) {
					$groups[$model_name.$append_model] = $value;

					unset($groups[$model_name]);
				}
			}

			if (is_string($only_index)) {
				$only_index = str_replace($append_model,'',$only_index).$append_model;
			}
		}

		/* do they want the entire group array or just a single part of it? */
		$output_array = ($only_index) ? $groups[$only_index] : $groups;

		/* default method output */
		$responds = $output_array;

		/* if request argument was true then put back into internal request property */
		if ($_request === true) {
			$this->_request = $output_array;

			$responds = $this;
		}

		return $responds;
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

	/*
	http://php.net/manual/en/function.parse-str.php#119484
	*/
	protected function http_parse_query($queryString) {
		$result = [];

		if (!empty($queryString)) {
			$parts = explode('&',$queryString);

			foreach ($parts as $part) {
				list($paramName, $paramValue) = explode('=',$part,2);

				$paramName = urldecode($paramName);
				$paramValue = urldecode($paramValue);

				if (preg_match_all('/\[([^\]]*)\]/m', $paramName, $matches)) {
					$paramName = substr($paramName, 0, strpos($paramName, '['));
					$keys = array_merge([$paramName], $matches[1]);
				} else {
					$keys = [$paramName];
				}

				$target = &$result;

				foreach ($keys as $index) {
					if ($index === '') {
						if (isset($target)) {
							if (is_array($target)) {
								$intKeys = array_filter(array_keys($target), 'is_int');
								$index = count($intKeys) ? max($intKeys)+1 : 0;
							} else {
								$target = [$target];
								$index  = 1;
							}
						} else {
							$target = [];
							$index = 0;
						}
					} elseif (isset($target[$index]) && !is_array($target[$index])) {
						$target[$index] = [$target[$index]];
					}

					$target = &$target[$index];
				}

				if (is_array($target)) {
					$target[] = $paramValue;
				} else {
					$target = $paramValue;
				}
			}
		}

		return $result;
	}

} /* end class */