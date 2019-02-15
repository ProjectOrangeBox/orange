<?php

$config['is_a_str']       = 'trim|required|filter_str[255]';
$config['is_a_oneorzero'] = 'trim|required|in_list[1,0]';
$config['is_a_md5']       = 'trim|required|md5|filter_str[32]';
$config['is_a_list']      = 'trim|required|filter_except[1234567890,]|filter_length[8192]';
$config['is_a_mongoid']   = 'trim|required|is_a_primary|max_length[24]';
$config['is_a_id']        = 'trim|required|integer|max_length[10]|less_than[4294967295]|filter_int[10]';

/* $attach['testing'] = function(&$field, &$param, &$error_string, &$field_data, &$validate) { ... } */
