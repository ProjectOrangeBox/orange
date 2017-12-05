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

	/**
	 * load the required models and setup the user
	 * @private
	 * @author Don Myers
	 */
	public function __construct() {
		ci()->load->model('o_user_model');
		
		/* set some constants */
		define('ADMIN_USER_ID',config('auth.admin user id'));
		define('ADMIN_ROLE_ID',config('auth.admin role id'));

		define('USER_USER_ID',config('auth.user user id'));
		define('USER_ROLE_ID',config('auth.user role id'));

		define('NOBODY_USER_ID',config('auth.nobody user id'));
		define('NOBODY_ROLE_ID',config('auth.nobody role id'));

		/* if this is a cli request we don't need to setup the user profile */
		if (!is_cli()) {
			/* default to nobody */
			$user_id = NOBODY_USER_ID;

			/* load a session saved user id - if any */
			$session_user_id = ci()->session->userdata($this->session_key);
			
			/* is it valid? */
			if ((int) $session_user_id > 0) {
				/* yes set it as the user id */
				$user_id = $session_user_id;
			}

			$this->refresh_userdata($user_id);
		}

		log_message('info', 'Auth Class Initialized');
	}

	/**
	 * Login handler
	 * @author Don Myers
	 * @param  string $email users email address
	 * @param  string $password users password
	 * @return bool
	 */
	public function login($email, $password) {
		$ajax = ci()->input->is_ajax_request();

		$success = $this->_login($email, $password);

		event::trigger('auth.login', $email, $success, $ajax);

		log_message('info', 'Auth Class login');

		return $success;
	}

	/**
	 * Logout handler
	 * @author Don Myers
	 * @return bool
	 */
	public function logout() {
		event::trigger('auth.logout');
		
		/* make them a guest */
		$this->refresh_userdata(USER_ROLE_ID);

		log_message('info', 'Auth Class logout');

		return true;
	}

	/**
	 * Refresh the users data from the database
	 * @author Don Myers
	 * @param  integer [$user_id = null] The Id of the user you want to refresh them as
	 * @return bool
	 */
	public function refresh_userdata($user_id = null) {
		/* get the user id from the user object */
		if (is_object(ci()->user) && $user_id == null) {
			$user_id = ci()->user->id;
		}
		
		/* double check user id is a integer greater than 0 */
		if ((int) $user_id > 0) {
			/* load the profile */
			$profile = ci()->o_user_model->get((int) $user_id);

			ci()->session->set_userdata([$this->session_key => $profile->id]);
		} else {
			$profile = ci()->o_user_model->get(USER_ROLE_ID);
		}

		/* clear password */
		unset($profile->password);

		ci()->user = &$profile;

		return true;
	}

	/**
	 * The Login heavy lifter with all the tests
	 * @private
	 * @author Don Myers
	 * @param  string $email users email address
	 * @param  string $password users password
	 * @return bool
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