<?php 
class Approval_Workflow_Options {
	var $options;
	var $option_name;
	var $is_site_option;
	
	function Approval_Workflow_Options($option_name, $is_site_options = false){
		$this->option_name = $option_name;
		$this->is_site_option = $is_site_options;
		if($this->is_site_option){
			$this->options = get_site_option($this->option_name);
		} else {
			$this->options = get_option($this->option_name);
		}
		if(!is_array($this->options)){
			$this->options = array();
		}
	}
	
	function __get($key){
		return $this->options[$key];
	}
	
	function __set($key, $value){
		$this->options[$key] = $value;
	}
	
	function __isset($key){
		return array_key_exists($key, $this->options);
	}
	
	function save(){
		if($this->is_site_option){
			update_site_option($this->option_name, $this->options);
		} else {
			update_option($this->option_name, $this->options);
		}
	}
}
?>