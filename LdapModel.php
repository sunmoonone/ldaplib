<?php
require_once __DIR__ . "/../settings.php";
class NotImplemented extends Exception{
	public function __construct(){
		$this->message="Not implemented";
	}
}
class EmptyDnError extends Exception {

	public function __construct() {
		$this->message = "dn is empty";
	}
}
class EmptyAttrError extends Exception {

	public function __construct($name) {
		$this->message = "$name is empty";
	}
}
class AttrRequiredError extends Exception {

	public function __construct($attr) {
		$this->message = "attribute " . $attr->name . " is required";
	}
}
class LdapModel implements  ArrayAccess{
	const MODTYPE_ADD="append";
	const MODTYPE_RM="slice";
	const MODTYPE_REPLACE="replace";
	const MODTYPE_SET=true;

	protected static $_objectclasses = array();
	protected $_states_ = array();
	protected $_values_ = array();
	protected $_loading_ = false;
	protected $_col_attrs_map_;
	protected $_properties_ = array();
	protected $_property_values_ = array();
	public $dn, $dsdn;
	public static $rdn_attr;
	protected $err;
	
	public static function getBase(){
		throw new NotImplemented();
	}
	
	public function __clearDirty(){
		$this->_states_=array();	
	}

	public function __construct(array $values=null) {
		if(empty($values))return $this;
		
		if(! empty($values ['dsdn'])){
			$this->dsdn = $values ['dsdn'];
			$this->dn=$this->dsdn;
		}
		$this->_setValues($values);
		
		if(! empty($values ['dn'])){
			$this->dn = $values ['dn'];
		}else if(! empty($values ['id'])){
			$this->dn = $values ['id'];
		}else if(! empty($values ['base']) && empty($this->dn)){
			$rdn=$this->get($this::$rdn_attr);
			if(empty($rdn)){
				throw new EmptyAttrError($this::$rdn_attr);
			}
			$this->dn = $this::$rdn_attr . "=" . $this->get($this::$rdn_attr) . "," . $values ['base'];
		}
	}
	
	public static function getFirstOcName(){
		$oc=static::$_objectclasses[0];
		return $oc::getName();
	}

	public static function getAttrs(){
		return new LdapAttributeCollection(static::$_objectclasses);
	}
	final protected function _get_attrs() {
		return new LdapAttributeCollection(static::$_objectclasses);
	}

	public function _setValues(&$values) {
		$attrs = $this->_get_attrs();
		foreach($attrs as $attr ){
			if(!array_key_exists($attr->name, $values)){
				continue;
			}
			$n = $attr->name;
			
			$this->__set($n, $values[$n]);	
			
			if(!$this->dsdn && $attr->must && $this->_values_[$n]===''){
				throw new AttrRequiredError($attr);
			}
		}
	}

	public static function buildDn($rdnVal){
		return static::$rdn_attr."={$rdnVal},".static::getBase();
	}
	
	/**
	 * 
	 * @param string $attr
	 * @param mixed $val list of values or a single value
	 */	
	public function addAttrValue($attr,$val){
		if(is_array($val)){
			foreach ($val as $v){
				$this->_values_[$attr][]=$v;	
			}
		}else{
			$this->_values_[$attr][]=$val;	
		}
		if(!$this->_loading_)
			$this->_states_[$attr]=self::MODTYPE_ADD;
	}

	/**
	 * 
	 * @param string $attr
	 * @param mixed $val list of values or a single value
	 */	
	public function rmAttrValue($attr,$val){
		if(is_array($val)){
			foreach ($val as $v){
				$this->_values_[$attr][]=$v;	
			}
		}else{
			$this->_values_[$attr][]=$val;	
		}
		if(!$this->_loading_)
			$this->_states_[$attr]=self::MODTYPE_RM;
	}

