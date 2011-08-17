<?php
/*
 * File: models.php
 * Data models to make database accesses more comfortable.
 *
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/utils.php");

db_connect();

/*
 * Variable: $ratatoeskr_settings
 * The global <Settings> object. For internal use.
 */
$ratatoeskr_settings = new Settings("rw");

/*
 * Class: DoesNotExistError
 * This Exception is thrown by an ::by_*-constructor or any array-like object if the desired object is not present in the database.
 */
class DoesNotExistError extends Exception { }

/*
 * Class: AlreadyExistsError
 * This Exception is thrown by an ::create-constructor if the creation of the object would result in duplicates.
 */
class AlreadyExistsError extends Exception { }

/*
 * Class: NotAllowedError
 */
class NotAllowedError extends Exception { }

/*
 * Class: User
 * Data model for Users
 */
class User
{
	private $id;
	
	/*
	 * Variables: Public class properties
	 * 
	 * $username - The username
	 * $pwhash   - SHA1-Hash of the password
	 * $mail     - E-Mail-address
	 * $fullname - The full name of the user.
	 * $language - Users language
	 */
	public $username;
	public $pwhash;
	public $mail;
	public $fullname;
	public $language;
	
	/* Should not be constructed directly. */
	private function __construct() {  }
	
	/*
	 * Constructor: create
	 * Creates a new user.
	 * 
	 * Parameters:
	 * 	$username - The username
	 * 	$pwhash   - SHA1-Hash of the password
	 * 	$mail     - E-Mail-address
	 * 	$fullname - The full name.
	 * 
	 * Returns:
	 * 	An User object
	 */
	public static function create($username, $pwhash, $mail, $fullname)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			global $ratatoeskr_settings;
			qdb("INSERT INTO `PREFIX_users` (`username`, `pwhash`, `mail`, `username`, `language`) VALUES ('%s', '%s', '%s', '%s', '%s')",
				$username, $pwhash, $mail, $fullname, $ratatoeskr_settings["default_language"]);
			$obj = new self;
			
			$obj->id       = mysql_insert_id();
			$obj->username = $username;
			$obj->pwhash   = $pwhash;
			$obj->mail     = $mail;
			$obj->fullname = $fullname;
			$obj->language = $ratatoeskr_settings["default_language"];
			
