<?php
/**
 * O_permission_model
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class O_permission_model extends \Database_model
{
	protected $table;
	protected $additional_cache_tags = '.acl';
	protected $entity = 'o_permission_entity';
	protected $rules = [
		'id'          => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'key'         => ['field' => 'key', 'label' => 'Key', 'rules' => 'required|strtolower|max_length[255]|filter_input[255]|is_uniquem[o_permission_model.key.id]'],
		'description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'required|max_length[255]|filter_input[255]|is_uniquem[o_permission_model.description.id]'],
		'group'       => ['field' => 'group', 'label' => 'Group', 'rules' => 'required|max_length[255]|filter_input[255]'],
		'migration'   => ['field' => 'migration', 'label' => 'Migration', 'rules' => 'max_length[255]'],
	];

	/**
	 * __construct
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function __construct()
	{
		$this->table = config('auth.permission table');

		parent::__construct();

		log_message('info', 'o_permission_model Class Initialized');
	}

	/**
	 * roles
	 * Insert description here
	 *
	 * @param $role
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function roles($role)
	{
		$dbc = $this->_database
			->from(config('auth.role permission table'))
			->join(config('auth.role table'), config('auth.role table').'.id = '.config('auth.role permission table').'.role_id')
			->where(['permission_id' => (int) $role_id])
			->get();

		return ($this->_database->num_rows() > 0) ? $dbc->result() : [];
	}

	/**
	 * _find_role_id
	 * Insert description here
	 *
	 * @param $role
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function _find_role_id($role)
	{
		return (int) ((int) $role > 0) ? $role : $this->o_role_model->column('id')->get_by(['name' => $role]);
	}

	/**
	 * _find_permission_id
	 * Insert description here
	 *
	 * @param $permission
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function _find_permission_id($permission)
	{
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}

	/**
	 * insert
	 * Insert description here
	 *
	 * @param $data
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function insert(array $data)
	{
		$success = parent::insert($data);
		$this->_refresh();
		$this->delete_cache_by_tags();

		return $success;
	}

	/**
	 * update
	 * Insert description here
	 *
	 * @param $data
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function update(array $data)
	{
		$success = parent::update($data);
		$this->_refresh();
		$this->delete_cache_by_tags();

		return $success;
	}

	/**
	 * _refresh
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function _refresh()
	{
		$records = $this->get_many();

		/* automatically adds permission to admin role */
		foreach ($records as $record) {
			ci('o_role_model')->add_permission(ADMIN_ROLE_ID, $record->id);
		}

		/* makes sure nobody user has NO permissions */
		ci('o_role_model')->remove_permission(NOBODY_USER_ID);
	}

	/* migration */
	public function migration_add($key=null, $group=null, $description=null, $migration=null)
	{
		$this->skip_rules = true;

		/* we already verified the key that's the "real" primary key */
		$success = (!$this->exists(['key'=>$key])) ? $this->insert(['key'=>$key,	'group'=>$group,'description'=>$description,'migration'=>$migration]) : false;

		$this->_refresh();

		return $success;
	}

	public function migration_remove(string $migration=null) : bool
	{
		$this->skip_rules = true;

		unset($this->has['delete_role']);

		$success = $this->delete_by(['migration'=>$migration]);

		$this->_refresh();

		return $success;
	}
}
