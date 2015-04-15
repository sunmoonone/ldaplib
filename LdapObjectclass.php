<?php
class LdapObjectclass{

	protected static $_attrs=array();

	public static function getAttrAt($index){
		return static::$_attrs[$index];
	}
	public static function getAttrsCount(){
		return count(static::$_attrs);
	}
}
