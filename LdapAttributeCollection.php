<?php
class LdapAttributeCollection implements Iterator {
	protected $classes = array();
	private $index=0;
	private $classIndex=0;
	private $attrIndex=0;
	private $classLen=0;

	public function __construct(array $objectclasses) {
		$this->classes = $objectclasses;
		$this->classLen= count($objectclasses);
	}

	public function rewind() {
		$this->index=0;
		$this->classIndex=0;
		$this->attrIndex=0;
	}

	public function current() {
		$cls=$this->classes[$this->classIndex];
		$a = $cls::getAttrAt($this->attrIndex);
		return new LdapAttribute($a[0], $a[1], isset($a[2])?$a[2]:"string");
	}

	public function key() {
		return $this->index;
	}

	public function next() {
		$cls=$this->classes[$this->classIndex];
		$count=$cls::getAttrsCount();
		if($count ==0 || $this->attrIndex == ($count-1)){
			if($this->classIndex == ($this->classLen-1)){
				$this->index=-1;
				return;	
			}
			$this->classIndex++;	
			$this->attrIndex=0;
		}else{
			$this->attrIndex++;
		}
		$this->index++;
	}

	public function valid() {
		if($this->index==-1)return false;
		return true;
	}
	
	public function namesToArray(){
		$ret=array();
		foreach ($this as $attr){
			$ret[] = $attr->name;
		}
		return $ret;
	}
}