<?php
/**
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * NOTE: Some of the ideas and/or code for the Wallet methods
 * are from various projects
 * Unfortneulty I did not keep detailed records of where a
 * idea and/or code may have came from
 * If you see a bit of code and have a public repro
 * which you are the maintainer of and can provide me a direct
 * link I will add credit where credit is due!
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

	/* used for flash messages */
	protected $initial_pause;
	protected $pause_for_each;

	public function __construct() {
		ci()->load->library('session');

		/* are there any flash msgs in the session from the last page? */
		ci()->load->vars(['wallet_messages' => [
			'messages'       => ci()->session->flashdata($this->msg_key),
			'initial_pause'  => config('wallet.initial_pause', 3),
			'pause_for_each' => config('wallet.pause_for_each', 1000),
		]]);

		/* any bread crumbs setup? */
		$default_breadcrumb_style = config('wallet.default_breadcrumb_style', null);

		if (is_array($default_breadcrumb_style)) {
			$this->default_breadcrumb_style = $default_breadcrumb_style;
		}

		log_message('info', 'Wallet Class Initialized');
	}

	/*
	Page Request Shared Storage

	This data is lost between page requests
	 */
	/**
	 * pocket function.
	 *
	 * @access public
	 * @param mixed $name
	 * @param mixed $value (default: null)
	 * @return void
	 */
	public function pocket($name, $value = null) {
		$return = $this;

		if ($value) {
			$this->request[$name] = $value;
		} else {
			$return = (isset($this->request[$name])) ? $this->request[$name] : null;
		}

		return $return;
	}

	/**
	 * Add or change snapdata
	 * Snap Data is available
	 * until it's read
	 *
	 * @param	mixed
	 * @param	string
	 * @return void
	 */
	public function snapdata($newdata = [], $newval = '') {
		if (is_string($newdata)) {
			$newdata = [$newdata => $newval];
		}

		if (count($newdata) > 0) {
			foreach ($newdata as $key => $val) {
				ci()->session->set_userdata($this->snap_key_prefix . $key, $val);
			}
		}

		return $this;
	}

	/**
	 * Fetch a specific snapdata item from the session array
	 * removed after read
	 *
	 * @param	string
	 * @return string
	 */
	public function get_snapdata($key) {
		/* read the snap data */
		$data = ci()->session->userdata($this->snap_key_prefix . $key);

		/* unset/remove the snap data */
		ci()->session->unset_userdata($this->snap_key_prefix . $key);

		/* return the snap data */
		return $data;
	}

	/**
	 * Fetch a specific snapdata item from the session array
	 * DO NOT removed after read
	 *
	 * @param	string
	 * @return string
	 */
	public function keep_snapdata($key) {
		/* read and return the snap data */
		return ci()->session->userdata($this->snap_key_prefix . $key);
	}

	/* append a new bread crumb on the end */
	/**
	 * breadcrumb function.
	 *
	 * @access public
	 * @param mixed $page (default: null)
	 * @param mixed $href (default: null)
	 * @return void
	 */
	public function breadcrumb($page = null, $href = null) {
		/* no page or href provided */
		if ($page && $href) {
			/* get the previous */
			$breadcrumbs = ci()->session->userdata($this->breadcrumb_key);

			/* push new one on breadcrumb */
			$breadcrumbs[] = ['page' => $page, 'href' => $href];

			/* save again */
			ci()->session->set_userdata($this->breadcrumb_key, $breadcrumbs);
		}

		return $this;
	}

	/* return all bread crumbs */
	/**
	 * breadcrumbs function.
	 *
	 * @access public
	 * @return void
	 */
	public function breadcrumbs() {
		return ci()->session->userdata($this->breadcrumb_key);
	}

	/* clear all breadcrumbs */
	/**
	 * eat_breadcrumbs function.
	 *
	 * @access public
	 * @return void
	 */
	public function eat_breadcrumbs() {
		ci()->session->unset_userdata($this->breadcrumb_key);

		return $this;
	}

	/* pop a bread crumb off the end and return it */
	/**
	 * eat_breadcrumb function.
	 *
	 * @access public
	 * @param string $which (default: 'last')
	 * @return void
	 */
	public function eat_breadcrumb($which = 'last') {
		/* get the previous */
		$breadcrumbs = ci()->session->userdata($this->breadcrumb_key);

		if ($which == 'first') {
			/* shift off the first */
			$crumb = array_shift($breadcrumbs);
		} else {
			/* pop off the last */
			$crumb = array_pop($breadcrumbs);
		}

		/* save again */
		ci()->session->set_userdata($this->breadcrumb_key, $breadcrumbs);

		return $crumb;
	}

	/**
	 * breadcrumbs_as_html function.
	 *
	 * @access public
	 * @param mixed $style (default: [])
	 * @return void
	 */
	public function breadcrumbs_as_html($style = []) {
		$style = array_merge($this->default_breadcrumb_style, $style);

		$breadcrumbs = $this->breadcrumbs();
		$output      = '';

		if (is_array($breadcrumbs)) {
			/* set output variable */
			$output .= $style['tag_open'];

			/* construct output */
			foreach ($breadcrumbs as $key => $crumb) {
				$keys = array_keys($breadcrumbs);

				/* if it's the last use last opening */
				if (end($keys) == $key) {
					$output .= $style['crumb_last_open'] . $crumb['page'] . $style['crumb_close'];
				} else {
					$output .= $style['crumb_open'] . '<a href="' . $crumb['href'] . '">' . $crumb['page'] . '</a>' . $style['crumb_divider'] . $style['crumb_close'];
				}
			}

			/* return output */
			$output .= $style['tag_close'];
		}

		/* no crumbs */
		return $output;
	}

	/*
	flash messages on redirect

	bootstrap:
	success, info, warning, danger
	green, blue, yellow, red
	 */
	/**
	 * msg function.
	 *
	 * @access public
	 * @param string $msg (default: '')
	 * @param string $type (default: 'yellow') [red|green|yellow|blue]
	 * @param mixed $redirect (default: null)
	 * @return void
	 */
	public function msg($msg = '', $type = 'yellow', $redirect = null) {
		$sticky = ($type == 'red' || $type == 'danger' || $type == 'warning' || $type == 'yellow');

		event::trigger('wallet.msg', $msg, $type, $sticky, $redirect);

		if (is_string($redirect) || $redirect === true) {
			$redirect = ($redirect) ? $redirect : ci()->input->server('HTTP_REFERER');

			$this->redirect_messages[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			ci()->session->set_flashdata($this->msg_key, $this->redirect_messages);

			redirect($redirect);
		} else {
			/* get the current page variable messages */
			$wallet_messages = ci()->load->get_var('wallet_messages');

			$current_msgs = (array) $wallet_messages['messages'];

			/* add our new current page message */
			$current_msgs[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			/* put it back into the current page variable */
			ci()->load->vars(['wallet_messages' => [
				'messages'       => $current_msgs,
				'initial_pause'  => config('wallet.initial_pause', 3),
				'pause_for_each' => config('wallet.pause_for_each', 1000),
			]]);
		}
		
		return $this;
	}

	/**
	 * Stash the user posted input in a session variable for later retrieval
	 * New Function
	 *
	 * @return mixed		reference to this object to allow chaining
	 */
	/**
	 * stash function.
	 *
	 * @access public
	 * @return void
	 */
	public function stash() {
		$this->snapdata($this->stash_key, ci()->input->request());

		return $this;
	}

	/**
	 * unStash the user posted input and clear the cache
	 * This also auto loads the $_POST variable again
	 * incase you need to access it via CodeIgniter methods
	 * which would be preferred for security
	 * New Function
	 *
	 * @return mixed		stored post variables
	 */
	/**
	 * unstash function.
	 *
	 * @access public
	 * @return void
	 */
	public function unstash() {
		$stashed = $this->get_snapdata($this->stash_key);

		/* put back RAW $_POST */
		$_POST = (is_array($stashed)) ? $stashed : [];

		/* and return stored */
		return $stashed;
	}

} /* end class */