	/**
	 * 
	 * @param array $values
	 * @throws EmptyAttrError
	 * @throws EmptyDnError
	 * @return LdapModel LdapModel object 
	 */	
	static function create($values) {
		$cls = get_called_class();
		$obj = new $cls();
		
		if(! empty($values ['dsdn'])){
			$obj->dsdn = $values ['dsdn'];
			$obj->dn=$values['dsdn'];
		}

		$obj->_setValues($values);

		if(! empty($values ['dn'])){
			$obj->dn = $values ['dn'];
		}else if(! empty($values ['id'])){
			$obj->dn = $values ['id'];
		}else if(! empty($values ['base']) && empty($obj->dn)){
			$rdn=$obj->get($cls::$rdn_attr);
			if(empty($rdn)){
				throw new EmptyAttrError($cls::$rdn_attr);
			}
			$obj->dn = $cls::$rdn_attr . "=" . $obj->get($cls::$rdn_attr) . "," . $values ['base'];
		}else if(empty($obj->dn)){
			$rdn=$obj->get($cls::$rdn_attr);
			if(empty($rdn)){
				throw new EmptyAttrError($cls::$rdn_attr);
			}
			$obj->dn = $cls::$rdn_attr . "=" . $obj->get($cls::$rdn_attr) . "," .$cls::getBase(); 
		}
		
		if(empty($obj->dn)){
			throw new EmptyDnError();
		}
		
		return $obj;
	}

	final public function get($name) {
		return $this->__get($name);	
	}

	final public function __get($name) {
		if($name == "id" || $name == "dn")
			return $this->dn;
		if($name == "dsdn")
			return $this->dsdn;
		if(array_key_exists($name,$this->_values_))
			return $this->_values_ [$name];
		return null;
	}

	final public function __set($name, $val) {
		if($name == "id" || $name=="dn"){
			$this->dn = $val;
			return;
		}
		if($name == "dsdn"){
			$this->dsdn = $val;
			return;
		}
		if(array_key_exists($name,$this->_values_)){
			$old = $this->_values_ [$name];
			if($old === $val)
				return;
		}
		$this->_values_ [$name] = $val;
		if(!$this->_loading_)
			$this->_states_ [$name] = self::MODTYPE_SET;
	}
	
	public function __setLoading($flag){
		$this->_loading_=$flag;	
	}
	
