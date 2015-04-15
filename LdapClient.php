<?php
class LdapClient {
	static function getRebind(){
		return XSession::get('rebind');	
	}
	static function setRebind($flag){
		XSession::set('rebind',$flag);	
	}
	static function open() {
		$id = XSession::id();
		$ds = LdapConnectionCache::get($id);	
		if($ds){
			if(self::getRebind()){
				$r = @ldap_bind($ds, XSession::get("dn"), XSession::get("ldap_pwd"));
				if(!$r){
					throw new Exception("unable to login to ldap server " . Config::LDAP_HOST);
				}else{
					self::setRebind(false);
				}
			}	
			return $ds;
		}
		
		$ds = @ldap_connect(Config::LDAP_HOST); // must be a valid LDAP server!
		
		if($ds){
			
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
			LdapConnectionCache::set($id, $ds);
			// NOT WORK server side sort
			// $ctrl1 = array("oid" => "1.2.840.113556.1.4.473",
			// // "iscritical" => true,
			// "value"=>"uidNumber"
			// );
			// // try to set both controls
			// if (!ldap_set_option($ds, LDAP_OPT_SERVER_CONTROLS, array($ctrl1)))
			// echo "Failed to set server controls";
		}else{
			throw new Exception("unable to connect to ldap server " . Config::LDAP_HOST);
		}
		if(! XSession::exists("loginname")){
			throw new Exception("请先登陆");
		}
		$r = @ldap_bind($ds, XSession::get("dn"), XSession::get("ldap_pwd"));
		if(!$r){
			throw new Exception("unable to login to ldap server " . Config::LDAP_HOST);
		}
		return $ds;
	}

	static function login($name, $pwd) {
		$id = XSession::id();
		$ds = LdapConnectionCache::get($id);
		if(!$ds){
			$ds = @ldap_connect(Config::LDAP_HOST); // must be a valid LDAP server!
			if($ds){
				ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
				LdapConnectionCache::set($id, $ds);
			}else{
				return array(
					false,"无法连接到ldap服务器"
				);
			}
		}
		
		
		if($name == "Manager")
			$name="cn=$name,".Config::LDAP_BASE;
		else{
			$r = @ldap_bind($ds, Config::LDAP_SYS_USER, Config::LDAP_SYS_PASSWORD);
			if(!$r) {
				return array(
					$r,"无法登录"
				);
			}
			$u = User::Objects($ds)->filter("(uid=$name)")->only("uid")->first();
			if(!$u){
				@ldap_unbind($ds);
				XSession::set("ldap_pwd", null);
				XSession::set("loginname", null);
				XSession::set("dn", null);
				return array(
					false,"用户不存在"
				);
			}	
			$name=$u->dn;
		}
		$r = @ldap_bind($ds, $name, $pwd);
		if($r){
			XSession::set("dn", $name);
			XSession::set("ldap_pwd", $pwd);
			XSession::set("loginname", $name);
			return array(
				$r,"success"
			);
		}else{
			return array(
				$r,"用户名或密码不正确"
			);
		}
	}

	static function logout() {
		$id = XSession::id();
		$ds = LdapConnectionCache::get($id);
		if($ds){
			@ldap_close($ds);
			LdapConnectionCache::remove($id);
		}
		XSession::set("ldap_pwd", null);
		XSession::set("loginname", null);
		XSession::set("dn", null);
	}

