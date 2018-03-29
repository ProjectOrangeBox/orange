<?php
/**
 * O_user_model
 * Insert description here
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
class O_user_model extends Database_model {
	protected $table;
	protected $additional_cache_tags = '.acl';
	protected $has_roles = true;
	protected $has_stamps = true;
	protected $has_soft_delete = true;
	protected $entity = true;
	protected $rules = [
		'id'              => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'username'        => ['field' => 'username', 'label' => 'User Name', 'rules' => 'required|trim|is_uniquem[o_user_model.username.id]'],
		'password'        => ['field' => 'password', 'label' => 'Password', 'rules' => 'required|max_length[255]|filter_input[255]'],
		'email'           => ['field' => 'email', 'label' => 'Email', 'rules' => 'required|trim|strtolower|valid_email|is_uniquem[o_user_model.email.id]|max_length[255]|filter_input[255]'],
		'is_active'       => ['field' => 'is_active', 'label' => 'Active', 'rules' => 'if_empty[0]|in_list[0,1]|filter_int[1]|max_length[1]|less_than[2]'],
		'user_read_role_id'    => ['field' => 'user_read_role_id', 'label' => 'User Read Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'user_edit_role_id'    => ['field' => 'user_edit_role_id', 'label' => 'User Edit Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'user_delete_role_id'  => ['field' => 'user_delete_role_id', 'label' => 'User Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
	];

/**
 * __construct
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function __construct() {
		$this->table = config('auth.user table');

		parent::__construct();

		$this->validate->attach('user_password', function (&$field, &$param, &$error_string, &$field_data, &$validate) {
			$error_string = 'Your password is not in the correct format.';
			return (bool) preg_match(config('auth.password regex'), $field);
		});

		log_message('info', 'o_user_model Class Initialized');
	}

/**
 * insert
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function insert($data) {
		$this->_password_check('insert',$data);
		if (!ci('errors')->has()) {
			return parent::insert($data);
		}
	}

/**
 * update
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function update($data) {
		if (!empty($data['password'])) {
			$this->_password_check('update',$data);
		} else {
			unset($data['password']);
		}
		if (!ci('errors')->has()) {
			return parent::update($data);
		}
	}

/**
 * _password_check
 * Insert description here
 *
 * @param $which
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _password_check($which,&$data) {
		$password_info = password_get_info($data['password']);
		if ($password_info['algo'] == 0) {
			if ($data['password'] != $data['confirm_password']) {
				ci('errors')->add('Passwords do not match.');
			}
			$this->rules['password']['rules'] .= '|user_password';
			if ($which == 'insert') {
				unset($data['id']);
			}
			$this->only_columns($data,$this->rules)->add_rule_set_columns($data,$which)->validate($data);
			$data['password'] = $this->hash_password($data['password']);
		}
	}

/**
 * delete
 * Insert description here
 *
 * @param $user_id
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function delete($user_id) {
		parent::delete($user_id);
		if (!ci('errors')->has()) {
			$this->update_by(['is_active'=>0],['id'=>$user_id]);
			$this->remove_role($user_id);
		}
	}

/**
 * add_role
 * Insert description here
 *
 * @param $user_id
 * @param $role
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function add_role($user_id, $role) {
		if ((int) $user_id < 0) {
			throw new Exception(__METHOD__.' please provide a integer for the user id');
		}
		if (is_array($role)) {
			foreach ($role as $role_id) {
				$this->add_role($user_id, $role_id);
			}
			return;
		}
		return $this->_database->replace(config('auth.user role table'), ['role_id' => (int) $this->_find_role_id($role), 'user_id' => (int) $user_id]);
	}

/**
 * remove_role
 * Insert description here
 *
 * @param $user_id
 * @param $role
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function remove_role($user_id, $role = null) {
		if ((int) $user_id < 0) {
			throw new Exception(__METHOD__.' please provide a integer for the user id');
		}
		if (is_array($role)) {
			foreach ($role as $role_id) {
				$this->remove_role($user_id, $role_id);
			}
			return;
		}
		if ($role === null) {
			$this->_database->delete(config('auth.user role table'), ['user_id' => (int) $user_id]);
			return;
		}
		return $this->_database->delete(config('auth.user role table'), ['user_id' => (int) $user_id, 'role_id' => (int) $this->_find_role_id($role)]);
	}

/**
 * roles
 * Insert description here
 *
 * @param $user_id
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function roles($user_id) {
		$dbc = $this->_database
			->from(config('auth.user role table'))
			->join(config('auth.role table'), config('auth.role table').'.id = '.config('auth.user role table').'.role_id')
			->where(['user_id' => (int) $user_id])
			->get();
		return ($dbc->num_rows() > 0) ? $dbc->result() : [];
	}

/**
 * hash_password
 * Insert description here
 *
 * @param $password
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function hash_password($password) {
		$password_info = password_get_info($password);
		if ($password_info['algo'] == 0) {
			$password = password_hash($password, PASSWORD_DEFAULT);
		}
		return $password;
	}

/**
 * get_user_by_login
 * Insert description here
 *
 * @param $login
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function get_user_by_login($login) {
		return $this->where('LOWER(username)=', strtolower($login))->or_where('LOWER(email)=', strtolower($login))->on_empty_return(false)->_get(false);
	}

/**
 * get_user_by_username
 * Insert description here
 *
 * @param $username
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function get_user_by_username($username) {
		return $this->where('LOWER(username)=', strtolower($username))->on_empty_return(false)->_get(false);
	}

/**
 * get_user_by_email
 * Insert description here
 *
 * @param $email
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function get_user_by_email($email) {
		return $this->where('LOWER(email)=', strtolower($email))->on_empty_return(false)->_get(false);
	}

/**
 * password
 * Insert description here
 *
 * @param $password
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function password($password) {
		$this->validate->single($this->rules['password']['rules'], $password);
		return ci('errors')->has();
	}

/**
 * _find_role_id
 * Insert description here
 *
 * @param $role
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function _find_role_id($role) {
		return (int) ((int) $role > 0) ? $role : $this->o_role_model->column('id')->get_by(['name' => $role]);
	}

/**
 * _find_permission_id
 * Insert description here
 *
 * @param $permission
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function _find_permission_id($permission) {
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}
}
