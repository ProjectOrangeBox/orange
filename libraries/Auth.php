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

class Auth {
	protected $session_key = 'user::data';

	public function __construct() {
		ci()->load->model(['o_permission_model','o_role_model','o_user_model']);

		define('ADMIN_ROLE_ID',config('auth.admin role id'));
		define('NOBODY_USER_ID',config('auth.nobody user id'));

		if (!is_cli()) {
			$this->refresh_userdata((int)ci()->session->userdata($this->session_key));
		}

		log_message('info', 'Auth Class Initialized');
	}

	public function login($email, $password) {
		$success = $this->_login($email, $password);

		event::trigger('auth.login', $email, $success);

		log_message('info', 'Auth Class login');

		return $success;
	}

	public function logout() {
		event::trigger('auth.logout');

		$this->refresh_userdata(NOBODY_USER_ID);

		log_message('info', 'Auth Class logout');

		return true;
	}

	public function refresh_userdata($user_id) {
		log_message('debug', 'Auth::refresh_userdata::'.$user_id);

		$user_id = ((int)$user_id > 0) ? (int)$user_id : NOBODY_USER_ID;
		$profile = ci()->o_user_model->get($user_id);

		if ((int)$profile->is_active == 1 && $profile instanceof O_user_entity) {
			unset($profile->password);
			ci()->user = &$profile;
			ci()->session->set_userdata([$this->session_key => $profile->id]);
		} else {
			$this->refresh_userdata(NOBODY_USER_ID);
		}

		log_message('info', 'Auth Class Refreshed');
	}

	protected function _login($login, $password) {
		if ((strlen(trim($login)) == 0) or (strlen(trim($password)) == 0)) {
			errors::add(config('auth.empty fields error'));
			log_message('debug', 'auth->user '.config('auth.empty fields error'));
			return false;
		}

		event::trigger('user.login.init', $login);

		if (!$user = ci()->o_user_model->get_user_by_email($login)) {
			log_message('debug', 'Auth Get User by email returned NULL');
			errors::add(config('auth.general failure error'));
			return false;
		}

		if (!($user instanceof O_user_entity)) {
			log_message('debug', 'Auth $user not an object');
			errors::add(config('auth.general failure error'));
			return false;
		}

		if ((int) $user->id === 0) {
			log_message('debug', 'Auth $user->id is 0 (no users id is 0)');
			errors::add(config('auth.general failure error'));
			return false;
		}

		if (password_verify($password, $user->password) !== true) {
			event::trigger('user.login.fail', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			errors::add(config('auth.general failure error'));
			return false;
		}

		if ((int) $user->is_active == 0) {
			event::trigger('user.login.in active', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			errors::add(config('auth.general failure error'));
			return false;
		}

		$this->refresh_userdata($user->id);

		return true;
	}

} /* end file */
