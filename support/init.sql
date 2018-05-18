CREATE TABLE `orange_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) DEFAULT NULL,
  `description` varchar(512) DEFAULT 'NULL',
  `group` varchar(128) DEFAULT NULL,
  `read_role_id` int(11) unsigned DEFAULT 1,
  `edit_role_id` int(11) unsigned DEFAULT 1,
  `delete_role_id` int(11) unsigned DEFAULT 1,
  `created_on` datetime DEFAULT '0000-00-00 00:00:00',
  `created_by` int(11) unsigned DEFAULT 3,
  `created_ip` varchar(15) DEFAULT '0.0.0.0',
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) unsigned DEFAULT 3,
  `updated_ip` varchar(15) DEFAULT '0.0.0.0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_key` (`key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `orange_role_permission` (
  `role_id` int(10) unsigned NOT NULL DEFAULT 0,
  `permission_id` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `orange_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8_bin NOT NULL DEFAULT '',
  `description` varchar(512) COLLATE utf8_bin NOT NULL DEFAULT '',
  `read_role_id` int(11) unsigned DEFAULT 1,
  `edit_role_id` int(11) unsigned DEFAULT 1,
  `delete_role_id` int(11) unsigned DEFAULT 1,
  `created_on` datetime DEFAULT '0000-00-00 00:00:00',
  `created_by` int(11) unsigned DEFAULT 3,
  `created_ip` varchar(15) CHARACTER SET utf8 DEFAULT '0.0.0.0',
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) unsigned DEFAULT 3,
  `updated_ip` varchar(15) CHARACTER SET utf8 DEFAULT '0.0.0.0',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`) USING BTREE
) ENGINE=InnoDB CHARSET=utf8;

CREATE TABLE `orange_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT '0000-00-00 00:00:00',
  `created_by` int(11) unsigned NOT NULL DEFAULT 1,
  `created_ip` varchar(16) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_ip` varchar(16) DEFAULT NULL,
  `read_role_id` int(11) unsigned DEFAULT 1,
  `edit_role_id` int(11) unsigned DEFAULT 1,
  `delete_role_id` int(11) unsigned DEFAULT 1,
  `name` varchar(64) CHARACTER SET latin1 NOT NULL DEFAULT '',
  `group` varchar(64) CHARACTER SET latin1 NOT NULL DEFAULT 'site',
  `value` text CHARACTER SET latin1 NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `help` varchar(255) CHARACTER SET latin1 DEFAULT '',
  `options` text DEFAULT NULL,
  `internal` varchar(64) GENERATED ALWAYS AS (lcase(concat(`group`,'::',`name`))) STORED,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `orange_user_role` (
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `role_id` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `orange_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT '0000-00-00 00:00:00',
  `created_by` int(11) unsigned NOT NULL DEFAULT 1,
  `created_ip` varchar(16) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) unsigned NOT NULL DEFAULT 1,
  `updated_ip` varchar(16) DEFAULT NULL,
  `deleted_on` datetime DEFAULT NULL,
  `deleted_by` int(11) unsigned DEFAULT 0,
  `deleted_ip` varchar(16) DEFAULT NULL,
  `is_deleted` tinyint(1) unsigned DEFAULT 0,
  `username` varchar(64) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `dashboard_url` varchar(255) DEFAULT NULL,
  `user_read_role_id` int(10) unsigned DEFAULT 3,
  `user_edit_role_id` int(10) unsigned DEFAULT 3,
  `user_delete_role_id` int(10) unsigned DEFAULT 3,
  `read_role_id` int(10) unsigned DEFAULT 1,
  `edit_role_id` int(11) unsigned DEFAULT 1,
  `delete_role_id` int(11) unsigned DEFAULT 1,
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(16) NOT NULL DEFAULT '0.0.0.0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email_unique` (`email`) USING BTREE,
  KEY `idx_email` (`email`) USING BTREE,
  KEY `idx_password` (`password`) USING BTREE,
  KEY `idx_username` (`username`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;