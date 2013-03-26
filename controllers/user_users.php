<?php

class User_Users extends Admin_Controller
{
	public $implement = 'Db_ListBehavior, Db_FormBehavior';
	public $list_model_class = 'User';
	public $list_record_url = null;

	public $form_preview_title = 'User';
	public $form_create_title = 'New User';
	public $form_edit_title = 'Edit User';
	public $form_model_class = 'User';
	public $form_not_found_message = 'User not found';
	public $form_redirect = null;

	public $form_edit_save_flash = 'User has been successfully saved';
	public $form_create_save_flash = 'User has been successfully added';
	public $form_edit_delete_flash = 'User has been successfully deleted';

	public $list_search_enabled = true;
	public $list_search_fields = array('@first_name', '@last_name', '@email', '@username');
	public $list_search_prompt = 'find users by name, username or email';

	protected $required_permissions = array('user:manage_users');

	public $global_handlers = array('onUpdateStatesList');

	public function __construct()
	{
		parent::__construct();
		$this->app_menu = 'users';
		$this->app_page = 'users';
		$this->app_module_name = 'System';

		$this->list_record_url = url('/user/users/edit/');
		$this->form_redirect = url('/user/users/');
	}

	public function index()
	{
		$this->app_page_title = 'Users';
	}

	protected function onUpdateStatesList()
	{
		$data = post('User');

		$form_model = $this->form_create_model_object();
		$form_model->country_id = $data['country_id'];
		echo ">>form_field_container_state_idUser<<";
		$this->form_render_field_container($form_model, 'state');
	}

}

