<?php
/*
 * File: ratatoeskr/sys/models.php
 * Data models to make database accesses more comfortable.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/utils.php");
require_once(dirname(__FILE__) . "/../libs/kses.php");
require_once(dirname(__FILE__) . "/textprocessors.php");
require_once(dirname(__FILE__) . "/pluginpackage.php");

db_connect();

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
 * Variable: $ratatoeskr_settings
 * The global <Settings> object. Can be accessed like an array.
 * Has these fields:
 * 
 * "default_language"        - The Language code of the default language.
 * "comment_visible_default" - True, if comments should be visible by default.
 * "allow_comments_default"  - True, if comments should be allowed by default.
 * "default_section"         - The id of the default <Section>.
 * "comment_textprocessor"   - The textprocessor to be used for comments.
 * "languages"               - Array of activated languages.
 * "last_db_cleanup"         - Timestamp of the last database cleanup.
 */
$ratatoeskr_settings = NULL;

/*
 * Constants: ARTICLE_STATUS_
 * Possible <Article>::$status values.
 * 
 * ARTICLE_STATUS_HIDDEN - Article is hidden (Numeric: 0)
 * ARTICLE_STATUS_LIVE   - Article is visible / live (Numeric: 1)
 * ARTICLE_STATUS_STICKY - Article is sticky (Numeric: 2)
 */
define("ARTICLE_STATUS_HIDDEN", 0);
define("ARTICLE_STATUS_LIVE",   1);
define("ARTICLE_STATUS_STICKY", 2);

/*
 * Class: DoesNotExistError
 * This Exception is thrown by an ::by_*-constructor or any array-like object if the desired object is not present in the database.
 */
class DoesNotExistError extends Exception { }

/*
 * Class: AlreadyExistsError
 * This Exception is thrown by an ::create-constructor or a save-method, if the creation/modification of the object would result in duplicates.
 */
class AlreadyExistsError extends Exception { }

/*
 * Class: NotAllowedError
 */
class NotAllowedError extends Exception { }

abstract class BySQLRowEnabled
{
	protected function __construct() {  }
	
	abstract protected function populate_by_sqlrow($sqlrow);
	
	protected static function by_sqlrow($sqlrow)
	{
		$obj = new static();
		$obj->populate_by_sqlrow($sqlrow);
		return $obj;
	}
}

/*
 * Class: User
 * Data model for Users
 */
class User extends BySQLRowEnabled
{
	private $id;
	
	/*
	 * Variables: Public class properties
	 * 
	 * $username - The username.
	 * $pwhash   - <PasswordHash> of the password.
	 * $mail     - E-Mail-address.
	 * $fullname - The full name of the user.
	 * $language - Users language
	 */
	public $username;
	public $pwhash;
	public $mail;
	public $fullname;
	public $language;
	
