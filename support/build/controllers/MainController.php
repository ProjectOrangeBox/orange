<?php

class MainController extends \MY_Controller
{
	public function indexAction()
	{
		$this->page->render();
	}

	public function route404Action()
	{
		show_404();
	}
} /* end class */
