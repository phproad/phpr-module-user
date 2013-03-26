<?php

class User_Preference
{
	public $table_name = 'user_preferences';
	protected static $preference_cache = array();

	public static function save_preferences($user)
	{

		$values_arr = array();

		if (!$user->added_preference_fields)
			return;

		foreach ($user->added_preference_fields as $code=>$info)
		{
			$module = $info[0];
			$name = $info[1];
			$value = ($user->$code) ? "'".$user->$code."'" : 'NULL';

			$values_arr[] = "('".$user->id."', '".$module->get_id()."', '".$name."', ".$value.")";
		}
		
		if (!$values_arr)
			return;

		$values = implode(',', $values_arr);
		Db_Helper::query('insert into user_preferences (user_id, module_id, name, value) values '.$values.' on duplicate key update value = values(value)');
	}

	public static function get_preference($user_id, $module_id, $name)
	{
		if (!array_key_exists($user_id, self::$preference_cache))
		{
			$preferences = Db_Helper::object_array(
				'select * from user_preferences where user_id=:user_id',
				array('user_id'=>$user_id));

			$user_preferences = array();
			foreach ($preferences as $preference)
			{
				if (!array_key_exists($preference->module_id, $user_preferences))
					$user_preferences[$preference->module_id] = array();

				$user_preferences[$preference->module_id][$preference->name] = $preference->value;
			}

			self::$preference_cache[$user_id] = $user_preferences;
		}

		if (!array_key_exists($user_id, self::$preference_cache))
			return null;

		if (!array_key_exists($module_id, self::$preference_cache[$user_id]))
			return null;

		if (!array_key_exists($name, self::$preference_cache[$user_id][$module_id]))
			return null;

		return self::$preference_cache[$user_id][$module_id][$name];
	}

	public static function get_preferences($user_id)
	{
		return Db_Helper::object_array(
			'select * from user_preferences where user_id=:user_id',
			array('user_id'=>$user_id));
	}
}

