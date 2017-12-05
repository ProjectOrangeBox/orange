<?php 

trait stamps_model_trait {
	protected $created_on_column_name = 'created_at';
	protected $created_by_column_name = 'created_by';
	protected $created_ip_column_name = 'created_ip';
	protected $has_created = false;

	protected $updated_on_column_name = 'updated_on';
	protected $updated_by_column_name = 'updated_by';
	protected $updated_ip_column_name = 'updated_ip';
	protected $has_updated = false;

	protected $deleted_on_column_name = 'deleted_on';
	protected $deleted_by_column_name = 'deleted_by';
	protected $deleted_ip_column_name = 'deleted_ip';
	protected $has_deleted = false;

} /* end class */
