<?php

class User_Groups extends Admin_Controller
{
	public $implement = 'Db_List_Behavior, Db_Form_Behavior';
	public $list_model_class = 'User_Group';
	public $list_record_url = null;

	public $form_preview_title = 'User Group';
	public $form_create_title = 'New User Group';
	public $form_edit_title = 'Edit User Group';
	public $form_model_class = 'User_Group';
	public $form_not_found_message = 'User group not found';
	public $form_redirect = null;

	public $form_edit_save_flash = 'User group has been successfully saved';
	public $form_create_save_flash = 'User group has been successfully added';
	public $form_edit_delete_flash = 'User group has been successfully deleted';

	protected $required_permissions = array('user:manage_groups');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'user';
		$this->app_page = 'groups';
		$this->app_module_name = 'User';

		$this->list_record_url = url('user/groups/edit');
		$this->form_redirect = url('user/groups');
	}
	
	public function index()
	{
		$this->app_page_title = 'User Groups';
	}
}

?>