	/*
	 * Constructor: create
	 * Creates a new user.
	 * 
	 * Parameters:
	 * 	$username - The username
	 * 	$pwhash   - <PasswordHash> of the password
	 * 
	 * Returns:
	 * 	An User object
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public static function create($username, $pwhash)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			global $ratatoeskr_settings;
			qdb("INSERT INTO `PREFIX_users` (`username`, `pwhash`, `mail`, `fullname`, `language`) VALUES ('%s', '%s', '', '', '%s')",
				$username, $pwhash, $ratatoeskr_settings["default_language"]);
			$obj = new self();
			
			$obj->id       = mysql_insert_id();
			$obj->username = $username;
			$obj->pwhash   = $pwhash;
			$obj->mail     = "";
			$obj->fullname = "";
			$obj->language = $ratatoeskr_settings["default_language"];
			
			return $obj;
		}
		throw new AlreadyExistsError("\"$name\" is already in database.");
	}
	
	protected function populate_by_sqlrow($sqlrow)
	{
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
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: by_name
	 * Get a User object by username
	 * 
	 * Parameters:
	 * 	$username - The username.
	 * 
	 * Returns:
	 * 	An User object.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_name($username)
	{
		$result = qdb("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE `username` = '%s'", $username);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Function: all
	 * Returns array of all available users.
	 */
	public static function all()
	{
		$rv = array();
		
		$result = qdb("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		
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
	 * 
	 * Throws:
	 * 	AlreadyExistsError
	 */
	public function save()
	{
		$result = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_users` WHERE `username` = '%s' AND `id` != %d", $this->username, $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow["n"] > 0)
			throw new AlreadyExistsError();
		
		qdb("UPDATE `PREFIX_users` SET `username` = '%s', `pwhash` = '%s', `mail` = '%s', `fullname` = '%s', `language` = '%s' WHERE `id` = %d",
			$this->username, $this->pwhash, $this->mail, $this->fullname, $this->language, $this->id);
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
		$result = qdb("SELECT `a`.`id` AS `id`, `a`.`name` AS `name` FROM `PREFIX_groups` `a` INNER JOIN `PREFIX_group_members` `b` ON `a`.`id` = `b`.`group` WHERE `b`.`user` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Group::by_sqlrow($sqlrow);
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
class Group extends BySQLRowEnabled
{
	private $id;
	
	/*
	 * Variables: Public class properties
	 * 
	 * $name - Name of the group.
	 */
	public $name;
	
	/*
	 * Constructor: create
	 * Creates a new group.
	 * 
	 * Parameters:
	 * 	$name - The name of the group.
	 * 
	 * Returns:
	 * 	An Group object
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public static function create($name)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			qdb("INSERT INTO `PREFIX_groups` (`name`) VALUES ('%s')", $name);
			$obj = new self();
			
			$obj->id   = mysql_insert_id();
			$obj->name = $name;
			
			return $obj;
		}
		throw new AlreadyExistsError("\"$name\" is already in database.");
	}
	
	protected function populate_by_sqlrow($sqlrow)
	{
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
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name` FROM `PREFIX_groups` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
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
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_name($name)
	{
		$result = qdb("SELECT `id`, `name` FROM `PREFIX_groups` WHERE `name` = '%s'", $name);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Function: all
	 * Returns array of all groups
	 */
	public static function all()
	{
		$rv = array();
		
		$result = qdb("SELECT `id`, `name` FROM `PREFIX_groups` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		
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
		$result = qdb("SELECT `a`.`id` AS `id`, `a`.`username` AS `username`, `a`.`pwhash` AS `pwhash`, `a`.`mail` AS `mail`, `a`.`fullname` AS `fullname`, `a`.`language` AS `language`
FROM `PREFIX_users` `a` INNER JOIN `PREFIX_group_members` `b` ON `a`.`id` = `b`.`user`
WHERE `b`.`group` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = User::by_sqlrow($sqlrow);
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
 * Class: Translation
 * A translation. Can only be stored using an <Multilingual> object.
 */
class Translation
{
	/*
	 * Variables: Public class variables.
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
		$obj = new self();
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
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$obj = new self();
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

class SettingsIterator implements Iterator
{
	private $index;
	private $keys;
	private $settings_obj;
	
	public function __construct($settings_obj, $keys)
	{
		$this->index = 0;
		$this->settings_obj = $settings_obj;
		$this->keys = $keys;
	}
	
	/* Iterator implementation */
	public function current() { return $this->settings_obj[$this->keys[$this->index]]; }
	public function key()     { return $this->keys[$this->index]; }
	public function next()    { ++$this->index; }
	public function rewind()  { $this->index = 0; }
	public function valid()   { return $this->index < count($this->keys); }
}

/*
 * Class: Settings
 * A class that holds the Settings of Ratatöskr.
 * You can access settings like an array.
 */
class Settings implements ArrayAccess, IteratorAggregate, Countable
{
	/* Singleton implementation */
	private function __copy() {}
	private static $instance = NULL;
	/*
	 * Constructor: get_instance
	 * Get an instance of this class.
	 * All instances are equal (ie. this is a singleton), so you can also use
	 * the global <$ratatoeskr_settings> instance.
	 */
	public static function get_instance()
	{
		if(self::$instance === NULL)
			self::$instance = new self;
		return self::$instance;
	}
	
	private $buffer;
	private $to_be_deleted;
	private $to_be_created;
	private $to_be_updated;
	
	private function __construct()
	{
		$this->buffer = array();
		$result = qdb("SELECT `key`, `value` FROM `PREFIX_settings_kvstorage` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$this->buffer[$sqlrow["key"]] = unserialize(base64_decode($sqlrow["value"]));
		
		$this->to_be_created = array();
		$this->to_be_deleted = array();
		$this->to_be_updated = array();
	}
	
	public function save()
	{
		foreach($this->to_be_deleted as $k)
			qdb("DELETE FROM `PREFIX_settings_kvstorage` WHERE `key` = '%s'", $k);
		foreach($this->to_be_updated as $k)
			qdb("UPDATE `PREFIX_settings_kvstorage` SET `value` = '%s' WHERE `key` = '%s'", base64_encode(serialize($this->buffer[$k])), $k);
		foreach($this->to_be_created as $k)
			qdb("INSERT INTO `PREFIX_settings_kvstorage` (`key`, `value`) VALUES ('%s', '%s')", $k, base64_encode(serialize($this->buffer[$k])));
		$this->to_be_created = array();
		$this->to_be_deleted = array();
		$this->to_be_updated = array();
	}
	
	/* ArrayAccess implementation */
	public function offsetExists($offset)
	{
		return isset($this->buffer[$offset]);
	}
	public function offsetGet($offset)
	{
		return $this->buffer[$offset];
	}
	public function offsetSet ($offset, $value)
	{
		if(!$this->offsetExists($offset))
		{
			if(in_array($offset, $this->to_be_deleted))
			{
				$this->to_be_updated[] = $offset;
				unset($this->to_be_deleted[array_search($offset, $this->to_be_deleted)]);
			}
			else
				$this->to_be_created[] = $offset;
		}
		elseif((!in_array($offset, $this->to_be_created)) and (!in_array($offset, $this->to_be_updated)))
			$this->to_be_updated[] = $offset;
		$this->buffer[$offset] = $value;
	}
	public function offsetUnset($offset)
	{
		if(in_array($offset, $this->to_be_created))
			unset($this->to_be_created[array_search($offset, $this->to_be_created)]);
		else
			$this->to_be_deleted[] = $offset;
		unset($this->buffer[$offset]);
	}
	
	/* IteratorAggregate implementation */
	public function getIterator() { return new SettingsIterator($this, array_keys($this->buffer)); }
	
	/* Countable implementation */
	public function count() { return count($this->buffer); }
}

$ratatoeskr_settings = Settings::get_instance();

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
class Comment extends BySQLRowEnabled
{
	private $id;
	private $article_id;
	private $language;
	private $timestamp;
	
	/*
	 * Variables: Public class variables.
	 * 
	 * $author_name   - Name of comment author.
	 * $author_mail   - E-Mail of comment author.
	 * $text          - Comment text.
	 * $visible       - Should the comment be visible?
	 * $read_by_admin - Was the comment read by an admin.
	 */
	public $author_name;
	public $author_mail;
	public $text;
	public $visible;
	public $read_by_admin;
	
	/*
	 * Functions: Getters
	 * 
	 * get_id        - Gets the comment ID.
	 * get_article   - Gets the article.
	 * get_language  - Gets the language.
	 * get_timestamp - Gets the timestamp.
	 */
	public function get_id()        { return $this->id;                           }
	public function get_article()   { return Article::by_id($this->article_id);   }
	public function get_language()  { return $this->language;                     }
	public function get_timestamp() { return $this->timestamp;                    }
	
	/*
	 * Constructor: create
	 * Creates a new comment.
	 * Automatically sets the $timestamp and $visible (default from setting "comment_visible_default").
	 * 
	 * Parameters:
	 * 	$article  - An <Article> Object.
	 * 	$language - Which language? (see <languages.php>)
	 */
	public static function create($article, $language)
	{
		global $ratatoeskr_settings;
		$obj = new self();
		
		qdb("INSERT INTO `PREFIX_comments` (`article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin`) VALUES (%d, '%s', '', '', '', UNIX_TIMESTAMP(NOW()), %d, 0)",
			$article->get_id(), $language, $ratatoeskr_settings["comment_visible_default"] ? 1 : 0);
		
		$obj->id            = mysql_insert_id();
		$obj->article_id    = $article->get_id();
		$obj->language      = $language;
		$obj->author_name   = "";
		$obj->author_mail   = "";
		$obj->text          = "";
		$obj->timestamp     = time();
		$obj->visible       = $ratatoeskr_settings["comment_visible_default"];
		$obj->read_by_admin = False;
		
		return $obj;
	}
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id            = $sqlrow["id"];
		$this->article_id    = $sqlrow["article"];
		$this->language      = $sqlrow["language"];
		$this->author_name   = $sqlrow["author_name"];
		$this->author_mail   = $sqlrow["author_mail"];
		$this->text          = $sqlrow["text"];
		$this->timestamp     = $sqlrow["timestamp"];
		$this->visible       = $sqlrow["visible"] == 1;
		$this->read_by_admin = $sqlrow["read_by_admin"] == 1;
	}
	
	/*
	 * Constructor: by_id
	 * Gets a Comment by ID.
	 * 
	 * Parameters:
	 * 	$id - The comments ID.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: all
	 * Get all comments
	 * 
	 * Returns:
	 * 	Array of Comment objects
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: htmlize_comment_text
	 * Creates the HTML representation of a comment text. It applys the page's comment textprocessor on it
	 * and filters some potentially harmful tags using kses.
	 * 
	 * Parameters:
	 * 	$text - Text to HTMLize.
	 * 
	 * Returns:
	 * 	HTML code.
	 */
	public static function htmlize_comment_text($text)
	{
		global $ratatoeskr_settings;
		
		return kses(textprocessor_apply($text, $ratatoeskr_settings["comment_textprocessor"]), array(
			"a" => array("href" => 1, "hreflang" => 1, "title" => 1, "rel" => 1, "rev" => 1),
			"b" => array(),
			"i" => array(),
			"u" => array(),
			"strong" => array(),
			"em" => array(),
			"p" => array("align" => 1),
			"br" => array(),
			"abbr" => array(),
			"acronym" => array(),
			"code" => array(),
			"pre" => array(),
			"blockquote" => array("cite" => 1),
			"h1" => array(),
			"h2" => array(),
			"h3" => array(),
			"h4" => array(),
			"h5" => array(),
			"h6" => array(), 
			"img" => array("src" => 1, "alt" => 1, "width" => 1, "height" => 1),
			"s" => array(),
			"q" => array("cite" => 1),
			"samp" => array(),
			"ul" => array(),
			"ol" => array(),
			"li" => array(),
			"del" => array(),
			"ins" => array(),
			"dl" => array(),
			"dd" => array(),
			"dt" => array(),
			"dfn" => array(),
			"div" => array(),
			"dir" => array(),
			"kbd" => array("prompt" => 1),
			"strike" => array(),
			"sub" => array(),
			"sup" => array(),
			"table" => array("style" => 1),
			"tbody" => array(), "thead" => array(), "tfoot" => array(),
			"tr" => array(),
			"td" => array("colspan" => 1, "rowspan" => 1),
			"th" => array("colspan" => 1, "rowspan" => 1),
			"tt" => array(),
			"var" => array()
		));
	}
	
	/*
	 * Function: create_html
	 * Applys <htmlize_comment_text> onto this comment's text.
	 *
	 * Returns:
	 * 	The HTML representation.
	 */
	public function create_html()
	{
		return self::htmlize_comment_text($this->text);
	}
	
	/*
	 * Function: save
	 * Save changes to database.
	 */
	public function save()
	{
		qdb("UPDATE `PREFIX_comments` SET `author_name` = '%s', `author_mail` = '%s', `text` = '%s', `visible` = %d, `read_by_admin` = %d WHERE `id` = %d",
			$this->author_name, $this->author_mail, $this->text, ($this->visible ? 1 : 0), ($this->read_by_admin ? 1 : 0), $this->id);
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
class Style extends BySQLRowEnabled
{
	private $id;
	
	/*
	 * Variables: Public class variables.
	 * 
	 * $name - The name of the style.
	 * $code - The CSS code.
	 */
	public $name;
	public $code;
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id   = $sqlrow["id"];
		$this->name = $sqlrow["name"];
		$this->code = $sqlrow["code"];
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
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public static function create($name)
	{
		try
		{
			self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			$obj = new self();
			$obj->name = $name;
			$obj->code = "";
		
			qdb("INSERT INTO `PREFIX_styles` (`name`, `code`) VALUES ('%s', '')",
				$name);
		
			$obj->id = mysql_insert_id();
			return $obj;
		}
		
		throw new AlreadyExistsError();
	}
	
	/*
	 * Constructor: by_id
	 * Gets a Style object by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name`, `code` FROM `PREFIX_styles` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: by_name
	 * Gets a Style object by name.
	 * 
	 * Parameters:
	 * 	$name - The name.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_name($name)
	{
		$result = qdb("SELECT `id`, `name`, `code` FROM `PREFIX_styles` WHERE `name` = '%s'", $name);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: all
	 * Get all styles
	 * 
	 * Returns:
	 * 	Array of Style objects
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id`, `name`, `code` FROM `PREFIX_styles` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: save
	 * Save changes to database.
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public function save()
	{
		$result = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_styles` WHERE `name` = '%s' AND `id` != %d", $this->name, $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow["n"] > 0)
			throw new AlreadyExistsError();
		
		qdb("UPDATE `PREFIX_styles` SET `name` = '%s', `code` = '%s' WHERE `id` = %d",
			$this->name, $this->code, $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_styles` WHERE `id` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_section_style_relations` WHERE `style` = %d", $this->id);
	}
}

/*
 * Class: Plugin
 * The representation of a plugin in the database.
 */
class Plugin extends BySQLRowEnabled
{
	private $id;
	
	/*
	 * Variables: Public class variables.
	 *
	 * $name              - Plugin name.
	 * $code              - Plugin code.
	 * $classname         - Main class of the plugin.
	 * $active            - Is the plugin activated?
	 * $author            - Author of the plugin.
	 * $versiontext       - Version (text)
	 * $versioncount      - Version (counter)
	 * $short_description - A short description.
	 * $updatepath        - URL for updates.
	 * $web               - Webpage of the plugin.
	 * $help              - Help page.
	 * $license           - License text.
	 * $installed         - Is this plugin installed? Used during the installation process.
	 * $update            - Should the plugin be updated at next start?
	 * $api               - The API version this Plugin needs.
	 */
	
	public $name;
	public $code;
	public $classname;
	public $active;
	public $author;
	public $versiontext;
	public $versioncount;
	public $short_description;
	public $updatepath;
	public $web;
	public $help;
	public $license;
	public $installed;
	public $update;
	public $api;
	
	/*
	 * Function: clean_db
	 * Performs some datadase cleanup jobs on the plugin table.
	 */
	public static function clean_db()
	{
		qdb("DELETE FROM `PREFIX_plugins` WHERE `installed` = 0 AND `added` < %d", (time() - (60*5)));
	}
	
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
		$obj = new self();
		qdb("INSERT INTO `PREFIX_plugins` (`added`) VALUES (%d)", time());
		$obj->id = mysql_insert_id();
		return $obj;
	}
	
	/*
	 * Function: fill_from_pluginpackage
	 * Fills plugin data from an <PluginPackage> object.
	 * 
	 * Parameters:
	 * 	$pkg - The <PluginPackage> object.
	 */
	public function fill_from_pluginpackage($pkg)
	{
		$this->name              = $pkg->name;
		$this->code              = $pkg->code;
		$this->classname         = $pkg->classname;
		$this->author            = $pkg->author;
		$this->versiontext       = $pkg->versiontext;
		$this->versioncount      = $pkg->versioncount;
		$this->short_description = $pkg->short_description;
		$this->updatepath        = $pkg->updatepath;
		$this->web               = $pkg->web;
		$this->license           = $pkg->license;
		$this->help              = $pkg->help;
		$this->api               = $pkg->api;
		
		if(!empty($pkg->custompub))
			array2dir($pkg->custompub, dirname(__FILE__) . "/../plugin_extradata/public/" . $this->get_id());
		if(!empty($pkg->custompriv))
			array2dir($pkg->custompriv, dirname(__FILE__) . "/../plugin_extradata/private/" . $this->get_id());
		if(!empty($pkg->tpls))
			array2dir($pkg->tpls, dirname(__FILE__) . "/../templates/src/plugintemplates/" . $this->get_id());
	}
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id                = $sqlrow["id"];
		$this->name              = $sqlrow["name"];
		$this->code              = $sqlrow["code"];
		$this->classname         = $sqlrow["classname"];
		$this->active            = ($sqlrow["active"] == 1);
		$this->author            = $sqlrow["author"];
		$this->versiontext       = $sqlrow["versiontext"];
		$this->versioncount      = $sqlrow["versioncount"];
		$this->short_description = $sqlrow["short_description"];
		$this->updatepath        = $sqlrow["updatepath"];
		$this->web               = $sqlrow["web"];
		$this->help              = $sqlrow["help"];
		$this->license           = $sqlrow["license"];
		$this->installed         = ($sqlrow["installed"] == 1);
		$this->update            = ($sqlrow["update"] == 1);
		$this->api               = $sqlrow["api"];
	}
	
	/*
	 * Constructor: by_id
	 * Gets plugin by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name`, `author`, `versiontext`, `versioncount`, `short_description`, `updatepath`, `web`, `help`, `code`, `classname`, `active`, `license`, `installed`, `update`, `api` FROM `PREFIX_plugins` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: all
	 * Gets all Plugins
	 * 
	 * Returns:
	 * 	List of <Plugin> objects.
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id`, `name`, `author`, `versiontext`, `versioncount`, `short_description`, `updatepath`, `web`, `help`, `code`, `classname`, `active`, `license`, `installed`, `update`, `api` FROM `PREFIX_plugins` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		qdb("UPDATE `PREFIX_plugins` SET `name` = '%s', `author` = '%s', `code` = '%s', `classname` = '%s', `active` = %d, `versiontext` = '%s', `versioncount` = %d, `short_description` = '%s', `updatepath` = '%s', `web` = '%s', `help` = '%s', `installed` = %d, `update` = %d, `license` = '%s', `api` = %d WHERE `id` = %d",
			$this->name, $this->author, $this->code, $this->classname, ($this->active ? 1 : 0), $this->versiontext, $this->versioncount, $this->short_description, $this->updatepath, $this->web, $this->help, ($this->installed ? 1 : 0), ($this->update ? 1 : 0), $this->license, $this->api, $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_plugins` WHERE `id` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_plugin_kvstorage` WHERE `plugin` = %d", $this->id);
		if(is_dir(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/private/" . $this->id))
			delete_directory(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/private/" . $this->id);
		if(is_dir(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/public/" . $this->id))
			delete_directory(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/public/" . $this->id);
		if(is_dir(SITE_BASE_PATH . "/ratatoeskr/templates/src/plugintemplates/" . $this->id))
			delete_directory(SITE_BASE_PATH . "/ratatoeskr/templates/src/plugintemplates/" . $this->id);
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
class Section extends BySQLRowEnabled
{
	private $id;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $name     - The name of the section.
	 * $title    - The title of the section (a <Multilingual> object).
	 * $template - Name of the template.
	 */
	public $name;
	public $title;
	public $template;
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id       = $sqlrow["id"];
		$this->name     = $sqlrow["name"];
		$this->title    = Multilingual::by_id($sqlrow["title"]);
		$this->template = $sqlrow["template"];
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
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public static function create($name)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			$obj           = new self();
			$obj->name     = $name;
			$obj->title    = Multilingual::create();
			$obj->template = "";
			
			$result = qdb("INSERT INTO `PREFIX_sections` (`name`, `title`, `template`) VALUES ('%s', %d, '')",
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
	 * 
	 * Returns: 
	 * 	A <Section> object.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: by_name
	 * Gets section by name.
	 * 
	 * Parameters:
	 * 	$name - The name.
	 * 
	 * Returns: 
	 * 	A <Section> object.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_name($name)
	{
		$result = qdb("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE `name` = '%s'", $name);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
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
		$result = qdb("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: get_styles
	 * Get all styles associated with this section.
	 * 
	 * Returns:
	 * 	List of <Style> objects.
	 */
	public function get_styles()
	{
		$rv = array();
		$result = qdb("SELECT `a`.`id` AS `id`, `a`.`name` AS `name`, `a`.`code` AS `code` FROM `PREFIX_styles` `a` INNER JOIN `PREFIX_section_style_relations` `b` ON `a`.`id` = `b`.`style` WHERE `b`.`section` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Style::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: add_style
	 * Add a style to this section.
	 * 
	 * Parameters:
	 * 	$style - A <Style> object.
	 */
	public function add_style($style)
	{
		$result = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_section_style_relations` WHERE `style` = %d AND `section` = %d", $style->get_id(), $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow["n"] == 0)
			qdb("INSERT INTO `PREFIX_section_style_relations` (`section`, `style`) VALUES (%d, %d)", $this->id, $style->get_id());
	}
	
	/*
	 * Function: remove_style
	 * Remove a style from this section.
	 * 
	 * Parameters:
	 * 	$style - A <Style> object.
	 */
	public function remove_style($style)
	{
		qdb("DELETE FROM `PREFIX_section_style_relations` WHERE `section` = %d AND `style` = %d", $this->id, $style->get_id());
	}
	
	/*
	 * Function: save
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public function save()
	{
		$result = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_sections` WHERE `name` = '%s' AND `id` != %d", $this->name, $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow["n"] > 0)
			throw new AlreadyExistsError();
		
		$this->title->save();
		qdb("UPDATE `PREFIX_sections` SET `name` = '%s', `title` = %d, `template` = '%s' WHERE `id` = %d",
			$this->name, $this->title->get_id(), $this->template, $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		$this->title->delete();
		qdb("DELETE FROM `PREFIX_sections` WHERE `id` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_section_style_relations` WHERE `section` = %d", $this->id);
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
		$result = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` FROM `PREFIX_articles` WHERE `section` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Article::by_sqlrow($sqlrow);
		return $rv;
	}
}

/*
 * Class: Tag
 * Representation of a tag
 */
class Tag extends BySQLRowEnabled
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
	
	protected function populate_by_sqlrow($sqlrow)
	{
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
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public static function create($name)
	{
		try
		{
			$obj = self::by_name($name);
		}
		catch(DoesNotExistError $e)
		{
			$obj = new self();
			
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
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: by_name
	 * Get tag by name
	 * 
	 * Parameters:
	 * 	$name - The name
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_name($name)
	{
		$result = qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `name` = '%s'", $name);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
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
		$result = qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
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
		$result = qdb(
"SELECT `a`.`id` AS `id`, `a`.`urlname` AS `urlname`, `a`.`title` AS `title`, `a`.`text` AS `text`, `a`.`excerpt` AS `excerpt`, `a`.`meta` AS `meta`, `a`.`custom` AS `custom`, `a`.`article_image` AS `article_image`, `a`.`status` AS `status`, `a`.`section` AS `section`, `a`.`timestamp` AS `timestamp`, `a`.`allow_comments` AS `allow_comments`
FROM `PREFIX_articles` `a`
INNER JOIN `PREFIX_article_tag_relations` `b` ON `a`.`id` = `b`.`article`
WHERE `b`.`tag` = '%d'" , $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Article::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: count_articles
	 * 
	 * Returns:
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
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public function save()
	{
		$result = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_tags` WHERE `name` = '%s' AND `id` != %d", $this->name, $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow["n"] > 0)
			throw new AlreadyExistsError();
		
		$this->title->save();
		qdb("UPDATE `PREFIX_tags` SET `name` = '%s', `title` = %d WHERE `id` = %d",
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
 * Class: Image
 * Representation of an image entry.
 */
class Image extends BySQLRowEnabled
{
	private $id;
	private $filename;
	
	private static $pre_maxw = 150;
	private static $pre_maxh = 100;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $name - The image name
	 */
	public $name;
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id   = $sqlrow["id"];
		$this->name = $sqlrow["name"];
		$this->file = $sqlrow["file"];
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
	 * 	$file - An uploaded image file (move_uploaded_file must be able to move the file!).
	 * 
	 * Throws:
	 * 	<IOError>, <UnknownFileFormat>
	 */
	public static function create($name, $file)
	{
		$obj = new self();
		$obj->name = $name;
		$obj->file = "0";
		
		qdb("INSERT INTO `PREFIX_images` (`name`, `file`) VALUES ('%s', '0')",
			$name);
		
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
	 * Get image by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name`, `file` FROM `PREFIX_images` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
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
		$result = qdb("SELECT `id`, `name`, `file` FROM `PREFIX_images` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: exchange_image
	 * Exchanges image file. Also saves object to database.
	 * 
	 * Parameters:
	 * 	$file - Location of new image.(move_uploaded_file must be able to move the file!)
	 * 
	 * Throws:
	 * 	<IOError>, <UnknownFileFormat>
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
		$new_fn = $this->id . "." . $imagetype_file_extensions[$imageinfo[2]];
		if(!move_uploaded_file($file, SITE_BASE_PATH . "/images/" . $new_fn))
			throw new IOError("Can not move file.");
		$this->file = $new_fn;
		$this->save();
		
		/* make preview image */
		switch($imageinfo[2])
		{
			case IMAGETYPE_GIF:  $img = imagecreatefromgif (SITE_BASE_PATH . "/images/" . $new_fn); break;
			case IMAGETYPE_JPEG: $img = imagecreatefromjpeg(SITE_BASE_PATH . "/images/" . $new_fn); break;
			case IMAGETYPE_PNG:  $img = imagecreatefrompng (SITE_BASE_PATH . "/images/" . $new_fn); break;
			default: $img = imagecreatetruecolor(40, 40); imagefill($img, 1, 1, imagecolorallocate($img, 127, 127, 127)); break;
		}
		$w_orig = imagesx($img);
		$h_orig = imagesy($img);
		if(($w_orig > self::$pre_maxw) or ($h_orig > self::$pre_maxh))
		{
			$ratio = $w_orig / $h_orig;
			if($ratio > 1)
			{
				$w_new = round(self::$pre_maxw);
				$h_new = round(self::$pre_maxw / $ratio);
			}
			else
			{
				$h_new = round(self::$pre_maxh);
				$w_new = round(self::$pre_maxh * $ratio);
			}
			$preview = imagecreatetruecolor($w_new, $h_new);
			imagecopyresized($preview, $img, 0, 0, 0, 0, $w_new, $h_new, $w_orig, $h_orig);
			imagepng($preview, SITE_BASE_PATH . "/images/previews/{$this->id}.png");
		}
		else
			imagepng($img, SITE_BASE_PATH . "/images/previews/{$this->id}.png");
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		qdb("UPDATE `PREFIX_images` SET `name` = '%s', `file` = '%s' WHERE `id` = %d",
			$this->name, $this->file, $this->id);
	}
	
	/*
	 * Function: delete
	 */
	public function delete()
	{
		if(is_file(SITE_BASE_PATH . "/images/" . $this->file))
			unlink(SITE_BASE_PATH . "/images/" . $this->file);
		if(is_file(SITE_BASE_PATH . "/images/previews/{$this->id}.png"))
			unlink(SITE_BASE_PATH . "/images/previews/{$this->id}.png");
		qdb("DELETE FROM `PREFIX_images` WHERE `id` = %d", $this->id);
	}
}

/*
 * Class: RepositoryUnreachableOrInvalid
 * A Exception that will be thrown, if the repository is aunreachable or seems to be an invalid repository.
 */
class RepositoryUnreachableOrInvalid extends Exception { }

/*
 * Class: Repository
 * Representation of an plugin repository.
 */
class Repository extends BySQLRowEnabled
{
	private $id;
	private $baseurl;
	private $name;
	private $description;
	private $lastrefresh;
	
	private $stream_ctx;
	
	/*
	 * Variables: Public class variables
	 * $packages - Array with all packages from this repository. A entry itself is an array: array(name, versioncounter, description)
	 */
	public $packages;
	
	protected function __construct()
	{
		$this->stream_ctx = stream_context_create(array("http" => array("timeout" => 5)));
	}
	
	/*
	 * Functions: Getters
	 * get_id          - Get internal ID.
	 * get_baseurl     - Get the baseurl of the repository.
	 * get_name        - Get repository name.
	 * get_description - Get repository description.
	 */
	public function get_id()          { return $this->id;          }
	public function get_baseurl()     { return $this->baseurl;     }
	public function get_name()        { return $this->name;        }
	public function get_description() { return $this->description; }
	
	/*
	 * Constructor: create
	 * Create a new repository entry from a base url.
	 * 
	 * Parameters:
	 * 	$baseurl - The baseurl of the repository.
	 * 
	 * Throws:
	 * 	Could throw a <RepositoryUnreachableOrInvalid> exception. In this case, nothing will be written to the database.
	 */
	public static function create($baseurl)
	{
		$obj = new self();
		
		if(preg_match('/^(http[s]?:\\/\\/.*?)[\\/]?$/', $baseurl, $matches) == 0)
			throw new RepositoryUnreachableOrInvalid();
		
		$obj->baseurl = $matches[1];
		$obj->refresh(True);
		
		qdb("INSERT INTO `ratatoeskr_repositories` () VALUES ()");
		$obj->id = mysql_insert_id();
		$obj->save();
		return $obj;
	}
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id          = $sqlrow["id"];
		$this->name        = $sqlrow["name"];
		$this->description = $sqlrow["description"];
		$this->baseurl     = $sqlrow["baseurl"];
		$this->packages    = unserialize(base64_decode($sqlrow["pkgcache"]));
		$this->lastrefresh = $sqlrow["lastrefresh"];
	}
	
	/*
	 * Constructor: by_id
	 * Get a repository entry by ID.
	 * 
	 * Parameters:
	 * 	$id - ID.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `name`, `description`, `baseurl`, `pkgcache`, `lastrefresh` FROM `PREFIX_repositories` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if(!$sqlrow)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: all
	 * Gets all available repositories.
	 * 
	 * Returns:
	 * 	Array of <Repository> objects.
	 */
	public static function all()
	{
		$rv = array();
		$result = qdb("SELECT `id`, `name`, `description`, `baseurl`, `pkgcache`, `lastrefresh` FROM `PREFIX_repositories` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	private function save()
	{
		qdb("UPDATE `PREFIX_repositories` SET `baseurl` = '%s', `name` = '%s', `description` = '%s', `pkgcache` = '%s', `lastrefresh` = %d WHERE `id` = %d",
		    $this->baseurl,
		    $this->name,
		    $this->description,
		    base64_encode(serialize($this->packages)),
		    $this->lastrefresh,
		    $this->id);
	}
	
	/* 
	 * Function: delete
	 * Delete the repository entry from the database.
	 */
	public function delete()
	{
		qdb("DELETE FROM `PREFIX_repositories` WHERE `id` = %d", $this->id);
	}
	
	/*
	 * Function: refresh
	 * Refresh the package cache and the name and description.
	 * 
	 * Parameters:
	 * 	$force - Force a refresh, even if the data was already fetched in the last 6 hours (default: False).
	 * 
	 * Throws:
	 * 	<RepositoryUnreachableOrInvalid>
	 */
	public function refresh($force = False)
	{
		if(($this->lastrefresh > (time() - (60*60*4))) and (!$force))
			return;
		
		$repometa = @file_get_contents($this->baseurl . "/repometa", False, $this->stream_ctx);
		if($repometa === FALSE)
			throw new RepositoryUnreachableOrInvalid();
		$repometa = @unserialize($repometa);
		if((!is_array($repometa)) or (!isset($repometa["name"])) or (!isset($repometa["description"])))
			throw new RepositoryUnreachableOrInvalid();
		
		$this->name        = $repometa["name"];
		$this->description = $repometa["description"];
		$this->packages    = @unserialize(@file_get_contents($this->baseurl . "/packagelist", False, $ctx));
		
		$this->lastrefresh = time();
		
		$this->save();
	}
	
	/*
	 * Function: get_package_meta
	 * Get metadata of a plugin package from this repository.
	 * 
	 * Parameters:
	 * 	$pkgname - The name of the package.
	 * 
	 * Throws:
	 * 	A <DoesNotExistError> Exception, if the package was not found.
	 * 
	 * Returns:
	 * 	A <PluginPackageMeta> object
	 */
	public function get_package_meta($pkgname)
	{
		$found = False;
		foreach($this->packages as $p)
		{
			if($p[0] == $pkgname)
			{
				$found = True;
				break;
			}
		}
		if(!$found)
			throw new DoesNotExistError("Package not in package cache.");
		
		$pkgmeta = @unserialize(@file_get_contents($this->baseurl . "/packages/" . urlencode($pkgname) . "/meta", False, $this->stream_ctx));
		
		if(!($pkgmeta instanceof PluginPackageMeta))
			throw new DoesNotExistError();
		
		return $pkgmeta;
	}
	
	/*
	 * Function: download_package
	 * Download a package from the repository
	 * 
	 * Parameters:
	 * 	$pkgname - Name of the package.
	 * 	$version - The version to download (defaults to "current").
	 * 
	 * Throws:
	 * 	* A <DoesNotExistError> Exception, if the package was not found.
	 * 	* A <InvalidPackage> Exception, if the package was malformed.
	 * 
	 * Returns:
	 * 	A <PluginPackage> object.
	 */
	public function download_package($pkgname, $version = "current")
	{
		$found = False;
		foreach($this->packages as $p)
		{
			if($p[0] == $pkgname)
			{
				$found = True;
				break;
			}
		}
		if(!$found)
			throw new DoesNotExistError("Package not in package cache.");
		
		$raw = @file_get_contents($this->baseurl . "/packages/" . urlencode($pkgname) . "/versions/" . urlencode($version), False, $this->stream_ctx);
		if($raw === False)
			throw new DoesNotExistError();
		
		return PluginPackage::load($raw);
	}
}

/*
 * Class: Article
 * Representation of an article
 */
class Article extends BySQLRowEnabled
{
	private $id;
	
	private $section_id;
	private $section_obj;
	
	/*
	 * Variables: Public class variables
	 * 
	 * $urlname        - URL name
	 * $title          - Title (an <Multilingual> object)
	 * $text           - The text (an <Multilingual> object)
	 * $excerpt        - Excerpt (an <Multilingual> object)
	 * $meta           - Keywords, comma seperated
	 * $custom         - Custom fields, is an array
	 * $article_image  - The article <Image>. If none: NULL
	 * $status         - One of the ARTICLE_STATUS_* constants
	 * $timestamp      - Timestamp
	 * $allow_comments - Are comments allowed?
	 */
	public $urlname;
	public $title;
	public $text;
	public $excerpt;
	public $meta;
	public $custom;
	public $article_image;
	public $status;
	public $timestamp;
	public $allow_comments;
	
	protected function __construct()
	{
		$this->section_obj = NULL;
	}
	
	protected function populate_by_sqlrow($sqlrow)
	{
		$this->id             = $sqlrow["id"];
		$this->urlname        = $sqlrow["urlname"];
		$this->title          = Multilingual::by_id($sqlrow["title"]);
		$this->text           = Multilingual::by_id($sqlrow["text"]);
		$this->excerpt        = Multilingual::by_id($sqlrow["excerpt"]);
		$this->meta           = $sqlrow["meta"];
		$this->custom         = unserialize(base64_decode($sqlrow["custom"]));
		$this->article_image  = $sqlrow["article_image"] == 0 ? NULL : Image::by_id($sqlrow["article_image"]);
		$this->status         = $sqlrow["status"];
		$this->section_id     = $sqlrow["section"];
		$this->timestamp      = $sqlrow["timestamp"];
		$this->allow_comments = $sqlrow["allow_comments"] == 1;
	}
	
	/*
	 * Function: get_id
	 */
	public function get_id() { return $this->id; }
	
	/*
	 * Constructor: create
	 * Create a new Article object.
	 * 
	 * Parameters:
	 * 	urlname - A unique URL name
	 * 
	 * Throws:
	 * 	<AlreadyExistsError>
	 */
	public static function create($urlname)
	{
		global $ratatoeskr_settings;
		
		try
		{
			self::by_urlname($urlname);
		}
		catch(DoesNotExistError $e)
		{
			$obj = new self();
			$obj->urlname        = $urlname;
			$obj->title          = Multilingual::create();
			$obj->text           = Multilingual::create();
			$obj->excerpt        = Multilingual::create();
			$obj->meta           = "";
			$obj->custom         = array();
			$obj->article_image  = NULL;
			$obj->status         = ARTICLE_STATUS_HIDDEN;
			$obj->section_id     = $ratatoeskr_settings["default_section"];
			$obj->timestamp      = time();
			$obj->allow_comments = $ratatoeskr_settings["allow_comments_default"];
		
			qdb("INSERT INTO `PREFIX_articles` (`urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments`) VALUES ('', %d, %d, %d, '', '%s', 0, %d, %d, %d, %d)",
				$obj->title->get_id(),
				$obj->text->get_id(),
				$obj->excerpt->get_id(),
				base64_encode(serialize($obj->custom)),
				$obj->status,
				$obj->section_id,
				$obj->timestamp,
				$obj->allow_comments ? 1 : 0);
			$obj->id = mysql_insert_id();
			return $obj;
		}
		
		throw new AlreadyExistsError();
	}
	
	/*
	 * Constructor: by_id
	 * Get by ID.
	 * 
	 * Parameters:
	 * 	$id - The ID.
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_id($id)
	{
		$result = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `id` = %d", $id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: by_urlname
	 * Get by urlname
	 * 
	 * Parameters:
	 * 	$urlname - The urlname
	 * 
	 * Throws:
	 * 	<DoesNotExistError>
	 */
	public static function by_urlname($urlname)
	{
		$result = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `urlname` = '%s'", $urlname);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow === False)
			throw new DoesNotExistError();
		
		return self::by_sqlrow($sqlrow);
	}
	
	/*
	 * Constructor: by_multi
	 * Get Articles by multiple criterias
	 *
	 * Parameters:
	 * 	$criterias - Array that can have these keys: id (int) , urlname (string), section (<Section> object), status (int), onlyvisible, langavail(string), tag (<Tag> object)
	 * 	$sortby    - Sort by this field (id, urlname, timestamp or title)
	 * 	$sortdir   - Sorting directory (ASC or DESC)
	 * 	$count     - How many entries (NULL for unlimited)
	 * 	$offset    - How many entries should be skipped (NULL for none)
	 * 	$perpage   - How many entries per page (NULL for no paging)
	 * 	$page      - Page number (starting at 1, NULL for no paging)
	 * 	&$maxpage  - Number of pages will be written here, if paging is activated.
	 * 
	 * Returns:
	 * 	Array of Article objects
	 */
	public static function by_multi($criterias, $sortby, $sortdir, $count, $offset, $perpage, $page, &$maxpage)
	{
		$subqueries = array();
		foreach($criterias as $k => $v)
		{
			switch($k)
			{
				case "id":          $subqueries[] = qdb_fmt("`a`.`id`       =  %d",  $v);           break;
				case "urlname":     $subqueries[] = qdb_fmt("`a`.`urlname`  = '%s'", $v);           break;
				case "section":     $subqueries[] = qdb_fmt("`a`.`section`  =  %d",  $v->get_id()); break;
				case "status":      $subqueries[] = qdb_fmt("`a`.`status`   =  %d",  $v);           break;
				case "onlyvisible": $subqueries[] = "`a`.`status` != 0";                            break;
				case "langavail":   $subqueries[] = qdb_fmt("`b`.`language` = '%s'", $v);           break;
				case "tag":         $subqueries[] = qdb_fmt("`c`.`tag`      =  %d",  $v->get_id()); break;
				default: continue;
			}
		}
		
		if(($sortdir != "ASC") and ($sortdir != "DESC"))
			$sortdir = "ASC";
		$sorting = "";
		switch($sortby)
		{
			case "id":        $sorting = "ORDER BY `a`.`id` $sortdir";        break;
			case "urlname":   $sorting = "ORDER BY `a`.`urlname` $sortdir";   break;
			case "timestamp": $sorting = "ORDER BY `a`.`timestamp` $sortdir"; break;
			case "title":     $sorting = "ORDER BY `b`.`text` $sortdir";      break;
		}
		
		$result = qdb("SELECT `a`.`id` AS `id`, `a`.`urlname` AS `urlname`, `a`.`title` AS `title`, `a`.`text` AS `text`, `a`.`excerpt` AS `excerpt`, `a`.`meta` AS `meta`, `a`.`custom` AS `custom`, `a`.`article_image` AS `article_image`, `a`.`status` AS `status`, `a`.`section` AS `section`, `a`.`timestamp` AS `timestamp`, `a`.`allow_comments` AS `allow_comments` FROM `PREFIX_articles` `a`
INNER JOIN `PREFIX_translations` `b` ON `a`.`title` = `b`.`multilingual`
LEFT OUTER JOIN `PREFIX_article_tag_relations` `c` ON `a`.`id` = `c`.`article`
WHERE " . implode(" AND ", $subqueries) . " $sorting");
		
		$rows = array();
		$fetched_ids = array();
		while($sqlrow = mysql_fetch_assoc($result))
		{
			if(!in_array($sqlrow["id"], $fetched_ids))
			{
				$rows[]        = $sqlrow;
				$fetched_ids[] = $sqlrow["id"];
			}
		}
		
		if($count !== NULL)
			$rows = array_slice($rows, 0, $count);
		if($offset !== NULL)
			$rows = array_slice($rows, $offset);
		if(($perpage !== NULL) and ($page !== NULL))
		{
			$maxpage = ceil(count($rows) / $perpage);
			$rows = array_slice($rows, $perpage * ($page - 1), $perpage);
		}
		
		$rv = array();
		foreach($rows as $r)
			$rv[] = self::by_sqlrow($r);
		return $rv;
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
		$result = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE 1");
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = self::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: get_comments
	 * Getting comments for this article.
	 * 
	 * Parameters:
	 * 	$limit_lang   - Get only comments in a language (empty string for no limitation, this is the default).
	 * 	$only_visible - Do you only want the visible comments? (Default: False)
	 * 
	 * Returns:
	 * 	Array of <Comment> objects.
	 */
	public function get_comments($limit_lang = "", $only_visible = false)
	{
		$rv = array();
		
		$conditions = array(qdb_fmt("`article` = %d", $this->id));
		if($limit_lang != "")
			$conditions[] = qdb_fmt("`language` = '%s'", $limit_lang);
		if($only_visible)
			$conditions[] = "`visible` = 1";
		
		$result = qdb("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE " . implode(" AND ", $conditions));
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Comment::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: get_tags
	 * Get all Tags of this Article.
	 * 
	 * Returns:
	 * 	Array of <Tag> objects.
	 */
	public function get_tags()
	{
		$rv = array();
		$result = qdb("SELECT `a`.`id` AS `id`, `a`.`name` AS `name`, `a`.`title` AS `title` FROM `PREFIX_tags` `a` INNER JOIN `PREFIX_article_tag_relations` `b` ON `a`.`id` = `b`.`tag` WHERE `b`.`article` = %d", $this->id);
		while($sqlrow = mysql_fetch_assoc($result))
			$rv[] = Tag::by_sqlrow($sqlrow);
		return $rv;
	}
	
	/*
	 * Function: set_tags
	 * Set the Tags that should be associated with this Article.
	 * 
	 * Parameters:
	 * 	$tags - Array of <Tag> objects.
	 */
	public function set_tags($tags)
	{
		foreach($tags as $tag)
			$tag->save();
		
		qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `article`= %d", $this->id);
		
		$articleid = $this->id;
		/* So we just need to fire one query instead of count($this->tags) queries. */
		if(!empty($tags))
			qdb(
				"INSERT INTO `PREFIX_article_tag_relations` (`article`, `tag`) VALUES " .
				implode(",", array_map(function($tag) use ($articleid){ return qdb_fmt("(%d, %d)", $articleid, $tag->get_id()); }, $tags))
			);
	}
	
	/*
	 * Function: get_section
	 * Get the section of this article.
	 * 
	 * Returns:
	 * 	A <Section> object.
	 */
	public function get_section()
	{
		if($this->section_obj === NULL)
			$this->section_obj = Section::by_id($this->section_id);
		return $this->section_obj;
	}
	
	/*
	 * Function: set_section
	 * Set the section of this article.
	 * 
	 * Parameters:
	 * 	$section - A <Section> object.
	 */
	public function set_section($section)
	{
		$this->section_id  = $section->get_id();
		$this->section_obj = $section;
	}
	
	/*
	 * Function: save
	 */
	public function save()
	{
		$result = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_articles` WHERE `urlname` = '%s' AND `id` != %d", $this->urlname, $this->id);
		$sqlrow = mysql_fetch_assoc($result);
		if($sqlrow["n"] > 0)
			throw new AlreadyExistsError();
		
		$this->title->save();
		$this->text->save();
		$this->excerpt->save();
		
		qdb("UPDATE `PREFIX_articles` SET `urlname` = '%s', `title` = %d, `text` = %d, `excerpt` = %d, `meta` = '%s', `custom` = '%s', `article_image` = %d, `status` = %d, `section` = %d, `timestamp` = %d, `allow_comments` = %d WHERE `id` = %d",
			$this->urlname,
			$this->title->get_id(),
			$this->text->get_id(),
			$this->excerpt->get_id(),
			$this->meta,
			base64_encode(serialize($this->custom)),
			$this->article_image === NULL ? 0 : $this->article_image->get_id(),
			$this->status,
			$this->section_id,
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
		
		foreach($this->get_comments() as $comment)
			$comment->delete();
		
		qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `article` = %d", $this->id);
		qdb("DELETE FROM `PREFIX_articles` WHERE `id` = %d", $this->id);
	}
}

/*
 * Function: clean_database
 * Clean up the database
 */
function clean_database()
{
	global $ratatoeskr_settings;
	if((!isset($ratatoeskr_settings["last_db_cleanup"])) or ($ratatoeskr_settings["last_db_cleanup"] < (time() - 86400)))
	{
		Plugin::clean_db();
		$ratatoeskr_settings["last_db_cleanup"] = time();
	}
}

?>