	public function offsetExists ( $offset ){
		if($offset=="id" || $offset=="dn" || $offset=="dsdn")return true;
		return array_key_exists($offset, $this->_values_);
	}
	public function offsetGet ( $offset ){
		return $this->__get($offset);	
	}
	public function offsetSet ( $offset , $value ){
		$this->__set($offset, $value);	
	}
	public function offsetUnset ( $offset ){
		if($this->offsetExists($offset)){
			unset($this->_values_[$offset]);
		}
	}
	/**
	 * 
	 * @return boolean
	 */
	public function save($move = false) {
		return LdapClient::save($this,$move);
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function delete() {
		return LdapClient::delete($this);
	}

	public function &getValues($withEmptyStr=false) {
		$a = array();
		foreach($this->_values_ as $n => $v ){
			if(!$withEmptyStr && $v === '')
				continue;
			$a [$n] = $v;
		}
		
		foreach(static::$_objectclasses as $oc ){
			$a ['objectClass'] [] = $oc::getName();
		}
		return $a;
	}

	public function getDirtyValues() {
		$a = array();
		foreach($this->_states_ as $n => $v ){
			if($v === self::MODTYPE_SET && !($this->_values_[$n]==="")){
				$a [$n] = $this->_values_ [$n];
			}
		}
		return $a;
	}
	/**
	 * $modifs = [
	    [
	        "modtype" => "add",
	        "entry"  => array("cn"=>["Smith-Jones"]),
	    ],
	];
	 * @return multitype:multitype:
	 */
	public function getModifyValues() {
		$a = array();
		foreach($this->_states_ as $n => $v ){
			if(($v === self::MODTYPE_ADD || $v === self::MODTYPE_RM || $v === self::MODTYPE_REPLACE) && !($this->_values_[$n]==="")){
				$a [] = array(
						"modtype"=>$v,
						"entry" =>array("$n" => $this->_values_[$n])
				);
			}
		}
		return $a;
	}

	public function setError($err) {
		if(strpos($err, "(68)")){
			$err = "不能重复添加";
		}
		$this->err = $err;
	}

	public function getError() {
		return $this->err;
	}
	
	public static function filter($exp=""){
		$cls=get_called_class();
		$base=$cls::getBase();
		$q=new LdapQuery($cls, $base);
		if($exp)
			$q->filter($exp);
		return $q;
	}
	
	public static function Objects($ds=null){
		$cls=get_called_class();
		$base=$cls::getBase();
		return new LdapQuery($cls, $base,$ds);
	}
	
	public static function getObj($dn){
		return static::Objects()->get($dn);	
	}
	
	public static function filterByDn($dn){
		$cls=get_called_class();
		$base=$cls::getBase();
		$q=new LdapQuery($cls, $base);
		list($n,$rdn)=self::getRdn($dn);
		return $q->filter("($n=$rdn)");
	}
	
	public static function getRdn($dn){
		$p1= strpos($dn, "=");
		$p2 = strpos($dn, ",");
		return array(substr($dn,0,$p1), substr($dn, $p1+1,$p2-$p1-1));
			
	}	

	public static function getAsArray($dn){
		return static::Objects()->getArray($dn);
// 		return static::filterByDn($dn)->firstJson();	
	}
	
	public static function getAsObj($dn){
		return static::Objects()->get($dn);
// 		return static::filterByDn($dn)->first();
	}
	
}

class LdapQuery {
	protected $clauses=array();
	protected $attrs=array();
	protected $sorts=array();
	protected $base,$model,$ds,$limit=0;

	public function __construct($model,$base,$ds = null){
		$this->model=$model;
		$this->base=$base;	
		$this->ds=$ds;
		$this->filter("(objectClass=".$model::getFirstOcName().")");
	}
	public function getDataSource(){
		return $this->ds;
	}	
	public function filter($exp){
		$this->clauses[]=$exp;	
		return $this;
	}

	public function getQueryBase(){
		return $this->base;
	}
	public function getModel(){
		return $this->model;
	}
	public function all(){
		return LdapClient::searchObj($this);	
	}
	
	public function first(){
		return LdapClient::searchFirstObj($this);	
	}
	
	public function max($attr){
		return LdapClient::max($this, $attr);
	}
	
	public function firstJson(){
		return LdapClient::searchFirstJson($this);	
	}

	public function allJson(){
		return LdapClient::searchJson($this);	
	}

	public function count(){
		return LdapClient::count($this);
	}
	
	public function delete(){
		return LdapClient::batch_delete($this);
	}
	
	public function only($attr,$_=null){
		$args = func_get_args();	
		foreach($args as $arg){
			$this->attrs[]=$arg;
		}
		return $this;
	}
	
	public function limit($limit){
		$this->limit=$limit;		
		return $this;
	}
	
	public function order($attr,$_=null){
		$args=func_get_args();
		foreach($args as $arg){
			$this->sorts[]=$arg;
		}	
		return $this;
	}
	
	public function getQuery(){
		if(count($this->clauses)>1){
			return "(&".join("", $this->clauses).")";
		}
		return $this->clauses[0];	
	}

	public function getQueryAttrs(){
		if(!empty($this->attrs)){
			return $this->attrs;
		}	
		
		$model=$this->model;
		$attrs = $model::getAttrs();
		$a=array();
		foreach($attrs as $attr ){
			$a[] = $attr->name;
		}
		return $a;
	}
	
	public function getOrderBy(){
		return $this->sorts;	
	}
	public function getLimit(){
		return $this->limit;
	}

	public function get($dn){
		return LdapClient::get($this,$dn);
	}

	public function getArray($dn){
		return LdapClient::get($this,$dn,true);
	}
}