	/**
	 *
	 * @param LdapModel $obj        	
	 * @return boolean
	 */
	public static function save(LdapModel $obj,$move=false) {
		$ds = self::open();
		
		if($obj->dsdn){
			if($obj->dsdn == $obj->dn){
				$dirty = $obj->getDirtyValues();
				$r = true;
				if($dirty){
					$r = @ldap_modify($ds, $obj->dn, $obj->getDirtyValues());
				}

				$mods = $r? $obj->getModifyValues():null;
				if($mods){
					foreach ($mods as $mod){
						if($mod['modtype'] == LdapModel::MODTYPE_ADD){
							$r = @ldap_mod_add($ds, $obj->dn, $mod["entry"]);
							
						}else if($mod['modtype'] == LdapModel::MODTYPE_RM){
							$r = @ldap_mod_del($ds, $obj->dn, $mod['entry']);

						}else if($mod['modtype'] == LdapModel::MODTYPE_REPLACE){
							$r = @ldap_mod_replace($ds, $obj->dn, $mod['entry']);
						}
						if(!$r)break;
					}	
				}
			}else{
				if($move){
					list($newrdn,$newparent) = self::explode_rename($obj->dn);
					$r = @ldap_rename($ds, $obj->dsdn, $newrdn, $newparent, true);	
					if($r){
						$obj->dsdn=$obj->dn;
						self::save($obj);
					}
				}else{
					$r = @ldap_add($ds, $obj->dn, $obj->getValues());
					if($r) ldap_delete($ds, $obj->dsdn);
				}
			}
		}else{
			$r = @ldap_add($ds, $obj->dn, $obj->getValues());
		}
		if(! $r){
			$obj->setError(sprintf("ldap error (%d) : %s",ldap_errno($ds) , ldap_error($ds)));
		}else{
			$obj->__clearDirty();
		}
		return $r;
	}
	
	static function explode_rename($dn){
		$p = strpos($dn, ',');	
		return array(substr($dn, 0,$p),substr($dn, $p+1));
	}

	public static function delete(LdapModel $obj) {
		if(! $obj->dsdn){
			throw new EmptyAttrError("dsdn");
		}
		$ds =self::open();
		$r = @ldap_delete($ds, $obj->dsdn);
		if(! $r){
			$obj->setError("ldap error:" . ldap_error($ds));
		}
		return $r;
	}
	/**
	 * delete all entries that match a query 
	 * @param LdapQuery $q
	 * @return integer count of entries deleted
	 */	
	public static function batch_delete(LdapQuery $q){
		if($q->getDataSource()){
			$ds = $q->getDataSource(); 
		}else{
			$ds = self::open();
		}
		$attrs = $q->getQueryAttrs();
		$ret = 0;
		$sr = @ldap_search($ds, $q->getQueryBase(), $q->getQuery(), $attrs, null, $q->getLimit());
		if(! $sr)
			return $ret;
		
		$count = ldap_count_entries($ds, $sr);
		if(! $count){
			return $ret;
		}

		$c =0;
		$i = ldap_first_entry($ds, $sr); 
		do{
			$dn = ldap_get_dn($ds, $i);
			$r = ldap_delete($ds, $dn);
			if(! $r){
				get_logger("LdapClient")->error("delete $dn failed:",ldap_error($ds));
				break;
			}else{
				$c++;
			}

		}while($i=ldap_next_entry($ds, $i));
		ldap_free_result($sr);
				
		return $c;
	}

	public static function &searchJson(LdapQuery $q) {
		return self::search($q, true);
	}

	public static function &searchObj(LdapQuery $q) {
		return self::search($q);
	}
	
	public static function count(LdapQuery $q){
		if($q->getDataSource()){
			$ds = $q->getDataSource(); 
		}else{
			$ds = self::open();
		}
		$attrs = $q->getQueryAttrs();
		
		$sr = ldap_search($ds, $q->getQueryBase(), $q->getQuery(), $attrs, null, $q->getLimit());
		if(! $sr)
			return 0;
		
		$count = ldap_count_entries($ds, $sr);
		return $count;
	}

