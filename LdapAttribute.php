<?php
class LdapAttribute{
	public $name;
	public $must;
	public $type;

	public function __construct($name,$must,$type="string"){
		$this->name=$name;
		$this->must=$must;
		$this->type=$type;
	}
	public static function create($name,$must,$type="string"){
		return new LdapAttribute($name, $must,$type);		
	}
	
	
}
