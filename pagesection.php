<?php
class PageSection {
	private $template;
	private $data;

	public function __construct($template) {
		global $relative_request_url;
		global $active_user;
		global $database;
		global $config;
		$this->template = $template;
		$this->data = new StdClass;
		$this->data->menu_items = array();
		if($active_user) {
			if($active_user->admin) {
				$this->data->menu_items['/profiles'] = 'Profiles';
				$this->data->menu_items['/servers'] = 'Servers';
			}
			$this->data->menu_items['/certificates'] = 'Certificates';
			if($active_user->admin) {
				$this->data->menu_items['/services'] = 'Services';
				$this->data->menu_items['/scripts'] = 'Scripts';
				$this->data->menu_items['/users'] = 'Users';
				$this->data->menu_items['/activity'] = 'Activity';
				$this->data->menu_items['/help'] = 'Help';
			}
		}
		$this->data->relative_request_url = $relative_request_url;
		$this->data->active_user = $active_user;
		$this->data->web_config = $config['web'];
		$this->data->email_config = $config['email'];
		if($active_user && $active_user->developer) {
			$this->data->database = $database;
		}
	}
	public function set_by_array($array, $prefix = '') {
		foreach($array as $item => $data) {
			$this->setData($prefix.$item, $data);
		}
	}
	public function set($item, $data) {
		$this->data->$item = $data;
	}
	public function get($item) {
		if(isset($this->data->$item)) {
			if(is_object($this->data->$item) && get_class($this->data->$item) == 'PageSection') {
				return $this->data->$item->generate();
			} else {
				return $this->data->$item;
			}
		} else {
			return null;
		}
	}
	public function config() {
		global $config;
		return $config;
	}
	public function generate() {
		ob_start();
		$data = $this->data;
		include_once(path_join('templates', 'functions.php'));
		include(path_join('templates', $this->template.'.php'));
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
}