	protected static function &search(LdapQuery $q, $retJson = false, $first = false) {
		if($q->getDataSource()){
			$ds = $q->getDataSource(); 
		}else{
			$ds =self::open();
		}
		$attrs = $q->getQueryAttrs();
		$sortby = $q->getOrderBy();
		$ret = $first? null: array();
		$sr = @ldap_search($ds, $q->getQueryBase(), $q->getQuery(), $attrs, null, $q->getLimit());
		if(! $sr)
			return $ret;
		
		$count = ldap_count_entries($ds, $sr);
		if(! $count){
			return $ret;
		}
		
		if($sortby){
			foreach($sortby as $attr ){
				$sortr = ldap_sort($ds, $sr, $attr);
				// echo "sorby $attr result: $sortr";
			}
		}
		
		if($first){
			$model = $q->getModel();
			if($retJson){
				$obj = array();
			}else{
				$obj = new $model();
				$obj->__setLoading(true);
			}
			// ldap_get_attributes returns camel case attr names
			$i = ldap_first_entry($ds, $sr);
			$dn = ldap_get_dn($ds, $i);
			$obj ['dsdn'] = ldap_get_dn($ds, $i);
			$obj ['dn'] = $obj ['dsdn'];
			$arr = ldap_get_attributes($ds, $i);
			$objAttrs=$model::getAttrs()->namesToArray();

			foreach($arr as $k => $v ){
				if(is_numeric($k))continue;
				if(!in_array($k, $objAttrs)) continue;
				
				$obj [$k] = $arr [$k] [0];
			}
			ldap_free_result($sr);
			if(!$retJson) $obj->__setLoading(false);
			return $obj;
		}
		
		$data = array();
		// NOTE returns lower case attr names
		$info = ldap_get_entries($ds, $sr);
		for($i = 0; $i < $info ["count"]; $i ++){
			if($retJson){
				$obj = array();
			}else{
				$model = $q->getModel();
				$obj = new $model();
				$obj->__setLoading(true);
			}
			$obj ['dsdn'] = $info [$i] ['dn'];
			$obj ['dn'] = $info [$i] ['dn'];
			foreach($attrs as $attr ){
				$lattr = strtolower($attr);
				if(isset($info [$i] [$lattr])){
					$obj [$attr] = $info [$i] [$lattr] [0];
				}
			}
			if(!$retJson) $obj->__setLoading(false);
			$data [] = $obj;
		} // end for
		ldap_free_result($sr);
		return $data;
	}

	public static function get(LdapQuery $q,$dn,$retArray=false){
		if($q->getDataSource()){
			$ds = $q->getDataSource();
		}else{
			$ds = self::open();
		}
		$attrs = $q->getQueryAttrs();

		$sr = @ldap_read($ds, $dn, $q->getQuery(),$attrs,null,$q->getLimit());
		if(! $sr) return null;
		
		$info = ldap_get_entries($ds, $sr);
		$i=0;	
		if($retArray){
			$obj = array();
		}else{
			$model = $q->getModel();
			$obj = new $model();
			$obj->__setLoading(true);
		}
		$obj ['dsdn'] = $info [$i] ['dn'];
		$obj ['dn'] = $info [$i] ['dn'];
		foreach($attrs as $attr ){
			$lattr = strtolower($attr);
			if(isset($info [$i] [$lattr])){
				$obj [$attr] = $info [$i] [$lattr] [0];
			}
		}
		if(!$retArray) $obj->__setLoading(false);
		ldap_free_result($sr);
		return $obj;
	}
	
	public static function max(LdapQuery $q, $attribute) {
		if($q->getDataSource()){
			$ds = $q->getDataSource();
		}else{
			$ds = self::open();
		}
		$attrs = $q->getQueryAttrs();
		
		$sortby = $q->getOrderBy();
		
		$ret = null;
		$sr = ldap_search($ds, $q->getQueryBase(), $q->getQuery(), $attrs, null, $q->getLimit());
		if(! $sr)
			return $ret;
		
		$count = ldap_count_entries($ds, $sr);
		if(! $count){
			return $ret;
		}
		
		if($sortby){
			foreach($sortby as $attr ){
				$sortr = ldap_sort($ds, $sr, $attr);
				// echo "sorby $attr result: $sortr";
			}
		}
		
		$max = 0;
		// NOTE returns lower case attr names
		$info = ldap_get_entries($ds, $sr);
		for($i = 0; $i < $info ["count"]; $i ++){
			foreach($attrs as $attr ){
				if($attr != $attribute){
					continue;
				}
				
				$lattr = strtolower($attr);
				if(isset($info [$i] [$lattr])){
					if($info [$i] [$lattr] [0] > $max){
						$max = $info [$i] [$lattr] [0];
					}
				}
			}
		} // end for
		ldap_free_result($sr);
		return $max;
	}

