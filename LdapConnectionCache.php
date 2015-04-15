<?php
class LdapConnectionCache{
	private static $connections=array();

	public static function get($key){
		return isset(self::$connections[$key])?self::$connections[$key][0]:null;
	}

	public static function set($key ,$con){
		if(isset(self::$connections[$key])){
			list($ds,$t) = self::$connections[$key];
			if($ds === $con){
				self::$connections[$key][1]=time();	
			}else{
				self::$connections[$key][0]= $con;
				self::$connections[$key][1]=time();	
				@ldap_close($ds);
			}
		}else{
			self::$connections[$key] = array($con,time());		
		}
	} 
	
	static function checkClose($key){
		if(isset(self::$connections[$key])){
			list($ds,$t) = self::$connections[$key];

			if((time()-$t) > 3600){
				@ldap_close($ds);
				unset(self::$connections[$key]);
			}
		}	
	}
	
	static function remove($key){
		unset(self::$connections[$key]);
	}
	
	public function __destruct(){
		if(!self::$connections){return;}
		foreach (self::$connections as $key => $v) {
			@ldap_close($v[0]);
		}
	}
}