			return $obj;
		}
		throw new AlreadyExistsError("\"$name\" is already in database.");
	}
	
	/* DANGER: $result must be valid! The calling function has to check this! */
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow == False)
			throw new DoesNotExistError();
		
		$this->id       = $sqlrow["id"];
		$this->username = $sqlrow["username"];
		$this->pwhash   = $sqlrow["pwhash"];
		$this->mail     = $sqlrow["mail"];
		$this->fullname = $sqlrow["fullname"];
		$this->language = $sqlrow["language"];
	}
	
	/*
	 * Constructor: by_id
	 * Get a User object by ID
	 * 
	 * Parameters:
	 * 	$id - The ID.
	 * 
	 * Returns:
	 * 	An User object.
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE `id` = %d", $id);
		
		$obj = new self;
		$obj->populate_by_sqlresult($result);
		return $obj;
	}
	
	/*
	 * Constructor: by_username
	 * Get a User object by username
	 * 
	 * Parameters:
	 * 	$username - The username.
	 * 
	 * Returns:
	 * 	An User object.
	 */
	public static function by_name($username)
	{
		$result = qdb("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE `name` = '%s'", $username);
		
		$obj = new self;
		$obj->populate_by_sqlresult($result);
		return $obj;
	}
	
	/*
	 * Function: all_users
	 * Returns array of all available users.
	 */
	public static function all_users()
	{
		$rv = array();
		
		$result = qdb("SELECT `id` FROM `PREFIX_users` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($sqlrow["id"]);
		
		return $rv;
	}
	
	/*
	 * Function: get_id
	 * Returns:
	 * 	The user ID.
	 */
	public function get_id()
	{
		return $this->id;
	}
	
	/*
	 * Function: save
	 * Saves the object to database
	 */
	public function save()
	{
		qdb("UPDATE `PREFIX_users` SET `username` = '%s', `pwhash` = '%s', `mail` = '%s', `fullname` = '%s', `language` = '%s' WHERE `id` = %d",
			$this->username, $this->pwhash, $this->mail, $this->id, $this->fullname, $this->language);
	}
	
	/*
	 * Function: delete
	 * Deletes the user from the database.
	 * WARNING: Do NOT use this object any longer after you called this function!
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_group_members` WHERE `user` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_users` WHERE `id` = %d", $this->id);
	}
	
	/*
	 * Function: get_groups
	 * Returns:
	 * 	List of all groups where this user is a member (array of <Group> objects).
	 */
	public function get_groups()
	{
		$rv = array();
		$result = qdb("SELECT `group` FROM `PREFIX_group_members` WHERE `user` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
		{
			try
			{
				$rv[] = Group::by_id($sqlrow["group"]);
			}
			catch(DoesNotExistError $e)
			{
				/* WTF?!? This should be fixed! */
				qdb("DELETE FROM `PREFIX_group_members` WHERE `user` = %d AND `group` = %d", $this->id, $sqlrow["group"]);
			}
		}
		return $rv;
	}
	
	/*
	 * Function: member_of
	 * Checks, if the user is a member of a group.
	 * 
	 * Parameters:
	 * 	$group - A Group object
	 * 
	 * Returns:
	 * 	True, if the user is a member of $group. False, if not.
	 */
	public function member_of($group)
	{
		$result = qdb("SELECT COUNT(*) AS `num` FROM `PREFIX_group_members` WHERE `user` = %d AND `group` = %d", $this->id, $group->get_id());
		$sqlrow = mysql_fetch_assoc($result);
		return ($sqlrow["num"] > 0);
	}
}

/*
 * Class: Group
 * Data model for groups
 */
class Group
{
	private $id;
	
	/*
	 * Variables: Public class properties
	 * 
	 * $name - Name of the group.
	 */
	public $name;
	
	/* Should not be constructed directly. */
	private function __construct() {  }
	
	/*
	 * Constructor: create
	 * Creates a new group.
	 * 
	 * Parameters:
	 * 	$name - The name of the group.
	 * 
	 * Returns:
	 * 	An Group object
	 */
	public static function create($name)
	{
		try
		{
			$obj = self::by_username($name);
		}
		catch(DoesNotExistError $e)
		{
			qdb("INSERT INTO `PREFIX_groups` (`name`) VALUES ('%s')", $name);
			$obj = new self;
			
			$obj->id   = mysql_insert_id();
			$obj->name = $name;
			
			return $obj;
		}
		throw new AlreadyExistsError("\"$name\" is already in database.");
	}
	
	/* DANGER: $result must be valid! The calling function has to check this! */
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow == False)
			throw new DoesNotExistError();
		
		$this->id   = $sqlrow["id"];
		$this->name = $sqlrow["name"];
	}
	
	/*
	 * Constructor: by_id
	 * Get a Group object by ID
	 * 
	 * Parameters:
	 * 	$id - The ID.
	 * 
	 * Returns:
	 * 	A Group object.
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name` FROM `PREFIX_groups` WHERE `id` = %d", $id);
		
		$obj = new self;
		$obj->populate_by_sqlresult($result);
		return $obj;
	}
	
	/*
	 * Constructor: by_name
	 * Get a Group object by name
	 * 
	 * Parameters:
	 * 	$name - The group name.
	 * 
	 * Returns:
	 * 	A Group object.
	 */
	public static function by_name($name)
	{
		$result = qdb("SELECT `id`, `name` FROM `PREFIX_groups` WHERE `name` = '%s'", $name);
		
		$obj = new self;
		$obj->populate_by_sqlresult($result);
		return $obj;
	}
	
	/*
	 * Function: all_groups
	 * Returns array of all groups
	 */
	public static function all_groups()
	{
		$rv = array();
		
		$result = qdb("SELECT `id` FROM `PREFIX_groups` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($sqlrow["id"]);
		
		return $rv;
	}
	
	/*
	 * Function: get_id
	 * Returns:
	 * 	The group ID.
	 */
	public function get_id()
	{
		return $this->id;
	}
	
	/*
	 * Function: delete
	 * Deletes the group from the database.
	 * WARNING: Do NOT use this object any longer after you called this function!
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_group_members` WHERE `group` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_groups` WHERE `id` = %d", $this->id);
	}
	
	/*
	 * Function: get_members
	 * Get all members of the group.
	 *
	 * Returns:
	 * 	Array of <User> objects.
	 */
	public function get_members()
	{
		$rv = array();
		$result = qdb("SELECT `user` FROM `PREFIX_group_members` WHERE `group` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
		{
			try
			{
				$rv[] = User::by_id($sqlrow["user"]);
			}
			catch(DoesNotExistError $e)
			{
				/* WTF?!? This should be fixed!*/
				qdb("DELETE FROM `PREFIX_group_members` WHERE `user` = %d AND `group` = %d", $sqlrow["user"], $this->id);
			}
		}
		return $rv;
	}
	
	/*
	 * Function: exclude_user
	 * Excludes user from group.
	 * 
	 * Parameters:
	 * 	$user - <User> object.
	 */
	public function exclude_user($user)
	{
		qdb("DELETE FROM `PREFIX_group_members` WHERE `user` = %d AND `group` = %d", $user->get_id(), $this->id);
	}
	
	/*
	 * Function: include_user
	 * Includes user to group.
	 * 
	 * Parameters:
	 * 	$user - <User> object.
	 */
	public function include_user($user)
	{
		if(!$user->member_of($this))
			qdb("INSERT INTO `PREFIX_group_members` (`user`, `group`) VALUES (%d, %d)", $user->get_id(), $this->id);
	}
}

/*
 * Class: ACL
 * Data model for AccessControlLists
 */
class ACL
{
	private $id;
	
	/*
	 * Variables: Public class properties. *_rights are arrays, which can have this elements: "read", "write", "delete".
	 * 
	 * $users         - Array of <User> objects
	 * $user_rights   - User rights
	 * $groups        - Array of <Group> objects
	 * $group_rights  - Group rights
	 * $others_rights - The rights of everyone
	 */
	public $users;
	public $user_rights;
	public $groups;
	public $group_rights;
	public $others_rights;
	
	/* Should not be constructed directly. */
	private function __construct()
	{
		$this->users         = array();
		$this->user_rights   = array();
		$this->groups        = array();
		$this->group_rights  = array();
		$this->others_rights = array();
	}
	
	/*
	 * Function: get_id
	 * Returns:
	 * 	The ACL ID.
	 */
	public function get_id()
	{
		return $this->id;
	}
	
	private static function filter_invalid_rights($rights)
	{
		return array_filter($rights, function($x) { return in_array($x, array("read", "write", "delete")); });
	}
	
	/*
	 * Constructor: by_json
	 * Creates an ACL object from an JSON string.
	 * 
	 * Parameters:
	 * 	json - The JSON string
	 *
	 * Returns:
	 * 	An ACL object
	 */
	public static function by_json($json)
	{
		$obj = new self;
		$obj->id = 0;
		
		$pre = json_decode($json, True);
		if($pre === NULL)
			return $obj;
		
		$obj->users = array_filter(
			array_map(
				function($x){ try{ return User::by_id($x); } catch(DoesNotExistError $e) { return NULL; } },
				$pre["users"]),
			function($x) { return $x!==NULL; });
		$obj->groups = array_filter(
			array_map(
				function($x){ try{ return Group::by_id($x); } catch(DoesNotExistError $e) { return NULL; } },
				$pre["groups"]),
			function($x) { return $x!==NULL; });
		$obj->user_rights = self::filter_invalid_rights($pre["rights"]["users"]);
		$obj->group_rights = self::filter_invalid_rights($pre["rights"]["groups"]);
		$obj->others_rights = self::filter_invalid_rights($pre["rights"]["others"]);
		
		return $obj;
	}
	
	/*
	 * Constructor: create
	 * Creates a new ACL object.
	 *
	 * Params:
	 * 	$in_db - Should this ACL be stored in the database? (Defaults to True)
	 * 
	 * Returns:
	 * 	ACL object.
	 */
	public static function create($in_db = True)
	{
		$obj = new self;
		
		if($in_db)
		{
			qdb("INSERT INTO `PREFIX_acl` (`acl_json`) VALUES ('%s')", $obj->to_json());
			$obj->id = mysql_insert_id();
		}
		
		return $obj;
	}
	
	/*
	 * Constructor: by_id
	 * Gets ACL object by id.
	 *
	 * Parameters:
	 * 	$id - The ID.
	 *
	 * Returns:
	 * 	An ACL object.
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `acl_json` FROM `PREFIX_acl` WHERE `id` = %d", $id);
		
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow == False)
			throw new DoesNotExistError("ACL with ID = \"$id\" does not exist.");
		$obj = self::by_json($sqlrow["acl_json"]);
		$obj->id = $id;
		
		return $obj;
	}
	
	/*
	 * Function: to_json
	 * Genearets JSON string
	 *
	 * Returns:
	 * 	JSON string.
	 */
	public function to_json()
	{
		return json_encode(array(
			"users"  => array_map(function($x) { return $x->get_id(); }, $this->users),
			"groups" => array_map(function($x) { return $x->get_id(); }, $this->groups),
			"rights" => array(
				"users"  => self::filter_invalid_rights($this->user_rights),
				"groups" => self::filter_invalid_rights($this->group_rights),
				"others" => self::filter_invalid_rights($this->others_rights)
			)
		));
	}
	
	/*
	 * Function: save
	 * If ACL comes from database, save it. Do nothing otherwise.
	 */
	public function save()
	{
		if($this->id > 0)
			qdb("UPDATE `PREFIX_acl` SET `acl_json` = '%s'", $this->to_json);
	}
	
	/*
	 * Function: delete
	 * If ACL comes from database, delete it. Do nothing otherwise.
	 */
	public function delete()
	{
		if($this->id > 0)
		{
			qdb("DELETE FROM `PREFIX_acl` WHERE `id` = %d", $this->id);
			$this->id = 0;
		}
	}
	
	/*
	 * Function: user_rights
	 * Get the rights of $user.
	 *
	 * Parameters:
	 * 	$user - A <User> object.
	 *
	 * Returns:
	 * 	An Array of rights.
	 */
	public function user_rights($user)
	{
		$get_id_func = function($x) { return $x->get_id(); };
		$rights = $this->others_rights;
		if(in_array($user->get_id(), array_map($get_id_func, $this->users)))
			$rights = array_merge($rights, $this->user_rights);
		$temp = array_intersect(array_map($get_id_func, $user->get_groups()), $this->groups);
		if(!empty($temp))
			$rights = array_merge($rights, $this->group_rights);
		return self::filter_invalid_rights(array_unique($rights));
	}
}

/*
 * Class: Translation
 * A translation. Can only be stored using an <Multilingual> object.
 */
class Translation
{
	/*
	 * Varialbes: Public class variables.
	 * 
	 * $text - The translated text.
	 * $texttype - The type of the text. Has only a meaning in a context.
	 */
	public $text;
	public $texttype;
	
	/*
	 * Constructor: __construct
	 * Creates a new Translation object.
	 * IT WILL NOT BE STORED TO DATABASE!
	 *
	 * Parameters:
	 * 	$text - The translated text.
	 * 	$texttype - The type of the text. Has only a meaning in a context.
	 * 
	 * See also:
	 * 	<Multilingual>
	 */
	public function __construct($text, $texttype)
	{
		$this->text     = $text;
		$this->texttype = $texttype;
	}
}

/*
 * Class: Multilingual
 * Container for <Translation> objects.
 * Translations can be accessed array-like. So, if you want the german translation: $translation = $my_multilingual["de"];
 * 
 * See also:
 * 	<languages.php>
 */
class Multilingual implements Countable, ArrayAccess, IteratorAggregate
{
	private $translations;
	private $id;
	private $to_be_deleted;
	private $to_be_created;
	
	private function __construct()
	{
		$this->translations  = array();
		$this->to_be_deleted = array();
		$this->to_be_created = array();
	}
	
	/*
	 * Function: get_id
	 * Retuurns the ID of the object.
	 */
	public function get_id()
	{
		return $this->id;
	}
	
	/*
	 * Constructor: create
	 * Creates a new Multilingual object
	 * 
	 * Returns:
	 * 	An Multilingual object.
	 */
	public static function create()
	{
		$obj = new self;
		qdb("INSERT INTO `PREFIX_multilingual` () VALUES ()");
		$obj->id = mysql_insert_id();
		return $obj;
	}
	
	/*
	 * Constructor: by_id
	 * Gets an Multilingual object by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID.
	 * 
	 * Returns:
	 * 	An Multilingual object.
	 */
	public static function by_id($id)
	{
		$obj = new self;
		$result = qdb("SELECT `id` FROM `PREFIX_multilingual` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow == False)
			throw new DoesNotExistError();
		$obj->id = $id;
		
		$result = qdb("SELECT `language`, `text`, `texttype` FROM `PREFIX_translations` WHERE `multilingual` = %d", $id);
		while($sqlrow = mysql_fetch_assoc($result))
			$obj->translations[$sqlrow["language"]] = new Translation($sqlrow["text"], $sqlrow["texttype"]);
		
		return $obj;
	}
	
	/*
	 * Function: save
	 * Saves the translations to database.
	 */
	public function save()
	{
		foreach($this->to_be_deleted as $deletelang)
			qdb("DELETE FROM `PREFIX_translations` WHERE `multilingual` = %d AND `language` = '%s'", $this->id, $deletelang);
		$this->to_be_deleted = array();
		
		foreach($this->to_be_created as $lang)
			qdb("INSERT INTO `PREFIX_translations` (`multilingual`, `language`, `text`, `texttype`) VALUES (%d, '%s', '%s', '%s')",
				$this->id, $lang, $this->translations[$lang]->text, $this->translations[$lang]->texttype);
		
		foreach($this->translations as $lang => $translation)
		{
			if(!in_array($lang, $this->to_be_created))
				qdb("UPDATE `PREFIX_translations` SET `text` = '%s', `texttype` = '%s' WHERE `multilingual` = %d AND `language` = '%s'",
					$translation->text, $translation->texttype, $this->id, $lang);
		}
		
		$this->to_be_created = array();
	}
	
	/*
	 * Function: delete
	 * Deletes the data from database.
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_translations` WHERE `multilingual` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_multilingual` WHERE `id` = %d", $this->id);
	}
	
	/* Countable interface implementation */
	public function count() { return count($this->languages); }
	
	/* ArrayAccess interface implementation */
	public function offsetExists($offset) { return isset($this->translations[$offset]); }
	public function offsetGet($offset)
	{
		if(isset($this->translations[$offset]))
			return $this->translations[$offset];
		else
			throw new DoesNotExistError();
	}
	public function offsetUnset($offset)
	{
		unset($this->translations[$offset]);
		if(in_array($offset, $this->to_be_created))
			unset($this->to_be_created[array_search($offset, $this->to_be_created)]);
		else
			$this->to_be_deleted[] = $offset;
	}
	public function offsetSet($offset, $value)
	{
		if(!isset($this->translations[$offset]))
		{
			if(in_array($offset, $this->to_be_deleted))
				unset($this->to_be_deleted[array_search($offset, $this->to_be_deleted)]);
			else
				$this->to_be_created[] = $offset;
		}
		$this->translations[$offset] = $value;
	}
	
	/* IteratorAggregate interface implementation */
	public function getIterator() { return new ArrayIterator($this->translations); }
}

/*
 * Variable: $global_settings_keys_buffer
 * Buffer for settings key.
 * NEVER(!) MODIFY DIRECTLY!
 */
$global_settings_keys_buffer = NULL;

/* DO NOT CONSTRUCT THIS YOURSELF! */
class SettingsIterator implements Iterator
{
	private $iter_keys_buffer;
	private $settings_obj;
	private $position = 0;
	
	public function __construct($settings_obj)
	{
		global $global_settings_keys_buffer;
		$this->settings_obj = $settings_obj;
		$this->iter_keys_buffer = array_slice($global_settings_keys_buffer, 0); /* a.k.a. copying */
	}
	
	function rewind()  { return $this->position = 0; }
	function current() { return $this->settings_obj->offsetGet($this->iter_keys_buffer[$this->position]); }
	function key()     { return $this->iter_keys_buffer[$this->position]; }
	function next()    { ++$this->position; }
	function valid()   { return isset($this->iter_keys_buffer[$this->position]); }
}

/*
 * Class: Settings
 * Representing the settings.
 * You can access them like an array.
 */
class Settings implements Countable, ArrayAccess, IteratorAggregate
{
	private $rw;
	
	/*
	 * Constructor: __construct
	 * Creates a new Settings object.
	 *
	 * Parameters:
	 * 	$mode - "rw" for read-write access, "r" for read-only access (default)
	 */
	public function __construct($mode="r")
	{
		global $global_settings_keys_buffer;
		if($global_settings_keys_buffer === NULL)
		{
			$global_settings_keys_buffer = array();
			$result = qdb("SELECT `key` FROM `PREFIX_settings_kvstorage` WHERE 1");
			while($sqlrow = mysql_fetch_assoc($result))
				$global_settings_keys_buffer[] = $sqlrow["key"];
		}
		$this->rw = ($mode == "rw");
	}
	
	/* Countable interface implementation */
	public function count() { global $global_settings_keys_buffer; return count($global_settings_keys_buffer); }
	
	/* ArrayAccess interface implementation */
	public function offsetExists($offset) { global $global_settings_keys_buffer; return in_array($offset, $global_settings_keys_buffer); }
	public function offsetGet($offset)
	{
		global $global_settings_keys_buffer;
		if($this->offsetExists($offset))
		{
			$result = qdb("SELECT `value` FROM `PREFIX_settings_kvstorage` WHERE `key` = '%s'", $offset);
			$sqlrow = mysql_fetch_assoc($result);
			return unserialize(base64_decode($sqlrow["value"]));
		}
		else
			throw new DoesNotExistError();
	}
	public function offsetUnset($offset)
	{
		global $global_settings_keys_buffer;
		if(!$this->rw)
			throw new NotAllowedError();
		unset($global_settings_keys_buffer[array_search($offset, $global_settings_keys_buffer)]);
		qdb("DELETE FROM `PREFIX_settings_kvstorage` WHERE `key` = '%s'", $offset);
	}
	public function offsetSet($offset, $value)
	{
		global $global_settings_keys_buffer;
		if(!$this->rw)
			throw new NotAllowedError();
		if(in_array($offset, $global_settings_keys_buffer))
			qdb("UPDATE `PREFIX_settings_kvstorage` SET `value` = '%s' WHERE `key` = '%s'",  base64_encode(serialize($value)), $offset);
		else
		{
			$global_settings_keys_buffer[] = $offset;
			qdb("INSERT INTO `PREFIX_settings_kvstorage` (`key`, `value`) VALUES ('%s', '%s')", $offset, base64_encode(serialize($value)));
		}
	}
	
	/* IteratorAggregate interface implementation */
	public function getIterator() { return new SettingsIterator($this); }
}

/*
 * Class: PluginKVStorage
 * A Key-Value-Storage for Plugins
 * Can be accessed like an array.
 * Keys are strings and Values can be everything serialize() can process.
 */
class PluginKVStorage implements Countable, ArrayAccess, Iterator
{
	private $plugin_id;
	private $keybuffer;
	private $counter;
	
	/*
	 * Constructor: __construct
	 * 
	 * Parameters:
	 * 	$plugin_id - The ID of the Plugin.
	 */
	public function __construct($plugin_id)
	{
		$this->keybuffer = array();
		$this->plugin_id = $plugin_id;
		
		$result = qdb("SELECT `key` FROM `PREFIX_plugin_kvstorage` WHERE `plugin` = %d", $plugin_id);
		while($sqlrow = mysql_fetch_assoc($result))
			$this->keybuffer[] = $sqlrow["key"];
		
		$this->counter = 0;
	}
	
	/* Countable interface implementation */
	public function count() { return count($this->keybuffer); }
	
	/* ArrayAccess interface implementation */
	public function offsetExists($offset) { return in_array($offset, $this->keybuffer); }
	public function offsetGet($offset)
	{
		if($this->offsetExists($offset))
		{
			$result = qdb("SELECT `value` FROM `PREFIX_plugin_kvstorage` WHERE `key` = '%s' AND `plugin` = %d", $offset, $this->plugin_id);
			$sqlrow = mysql_fetch_assoc($result);
			return unserialize(base64_decode($sqlrow["value"]));
		}
		else
			throw new DoesNotExistError();
	}
	public function offsetUnset($offset)
	{
		if($this->offsetExists($offset))
		{
			unset($this->keybuffer[array_search($offset, $this->keybuffer)]);
			$this->keybuffer = array_merge($this->keybuffer);
			qdb("DELETE FROM `PREFIX_plugin_kvstorage` WHERE `key` = '%s' AND `plugin` = %d", $offset, $this->plugin_id);
		}
	}
	public function offsetSet($offset, $value)
	{
		if($this->offsetExists($offset))
			qdb("UPDATE `PREFIX_plugin_kvstorage` SET `value` = '%s' WHERE `key` = '%s' AND `plugin` = %d",
				base64_encode(serialize($value)), $offset, $this->plugin_id);
		else
		{
			qdb("INSERT INTO `PREFIX_plugin_kvstorage` (`plugin`, `key`, `value`) VALUES (%d, '%s', '%s')",
				$this->plugin_id, $offset, base64_encode(serialize($value)));
			$this->keybuffer[] = $offset;
		}
	}
	
	/* Iterator interface implementation */
	function rewind()  { return $this->position = 0; }
	function current() { return $this->offsetGet($this->keybuffer[$this->position]); }
	function key()     { return $this->keybuffer[$this->position]; }
	function next()    { ++$this->position; }
	function valid()   { return isset($this->keybuffer[$this->position]); }
}

/*
 * Class: Comment
 * Representing a user comment
 */
class Comment
{
	private $id;
	private $article;
	private $language;
	private $timestamp;
	
	/*
	 * Variables: Public class variables.
	 * 
	 * $author_name - Name of comment author.
	 * $author_mail - E-Mail of comment author.
	 * $text        - Comment text.
	 * $visible     - Should the comment be visible?
	 */
	public $author_name;
	public $author_mail;
	public $text;
	public $visible;
	
	/* Should not be constructed manually. */
	private function __construct() { }
	
	/*
	 * Functions: Getters
	 * 
	 * get_id        - Gets the comment ID.
	 * get_article   - Gets the article.
	 * get_language  - Gets the language.
	 * get_timestamp - Gets the timestamp.
	 */
	public function get_id()        { return $this->id;        }
	public function get_article()   { return $this->article;   }
	public function get_language()  { return $this->language;  }
	public function get_timestamp() { return $this->timestamp; }
	
	/*
	 * Constructor: create
	 * Creates a new comment.
	 * Automatically sets the $timestamp and $visible (default form setting "comment_visible_default").
	 * 
	 * Parameters:
	 * 	$article  - An <Article> Object.
	 * 	$language - Which language? (see <languages.php>)
	 */
	public static function create($article, $language)
	{
		global $ratatoeskr_settings;
		$obj = new self;
		
		qdb("INSERT INTO `PREFIX_comments` (`article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`) VALUES (%d, '%s', '', '', '', UNIX_TIMESTAMP(NOW()), %d)",
			$article->id, $language, $ratatoeskr_settings["comment_visible_default"] ? 1 : 0);
		
		$obj->id          = mysql_insert_id();
		$obj->article     = $article;
		$obj->language    = $language;
		$obj->author_name = "";
		$obj->author_mail = "";
		$obj->text        = "";
		$obj->timestamp   = time();
		$obj->visible     = $ratatoeskr_settings["comment_visible_default"];
	}
	
	/*
	 * Constructor: by_id
	 * Gets a Comment by ID.
	 * 
	 * Parameters:
	 * 	$id - The comments ID.
	 */
	public static function by_id($id)
	{
		$obj = new self;
		
		$result = qdb("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, visible` FROM `PREFIX_comments` WHERE `id` = %d",
			$id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$obj->id          = $sqlrow["id"];
		$obj->article     = Article::by_id($sqlrow["article"]);
		$obj->language    = $sqlrow["language"];
		$obj->author_name = $sqlrow["author_name"];
		$obj->author_mail = $sqlrow["author_mail"];
		$obj->text        = $sqlrow["text"];
		$obj->timestamp   = $sqlrow["timestamp"];
		$obj->visible     = $sqlrow["visible"] == 1;
		
		return $obj;
	}
	
	/*
	 * Function: save
	 * Save changes to database.
	 */
	public function save()
	{
		qdb("UPDATE `PREFIX_comments` SET `author_name` = '%s', `author_mail` = '%s', `text` = '%s', `visible` = %d` WHERE `id` = %d`",
			$this->author_name, $this->author_mail, $this->text, ($this->visible ? 1 : 0), $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_comments` WHERE `id` = %d", $this->id);
	}
}

/*
 * Class: Style
 * Represents a Style
 */
class Style
{
	private $id;
	
	/*
	 * Variables: Public class variables.
	 * 
	 * $name - The name of the style.
	 * $code - The CSS code.
	 * $acl  - An <ACL> object.
	 */
	public $name;
	public $code;
	public $acl;
	
	/* Should not be constructed manually */
	private function __construct() { }
	
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$this->id   = $sqlrow["id"];
		$this->name = $sqlrow["name"];
		$this->code = $sqlrow["code"];
		$this->acl  = ACL::by_id($sqlrow["acl"]);
	}
	
	/*
	 * Function: get_id
	 */
	public function get_id() { return $this->id; }
	
	/*
	 * Constructor: create
	 * Create a new style.
	 * 
	 * Parameters:
	 * 	$name - A name for the new style.
	 */
	public static function create($name)
	{
		$obj = new self;
		$obj->acl  = ACL::create(True);
		$obj->name = $name;
		$obj->code = "";
		
		qdb("INSERT INTO `PREFIX_styles` (`name`, `code`, `acl`) VALUES ('%s', '', %d)",
			$name, $this->acl->get_id());
		
		$obj->id = mysql_insert_id();
		return $obj;
	}
	
	/*
	 * Constructor: by_id
	 * Gets a style object by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID
	 */
	public static function by_id($id)
	{
		$obj = new seld;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `acl`, `name`, `code` FROM `PREFIX_styles` WHERE `id` = %d", $id));
		return $obj;
	}
	
	/*
	 * Constructor: by_name
	 * Gets a style object by name.
	 * 
	 * Parameters:
	 * 	$name - The name.
	 */
	public static function by_name($id)
	{
		$obj = new seld;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `acl`, `name`, `code` FROM `PREFIX_styles` WHERE `name` = '%s'", $name));
		return $obj;
	}
	
	/*
	 * Function: save
	 * Save changes to database.
	 */
	public function save()
	{
		$this->acl->save();
		qdb("UPDATE `PREFIX_styles` SET `name` = '%s', `code` = '%s', `acl` = %d WHERE `id` = %d",
			$this->name, $this->code, $this->acl, $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		$this->acl->delete();
		qdb("DELETE FROM `PREFIX_styles` WHERE `id` = %d", $this->id);
	}
}

/*
 * Class: PluginDB
 * The representation of a plugin in the database.
 * See <plugin.php> for loader functions and higher-level plugin access.
 */
class PluginDB
{
	private $id;
	
	/*
	 * Variables: Public class variables.
	 *
	 * $name        - Plugin name
	 * $class       - Main class of the plugin
	 * $version     - Plugin version
	 * $author      - Plugin author
	 * $author_url  - Website of author
	 * $description - Description of plugin
	 * $help        - Help page (HTML)
	 * $phpcode     - The plugin code
	 * $active      - Is the plugin active?
	 */
	
	public $name        = "";
	public $class       = "";
	public $version     = "";
	public $author      = "";
	public $author_url  = "";
	public $description = "";
	public $help        = "";
	public $phpcode     = "";
	public $active      = False;
	
	private function __construct() { }
	
	/*
	 * Function: get_id
	 */
	public function get_id() { return $this->id; }
	
	/*
	 * Constructor: create
	 * Creates a new, empty plugin database entry
	 */
	public static function create()
	{
		$obj = new self;
		qdb("INSERT INTO `PREFIX_plugins` () VALUES ()");
		$obj->id = mysql_insert_id();
		return $obj;
	}
	
	/*
	 * Constructor: by_id
	 * Gets plugin by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID
	 */
	public static function by_id($id)
	{
		$obj = new self;
		
		$result = qdb("SELECT `name`, `class`, `version`, `author`, `author_url`, `description`, `help`, `phpcode`, `active` FROM `PREFIX_plugins` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$obj->id          = $id;
		$obj->name        = $sqlrow["name"];
		$obj->class       = $sqlrow["class"];
		$obj->version     = $sqlrow["version"];
		$obj->author      = $sqlrow["author"];
		$obj->author_url  = $sqlrow["author_url"];
		$obj->description = $sqlrow["description"];
		$obj->help        = $sqlrow["help"];
		$obj->phpcode     = $sqlrow["phpcode"];
		$obj->active      = ($sqlrow["active"] == 1);
		
		return $obj;
	}
	
	/*
	 * Constructor: all
	 * Gets all Plugins
	 * 
	 * Returns:
	 * 	List of <PluginDB> objects.
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_plugins` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($sqlrow["id"]);
		return $rv;
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		qdb("UPDATE `PREFIX_plugins` SET `name` = '%s', `class` = '%s', `version` = '%s', `author` = '%s', `author_url` = '%s', `description` = '%s', `help` = '%s', `phpcode` = '%s', `active` = %d WHERE `id` = %d`",
			$this->name, $this->class, $this->version, $this->author, $this->author_url, $this->description, $this->help, $this->phpcode, $this->active ? 1 : 0, $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_plugins` WHERE `id` = %d", $this->id);
	}
	
	/*
	 * Function get_kvstorage
	 * Get the KeyValue Storage for the plugin.
	 * 
	 * Returns:
	 * 	An <PluginKVStorage> object.
	 */
	public function get_kvstorage()
	{
		return new PluginKVStorage($this->id);
	}
}

/*
 * Class: Section
 * Representing a section
 */
class Section
{
	private $id;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $name     - The name of the section.
	 * $title    - The title of the section (a <Multilingual> object).
	 * $template - Name of the template.
	 * $styles   - List of <Style> objects.
	 */
	public $name;
	public $title;
	public $template;
	public $styles;
	
	private function __construct() {}
	
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$this->id       = $sqlrow["id"];
		$this->name     = $sqlrow["name"];
		$this->title    = Multilingual::by_id($sqlrow["title"]);
		$this->template = $sqlrow["template"];
		$this->styles   = array_filter_empty(array_map(
			function($id) { try{return Style::by_id($id);}catch(DoesNotExistError $e){ return "";} },
			array_filter_empty(explode("+", $sqlrow["styles"]))));
	}
	
	/*
	 * Function: get_id
	 */
	public function get_id() { return $this->id; }
	
	/*
	 * Constructor: create
	 * Creates a new section.
	 * 
	 * Parameters:
	 * 	$name - The name of the new section.
	 */
	public static function create($name)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			$obj           = new self;
			$obj->name     = $name;
			$obj->title    = Multilingual::create();
			$obj->template = "";
			$obj->styles   = array();
			
			$result = qdb("INSERT INTO `PREFIX_sections` (`name`, `title`, `template`, `styles`) VALUES ('%s', %d, '', '')",
				$name, $obj->title->get_id());
			
			$obj->id = mysql_insert_id();
			
			return $obj;
		}
		
		throw new AlreadyExistsError();
	}
	
	/*
	 * Constructor: by_id
	 * Gets section by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID.
	 */
	public static function by_id($id)
	{
		$obj = new self;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `name`, `title`, `template`, `styles` FROM `PREFIX_sections` WHERE `id` = %d", $id));
		return $obj;
	}
	
	/*
	 * Constructor: by_name
	 * Gets section by name.
	 * 
	 * Parameters:
	 * 	$name - The name.
	 */
	public static function by_name($name)
	{
		$obj = new self;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `name`, `title`, `template`, `styles` FROM `PREFIX_sections` WHERE `name` = '%s'", $name));
		return $obj;
	}
	
	/*
	 * Constructor: all
	 * Gets all sections.
	 * 
	 * Returns:
	 * 	Array of Section objects.
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_sections` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($sqlrow["id"]);
		return $rv;
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		$styles = "+";
		foreach($this->styles as $style)
		{
			$style->save();
			$styles .= $style->get_id() . "+";
		}
		if($styles == "+")
			$styles = "";
		
		$this->title->save();
		qdb("UPDATE `PREFIX_sections` SET `name` = '%s', `title` = %d, `template` = '%s', `styles` = '%s' WHERE `id` = %d",
			$this->name, $this->title->get_id(), $this->template, $styles);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		$this->title->delete();
		qdb("DELETE FROM `PREFIX_sections` WHERE `id` = %d", $this->id);
	}
	
	/*
	 * Function: get_articles
	 * Get all articles in this section.
	 * 
	 * Returns:
	 * 	Array of <Article> objects
	 */
	public function get_articles()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_articles` WHERE `section` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Article::by_id($sqlrow["id"]);
		return $rv;
	}
}

/*
 * Class: Tag
 * Representation of a tag
 */
class Tag
{
	private $id;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $name - The name of the tag
	 * $title - The title (an <Multilingual> object)
	 */
	public $name;
	public $title;
	
	/*
	 * Function: get_id
	 */
	public function get_id() { return $this->id; }
	
	private function __construct() {}
	
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$this->id    = $sqlrow["id"];
		$this->name  = $sqlrow["name"];
		$this->title = Multilingual::by_id($sqlrow["title"]);
	}
	
	/*
	 * Constructor: create
	 * Create a new tag.
	 * 
	 * Parameters:
	 * 	$name - The name
	 */
	public static function create($name)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			$obj = new self;
			
			$obj->name  = $name;
			$obj->title = Multilingual::create();
			
			qdb("INSERT INTO `PREFIX_tags` (`name`, `title`) VALUES ('%s', %d)",
				$name, $obj->title->get_id());
			$obj->id = mysql_insert_id();
			
			return $obj;
		}
		throw new AlreadyExistsError();
	}
	
	/*
	 * Constructor: by_id
	 * Get tag by ID
	 * 
	 * Parameters:
	 * 	$id - The ID
	 */
	public static function by_id($id)
	{
		$obj = new self;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `id` = %d", $id));
		return $obj;
	}
	
	/*
	 * Constructor: by_name
	 * Get tag by name
	 * 
	 * Parameters:
	 * 	$name - The name
	 */
	public static function by_name($name)
	{
		$obj = new self;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `name` = '%s'", $name));
		return $obj;
	}
	
	/*
	 * Constructor: all
	 * Get all tags
	 * 
	 * Returns:
	 * 	Array of Tag objects.
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_tags` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($result["id"]);
		return $rv;
	}
	
	/*
	 * Function: get_articles
	 * Get all articles that are tagged with this tag
	 * 
	 * Returns:
	 * 	Array of <Article> objects
	 */
	public function get_articles()
	{
		$rv = array();
		$result = qdb("SELECT `article` FROM `PREFIX_article_tag_relations` WHERE `tag` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Article::by_id($sqlrow["id"]);
		return $rv;
	}
	
	/*
	 * Function: count_articles
	 * 
	 * Retutrns:
	 * 	The number of articles that are tagged with this tag.
	 */
	public function count_articles()
	{
		$result = qdb("SELECT COUNT(*) AS `num` FROM `PREFIX_article_tag_relations` WHERE `tag` = %d", $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		return $sqlrow["num"];
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		$this->title->save();
		qdb("UPDATE `PREFIX_tags` SET `name` = '%s', `title` = %d` WHERE `id` = %d",
			$this->name, $this->title->get_id(), $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		$this->title->delete();
		qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `tag` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_tags` WHERE `id` = %d", $this->id);
	}
}

/*
 * Class: UnknownFileFormat
 * Exception that will be thrown, if a input file has an unsupported file format.
 */
class UnknownFileFormat extends Exception { }

/*
 * Class: IOError
 * This Exception is thrown, if a IO-Error occurs (file not available, no read/write acccess...).
 */
class IOError extends Exception { }

/*
 * Array: $imagetype_file_extensions
 * Array of default file extensions for most IMAGETYPE_* constants
 */
$imagetype_file_extensions = array(
	IMAGETYPE_GIF     => "gif",
	IMAGETYPE_JPEG    => "jpg",
	IMAGETYPE_PNG     => "png",
	IMAGETYPE_BMP     => "bmp",
	IMAGETYPE_TIFF_II => "tif",
	IMAGETYPE_TIFF_MM => "tif",
);

/*
 * Class: Image
 * Representation of an image entry.
 */
class Image
{
	private $id;
	private $filename;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $name - The image name
	 * $alt - The alternative text (a <Multilingual> object)
	 * $acl - An <ACL> object
	 */
	public $name;
	public $alt;
	public $acl;
	
	private function __construct() { }
	
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$this->name = $sqlrow["name"];
		$this->alt  = Multilingual::by_id($sqlrow["alt"]);
		$this->file = $sqlrow["file"];
		$this->acl  = ACL::by_id($sqlrow["acl"]);
	}
	
	/*
	 * Functions: Getters
	 * 
	 * get_id - Get the ID
	 * get_filename - Get the filename
	 */
	public function get_id()       { return $this->id;   }
	public function get_filename() { return $this->file; }
	
	/*
	 * Constructor: create
	 * Create a new image
	 * 
	 * Parameters:
	 * 	$name - The name for the image
	 * 	$file - A uploaded image file (move_uploaded_file must be able to move the file!).
	 */
	public static function create($name, $file)
	{
		$obj = new self;
		$obj->name = $name;
		$obj->alt  = Multilingual::create();
		$obj->acl  = ACL::create();
		$obj->file = "0";
		
		qdb("INSERT INTO `PREFIX_images` (`name`, `alt`, `file`, `acl`) VALUES ('%s', %d, '0', %d)",
			$name, $obj->alt->get_id(), $obj->acl->get_id());
		
		$obj->id = mysql_insert_id();
		try
		{
			$obj->exchange_image($file);
		}
		catch(Exception $e)
		{
			$obj->delete();
			throw $e;
		}
		return $obj;
	}
	
	/*
	 * Constructor: by_id
	 * Get iimage by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID
	 */
	public static function by_id($id)
	{
		$obj = new self;
		$obj->populate_by_sqlresult(qdb("SELECT `id`, `name`, `alt`, `file`, `acl` FROM `PREFIX_images` WHERE `id` = %d", $id));
		return $obj;
	}
	
	/*
	 * Constructor: all
	 * Gets all images.
	 * 
	 * Returns:
	 * 	Array of <Image> objects.
	 */
	public function all()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_images` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($sqlrow["id"]);
		return $rv;
	}
	
	/*
	 * Function: exchange_image
	 * Exchanges image file. Also saves object to database.
	 * 
	 * Parameters:
	 * 	$file - Location of new image.(move_uploaded_file must be able to move the file!)
	 */
	public function exchange_image($file)
	{
		global $imagetype_file_extensions;
		if(!is_file($file))
			throw new IOError("\"$file\" is not available");
		$imageinfo = getimagesize($file);
		if($imageinfo === False)
			throw new UnknownFileFormat();
		if(!isset($imagetype_file_extensions[$imageinfo[2]]))
			throw new UnknownFileFormat();
		if(is_file(SITE_BASE_PATH . "/images/" . $this->file))
			unlink(SITE_BASE_PATH . "/images/" . $this->file);
		$new_fn = $this->id . $imagetype_file_extensions[$imageinfo[2]];
		move_uploaded_file($file, SITE_BASE_PATH . "/images/" . $new_fn);
		$this->file = $new_fn;
		$this->save();
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		$this->acl->save();
		$this->alt->save();
		qdb("UPDATE `PREFIX_images` SET `name` = '%s', `alt` = %d, `file` = '%s', `acl` = %d WHERE `id` = %d",
			$this->name, $this->alt->get_id(), $this->file, $this->acl->get_id(), $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		$this->acl->delete();
		$this->alt->delete();
		if(is_file(SITE_BASE_PATH . "/images/" . $this->file))
			unlink(SITE_BASE_PATH . "/images/" . $this->file);
		qdb("DELETE FROM `PREFIX_images` WHERE `id` = %d", $this->id);
	}
}

/*
 * Constants: Possible <Article>::$status values.
 * 
 * ARTICLE_STATUS_HIDDEN - Article is hidden
 * ARTICLE_STATUS_LIVE   - Article is visible / live
 * ARTICLE_STATUS_STICKY - Article is sticky
 */
define("ARTICLE_STATUS_HIDDEN", 0);
define("ARTICLE_STATUS_LIVE",   1);
define("ARTICLE_STATUS_STICKY", 2);

/*
 * Class: Article
 * Representation of an article
 */
class Article
{
	private $id;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $urltitle - URL title
	 * $title - Title (an <Multilingual> object)
	 * $text - The text (an <Multilingual> object)
	 * $excerpt - Excerpt (an <Multilingual> object)
	 * $meta - Keywords, comma seperated
	 * $custom - Custom fields, is an array
	 * $acl - an <ACL> object
	 * $article_image - The article <Image>. If none: NULL
	 * $status - One of the ARTICLE_STATUS_* constants
	 * $section - <Section>
	 * $timestamp - Timestamp
	 * $allow_comments - Are comments allowed?
	 * $tags - Arrray of <Tag> objects
	 */
	public $urltitle;
	public $title;
	public $text;
	public $excerpt;
	public $meta;
	public $custom;
	public $acl;
	public $article_image;
	public $status;
	public $section;
	public $timestamp;
	public $allow_comments;
	public $tags;
	
	private function __construct()
	{
		$this->tags = array();
	}
	
	private function populate_by_sqlresult($result)
	{
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		$this->id             = $sqlrow["id"];
		$this->urltitle       = $sqlrow["urltitle"];
		$this->title          = Multilingual::by_id($sqlrow["title"]);
		$this->text           = Multilingual::by_id($sqlrow["text"]);
		$this->excerpt        = Multilingual::by_id($sqlrow["excerpt"]);
		$this->meta           = $sqlrow["meta"];
		$this->custom         = unserialize(base64_decode($sqlrow["custom"]));
		$this->acl            = ACL::by_id($sqlrow["acl"]);
		$this->article_image  = $sqlrow["article_image"] == 0 ? NULL : Image::by_id($sqlrow["article_image"]);
		$this->status         = $sqlrow["status"];
		$this->section        = Section::by_id($sqlrow["section"]);
		$this->timestamp      = $sqlrow["timestamp"];
		$this->allow_comments = $sqlrow["allow_comments"] == 1;
		
		$result = qdb("SELECT `tag` FROM `PREFIX_article_tag_relations` WHERE `article` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$this->tags[] = Tag::by_id($sqlrow["tag"]);
	}
	
	/*
	 * Function: get_id
	 */
	public function get_id() { return $this->id; }
	
	/*
	 * Constructor: create
	 * Create a new Article object
	 */
	public static function create()
	{
		global $ratatoeskr_settings;
		$obj = new self;
		$obj->urltitle       = "";
		$obj->title          = Multilingual::create();
		$obj->text           = Multilingual::create();
		$obj->excerpt        = Multilingual::create();
		$obj->meta           = "";
		$obj->custom         = array();
		$obj->acl            = ACL::create();
		$obj->article_image  = NULL;
		$obj->status         = ARTICLE_STATUS_HIDDEN;
		$obj->section        = Section::by_id($ratatoeskr_settings["default_section"]);
		$obj->timestamp      = time();
		$obj->allow_comments = $ratatoeskr_settings["allow_comments_default"];
		
		qdb("INSERT INTO `PREFIX_articles` (`urltitle`, `title`, `text`, `excerpt`, `meta`, `custom`, `acl`, `article_image`, `status`, `section`, `timestamp`, `allow_comments`) VALUES ('', %d, %d, %d, '', '%s', %d, 0, %d, %d, %d, %d)",
			$obj->title->get_id(),
			$obj->text->get_id(),
			$obj->excerpt->get_id(),
			base64_encode(serialize($obj->custom)),
			$obj->acl->get_id(),
			$obj->status,
			$obj->section->get_id(),
			$obj->timestamp,
			$obj->allow_comments ? 1 : 0);
		$obj->id = mysql_insert_id();
		return $obj;
	}
	
	/*
	 * Constructor: by_id
	 * Get by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID.
	 */
	public static function by_id($id)
	{
		$obj = new self;
		$obj ->populate_by_sqlresult(qdb(
			"SELECT `id`, `urltitle`, `title`, `text`, `excerpt`, `meta`, `acl`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `id` = %d", $id
		));
		return $obj;
	}
	
	/*
	 * Constructor: by_urlname
	 * Get by urlname
	 * 
	 * Parameters:
	 * 	$urlname - The urlname
	 */
	public static function by_urlname($id)
	{
		$obj = new self;
		$obj ->populate_by_sqlresult(qdb(
			"SELECT `id`, `urltitle`, `title`, `text`, `excerpt`, `meta`, `custom`, `acl`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `urlname` = '%s'", $urlname
		));
		return $obj;
	}
	
	/*
	 * Constructor: all
	 * Get all articles
	 * 
	 * Returns:
	 * 	Array of Article objects
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_articles` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_id($sqlrow["id"]);
		return $rv;
	}
	
	/*
	 * Function: get_comments
	 * Getting comments for this article.
	 * 
	 * Returns:
	 * 	Array of <Comment> objects.
	 */
	public function get_comments()
	{
		$rv = array();
		$result = qdb("SELECT `id` FROM `PREFIX_comments` WHERE `article` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Comment::by_id($sqlrow["id"]);
		return $rv;
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		$this->title->save();
		$this->text->save();
		$this->excerpt->save();
		$this->acl->save();
		
		qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `article`= %d", $this->id);
		
		$articleid = $this->id;
		/* So we just need to fire one query instead of count($this->tags) queries. */
		if(!empty($this->tags))
			qdb(
				"INSERT INTO `PREFIX_article_tag_relations` (`article`, `tag`) VALUES " .
				implode(",",
					array_map(function($tag) use ($articleid){ return qdb_fmt("(%d, %d)", $articleid, $tag->get_id()); },
					$this->tags)
				)
			);
		
		qdb("UPDATE `PREFIX_articles` SET `urltitle` = '%s', `title` = %d, `text` = %d, `excerpt` = %d, `meta` = '%s', `custom` = '%s', `acl` = %d, `article_image` = %d, `status` = %d, `section` = %d, `timestamp` = %d, `allow_comments` = %d WHERE `id` = %d",
			$this->urltitle,
			$this->title->get_id(),
			$this->text->get_id(),
			$this->excerpt->get_id(),
			$this->meta,
			base64_encode(serialize($this->custom)),
			$this->acl->get_id(),
			$this->article_image === NULL ? 0 : $this->article_image->get_id(),
			$this->status,
			$this->section->get_id(),
			$this->timestamp,
			$this->allow_comments ? 1 : 0,
			$this->id
		);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		$this->title->delete();
		$this->text->delete();
		$this->excerpt->delete();
		$this->acl->delete();
		
		foreach($this->get_comments() as $comment)
			$comment->delete();
		
		qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `article` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_articles` WHERE `id` = %d", $this->id);
	}
}

?>
