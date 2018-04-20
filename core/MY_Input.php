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
	 * this makes it easier to pass returned array into a models
	 * or to provide additional processing
	 *
	 * @param array $copy associated array new index=>old index (copy) - preformed first
	 * @param array $move associated array new index=>old index (move) - preformed second
	 * @param array $remove indexes based on array index - preformed last
	 * @param string $default_model the name of the default model (where separator isn't present)
	 * @param string $only_index only return this model
	 * @param string $separator if this is present then treat each array index as a model + field pair
	 * @param boolean $append_model should "_model" be append to the array indexs
	 * @param array $_request form's index=>value associated array
	 *
	 * @return array
	 *
	 * it is possible to append [] (brackets) after the index name in the
	 * copy or move array in order to copy or move the value to the output of a array if needed
	 * $copy = ['roles[].parent_id'=>'id'];
	 * This would copy the id into the role index
	 *
	 */
	public function request_process($copy=[],$move=[],$remove=[],$default_model='#',$only_index=null,$separator='.',$append_model=false,$_request=[]) {
		/* use form request or what was sent in? */
		$request = ($_request) ? $_request : $this->_request;

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
			if (substr($key,-2) == '[]') {
				foreach ((array)$value as $i=>$v) {
					$child_index = substr($key,0,-2);

					if (is_array($groups[$child_index])) {
						foreach ($groups[$child_index] as $ii=>$child_record) {
							if (is_array($groups[$child_index][$ii])) {
								$groups[$child_index][$ii] = $child_record + [$i=>$v];
							} else {
								$groups[$child_index][$i] = $v;
							}
						}
					}

					unset($groups[$key]);
				}
			}
		}

		/* add _model if necessary */
		if ($append_model) {
			foreach ($groups as $model_name=>$value) {
				if (substr($model_name,-6) != '_model') {
					$groups[$model_name.'_model'] = $value;

					unset($groups[$model_name]);
				}
			}

			if (is_string($only_index)) {
				$only_index = str_replace('_model','',$only_index).'_model';
			}
		}

		/* do they want everything or just 1 */
		return ($only_index) ? $groups[$only_index] : $groups;
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
