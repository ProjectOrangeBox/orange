<?php 

trait soft_delete_trait {
	protected $soft_delete     = false;
	protected $soft_delete_key = 'is_deleted';

	/* internal */
	protected $_temporary_with_deleted = false;
	protected $_temporary_only_deleted = false;

} /* end class */
