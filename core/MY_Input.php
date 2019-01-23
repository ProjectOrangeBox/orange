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
	protected $request_type = 'html';
	protected $stash_key = '_input_stash_';
	protected $stash_hash_key = '_stash_hash_key_';

	public function __construct() {
		/* grab raw input for patch and such */
		$this->_raw_input_stream = file_get_contents('php://input');

		/* try to parse the input */
		parse_str($this->_raw_input_stream,$this->_request);

		/* did we get anything? if not fall back to the posted input */
		if (!count($this->_request)) {
			$this->_request = $_POST;
		}

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
	 * set_request
	 * replace or add input data
	 *
	 * @param string $index input parameter name
	 * @param mixed $replace_value value
	 *
	 * @return $this
	 *
	 * @examples set_request('name','Dr Pepper')
	 */
	public function set_request($index = null, $replace_value = null) {
		if (is_array($index) && $replace_value === true) {
			$this->_request = $index;
		} elseif (is_array($index)) {
			foreach ($index as $i=>$v) {
				$this->set_request($i,$v);
			}
		} else {
			$this->_request[$index] = $replace_value;
		}

		return $this;
	}

	/* return boolean success */
	public function valid($key,$rules='',$human=null)
	{
		if (is_array($key)) {
			foreach ($key as $k=>$r) {
				if (is_array($r)) {
					/**
					 * Key, Rule (1), Human (0)
					 * 'field_age'=>['Age','int|md5']]
					 * 'name'=>'int'
					 */
					$this->valid($k,$r[1],$r[0]);
				} else {
					/**
					 * Key, Rule
					 */
					$this->valid($k,$r);
				}
			}

			return ci('validate')->success();
		}

		$field = $this->request($key);

		ci('validate')->single($rules, $field, $human);

		/* return the value or allow chain-ing */
		return ci('validate')->success();
	}

	/* filter and replace */
	public function filter($key=null,$rules='')
	{
		if (is_array($key)) {
			foreach ($key as $k=>$r) {
				$this->filter($k,$r);
			}

			return $this;
		}

		$field = $this->request($key);

		ci('validate')->single($rules, $field);

		$this->_request[$key] = $field;

		return $this;
	}

	/* return filtered value but do not replace */
	public function filtered($key=null,$rules='')
	{
		if (is_array($key)) {
			$return = [];

			foreach ($key as $k=>$r) {
				$return[$k] = $this->filtered($k,$r);
			}

			return $return;
		}

		$field = $this->request($key);

		ci('validate')->single($rules, $field);

		/* return the value or allow chain-ing */
		return $field;
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
	 * @param string $only_index only return the model provided
	 * @param string $separator if this is present then treat each array index as a parent + child pair
	 * @param boolean $append_model should "_model" be append to the array indexes
	 * @param mixed $_request associated array index=>value - or - TRUE to use servers passed form data
	 *
	 * @return array
	 *
	 * if $_request is true the input is processed and placed back into client input
	 * if $_request is false the input is processed then a array is returned
	 * if $_request is an array then a array is returned
	 *
	 * it is possible to append [] (brackets) after the field name in the
	 * copy or move array in order to copy or move the value to the output of a array if needed
	 * $copy = ['roles|parent_id[]'=>'id'];
	 * This would copy the id to each role index
	 *
	 *
	 * <input type="hidden" name="repeatable|id[]" value="<?=$id ?>">
	 * <input type="hidden" name="repeatable|parent_id[]" value="<?=$parent_id ?>">
	 * <input type="text" class="form-control" name="repeatable|firstname][]" value="<?=$firstname ?>">
	 * <input type="text" class="form-control" name="repeatable|lastname][]" value="<?=$lastname ?>">
	 *
	 *
	 * $post = [
	 *   'id' => 89,
	 *   'name' => 'Johnny Appleseed',
	 *   'number' => 21,
	 *   'remove' => 'foobar',
	 *   'repeatable' => [
	 *     0 => [
	 *       'id' => 45,
	 *       'firstname' => 'Johnny',
	 *       'lastname' => 'Appleseed',
	 *       'checkers' => 0,
	 *     ],
	 *     1 => [
	 *       'id' => 78,
	 *       'firstname' => 'Don',
	 *       'lastname' => 'Jones',
	 *       'checkers' => 1,
	 *     ],
	 *     2 => [
	 *       'id' => 83,
	 *       'firstname' => 'Frank',
	 *       'lastname' => 'Peters',
	 *       'checkers' => 1,
	 *     ],
	 *   ],
	 * ];
	 *
	 * $copy, $move, $remove, $default_model, $only_index, $separator, $append_model, $_request
	 * $formatted = $this->input->request_remap(['fullname'=>'name'],['age'=>'number'],['remove'],'name',false,'|',true,$post);
	 *
	 */
	public function request_remap($copy=[],$move=[],$remove=[],$default_model='#',$only_index=null,$separator='|',$append_model=false,$_request=[]) {
		/* use form request or what was sent in? */
		if (is_bool($_request)) {
			/* if it's true or false use the form request data */
			$request = $this->_request;
		} elseif (is_array($_request)) {
			/* if it's a array then use what they sent in */
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

		/* default method output is our "new" array */
		$responds = $output_array;

		/* if request argument was true then put back into internal request property */
		if ($_request === true) {
			$this->_request = $output_array;

			/* switch the output to "this" to allow further chaining */
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
		$value = $this->_fetch_from_array($_COOKIE, $index, false);

		$value = ($value === null) ? $default : $value;

		return ($xss_clean) ? $this->security->xss_clean($value) : $value;
	}

	public function set_request_type($request_type)
	{
		/* options include cli, ajax, html */
		if (!in_array($request_type,['cli','ajax','html'])) {
			throw new Exception(__METHOD__.' unknown type '.$request_type.'.');
		}
		
		$this->request_type = $request_type;
		
		return $this;
	}

	public function is_ajax_request()
	{
		return ($this->request_type == 'ajax') ? true : (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	// --------------------------------------------------------------------

	/**
	 * Is CLI request?
	 *
	 * Test to see if a request was made from the command line.
	 *
	 * @deprecated	3.0.0	Use is_cli() instead
	 * @return	bool
	 */
	public function is_cli_request()
	{
		return ($this->request_type == 'cli') ? true : is_cli();
	}

	/**
	 * stash
	 * stash the request data
	 *
	 * @return
	 *
	 */
	public function stash() {
		$stash = $this->_request;

		/* is there even an array to store? */
		if (is_array($stash)) {
			/* put a simple checksum on this */
			$stash[$this->stash_hash_key] = md5(json_encode($stash).config('config.encryption_key'));

			ci('session')->set_tempdata($this->stash_key,$stash,3600); /* defaults to 10 minutes */
		}

		return $this;
	}

	/**
	 * unstash
	 * load the request from the stashed data
	 *
	 * @return
	 *
	 */
	public function unstash() {
		$success = false;

		$stashed = ci('session')->tempdata($this->stash_key);

		ci('session')->unset_tempdata($this->stash_key);

		if (is_array($stashed)) {
			$stored_key = $stashed[$this->stash_hash_key];

			unset($stashed[$this->stash_hash_key]);

			$check_key = md5(json_encode($stashed).config('config.encryption_key'));

			if ($check_key == $stored_key) {
				$success = true;
				$this->_request = $stashed;
			}
		}

		return $success;
	}

} /* end class */
