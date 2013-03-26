<?php

class User_Group extends Db_ActiveRecord
{
	const group_guest = 'guest';
	const group_registered = 'registered';
	
	public $table_name = 'user_groups';
	protected $api_added_columns = array();

	protected static $guest_group = null;
	protected static $cache = null;

	public $calculated_columns = array(
		'user_num'=>array('sql'=>"(select count(*) from users where group_id=user_groups.id)", 'type'=>db_number)
	);
	
	public static function create()
	{
		return new self();
	}

	public function define_columns($context = null)
	{
		$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the group name");
		$this->define_column('description', 'Description')->validation()->fn('trim');
		$this->define_column('user_num', 'Users')->validation()->fn('trim');
		$this->define_column('code', 'API Code')->validation()->fn('trim')->fn('mb_strtolower')->unique('The API Code "%s" is already in use.');
		
		$this->defined_column_list = array();
		Phpr::$events->fire_event('user:on_extend_user_group_model', $this);
		$this->api_added_columns = array_keys($this->defined_column_list);
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('name');
		$this->add_form_field('description');
		
		$field = $this->add_form_field('code')->comment('You can use the API code for referring the user group in the API calls.', 'above');
		if ($this->code == self::group_guest || $this->code == self::group_registered)
			$field->disabled = true;
			
		Phpr::$events->fire_event('user:on_extend_user_group_form', $this, $context);
		foreach ($this->api_added_columns as $column_name)
		{
			$form_field = $this->find_form_field($column_name);
			if ($form_field)
				$form_field->options_method('get_added_field_options');
		}
	}
	
	public function get_added_field_options($db_name, $current_key_value = -1)
	{
		$result = Phpr::$events->fire_event('user:on_get_user_group_field_options', $db_name, $current_key_value);
		foreach ($result as $options)
		{
			if (is_array($options) || (strlen($options && $current_key_value != -1)))
				return $options;
		}

		return false;
	}

	public function before_delete($id = null)
	{
		if ($this->code == self::group_guest || $this->code == self::group_registered)
			throw new Phpr_ApplicationException("The registered user group cannot be deleted");
		
		if ($this->user_num)
			throw new Phpr_ApplicationException("The group cannot be deleted because {$this->user_num} user(s) belong to this group");
	}
	
	public static function list_groups_by_codes($codes)
	{
		foreach ($codes as &$code)
			$code = mb_strtolower($code);
			
		if (!is_array($codes))
			$codes = array($codes);

		if (!count($codes))
			return new Db_DataCollection();

		return self::create()->where('code in (?)', array($codes))->find_all();
	}
	
	public static function list_groups()
	{
		if (self::$cache === null)
			self::$cache = self::create()->find_all()->as_array(null, 'id');
			
		return self::$cache;
	}

	public static function find_by_id($id)
	{
		$groups = self::list_groups();
		if (array_key_exists($id, $groups))
			return $groups[$id];
			
		return null;
	}

	public static function find_by_code($code)
	{
		$groups = self::list_groups();
		foreach ($groups as $group)
			if ($group->code === $code)
				return $group;
				
		return null;
	}
}
