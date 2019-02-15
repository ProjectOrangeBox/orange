<?php

/* validation */
$config['username min length'] = 8;
$config['username max length'] = 32;
$config['password regex'] = '/((?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,32})/';
$config['password copy'] = 'Password must be at least: 8 characters, 1 upper, 1 lower case letter, 1 number, Less than 32 characters';

/* Database settings */

/* anyone with this roll will have full access (all permissions) */
$config['admin user id'] = 1;
$config['admin role id'] = 1;

/* anyone with this user id is not logged in */
$config['nobody user id'] = 2;
$config['nobody role id'] = 2;

$config['everyone role id'] = 3;

$config['login h2'] = 'Please Sign in<h4>Using your Windows Login</h4>';
$config['username field'] = 'Login';

$config['empty fields error'] = 'Please enter your login credentials.';
$config['general failure error'] = 'Incorrect Login and/or Password';
$config['account not active error'] = 'Your account is not active.';

/* user, role, permission tables */
$config['user table'] = 'orange_users';
$config['user role table'] = 'orange_user_role';
$config['role table'] = 'orange_roles';
$config['role permission table'] = 'orange_role_permission';
$config['permission table'] = 'orange_permissions';
