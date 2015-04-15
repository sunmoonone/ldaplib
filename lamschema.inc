<?php

class LamApplicationProcess extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn", true),
			array("description",false),
		    array("l",false),// status a timestamp
		    array("ou",false),//value
  			array("seeAlso",false)
	);
	
	public static function getName(){
		return "applicationProcess";	
	}
}

class LamOrgnization extends LdapObjectclass{
	protected static $_attrs=array(
			array("o", true),
			array("postalCode",false),
  			array("description",false),
  			array("postOfficeBox",false),//email
  			array("telephoneNumber",false),
		    array("telexNumber",false),
		    array("userPassword",false),
		    array("l",false),//uique key //md5(time.o.sessionid)
		    array("st",false),//parent deparent's l
  			array("postalAddress",false)
	);
	
	public static function getName(){
		return "organization";	
	}
}

class LamOrgnizationUnit extends LdapObjectclass{
	protected static $_attrs=array(
			array("ou", true),
			array("description",false),
			array("businessCategory",false),
			array("destinationIndicator",false),
			array("facsimileTelephoneNumber",false),
			array("internationaliSDNNumber",false),
			array("l",false),
			array("physicalDeliveryOfficeName",false),
			array("postOfficeBox",false),
			array("postalAddress",false),
			array("postalCode",false),
			array("preferredDeliveryMethod",false),
			array("registeredAddress",false),
			array("searchGuide",false),
			array("seeAlso",false),
			array("st",false),
			array("street",false),
			array("telephoneNumber",false),
			array("teletexTerminalIdentifier",false),
			array("telexNumber",false),
			array("userPassword",false),
			array("x121Address",false)
	);
	
	public static function getName(){
		return "organizationalUnit";
	}
}

class LamAccount extends LdapObjectclass{
	protected static $_attrs=array(
			array("userid", true),
			array("description",false),
			array("host",false),
			array("localityName",false),
			array("organizationalName",false),
			array("organizationalUnitName",false),
			array("seeAlso",false)
		
	);
	
	public static function getName(){
		return "account";
	}
}

class LamPosixAccount extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn", true),
			array("uid", true),
			array("uidNumber", true),
			array("gidNumber", true),
			array("homeDirectory", true),
			array("userPassword",false),
			array("description",false)
		
	);
	
	public static function getName(){
		return "posixAccount";
	}
}

class LamHuanqiuUserGroup extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn",true),
			array("uid", true),
			array("uidNumber", true),
			array("gidNumber", true),
			array("groupName",false),
			array("displayName",false),
			array("o",false),
			array("title",false)
		
	);
	
	public static function getName(){
		return "huanqiuUserGroup";
	}
}


class LamOrganizationalPerson extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn", true),
			array("sn", true),//family name
  			array("description",false),
			array("userPassword",false),
			array("postalCode",false),
  			array("postOfficeBox",false),//email
  			array("telephoneNumber",false),
		    array("telexNumber",false),
  			array("postalAddress",false),
  			array("l",false),//gender
  			array("st",false),//mobile number
  			array("street",false),//group name
  			array("ou",false),//department dn in urlencode 
  			array("title",false),
  			array("email",false),
  			array("physicalDeliveryOfficeName",false),//department o
			array("seeAlso",false)
		
	);
	
	public static function getName(){
		return "organizationalPerson";
	}
}

class LamInetOrgPerson extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn", true),
			array("sn", true),//family name
  			array("description",false),
			array("userPassword",false),
			array("postalCode",false),
  			array("postOfficeBox",false),
  			array("telephoneNumber",false),//telephone number
		    array("telexNumber",false),//telex number
  			array("postalAddress",false),
  			array("l",false),//gender
  			array("st",false),
  			array("street",false),
  			array("ou",false),
  			array("o",false),//department o
			array("departmentNumber",false),//department number
  			array("title",false),//title 
  			array("email",false),//email
  			array("physicalDeliveryOfficeName",false),
			array("seeAlso",false),
			array("x121Address",false),
			array("registeredAddress",false),
			array("destinationIndicator",false),
			array("preferredDeliveryMethod",false),
			array("teletexTerminalIdentifier",false),
			array("internationaliSDNNumber",false),
			array("facsimileTelephoneNumber",false),
			array("businessCategory",false),
			array("carLicense",false),
			array("displayName",false),
			array("employeeNumber",false),//default 0
			array("employeeType",false),
			array("givenName",false),// english name or pinyin
			array("homePhone",false),//home phone
			array("homePostalAddress",false),
			array("initials",false),
			array("jpegPhoto",false),
			array("labeledURI",false),
			array("mail",false),
			array("manager",false),
			array("mobile",false),//mobile
			array("pager",false),
			array("photo",false),
			array("roomNumber",false),
			array("secretary",false),
			array("uid",false),//english or pinyin without whitespace and unique
			array("userCertificate",false),
			array("x500uniqueIdentifier",false),
			array("preferredLanguage",false),//default cn
			array("userSMIMECertificate",false),
			array("userPKCS12",false)
	);
	
	public static function getName(){
		return "inetOrgPerson";
	}
}


class LamHuanqiuPerson extends LdapObjectclass{
	protected static $_attrs=array(
			array("email", true)
			,array("objStatus", false,'int')//status empty or 0 ok; 1.disabled
			,array("groupName",false)//group id list
	);
	
	public static function getName(){
		return "huanqiuPerson";
	}
}


class LamHuanqiuGroup extends LdapObjectclass{
	protected static $_attrs=array(
			array("email", false)
	);
	
	public static function getName(){
		return "huanqiuGroup";
	}
}


class LamHuanqiuDepartment extends LdapObjectclass{
	protected static $_attrs=array(
			array("departmentNumber",true),
			array("parentDepartmentNumber",false),
			array("email", false)
	);
	
	public static function getName(){
		return "huanqiuDepartment";
	}
}


class LamPosixGroup extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn", true),
			array("gidNumber", true),
			array("userPassword",false),
			array("memberUid",false),
			array("description",false)
		
	);
	
	public static function getName(){
		return "posixGroup";
	}
}

class LamOrganizationalRole extends LdapObjectclass{
	protected static $_attrs=array(
			array("cn", true),
  			array("description",false),
			array("postalCode",false),
  			array("postOfficeBox",false),//email
  			array("telephoneNumber",false),
		    array("telexNumber",false),
  			array("postalAddress",false),
  			array("l",false),
  			array("st",false),//mobile number
  			array("ou",false)
	);
	
	public static function getName(){
		return "organizationalRole";
	}
}