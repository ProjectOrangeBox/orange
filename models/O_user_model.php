<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class o_user_model extends Database_model {
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

	public function __construct() {
		$this->table = config('auth.user table');

		parent::__construct();

		$this->validate->attach('user_password', function (&$field, &$param, &$error_string, &$field_data, &$validate) {
			$error_string = 'Your password is not in the correct format.';

			return (bool) preg_match(config('auth.password regex'), $field);
		});

		log_message('info', 'o_user_model Class Initialized');
	}

	public function insert($data) {
		$this->_password_check('insert',$data);

		if (!ci('errors')->has()) {
			return parent::insert($data);
		}
	}

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

			$this->only_columns_with_rules($data)->add_rule_set_columns($data,$which)->validate($data);

			$data['password'] = $this->hash_password($data['password']);
		}
	}

	public function delete($user_id) {
		parent::delete($user_id);

		if (!ci('errors')->has()) {
			$this->update_by(['is_active'=>0],['id'=>$user_id]);

			$this->remove_role($user_id);
		}
	}

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

	public function roles($user_id) {
		$dbc = $this->_database
			->from(config('auth.user role table'))
			->join(config('auth.role table'), config('auth.role table').'.id = '.config('auth.user role table').'.role_id')
			->where(['user_id' => (int) $user_id])
			->get();
		return ($dbc->num_rows() > 0) ? $dbc->result() : [];
	}

	public function hash_password($password) {
		$password_info = password_get_info($password);

		if ($password_info['algo'] == 0) {
			$password = password_hash($password, PASSWORD_DEFAULT);
		}

		return $password;
	}

	public function get_user_by_login($login) {
		return $this->where('LOWER(username)=', strtolower($login))->or_where('LOWER(email)=', strtolower($login))->set_temp_return_on_single(false)->_get(false);
	}

	public function get_user_by_username($username) {
		return $this->where('LOWER(username)=', strtolower($username))->set_temp_return_on_single(false)->_get(false);
	}

	public function get_user_by_email($email) {
		return $this->where('LOWER(email)=', strtolower($email))->set_temp_return_on_single(false)->_get(false);
	}

	public function password($password) {
		$this->validate->single($this->rules['password']['rules'], $password);

		return ci('errors')->has();
	}

	public function _find_role_id($role) {
		return (int) ((int) $role > 0) ? $role : $this->o_role_model->column('id')->get_by(['name' => $role]);
	}

	public function _find_permission_id($permission) {
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}

} /* end file */
