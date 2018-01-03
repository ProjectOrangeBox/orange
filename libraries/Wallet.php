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
 * libraries: event
 * models:
 * helpers:
 * functions:
 *
 */

class Wallet {
	protected $redirect_messages = [];
	protected $request           = [];
	protected $msg_key         = 'internal::wallet::msg';
	protected $stash_key       = 'internal::wallet::stash';
	protected $default_msgs = [
		'success' => 'Request Completed',
		'failed'  => 'Request Failed',
		'denied'  => 'Access Denied',
		'created' => 'Record Created',
		'updated' => 'Record Updated',
		'deleted' => 'Record Deleted',
	];
	public function __construct() {
		ci('load')->vars(['wallet_messages' => [
			'messages'       => ci('session')->flashdata($this->msg_key),
			'initial_pause'  => config('wallet.initial_pause', 3),
			'pause_for_each' => config('wallet.pause_for_each', 1000),
		]]);

		log_message('info', 'Wallet Class Initialized');
	}

	public function pocket($name, $value = null) {
		$return = $this;

		if ($value) {
			$this->request[$name] = $value;
		} else {
			$return = (isset($this->request[$name])) ? $this->request[$name] : null;
		}

		return $return;
	}

	public function snapdata($newdata = null, $newval = null) {
		$newdata = (is_array($newdata)) ? $newdata : [$newdata => $newval];

		ci('session')->set_tempdata($newdata, null, 3600);

		return $this;
	}

	public function get_snapdata($key) {
		$data = ci('session')->tempdata($key);

		ci('session')->unset_tempdata($key);

		return $data;
	}

	public function keep_snapdata($key) {
		return ci('session')->tempdata($key);
	}

	public function msg($msg = '', $type = 'yellow', $redirect = null) {
		$sticky = ($type == 'red' || $type == 'danger' || $type == 'warning' || $type == 'yellow');

		event::trigger('wallet.msg', $msg, $type, $sticky, $redirect);

		if (is_string($redirect) || $redirect === true) {
			$redirect = (is_string($redirect)) ? $redirect : ci('input')->server('HTTP_REFERER');
			$this->redirect_messages[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			ci('session')->set_flashdata($this->msg_key, $this->redirect_messages);

			redirect($redirect);
		} else {
			$wallet_messages = ci('load')->get_var('wallet_messages');
			$current_msgs = (array) $wallet_messages['messages'];
			$current_msgs[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			ci('load')->vars(['wallet_messages' => [
				'messages'       => $current_msgs,
				'initial_pause'  => config('wallet.initial_pause', 3),
				'pause_for_each' => config('wallet.pause_for_each', 1000),
			]]);
		}

		return $this;
	}

	public function stash() {
		$this->snapdata($this->stash_key, ci('input')->request());

		return $this;
	}

	public function unstash() {
		$stashed = $this->get_snapdata($this->stash_key);

		$_POST = (is_array($stashed)) ? $stashed : [];

		return $stashed;
	}

} /* end file */
