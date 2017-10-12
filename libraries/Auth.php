<?php
/**
 * This package has been extended and modified
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * @based on Tank_auth (http://konyukhov.com/soft/)
 * @based on DX Auth by Dexcell (http://dexcell.shinsengumiteam.com/dx_auth)
 *
 * required
 * core: session, load, input,
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class Auth {
	protected $session_key = 'user::data';

	public function __construct() {
		ci()->load->model('o_user_model');

		/* if this is a cli request we don't need to setup the user profile */
		if (!is_cli()) {
			$user_id = config('auth.guest role id');

			$session_user_id = ci()->session->userdata($this->session_key);

			if ((int) $session_user_id > 0) {
				$user_id = $session_user_id;
			}

			$this->refresh_userdata($user_id);
		}

		log_message('info', 'Auth Class Initialized');
	}

	/**
	 * login function.
	 * 
	 * @access public
	 * @param mixed $email
	 * @param mixed $password
	 * @return void
	 */
	public function login($email, $password) {
		$ajax = ci()->input->is_ajax_request();

		$success = $this->_login($email, $password);

		event::trigger('auth.login', $email, $success, $ajax);

		log_message('info', 'Auth Class login');

		return $success;
	}

	/**
	 * logout function.
	 * 
	 * @access public
	 * @return void
	 */
	public function logout() {
		event::trigger('auth.logout');

		$this->refresh_userdata(config('auth.guest role id'));

		log_message('info', 'Auth Class logout');

		return true;
	}

	/**
	 * refresh_userdata function.
	 * 
	 * @access public
	 * @param mixed $user_id (default: null)
	 * @return void
	 */
	public function refresh_userdata($user_id = null) {
		if (is_object(ci()->user) && $user_id == null) {
			$user_id = ci()->user->id;
		}

		if ((int) $user_id > 0) {
			$profile = ci()->o_user_model->get((int) $user_id);

			ci()->session->set_userdata([$this->session_key => $profile->id]);
		} else {
			$profile = ci()->o_user_model->get((int) config('auth.guest role id'));
		}

		ci()->user = &$profile;

		return true;
	}

	/**
	 * _login function.
	 * 
	 * @access protected
	 * @param mixed $login
	 * @param mixed $password
	 * @return void
	 */
	protected function _login($login, $password) {
		/* TEST -- did they send anything in? */
		if ((strlen(trim($login)) == 0) or (strlen(trim($password)) == 0)) {
			errors::add(config('auth.empty fields error'));

			log_message('debug', 'auth->user ' . config('auth.empty fields error'));

			return false;
		}

		/* basic trigger to let listeners know we are trying to init login */
		event::trigger('user.login.init', $login);

		/* TEST -- ok does this users email exists? */
		if (is_null($user = ci()->o_user_model->get_user_by_email($login))) {
			log_message('debug', 'Auth Get User by email returned NULL');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- another safety check - is user a object */
		if (!is_object($user)) {
			log_message('debug', 'Auth $user not an object');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- another safety check - is the user id a integer less than 1 */
		if ((int) $user->id === 0) {
			log_message('debug', 'Auth $user->id is 0 (no users id is 0)');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- OK check users password with the built in password hasher */
		if (password_verify($password, $user->password) !== true) {
			/* this is the real password wrong error */
			event::trigger('user.login.fail', $login);

			log_message('debug', 'auth->user Incorrect Login and/or Password');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- Ok login looks good but is the user active? */
		if ((int) $user->is_active == 0) {
			/* this is the real password wrong error */
			event::trigger('user.login.in active', $login);

			errors::add(config('auth.account not active error'));

			log_message('debug', 'auth->user ' . config('auth.account not active error'));

			return false;
		}

		/* attach it to CI */
		$this->refresh_userdata($user->id);

		return true;
	}

} /* end class */