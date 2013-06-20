<?php

class User_Module extends Core_Module_Base
{

	protected function set_module_info()
	{
		return new Core_Module_Detail(
			"User",
			"Front end user module",
			"PHPRoad",
			"http://phproad.com/"
		);
	}

	public function build_admin_menu($menu)
	{
		$dash = $menu->add('users', 'Users', '/user/users', 200)->icon('group')->permission('manage_users');
	}

	public function build_admin_settings($settings)
	{
		//$settings->add('/user/groups', 'User Groups', 'Define what groups exist', '/modules/user/assets/images/group_config.png', 300);
	}    

	public function build_admin_permissions($host)
	{
		$host->add_permission_field($this, 'manage_users', 'Manage users', 'left')->display_as(frm_checkbox)->comment('Manage service users');
	}

}
