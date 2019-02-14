<?php

/* 001_init.php */

class Migration_001_init extends Migration_base
{

	/* example up function */
	public function up()
	{
		$hash = $this->get_hash();

		echo $hash.' up'.chr(10);

		/* default users */
		$table = config('auth.user table');

		$email = $this->console->prompt('Please enter a administrator email');
		$password = $this->console->prompt('Please enter a administrator password');

		$password = ci('o_user_model')->hash_password($password);

		ci()->db->query("INSERT INTO `$table` (`id`, `created_on`, `created_by`, `created_ip`, `updated_on`, `updated_by`, `updated_ip`, `deleted_on`, `deleted_by`, `deleted_ip`, `is_deleted`, `username`, `email`, `password`, `dashboard_url`, `user_read_role_id`, `user_edit_role_id`, `user_delete_role_id`, `read_role_id`, `edit_role_id`, `delete_role_id`, `is_active`, `last_login`, `last_ip`) VALUES (1,NULL,1,NULL,'2017-12-01 10:45:22',10,'192.168.64.1',NULL,0,NULL,0,'Administrator','$email','$password',NULL,1,1,1,1,1,0,1,NULL,'0.0.0.0')");
		ci()->db->query("INSERT INTO `$table` (`id`, `created_on`, `created_by`, `created_ip`, `updated_on`, `updated_by`, `updated_ip`, `deleted_on`, `deleted_by`, `deleted_ip`, `is_deleted`, `username`, `email`, `password`, `dashboard_url`, `user_read_role_id`, `user_edit_role_id`, `user_delete_role_id`, `read_role_id`, `edit_role_id`, `delete_role_id`, `is_active`, `last_login`, `last_ip`) VALUES (2,NULL,1,NULL,'2017-11-28 12:31:49',1,'192.168.64.1','2018-01-08 09:07:31',0,'',0,'Unknown User','nobody@example.com','false',NULL,3,3,3,1,1,0,1,NULL,'0.0.0.0')");

		/* default roles */
		$table = config('auth.role table');
		
		ci()->db->query("INSERT INTO `$table` (`id`, `name`, `description`, `migration`) VALUES (1,'Admin','Logged in super user','$hash')");
		ci()->db->query("INSERT INTO `$table` (`id`, `name`, `description`, `migration`) VALUES (2,'Nobody','Users not logged in','$hash')");
		ci()->db->query("INSERT INTO `$table` (`id`, `name`, `description`, `migration`) VALUES (3,'Everyone','Every user including nobody users','$hash')");
		
		/* assign admin user to admin role */
		$table = config('auth.user role table');

		ci()->db->query("INSERT INTO `$table` (`user_id`, `role_id`) VALUES (1,1)");
		
		/* we don't actually need to attach every user to the everyone role. the code does this manually without the db link */

		return true;
	}

	/* example down function */
	public function down()
	{
		/* don't delete the defaults */
		$hash = $this->get_hash();

		echo $hash.' down'.chr(10);

		return true;
	}
} /* end migration */
