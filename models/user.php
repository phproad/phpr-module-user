<?php

class User extends Phpr_User
{
	public $table_name = 'users';
	public $implement = 'Db_AutoFootprints, Db_Model_Attachments';
	public $auto_footprints_visible = true;
	public $auto_footprints_default_invisible = true;

	protected $api_added_columns = array();
	protected $added_preference_fields = array();

	public $belongs_to = array(
		'group' => array('class_name'=>'User_Group', 'foreign_key'=>'group_id'),
	);

	public $custom_columns = array(
		'password_confirm' => db_varchar, 
		'display_name' => db_varchar,
		'name' => db_varchar
	);

	public $calculated_columns = array(
		'name' => "trim(concat(ifnull(first_name, ''), ' ', ifnull(last_name, ' ')))"
	);

	public function define_columns($context = null) 
	{
		$this->define_column('username', 'Username')->validation()->fn('trim')->fn('ucwords')
				->regexp(',^[/a-z0-9_\.-]*$,i', "Username can contain only letters, numbers and signs _, -, /, and .")
				->method('validate_username');

		$this->define_column('first_name', 'First Name')->order('asc')->validation()->fn('trim');
		$this->define_column('last_name', 'Last Name')->validation()->fn('trim');
		$this->define_column('password', 'Password')->invisible()->validation()->fn('trim');
		$this->define_column('password_confirm', 'Password Confirmation')->invisible()->validation()->matches('password', 'Password and confirmation password do not match.');
		$this->define_column('email', 'Email')->validation()->fn('trim')->fn('mb_strtolower')->required()->Email('Please provide valid email address.')->method('validate_email');
		$this->define_column('guest', 'Guest');
		$this->define_relation_column('group', 'group', 'Group ', db_varchar, '@name');
		$this->define_column('deleted_at', 'Deleted')->default_invisible()->date_format('%x %H:%M');

		$this->defined_column_list = array();
		Phpr::$events->fire_event('user:on_extend_user_model', $this, $context);
		$this->api_added_columns = array_keys($this->defined_column_list);
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('username', 'left')->tab('User');
		$this->add_form_field('group', 'right')->tab('User');

		$this->add_form_field('first_name', 'left')->tab('User');
		$this->add_form_field('last_name', 'right')->tab('User');
		$this->add_form_field('email')->tab('User');
		$this->add_form_field('password', 'left')->tab('User')->display_as(frm_password)->no_preview();
		$this->add_form_field('password_confirm', 'right')->tab('User')->display_as(frm_password)->no_preview();

		$this->load_preferences_ui();

		if (!$this->is_new_record())
			$this->load_user_preferences();

		Phpr::$events->fire_event('user:on_extend_user_form', $this, $context);
		foreach ($this->api_added_columns as $column_name)
		{
			$form_field = $this->find_form_field($column_name);
			if ($form_field)
				$form_field->options_method('get_added_field_options');
		}
	}

	// Extensibility
	//

	public function get_added_field_options($db_name, $current_key_value = -1)
	{
		$result = Phpr::$events->fire_event('user:on_get_user_field_options', $db_name, $current_key_value);
		foreach ($result as $options)
		{
			if (is_array($options) || (strlen($options && $current_key_value != -1)))
				return $options;
		}

		return false;
	}

	// Events
	//

	public function after_update()
	{
		Phpr::$events->fire_event('user:on_user_updated', $this);
	}

	public function after_create()
	{
		Phpr::$events->fire_event('user:on_user_created', $this);
	}

	public function before_save($session_key = null)
	{
		$this->plain_password = $this->password;

		if (!$this->guest)
		{
			if (!strlen($this->password))
			{
				if ($this->is_new_record())
					$this->validation->set_error('Please provide a password.', 'password', true);
				else
					$this->password = $this->fetched['password'];
			} else
				$this->password = Phpr_SecurityFramework::create()->salted_hash($this->password);
		}

		if ($this->is_new_record() && (!strlen($this->username)))
		{
			$username = explode("@",$this->email);
			$this->username = Db_Helper::get_unique_column_value($this, 'username', $username[0]);
		}
	}

	public function after_save()
	{
		User_Preference::save_preferences($this);
	}

	public function before_create($session_key = null)
	{
		$this->signup_ip = Phpr::$request->get_user_ip();
		if ($this->guest)
		{
			$group = User_Group::create()->find_by_code(User_Group::group_guest);
			if ($group)
				$this->group_id = $group->id;
		} 
		else if (!$this->group_id)
		{
			$group = User_Group::create()->find_by_code(User_Group::group_registered);
			if ($group)
				$this->group_id = $group->id;
		}
	}

	// Options
	//

	public function get_group_options($key_value = -1)
	{
		if ($key_value != -1)
		{
			if (!strlen($key_value))
				return null;

			$obj = User_Group::create()->find($key_value);
			return $obj ? $obj->name : null;
		}
		
		$groups = User_Group::create()->where('(code is null or code != ?)', User_Group::group_guest)->order('name')->find_all();
		return $groups->as_array('name', 'id');
	}

	// Validation
	//

	public function validate_email($name, $value)
	{
		if ($this->guest)
			return true;

		$value = trim(strtolower($value));
		$user = self::create()->where('(guest <> 1 or guest is null)')->where('email=?', $value);
		if ($this->id)
			$user->where('id <> ?', $this->id);

		$user = $user->find();

		if ($user)
			$this->validation->set_error(__("Email %s is already in use", $value, true), $name, true);

		return true;
	}

