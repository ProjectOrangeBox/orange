<?php
$config['allow registration'] = true;

$config['self validate email'] = true;
$config['send welcome email'] = true;

$config['user defaults'] = [
	'is_active' => 1,
	'user_read_role_id'=>0,
	'user_edit_role_id'=>0,
	'user_delete_role_id'=>0,
	'read_role_id'=>0,
	'edit_role_id'=>0,
	'delete_role_id'=>0,
];

$config['site name'] = 'SkyNet';
$config['email activation expire'] = 240;
$config['activate url'] = '/user-registration/activate';

/* register */
$config['email from register'] = 'admin@example.com';
$config['email from human register'] = 'administrator';

/* welcome email settings */
$config['email from welcome'] = 'admin@example.com';
$config['email from human welcome'] = 'administrator';

$config['check username'] = true;
$config['check email'] = true;
