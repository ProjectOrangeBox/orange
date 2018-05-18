<?php

$config['upload_path'] = ROOTPATH.'/var/uploads/';
$config['allowed_types'] = 'gif|jpg|png';
$config['max_size'] = '9000';

$config['max_width'] = '2000';
$config['max_height'] = '2000';

$config['move_to'] = WWW.'/images/';

$config['excel_src'] = [
	'allowed_types'=>'xls|xlsx',
];