	public function validate_username($name, $value)
	{
		if ($this->guest)
			return true;

		$value = trim(ucwords($value));
		$user = self::create()->where('username=?', $value);
		if ($this->id)
			$user->where('id <> ?', $this->id);

		$user = $user->find();

		if ($user)
			$this->validation->set_error(__("Sorry %s is already taken!", $value, true), $name, true);

		return true;
	}

	// Service methods
	//

	public function generate_password()
	{
		$letters = 'abcdefghijklmnopqrstuvwxyz';
		$password = null;
		for ($i = 1; $i <= 6; $i++)
			$password .= $letters[rand(0, 25)];

		$this->password_confirm = $password;
		$this->password = $password;
	}

	public static function reset_password($email)
	{
		$user = self::create()->where('(guest <> 1 or guest is null)')->where("email=:email or username=:email", array('email'=>$email))->find();
		if (!$user)
			throw new Phpr_ApplicationException('User with specified details is not found');

		$user->generate_password();
		$user->save();

		if (module_exists('notify'))
			Notify::trigger('user:password_reset', array('user'=>$user));
	}

	public function set_notify_vars(&$template, $prefix='')
	{
		$this->eval_custom_columns();
		$template->set_vars(array(
			$prefix.'name'         => $this->name,
			$prefix.'display_name' => $this->display_name,
			$prefix.'first_name'   => $this->first_name,
			$prefix.'last_name'    => $this->last_name,
			$prefix.'email'        => $this->email,
			$prefix.'login'        => $this->login,
			$prefix.'password'     => $this->plain_password,
			$prefix.'phone'        => $this->phone,
			$prefix.'mobile'       => $this->mobile,
			$prefix.'street_addr'  => $this->street_addr,
			$prefix.'city'         => $this->city,
			$prefix.'zip'          => $this->zip,
			$prefix.'country'      => ($this->country) ? $this->country->name : null,
			$prefix.'state'        => ($this->state) ? $this->state->name : null            
		));
	}

	public static function find_registered_by_email($email)
	{
		$value = trim(strtolower($email));
		$user = self::create()->where('(users.guest <> 1 or users.guest is null)')->where('email=?', $value);

		return $user->find();
	}

	public function convert_to_registered($send_notification = true, $group_id = null)
	{
		if (self::find_registered_by_email($this->email))
			throw new Phpr_ApplicationException("Registered user with email {$obj->email} already exists.");

		if ($send_notification)
			$this->generate_password();
		else
			$this->password = null;

		if (!$group_id)
			$group_id = User_Group::find_by_code(User_Group::group_registered)->id;

		$this->group_id = $group_id;
		$this->guest = 0;
		$this->save();
		
		if ($send_notification)
			Notify::trigger('user:register_confirm', array('user'=>$this));
	}

	// Custom columns
	//

	public function eval_name()
	{
		return $this->first_name . ' ' . $this->last_name;
	}

	public function eval_display_name()
	{
		//@todo Point for customisation: 
		//      How do you prefer your name displayed?
		
		if ($this->first_name || $this->last_name)
			return $this->eval_name();

		return $this->username;
	}

	// Preferences
	//

	public function add_preference_field($module, $code, $title, $default=null, $side = 'full', $type = db_text)
	{
		$module_id = $module->get_id();

		$original_code = $code;
		$code = $module_id.'_'.$code;

		if ($default !== null)
			$this->{$code} = $default;

		$this->define_custom_column($code, $title, $type)->validation();
		$form_field = $this->add_form_field($code, $side)->options_method('get_added_preference_field_options')->tab($module->get_module_info()->name)->css_class_name('preference_field');

		$this->added_preference_fields[$code] = array($module, $original_code);

		return $form_field;
	}

	public function get_added_preference_field_options($db_name, $current_key_value = -1)
	{
		if (!isset($db_name, $this->added_preference_fields))
			return array();

		$module = $this->added_preference_fields[$db_name][0];
		$code = $this->added_preference_fields[$db_name][1];
		$class_name = get_class($module);

		$method_name = "get_{$code}_options";
		if (!method_exists($module, $method_name))
			throw new Phpr_SystemException("Method {$method_name} is not defined in {$class_name} class.");

		return $module->$method_name($current_key_value);
	}

	public function load_user_preferences()
	{
		if (!$this->added_preference_fields)
			$this->load_preferences_ui();

		$preferences = User_Preference::get_preferences($this->id);
		foreach ($preferences as $preference)
		{
			$field_code = $preference->module_id.'_'.$preference->name;
			if (array_key_exists($field_code, $this->added_preference_fields))
			{
				$this->$field_code = $preference->value;
			}
		}
	}

	public function get_preference($module_id, $name)
	{
		if (!is_array($name))
			return User_Preference::get_preference($this->id, $module_id, $name);
		else
		{
			foreach ($name as $preference)
			{
				if (User_Preference::get_preference($this->id, $module_id, $preference))
					return true;
			}

			return false;
		}
	}

	// TODO this method is really, really inefficient
	public static function list_users_having_preference($module_id, $name)
	{
		$users = self::create()->find_all();
		$result = array();

		foreach ($users as $user)
		{
			if (!$user->enabled)
				continue;

			if ($user->get_preference($module_id, $name))
				$result[] = $user;
		}

		return $result;
	}

	private function load_preferences_ui()
	{
		$modules = Core_Module_Manager::get_modules();

		foreach ($modules as $id=>$module)
		{
			$module->build_user_preferences($this);
		}
	}

	// Required by PHPR
	public function find_user($login, $password)
	{
		$login = mb_strtolower($login);
		return $this->where('email=:login or username=:login', array('login'=>$login))->where('password=?', Phpr_SecurityFramework::create()->salted_hash($password))->where('(guest is null or guest=0)')->where('deleted_at is null')->find();
	}
}