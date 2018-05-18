<?php
$route = array (
  'default_controller' => 'main/index',

  'cli/(.*)' => function($url) {
  	return 'cli/'.$url;
  },

  'admin/(.*)' => function($url) {
  	middleware('AdminMiddleware','PublicMiddleware','GuiMiddleware');

  	return 'admin/'.$url;
  },

  'login(.*)' => function($url) {
  	middleware('LoginMiddleware','PublicMiddleware','GuiMiddleware');

  	return 'login'.$url;
  },

  '(.*)' => function($url) {
  	middleware('PublicMiddleware','GuiMiddleware');

  	return $url;
  },

  '404_override' => 'main/route404',
  'translate_uri_dashes' => false,
);