	public static function searchFirstObj(LdapQuery $q) {
		return self::search($q, false, true);
	}

	public static function searchFirstJson(LdapQuery $q) {
		return self::search($q, true, true);
	}
}

/**
 * Returns the hash value of a plain text password.
 * @see getSupportedHashTypes()
 *
 * @param string $password the password string
 * @param boolean $enabled marks the hash as enabled/disabled (e.g. by prefixing "!")
 * @param string $hashType password hash type (CRYPT, CRYPT-SHA512, SHA, SSHA, MD5, SMD5, PLAIN)
 * @return string the password hash
 */
function pwd_hash($password, $enabled = true, $hashType = 'SSHA') {
	// check for empty password
	if (! $password || ($password == "")) {
		return "";
	}
	$hash = "";
	switch ($hashType) {
		case 'CRYPT':
			$hash = "{CRYPT}" . crypt($password);
			break;
		case 'CRYPT-SHA512':
			$hash = "{CRYPT}" . crypt($password, '$6$' . generateSalt(16));
			break;
		case 'MD5':
			$hash = "{MD5}" . base64_encode(convertHex2bin(md5($password)));
			break;
		case 'SMD5':
			$salt = generateSalt(4);
			$hash = "{SMD5}" . base64_encode(convertHex2bin(md5($password . $salt)) . $salt);
			break;
		case 'SHA':
			$hash = "{SHA}" . base64_encode(convertHex2bin(sha1($password)));
			break;
		case 'PLAIN':
			$hash = $password;
			break;
		case 'SSHA':
		default: // use SSHA if the setting is invalid
			$salt = generateSalt(4);
			$hash = "{SSHA}" . base64_encode(convertHex2bin(sha1($password . $salt)) . $salt);
			break;
	}
	// enable/disable password
	if (! $enabled) return pwd_disable($hash);
	else return $hash;
}


/**
 * Converts a HEX string to a binary value
 *
 * @param string $value HEX string
 * @return binary result binary
 */
function convertHex2bin($value) {
	return pack("H*", $value);
}


/**
 * Returns the list of supported hash types (e.g. SSHA).
 *
 * @return array hash types
 */
function getSupportedHashTypes() {
	if (version_compare(phpversion(), '5.3.2') < 0) {
		// CRYPT-SHA512 requires PHP 5.3.2 or higher
		return array('CRYPT', 'SHA', 'SSHA', 'MD5', 'SMD5', 'PLAIN');
	}
	return array('CRYPT', 'CRYPT-SHA512', 'SHA', 'SSHA', 'MD5', 'SMD5', 'PLAIN');
}

/**
 * Calculates a password salt of the given legth.
 *
 * @param int $len salt length
 * @return String the salt string
 *
 */
function generateSalt($len) {
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890./';
	$salt = '';
	for ($i = 0; $i < $len; $i++) {
		$pos= getRandomNumber() % strlen($chars);
		$salt .= $chars{$pos};
	}
	return $salt;
}
/**
* Marks an password hash as disabled and returns the new hash string
*
* @param string $hash hash value to disable
* @return string disabled hash value
*/
function pwd_disable($hash) {
	// check if password is disabled (old wrong LAM method)
	if ((substr($hash, 0, 2) == "!{") || ((substr($hash, 0, 2) == "*{"))) {
		return $hash;
	}
	// check for "!" or "*" at beginning of password hash
	else {
		if (substr($hash, 0, 1) == "{") {
			$pos = strpos($hash, "}");
			if ((substr($hash, $pos + 1, 1) == "!") || (substr($hash, $pos + 1, 1) == "*")) {
				// hash already disabled
				return $hash;
			}
			else return substr($hash, 0, $pos + 1) . "!" . substr($hash, $pos + 1, strlen($hash));  // not disabled
		}
		else return $hash;  // password is plain text
	}
}

/**
 * Returns a random number.
 *
 * @return int random number
 */
function getRandomNumber() {
	if (function_exists('openssl_random_pseudo_bytes')) {
		return abs(hexdec(bin2hex(openssl_random_pseudo_bytes(5))));
	}
	return abs(mt_rand());
}

