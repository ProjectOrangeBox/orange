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

class Wallet {
	protected $redirect_messages = [];
	protected $request           = [];
	protected $msg_key         = 'internal::wallet::msg';
	protected $snap_key_prefix = 'internal::wallet::snap::';
	protected $breadcrumb_key  = 'internal::wallet::breadcrumbs';
	protected $stash_key       = 'internal::wallet::stash';
	protected $default_breadcrumb_style = [
		'crumb_divider'   => '<span class="divider"> / </span>',
		'tag_open'        => '<ul class="breadcrumb">',
		'tag_close'       => '</ul>',
		'crumb_open'      => '<li>',
		'crumb_last_open' => '<li class="active">',
		'crumb_close'     => '</li>',
	];
	protected $default_msgs = [
		'success' => 'Request Completed',
		'failed'  => 'Request Failed',
		'denied'  => 'Access Denied',
		'created' => 'Record Created',
		'updated' => 'Record Updated',
		'deleted' => 'Record Deleted',
	];

	protected $initial_pause;
	protected $pause_for_each;

	public function __construct() {
		ci()->load->library('session');

		ci()->load->vars(['wallet_messages' => [
			'messages'       => ci()->session->flashdata($this->msg_key),
			'initial_pause'  => config('wallet.initial_pause', 3),
			'pause_for_each' => config('wallet.pause_for_each', 1000),
		]]);

		$default_breadcrumb_style = config('wallet.default_breadcrumb_style', null);

		if (is_array($default_breadcrumb_style)) {
			$this->default_breadcrumb_style = $default_breadcrumb_style;
		}

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

	public function snapdata($newdata = [], $newval = '') {
		if (is_string($newdata)) {
			$newdata = [$newdata => $newval];
		}

		if (count($newdata) > 0) {
			foreach ($newdata as $key => $val) {
				ci()->session->set_userdata($this->snap_key_prefix.$key, $val);
			}
		}

		return $this;
	}

	public function get_snapdata($key) {
		$data = ci()->session->userdata($this->snap_key_prefix.$key);

		ci()->session->unset_userdata($this->snap_key_prefix.$key);

		return $data;
	}

	public function keep_snapdata($key) {
		return ci()->session->userdata($this->snap_key_prefix.$key);
	}

	public function breadcrumb($page = null, $href = null) {
		if ($page && $href) {
			$breadcrumbs = ci()->session->userdata($this->breadcrumb_key);

			$breadcrumbs[] = ['page' => $page, 'href' => $href];

			ci()->session->set_userdata($this->breadcrumb_key, $breadcrumbs);
		}

		return $this;
	}

	public function breadcrumbs() {
		return ci()->session->userdata($this->breadcrumb_key);
	}

	public function eat_breadcrumbs() {
		ci()->session->unset_userdata($this->breadcrumb_key);

		return $this;
	}

	public function eat_breadcrumb($which = 'last') {
		$breadcrumbs = ci()->session->userdata($this->breadcrumb_key);

		$crumb = ($which == 'first') ? array_shift($breadcrumbs) : array_pop($breadcrumbs);

		ci()->session->set_userdata($this->breadcrumb_key, $breadcrumbs);

		return $crumb;
	}

	public function breadcrumbs_as_html($style = []) {
		$style = array_merge($this->default_breadcrumb_style, $style);
		$breadcrumbs = $this->breadcrumbs();
		$output      = '';

		if (is_array($breadcrumbs)) {
			$output .= $style['tag_open'];

			foreach ($breadcrumbs as $key => $crumb) {
				$keys = array_keys($breadcrumbs);

				if (end($keys) == $key) {
					$output .= $style['crumb_last_open'].$crumb['page'].$style['crumb_close'];
				} else {
					$output .= $style['crumb_open'].'<a href="'.$crumb['href'].'">'.$crumb['page'].'</a>'.$style['crumb_divider'].$style['crumb_close'];
				}
			}

			$output .= $style['tag_close'];
		}

		return $output;
	}

	public function msg($msg = '', $type = 'yellow', $redirect = null) {
		$sticky = ($type == 'red' || $type == 'danger' || $type == 'warning' || $type == 'yellow');
		event::trigger('wallet.msg', $msg, $type, $sticky, $redirect);

		if (is_string($redirect) || $redirect === true) {
			$redirect = (is_string($redirect)) ? $redirect : ci()->input->server('HTTP_REFERER');
			$this->redirect_messages[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			ci()->session->set_flashdata($this->msg_key, $this->redirect_messages);

			redirect($redirect);
		} else {
			$wallet_messages = ci()->load->get_var('wallet_messages');
			$current_msgs = (array) $wallet_messages['messages'];
			$current_msgs[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			ci()->load->vars(['wallet_messages' => [
				'messages'       => $current_msgs,
				'initial_pause'  => config('wallet.initial_pause', 3),
				'pause_for_each' => config('wallet.pause_for_each', 1000),
			]]);
		}

		return $this;
	}

	public function stash() {
		$this->snapdata($this->stash_key, ci()->input->request());

		return $this;
	}

	public function unstash() {
		$stashed = $this->get_snapdata($this->stash_key);

		$_POST = (is_array($stashed)) ? $stashed : [];

		return $stashed;
	}

} /* end file */