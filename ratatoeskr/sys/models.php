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

use r7r\cms\sys\textprocessors\TextprocessorRepository;
use r7r\cms\sys\Env;
use r7r\cms\sys\models\KVStorage;
use r7r\cms\sys\Database;
use r7r\cms\sys\DbTransaction;

require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/utils.php");
require_once(dirname(__FILE__) . "/textprocessors.php");
require_once(dirname(__FILE__) . "/pluginpackage.php");

db_connect();

/*
 * Array: $imagetype_file_extensions
 * Array of default file extensions for most IMAGETYPE_* constants
 */
$imagetype_file_extensions = [
    IMAGETYPE_GIF     => "gif",
    IMAGETYPE_JPEG    => "jpg",
    IMAGETYPE_PNG     => "png",
    IMAGETYPE_BMP     => "bmp",
    IMAGETYPE_TIFF_II => "tif",
    IMAGETYPE_TIFF_MM => "tif",
];

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
$ratatoeskr_settings = null;

/*
 * Constants: ARTICLE_STATUS_
 * Possible <Article>::$status values.
 *
 * ARTICLE_STATUS_HIDDEN - Article is hidden (Numeric: 0)
 * ARTICLE_STATUS_LIVE   - Article is visible / live (Numeric: 1)
 * ARTICLE_STATUS_STICKY - Article is sticky (Numeric: 2)
 */
define("ARTICLE_STATUS_HIDDEN", 0);
define("ARTICLE_STATUS_LIVE", 1);
define("ARTICLE_STATUS_STICKY", 2);

/*
 * Class: DoesNotExistError
 * This Exception is thrown by an ::by_*-constructor or any array-like object if the desired object is not present in the database.
 */
class DoesNotExistError extends Exception
{
}

/*
 * Class: AlreadyExistsError
 * This Exception is thrown by an ::create-constructor or a save-method, if the creation/modification of the object would result in duplicates.
 */
class AlreadyExistsError extends Exception
{
}

/*
 * Class: InvalidDataError
 * Exception that will be thrown, if a object with invalid data (e.g. urlname in this form not allowed) should have been saved / created.
 * Unless something else is said at a function, the exception message is a translation key.
 */
class InvalidDataError extends Exception
{
}

abstract class BySQLRowEnabled
{
    protected function __construct()
    {
    }

    abstract protected function populate_by_sqlrow($sqlrow);

    protected static function by_sqlrow($sqlrow)
    {
        $obj = new static();
        $obj->populate_by_sqlrow($sqlrow);
        return $obj;
    }
}

/**
 * Data model for Users
 */
class User extends BySQLRowEnabled
{
    private $id;

    /** @var string The username. */
    public $username;

    /** @var string Hash of the password. */
    public $pwhash;

    /** @var string E-Mail-address. */
    public $mail;

    /** @var string The full name of the user. */
    public $fullname;

    /** @var string Users language */
    public $language;


    /**
     * Creates a new user.
     *
     * @param string|mixed $username The username
     * @param string|mixed $pwhash Hash of the password
     * @param Database|null $db
     * @return self
     * @throws AlreadyExistsError
     */
    public static function create($username, $pwhash, ?Database $db = null): self
    {
        $username = (string)$username;
        $pwhash = (string)$pwhash;
        $db = $db ?? Env::getGlobal()->database();

        try {
            self::by_name($username, $db);
        } catch (DoesNotExistError $e) {
            global $ratatoeskr_settings;
            $db->query(
                "INSERT INTO `PREFIX_users` (`username`, `pwhash`, `mail`, `fullname`, `language`) VALUES (?, ?, '', '', ?)",
                $username,
                $pwhash,
                $ratatoeskr_settings["default_language"]
            );
            $obj = new self();

            $obj->id       = $db->lastInsertId();
            $obj->username = $username;
            $obj->pwhash   = $pwhash;
            $obj->mail     = "";
            $obj->fullname = "";
            $obj->language = $ratatoeskr_settings["default_language"];

            return $obj;
        }
        throw new AlreadyExistsError("\"$username\" is already in database.");
    }

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id       = (int)$sqlrow["id"];
        $this->username = (string)$sqlrow["username"];
        $this->pwhash   = (string)$sqlrow["pwhash"];
        $this->mail     = (string)$sqlrow["mail"];
        $this->fullname = (string)$sqlrow["fullname"];
        $this->language = (string)$sqlrow["language"];
    }

    /**
     * Get a User object by ID
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get a User object by username
     *
     * @param string|mixed $username
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_name($username, ?Database $db = null): self
    {
        $username = (string)$username;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE `username` = ?", $username);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Returns array of all available users.
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];

        $stmt = $db->query("SELECT `id`, `username`, `pwhash`, `mail`, `fullname`, `language` FROM `PREFIX_users` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }

        return $rv;
    }

    /**
     * @return int The user ID.
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Saves the object to database
     *
     * @param Database|null $db
     * @throws AlreadyExistsError
     */
    public function save(?Database $db = null)
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $stmt = $db->query("SELECT COUNT(*) AS `n` FROM `PREFIX_users` WHERE `username` = ? AND `id` != ?", $this->username, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $db->query(
                "UPDATE `PREFIX_users` SET `username` = ?, `pwhash` = ?, `mail` = ?, `fullname` = ?, `language` = ? WHERE `id` = ?",
                $this->username,
                $this->pwhash,
                $this->mail,
                $this->fullname,
                $this->language,
                $this->id
            );
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /**
     * Deletes the user from the database.
     * WARNING: Do NOT use this object any longer after you called this function!
     * @param Database|null $db
     */
    public function delete(?Database $db = null)
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $db->query("DELETE FROM `PREFIX_group_members` WHERE `user` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_users` WHERE `id` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /**
     * Get a list of all groups where this user is a member.
     * @param Database|null $db
     * @return array
     */
    public function get_groups(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query(
            "SELECT
                `a`.`id` AS `id`,
                `a`.`name` AS `name`
            FROM `PREFIX_groups` `a`
            INNER JOIN `PREFIX_group_members` `b`
                ON `a`.`id` = `b`.`group`
            WHERE `b`.`user` = ?",
            $this->id
        );
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Group::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Checks, if the user is a member of a group.
     *
     * @param Group $group
     * @param Database|null $db
     * @return bool
     */
    public function member_of(Group $group, ?Database $db = null): bool
    {
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query(
            "SELECT COUNT(*) AS `num` FROM `PREFIX_group_members` WHERE `user` = ? AND `group` = ?",
            $this->id,
            $group->get_id()
        );
        $sqlrow = $stmt->fetch();
        return $sqlrow["num"] > 0;
    }
}

/**
 * Data model for groups
 */
class Group extends BySQLRowEnabled
{
    /** @var int */
    private $id;

    /** @var string Name of the group */
    public $name;

    /**
     * Creates a new group.
     *
     * @param string|mixed $name The name of the group.
     * @return self
     * @throws AlreadyExistsError
     */
    public static function create($name, ?Database $db = null): self
    {
        $name = (string)$name;
        $db = $db ?? Env::getGlobal()->database();

        try {
            self::by_name($name, $db);
        } catch (DoesNotExistError $e) {
            $db->query("INSERT INTO `PREFIX_groups` (`name`) VALUES (?)", $name);
            $obj = new self();

            $obj->id   = $db->lastInsertId();
            $obj->name = $name;

            return $obj;
        }
        throw new AlreadyExistsError("\"$name\" is already in database.");
    }

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id   = (int)$sqlrow["id"];
        $this->name = (string)$sqlrow["name"];
    }

    /**
     * Get a Group object by ID
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name` FROM `PREFIX_groups` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get a Group object by name
     *
     * @param string|mixed $name The group name
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_name($name, ?Database $db = null)
    {
        $name = (string)$name;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name` FROM `PREFIX_groups` WHERE `name` = ?", $name);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Returns array of all groups
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];

        $stmt = $db->query("SELECT `id`, `name` FROM `PREFIX_groups` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }

        return $rv;
    }

    /**
     * @return int
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Deletes the group from the database.
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $db->query("DELETE FROM `PREFIX_group_members` WHERE `group` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_groups` WHERE `id` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /**
     * Get all members of the group.
     *
     * @param Database|null $db
     * @return User[]
     */
    public function get_members(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query(
            "SELECT
                `a`.`id` AS `id`,
                `a`.`username` AS `username`,
                `a`.`pwhash` AS `pwhash`,
                `a`.`mail` AS `mail`,
                `a`.`fullname` AS `fullname`,
                `a`.`language` AS `language`
            FROM `PREFIX_users` `a`
            INNER JOIN `PREFIX_group_members` `b`
                ON `a`.`id` = `b`.`user`
            WHERE `b`.`group` = ?",
            $this->id
        );
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = User::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Exclude a user from the group.
     *
     * @param User $user
     * @param Database|null $db
     */
    public function exclude_user(User $user, ?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query("DELETE FROM `PREFIX_group_members` WHERE `user` = ? AND `group` = ?", $user->get_id(), $this->id);
    }

    /**
     * Add user to the group.
     *
     * @param User $user
     * @param Database|null $db
     */
    public function include_user(User $user, ?Database $db = null)
    {
        $db = $db ?? Env::getGlobal()->database();

        if (!$user->member_of($this, $db)) {
            $db->query("INSERT INTO `PREFIX_group_members` (`user`, `group`) VALUES (?, ?)", $user->get_id(), $this->id);
        }
    }
}

/**
 * A translation. Can only be stored using a {@see Multilingual} object.
 */
class Translation
{
    /** @var string The translated text. */
    public $text;

    /** @var string The type of the text. Has only a meaning in a context. */
    public $texttype;

    /**
     * Creates a new Translation object.
     * IT WILL NOT BE STORED TO DATABASE!
     *
     * @param string|mixed $text The translated text.
     * @param string|mixed $texttype The type of the text. Has only a meaning in a context.
     */
    public function __construct($text, $texttype)
    {
        $this->text     = (string)$text;
        $this->texttype = (string)$texttype;
    }

    /**
     * Applies a textprocessor to the text according to texttype.
     * @param TextprocessorRepository $textprocessors
     * @return string
     */
    public function applyTextprocessor(TextprocessorRepository $textprocessors): string
    {
        return $textprocessors->apply((string)$this->text, (string)$this->texttype);
    }
}

/**
 * Container for {@see Translation} objects.
 * Translations can be accessed array-like. So, if you want the german translation: $translation = $my_multilingual["de"];
 */
class Multilingual implements Countable, ArrayAccess, IteratorAggregate
{
    /** @var int */
    private $id;

    private $translations = [];
    private $to_be_deleted = [];
    private $to_be_created = [];

    private function __construct()
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Creates a new Multilingual object
     *
     * @param Database|null $db
     * @return self
     */
    public static function create(?Database $db = null): self
    {
        $db = $db ?? Env::getGlobal()->database();

        $obj = new self();
        $db->query("INSERT INTO `PREFIX_multilingual` () VALUES ()");
        $obj->id = $db->lastInsertId();
        return $obj;
    }

    /**
     * Gets an Multilingual object by ID.
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $obj = new self();
        $stmt = $db->query("SELECT `id` FROM `PREFIX_multilingual` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow == false) {
            throw new DoesNotExistError();
        }
        $obj->id = $id;

        $stmt = $db->query("SELECT `language`, `text`, `texttype` FROM `PREFIX_translations` WHERE `multilingual` = ?", $id);
        while ($sqlrow = $stmt->fetch()) {
            $obj->translations[$sqlrow["language"]] = new Translation($sqlrow["text"], $sqlrow["texttype"]);
        }

        return $obj;
    }

    /**
     * Saves the translations to database.
     * @param Database|null $db
     */
    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            // TODO: These mass deletions/saves can be implemented much more efficiently
            foreach ($this->to_be_deleted as $deletelang) {
                $db->query("DELETE FROM `PREFIX_translations` WHERE `multilingual` = ? AND `language` = ?", $this->id, $deletelang);
            }

            foreach ($this->to_be_created as $lang) {
                $db->query(
                    "INSERT INTO `PREFIX_translations` (`multilingual`, `language`, `text`, `texttype`) VALUES (?, ?, ?, ?)",
                    $this->id,
                    $lang,
                    $this->translations[$lang]->text,
                    $this->translations[$lang]->texttype
                );
            }

            foreach ($this->translations as $lang => $translation) {
                if (!in_array($lang, $this->to_be_created)) {
                    $db->query(
                        "UPDATE `PREFIX_translations` SET `text` = ?, `texttype` = ? WHERE `multilingual` = ? AND `language` = ?",
                        $translation->text,
                        $translation->texttype,
                        $this->id,
                        $lang
                    );
                }
            }

            $this->to_be_deleted = [];
            $this->to_be_created = [];
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /**
     * Deletes the data from database.
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $db->query("DELETE FROM `PREFIX_translations` WHERE `multilingual` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_multilingual` WHERE `id` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /* Countable interface implementation */
    public function count()
    {
        return count($this->translations);
    }

    /* ArrayAccess interface implementation */
    public function offsetExists($offset)
    {
        return isset($this->translations[$offset]);
    }
    public function offsetGet($offset)
    {
        if (isset($this->translations[$offset])) {
            return $this->translations[$offset];
        } else {
            throw new DoesNotExistError();
        }
    }
    public function offsetUnset($offset)
    {
        unset($this->translations[$offset]);
        if (in_array($offset, $this->to_be_created)) {
            unset($this->to_be_created[array_search($offset, $this->to_be_created)]);
        } else {
            $this->to_be_deleted[] = $offset;
        }
    }
    public function offsetSet($offset, $value)
    {
        if (!isset($this->translations[$offset])) {
            if (in_array($offset, $this->to_be_deleted)) {
                unset($this->to_be_deleted[array_search($offset, $this->to_be_deleted)]);
            } else {
                $this->to_be_created[] = $offset;
            }
        }
        $this->translations[$offset] = $value;
    }

    /* IteratorAggregate interface implementation */
    public function getIterator()
    {
        return new ArrayIterator($this->translations);
    }
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
    public function current()
    {
        return $this->settings_obj[$this->keys[$this->index]];
    }
    public function key()
    {
        return $this->keys[$this->index];
    }
    public function next()
    {
        ++$this->index;
    }
    public function rewind()
    {
        $this->index = 0;
    }
    public function valid()
    {
        return $this->index < count($this->keys);
    }
}

/*
 * Class: Settings
 * A class that holds the Settings of Ratatöskr.
 * You can access settings like an array.
 */
class Settings implements ArrayAccess, IteratorAggregate, Countable
{
    /* Singleton implementation */
    private function __copy()
    {
    }
    private static $instance = null;
    /*
     * Constructor: get_instance
     * Get an instance of this class.
     * All instances are equal (ie. this is a singleton), so you can also use
     * the global <$ratatoeskr_settings> instance.
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private $buffer;
    private $to_be_deleted;
    private $to_be_created;
    private $to_be_updated;

    private function __construct()
    {
        $this->buffer = [];
        $stmt = qdb("SELECT `key`, `value` FROM `PREFIX_settings_kvstorage` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $this->buffer[$sqlrow["key"]] = unserialize(base64_decode($sqlrow["value"]));
        }

        $this->to_be_created = [];
        $this->to_be_deleted = [];
        $this->to_be_updated = [];
    }

    public function save()
    {
        $tx = new Transaction();
        try {
            foreach ($this->to_be_deleted as $k) {
                qdb("DELETE FROM `PREFIX_settings_kvstorage` WHERE `key` = ?", $k);
            }
            foreach ($this->to_be_updated as $k) {
                qdb("UPDATE `PREFIX_settings_kvstorage` SET `value` = ? WHERE `key` = ?", base64_encode(serialize($this->buffer[$k])), $k);
            }
            foreach ($this->to_be_created as $k) {
                qdb("INSERT INTO `PREFIX_settings_kvstorage` (`key`, `value`) VALUES (?, ?)", $k, base64_encode(serialize($this->buffer[$k])));
            }

            $this->to_be_created = [];
            $this->to_be_deleted = [];
            $this->to_be_updated = [];
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
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
    public function offsetSet($offset, $value)
    {
        if (!$this->offsetExists($offset)) {
            if (in_array($offset, $this->to_be_deleted)) {
                $this->to_be_updated[] = $offset;
                unset($this->to_be_deleted[array_search($offset, $this->to_be_deleted)]);
            } else {
                $this->to_be_created[] = $offset;
            }
        } elseif ((!in_array($offset, $this->to_be_created)) and (!in_array($offset, $this->to_be_updated))) {
            $this->to_be_updated[] = $offset;
        }
        $this->buffer[$offset] = $value;
    }
    public function offsetUnset($offset)
    {
        if (in_array($offset, $this->to_be_created)) {
            unset($this->to_be_created[array_search($offset, $this->to_be_created)]);
        } else {
            $this->to_be_deleted[] = $offset;
        }
        unset($this->buffer[$offset]);
    }

    /* IteratorAggregate implementation */
    public function getIterator()
    {
        return new SettingsIterator($this, array_keys($this->buffer));
    }

    /* Countable implementation */
    public function count()
    {
        return count($this->buffer);
    }
}

$ratatoeskr_settings = Settings::get_instance();

/**
 * A Key-Value-Storage for Plugins
 * Can be accessed like an array.
 * Keys are strings and Values can be everything serialize() can process.
 */
class PluginKVStorage extends KVStorage
{
    /**
     * @param int|mixed $plugin_id The ID of the Plugin.
     * @param Database|null $db Optionally a database opject. Defaults to the global one
     */
    public function __construct($plugin_id, ?Database $db = null)
    {
        $this->init(
            "PREFIX_plugin_kvstorage",
            ["plugin" => (int)$plugin_id],
            $db ?? Env::getGlobal()->database()
        );
    }
}

/**
 * Representing a user comment
 */
class Comment extends BySQLRowEnabled
{
    /** @var int */
    private $id;

    /** @var int */
    private $article_id;

    /** @var string */
    private $language;

    /** @var int */
    private $timestamp;

    /** @var string Name of comment author */
    public $author_name;

    /** @var string E-Mail of comment author */
    public $author_mail;

    /** @var string Comment text */
    public $text;

    /** @var bool Should the comment be visible? */
    public $visible;

    /** @var bool Was the comment read by an admin */
    public $read_by_admin;

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Get the article for this comment
     * @return Article
     * @throws DoesNotExistError
     */
    public function get_article(): Article
    {
        return Article::by_id($this->article_id);
    }

    public function get_language(): string
    {
        return $this->language;
    }

    public function get_timestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Creates a new comment.
     * Automatically sets the $timestamp and $visible (default from setting "comment_visible_default").
     *
     * @param Article $article
     * @param string $language
     * @param Database|null $db
     * @return self
     */
    public static function create(Article $article, $language, ?Database $db = null): self
    {
        global $ratatoeskr_settings;

        $language = (string)$language;
        $db = $db ?? Env::getGlobal()->database();

        $obj = new self();
        $obj->timestamp = time();

        $db->query(
            "INSERT INTO `PREFIX_comments` (`article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin`) VALUES (?, ?, '', '', '', ?, ?, 0)",
            $article->get_id(),
            $language,
            $obj->timestamp,
            $ratatoeskr_settings["comment_visible_default"] ? 1 : 0
        );

        $obj->id            = $db->lastInsertId();
        $obj->article_id    = $article->get_id();
        $obj->language      = $language;
        $obj->author_name   = "";
        $obj->author_mail   = "";
        $obj->text          = "";
        $obj->visible       = (bool)$ratatoeskr_settings["comment_visible_default"];
        $obj->read_by_admin = false;

        return $obj;
    }

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id            = (int)$sqlrow["id"];
        $this->article_id    = (int)$sqlrow["article"];
        $this->language      = (string)$sqlrow["language"];
        $this->author_name   = (string)$sqlrow["author_name"];
        $this->author_mail   = (string)$sqlrow["author_mail"];
        $this->text          = (string)$sqlrow["text"];
        $this->timestamp     = (int)$sqlrow["timestamp"];
        $this->visible       = $sqlrow["visible"] == 1;
        $this->read_by_admin = $sqlrow["read_by_admin"] == 1;
    }

    /**
     * Gets a Comment by ID.
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get all comments
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Creates the HTML representation of a comment text. It applies the page's comment textprocessor on it
     * and filters some potentially harmful tags using HTMLPurifier.
     *
     * @param string $text Text to HTMLize.
     * @param TextprocessorRepository|null $textprocessors
     * @return string HTML code.
     */
    public static function htmlize_comment_text($text, ?TextprocessorRepository $textprocessors = null)
    {
        global $ratatoeskr_settings;

        $textprocessors = $textprocessors ?? Env::getGlobal()->textprocessors();

        $purifierConfig = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($purifierConfig);

        return $purifier->purify($textprocessors->mustApply($text, $ratatoeskr_settings["comment_textprocessor"]), [
            "a" => ["href" => 1, "hreflang" => 1, "title" => 1, "rel" => 1, "rev" => 1],
            "b" => [],
            "i" => [],
            "u" => [],
            "strong" => [],
            "em" => [],
            "p" => ["align" => 1],
            "br" => [],
            "abbr" => [],
            "acronym" => [],
            "code" => [],
            "pre" => [],
            "blockquote" => ["cite" => 1],
            "h1" => [],
            "h2" => [],
            "h3" => [],
            "h4" => [],
            "h5" => [],
            "h6" => [],
            "img" => ["src" => 1, "alt" => 1, "width" => 1, "height" => 1],
            "s" => [],
            "q" => ["cite" => 1],
            "samp" => [],
            "ul" => [],
            "ol" => [],
            "li" => [],
            "del" => [],
            "ins" => [],
            "dl" => [],
            "dd" => [],
            "dt" => [],
            "dfn" => [],
            "div" => [],
            "dir" => [],
            "kbd" => ["prompt" => 1],
            "strike" => [],
            "sub" => [],
            "sup" => [],
            "table" => ["style" => 1],
            "tbody" => [], "thead" => [], "tfoot" => [],
            "tr" => [],
            "td" => ["colspan" => 1, "rowspan" => 1],
            "th" => ["colspan" => 1, "rowspan" => 1],
            "tt" => [],
            "var" => []
        ]);
    }

    /**
     * Applies {@see self::htmlize_comment_text()} onto this comment's text.
     *
     * @return string The HTML representation.
     */
    public function create_html(): string
    {
        return self::htmlize_comment_text($this->text);
    }

    /**
     * Save changes to database.
     * @param Database|null $db
     */
    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query(
            "UPDATE `PREFIX_comments` SET `author_name` = ?, `author_mail` = ?, `text` = ?, `visible` = ?, `read_by_admin` = ? WHERE `id` = ?",
            $this->author_name,
            $this->author_mail,
            $this->text,
            ($this->visible ? 1 : 0),
            ($this->read_by_admin ? 1 : 0),
            $this->id
        );
    }

    /**
     * Delete the comment
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query("DELETE FROM `PREFIX_comments` WHERE `id` = ?", $this->id);
    }
}

/**
 * Represents a Style
 */
class Style extends BySQLRowEnabled
{
    /** @var int */
    private $id;

    /** @var string The name of the style */
    public $name;

    /** @var string The CSS code */
    public $code;

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id   = (int)$sqlrow["id"];
        $this->name = (string)$sqlrow["name"];
        $this->code = (string)$sqlrow["code"];
    }

    /**
     * Test, if a name is a valid Style name.
     *
     * @param string $name The name to test
     * @return bool
     */
    public static function test_name($name): bool
    {
        return preg_match("/^[a-zA-Z0-9\\-_\\.]+$/", $name) == 1;
    }

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Create a new style.
     *
     * @param string $name A name for the new style.
     * @param Database|null $db
     * @return self
     * @throws AlreadyExistsError If there is already a style with this name
     * @throws InvalidDataError If the name is invalid, see <a href='psi_element://Style::test_name()'>Style::test_name()</a>
     */
    public static function create($name, ?Database $db = null): self
    {
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_name($name)) {
            throw new InvalidDataError("invalid_style_name");
        }

        try {
            self::by_name($name, $db);
        } catch (DoesNotExistError $e) {
            $obj = new self();
            $obj->name = $name;
            $obj->code = "";

            $db->query("INSERT INTO `PREFIX_styles` (`name`, `code`) VALUES (?, '')", $name);

            $obj->id = $db->lastInsertId();
            return $obj;
        }

        throw new AlreadyExistsError();
    }

    /**
     * Get a style by ID
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name`, `code` FROM `PREFIX_styles` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Gets a Style object by name.
     *
     * @param string|mixed $name
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_name($name, ?Database $db = null): self
    {
        $name = (string)$name;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name`, `code` FROM `PREFIX_styles` WHERE `name` = ?", $name);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get all styles
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `name`, `code` FROM `PREFIX_styles` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Save changes to database.
     *
     * @param Database|null $db
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_name($this->name)) {
            throw new InvalidDataError("invalid_style_name");
        }

        $tx = new DbTransaction($db);
        try {
            $stmt = $db->query("SELECT COUNT(*) AS `n` FROM `PREFIX_styles` WHERE `name` = ? AND `id` != ?", $this->name, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $db->query(
                "UPDATE `PREFIX_styles` SET `name` = ?, `code` = ? WHERE `id` = ?",
                $this->name,
                $this->code,
                $this->id
            );
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $db->query("DELETE FROM `PREFIX_styles` WHERE `id` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_section_style_relations` WHERE `style` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
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
        qdb("DELETE FROM `PREFIX_plugins` WHERE `installed` = 0 AND `added` < ?", (time() - (60*5)));
    }

    /*
     * Function: get_id
     */
    public function get_id()
    {
        return $this->id;
    }

    /*
     * Constructor: create
     * Creates a new, empty plugin database entry
     */
    public static function create()
    {
        global $db_con;

        $obj = new self();
        qdb("INSERT INTO `PREFIX_plugins` (`added`) VALUES (?)", time());
        $obj->id = $db_con->lastInsertId();
        return $obj;
    }

    /*
     * Function: fill_from_pluginpackage
     * Fills plugin data from an <PluginPackage> object.
     *
     * Parameters:
     *  $pkg - The <PluginPackage> object.
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

        if (!empty($pkg->custompub)) {
            array2dir($pkg->custompub, dirname(__FILE__) . "/../plugin_extradata/public/" . $this->get_id());
        }
        if (!empty($pkg->custompriv)) {
            array2dir($pkg->custompriv, dirname(__FILE__) . "/../plugin_extradata/private/" . $this->get_id());
        }
        if (!empty($pkg->tpls)) {
            array2dir($pkg->tpls, dirname(__FILE__) . "/../templates/src/plugintemplates/" . $this->get_id());
        }
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
     *  $id - The ID
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_id($id)
    {
        $stmt = qdb("SELECT `id`, `name`, `author`, `versiontext`, `versioncount`, `short_description`, `updatepath`, `web`, `help`, `code`, `classname`, `active`, `license`, `installed`, `update`, `api` FROM `PREFIX_plugins` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: all
     * Gets all Plugins
     *
     * Returns:
     *  List of <Plugin> objects.
     */
    public static function all()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `name`, `author`, `versiontext`, `versioncount`, `short_description`, `updatepath`, `web`, `help`, `code`, `classname`, `active`, `license`, `installed`, `update`, `api` FROM `PREFIX_plugins` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: save
     */
    public function save()
    {
        qdb(
            "UPDATE `PREFIX_plugins` SET `name` = ?, `author` = ?, `code` = ?, `classname` = ?, `active` = ?, `versiontext` = ?, `versioncount` = ?, `short_description` = ?, `updatepath` = ?, `web` = ?, `help` = ?, `installed` = ?, `update` = ?, `license` = ?, `api` = ? WHERE `id` = ?",
            $this->name,
            $this->author,
            $this->code,
            $this->classname,
            ($this->active ? 1 : 0),
            $this->versiontext,
            $this->versioncount,
            $this->short_description,
            $this->updatepath,
            $this->web,
            $this->help,
            ($this->installed ? 1 : 0),
            ($this->update ? 1 : 0),
            $this->license,
            $this->api,
            $this->id
        );
    }

    /*
     * Function: delete
     */
    public function delete()
    {
        $tx = new Transaction();
        try {
            qdb("DELETE FROM `PREFIX_plugins` WHERE `id` = ?", $this->id);
            qdb("DELETE FROM `PREFIX_plugin_kvstorage` WHERE `plugin` = ?", $this->id);
            qdb("DELETE FROM `PREFIX_article_extradata` WHERE `plugin` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }

        if (is_dir(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/private/" . $this->id)) {
            delete_directory(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/private/" . $this->id);
        }
        if (is_dir(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/public/" . $this->id)) {
            delete_directory(SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/public/" . $this->id);
        }
        if (is_dir(SITE_BASE_PATH . "/ratatoeskr/templates/src/plugintemplates/" . $this->id)) {
            delete_directory(SITE_BASE_PATH . "/ratatoeskr/templates/src/plugintemplates/" . $this->id);
        }
    }

    /*
     * Function get_kvstorage
     * Get the KeyValue Storage for the plugin.
     *
     * Returns:
     *  An <PluginKVStorage> object.
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
     * Function: test_name
     * Tests, if a name is a valid section name.
     *
     * Parameters:
     *  $name - The name to test.
     *
     * Returns:
     *  True, if the name is a valid section name, False otherwise.
     */
    public static function test_name($name)
    {
        return preg_match("/^[a-zA-Z0-9\\-_]+$/", $name) != 0;
    }

    /*
     * Function: get_id
     */
    public function get_id()
    {
        return $this->id;
    }

    /*
     * Constructor: create
     * Creates a new section.
     *
     * Parameters:
     *  $name - The name of the new section.
     *
     * Throws:
     *  <AlreadyExistsError>, <InvalidDataError>
     */
    public static function create($name)
    {
        global $db_con;

        if (!self::test_name($name)) {
            throw new InvalidDataError("invalid_section_name");
        }

        try {
            self::by_name($name);
        } catch (DoesNotExistError $e) {
            $obj           = new self();
            $obj->name     = $name;
            $obj->title    = Multilingual::create();
            $obj->template = "";

            qdb("INSERT INTO `PREFIX_sections` (`name`, `title`, `template`) VALUES (?, ?, '')", $name, $obj->title->get_id());

            $obj->id = $db_con->lastInsertId();

            return $obj;
        }

        throw new AlreadyExistsError();
    }

    /*
     * Constructor: by_id
     * Gets section by ID.
     *
     * Parameters:
     *  $id - The ID.
     *
     * Returns:
     *  A <Section> object.
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_id($id)
    {
        $stmt = qdb("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: by_name
     * Gets section by name.
     *
     * Parameters:
     *  $name - The name.
     *
     * Returns:
     *  A <Section> object.
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_name($name)
    {
        $stmt = qdb("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE `name` = ?", $name);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: all
     * Gets all sections.
     *
     * Returns:
     *  Array of Section objects.
     */
    public static function all()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: get_styles
     * Get all styles associated with this section.
     *
     * Returns:
     *  List of <Style> objects.
     */
    public function get_styles()
    {
        $rv = [];
        $stmt = qdb("SELECT `a`.`id` AS `id`, `a`.`name` AS `name`, `a`.`code` AS `code` FROM `PREFIX_styles` `a` INNER JOIN `PREFIX_section_style_relations` `b` ON `a`.`id` = `b`.`style` WHERE `b`.`section` = ?", $this->id);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Style::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: add_style
     * Add a style to this section.
     *
     * Parameters:
     *  $style - A <Style> object.
     */
    public function add_style($style)
    {
        $tx = new Transaction();
        try {
            $stmt = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_section_style_relations` WHERE `style` = ? AND `section` = ?", $style->get_id(), $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] == 0) {
                qdb("INSERT INTO `PREFIX_section_style_relations` (`section`, `style`) VALUES (?, ?)", $this->id, $style->get_id());
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /*
     * Function: remove_style
     * Remove a style from this section.
     *
     * Parameters:
     *  $style - A <Style> object.
     */
    public function remove_style($style)
    {
        qdb("DELETE FROM `PREFIX_section_style_relations` WHERE `section` = ? AND `style` = ?", $this->id, $style->get_id());
    }

    /*
     * Function: save
     *
     * Throws:
     *  <AlreadyExistsError>, <InvalidDataError>
     */
    public function save()
    {
        if (!self::test_name($this->name)) {
            throw new InvalidDataError("invalid_section_name");
        }

        $tx = new Transaction();
        try {
            $stmt = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_sections` WHERE `name` = ? AND `id` != ?", $this->name, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $this->title->save();
            qdb(
                "UPDATE `PREFIX_sections` SET `name` = ?, `title` = ?, `template` = ? WHERE `id` = ?",
                $this->name,
                $this->title->get_id(),
                $this->template,
                $this->id
            );
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /*
     * Function: delete
     */
    public function delete()
    {
        $tx = new Transaction();
        try {
            $this->title->delete();
            qdb("DELETE FROM `PREFIX_sections` WHERE `id` = ?", $this->id);
            qdb("DELETE FROM `PREFIX_section_style_relations` WHERE `section` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /*
     * Function: get_articles
     * Get all articles in this section.
     *
     * Returns:
     *  Array of <Article> objects
     */
    public function get_articles()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `section` = ?", $this->id);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Article::by_sqlrow($sqlrow);
        }
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
     * Function: test_name
     * Test, if a name is a valid tag name.
     *
     * Parameters:
     *  $name - Name to test.
     *
     * Returns:
     *  True, if the name is valid, False otherwise.
     */
    public static function test_name($name)
    {
        return (strpos($name, ",") === false) and (strpos($name, " ") === false);
    }

    /*
     * Function: get_id
     */
    public function get_id()
    {
        return $this->id;
    }

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
     *  $name - The name
     *
     * Throws:
     *  <AlreadyExistsError>, <InvalidDataError>
     */
    public static function create($name)
    {
        global $db_con;
        if (!self::test_name($name)) {
            throw new InvalidDataError("invalid_tag_name");
        }

        try {
            self::by_name($name);
        } catch (DoesNotExistError $e) {
            $obj = new self();

            $obj->name  = $name;
            $obj->title = Multilingual::create();

            qdb(
                "INSERT INTO `PREFIX_tags` (`name`, `title`) VALUES (?, ?)",
                $name,
                $obj->title->get_id()
            );
            $obj->id = $db_con->lastInsertId();

            return $obj;
        }
        throw new AlreadyExistsError();
    }

    /*
     * Constructor: by_id
     * Get tag by ID
     *
     * Parameters:
     *  $id - The ID
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_id($id)
    {
        $stmt = qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: by_name
     * Get tag by name
     *
     * Parameters:
     *  $name - The name
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_name($name)
    {
        $stmt = qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `name` = ?", $name);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: all
     * Get all tags
     *
     * Returns:
     *  Array of Tag objects.
     */
    public static function all()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: get_articles
     * Get all articles that are tagged with this tag
     *
     * Returns:
     *  Array of <Article> objects
     */
    public function get_articles()
    {
        $rv = [];
        $stmt = qdb(
            "SELECT `a`.`id` AS `id`, `a`.`urlname` AS `urlname`, `a`.`title` AS `title`, `a`.`text` AS `text`, `a`.`excerpt` AS `excerpt`, `a`.`meta` AS `meta`, `a`.`custom` AS `custom`, `a`.`article_image` AS `article_image`, `a`.`status` AS `status`, `a`.`section` AS `section`, `a`.`timestamp` AS `timestamp`, `a`.`allow_comments` AS `allow_comments`
FROM `PREFIX_articles` `a`
INNER JOIN `PREFIX_article_tag_relations` `b` ON `a`.`id` = `b`.`article`
WHERE `b`.`tag` = ?",
            $this->id
        );
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Article::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: count_articles
     *
     * Returns:
     *  The number of articles that are tagged with this tag.
     */
    public function count_articles()
    {
        $stmt = qdb("SELECT COUNT(*) AS `num` FROM `PREFIX_article_tag_relations` WHERE `tag` = ?", $this->id);
        $sqlrow = $stmt->fetch();
        return $sqlrow["num"];
    }

    /*
     * Function: save
     *
     * Throws:
     *  <AlreadyExistsError>, <InvalidDataError>
     */
    public function save()
    {
        if (!self::test_name($this->name)) {
            throw new InvalidDataError("invalid_tag_name");
        }

        $tx = new Transaction();
        try {
            $stmt = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_tags` WHERE `name` = ? AND `id` != ?", $this->name, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $this->title->save();
            qdb(
                "UPDATE `PREFIX_tags` SET `name` = ?, `title` = ? WHERE `id` = ?",
                $this->name,
                $this->title->get_id(),
                $this->id
            );
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /*
     * Function: delete
     */
    public function delete()
    {
        $tx = new Transaction();
        try {
            $this->title->delete();
            qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `tag` = ?", $this->id);
            qdb("DELETE FROM `PREFIX_tags` WHERE `id` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }
}

/*
 * Class: UnknownFileFormat
 * Exception that will be thrown, if a input file has an unsupported file format.
 */
class UnknownFileFormat extends Exception
{
}

/*
 * Class: IOError
 * This Exception is thrown, if a IO-Error occurs (file not available, no read/write acccess...).
 */
class IOError extends Exception
{
}

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
    public function get_id()
    {
        return $this->id;
    }
    public function get_filename()
    {
        return $this->file;
    }

    /*
     * Constructor: create
     * Create a new image
     *
     * Parameters:
     *  $name - The name for the image
     *  $file - An uploaded image file (move_uploaded_file must be able to move the file!).
     *
     * Throws:
     *  <IOError>, <UnknownFileFormat>
     */
    public static function create($name, $file)
    {
        $obj = new self();
        $obj->name = $name;
        $obj->file = "0";

        $tx = new Transaction();
        try {
            global $db_con;

            qdb("INSERT INTO `PREFIX_images` (`name`, `file`) VALUES (?, '0')", $name);
            $obj->id = $db_con->lastInsertId();
            $obj->exchange_image($file);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
        return $obj;
    }

    /*
     * Constructor: by_id
     * Get image by ID.
     *
     * Parameters:
     *  $id - The ID
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_id($id)
    {
        $stmt = qdb("SELECT `id`, `name`, `file` FROM `PREFIX_images` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: all
     * Gets all images.
     *
     * Returns:
     *  Array of <Image> objects.
     */
    public function all()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `name`, `file` FROM `PREFIX_images` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: exchange_image
     * Exchanges image file. Also saves object to database.
     *
     * Parameters:
     *  $file - Location of new image.(move_uploaded_file must be able to move the file!)
     *
     * Throws:
     *  <IOError>, <UnknownFileFormat>
     */
    public function exchange_image($file)
    {
        global $imagetype_file_extensions;
        if (!is_file($file)) {
            throw new IOError("\"$file\" is not available");
        }
        $imageinfo = getimagesize($file);
        if ($imageinfo === false) {
            throw new UnknownFileFormat();
        }
        if (!isset($imagetype_file_extensions[$imageinfo[2]])) {
            throw new UnknownFileFormat();
        }
        if (is_file(SITE_BASE_PATH . "/images/" . $this->file)) {
            unlink(SITE_BASE_PATH . "/images/" . $this->file);
        }
        $new_fn = $this->id . "." . $imagetype_file_extensions[$imageinfo[2]];
        if (!move_uploaded_file($file, SITE_BASE_PATH . "/images/" . $new_fn)) {
            throw new IOError("Can not move file.");
        }
        $this->file = $new_fn;
        $this->save();

        /* make preview image */
        switch ($imageinfo[2]) {
            case IMAGETYPE_GIF:  $img = imagecreatefromgif(SITE_BASE_PATH . "/images/" . $new_fn); break;
            case IMAGETYPE_JPEG: $img = imagecreatefromjpeg(SITE_BASE_PATH . "/images/" . $new_fn); break;
            case IMAGETYPE_PNG:  $img = imagecreatefrompng(SITE_BASE_PATH . "/images/" . $new_fn); break;
            default: $img = imagecreatetruecolor(40, 40); imagefill($img, 1, 1, imagecolorallocate($img, 127, 127, 127)); break;
        }
        $w_orig = imagesx($img);
        $h_orig = imagesy($img);
        if (($w_orig > self::$pre_maxw) or ($h_orig > self::$pre_maxh)) {
            $ratio = $w_orig / $h_orig;
            if ($ratio > 1) {
                $w_new = round(self::$pre_maxw);
                $h_new = round(self::$pre_maxw / $ratio);
            } else {
                $h_new = round(self::$pre_maxh);
                $w_new = round(self::$pre_maxh * $ratio);
            }
            $preview = imagecreatetruecolor($w_new, $h_new);
            imagecopyresized($preview, $img, 0, 0, 0, 0, $w_new, $h_new, $w_orig, $h_orig);
            imagepng($preview, SITE_BASE_PATH . "/images/previews/{$this->id}.png");
        } else {
            imagepng($img, SITE_BASE_PATH . "/images/previews/{$this->id}.png");
        }
    }

    /*
     * Function: save
     */
    public function save()
    {
        qdb(
            "UPDATE `PREFIX_images` SET `name` = ?, `file` = ? WHERE `id` = ?",
            $this->name,
            $this->file,
            $this->id
        );
    }

    /*
     * Function: delete
     */
    public function delete()
    {
        qdb("DELETE FROM `PREFIX_images` WHERE `id` = ?", $this->id);
        if (is_file(SITE_BASE_PATH . "/images/" . $this->file)) {
            unlink(SITE_BASE_PATH . "/images/" . $this->file);
        }
        if (is_file(SITE_BASE_PATH . "/images/previews/{$this->id}.png")) {
            unlink(SITE_BASE_PATH . "/images/previews/{$this->id}.png");
        }
    }
}

/*
 * Class: RepositoryUnreachableOrInvalid
 * A Exception that will be thrown, if the repository is unreachable or seems to be an invalid repository.
 */
class RepositoryUnreachableOrInvalid extends Exception
{
}

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
        $this->stream_ctx = stream_context_create(["http" => ["timeout" => 5]]);
    }

    /*
     * Functions: Getters
     * get_id          - Get internal ID.
     * get_baseurl     - Get the baseurl of the repository.
     * get_name        - Get repository name.
     * get_description - Get repository description.
     */
    public function get_id()
    {
        return $this->id;
    }
    public function get_baseurl()
    {
        return $this->baseurl;
    }
    public function get_name()
    {
        return $this->name;
    }
    public function get_description()
    {
        return $this->description;
    }

    /*
     * Constructor: create
     * Create a new repository entry from a base url.
     *
     * Parameters:
     *  $baseurl - The baseurl of the repository.
     *
     * Throws:
     *  Could throw a <RepositoryUnreachableOrInvalid> exception. In this case, nothing will be written to the database.
     */
    public static function create($baseurl)
    {
        $obj = new self();

        if (preg_match('/^(http[s]?:\\/\\/.*?)[\\/]?$/', $baseurl, $matches) == 0) {
            throw new RepositoryUnreachableOrInvalid();
        }

        $obj->baseurl = $matches[1];
        $obj->refresh(true);

        $tx = new Transaction();
        try {
            global $db_con;

            qdb(
                "INSERT INTO PREFIX_repositories (baseurl, name, description, pkgcache, lastrefresh) VALUES (?, ?, ?, ?, ?)",
                $obj->baseurl,
                $obj->name,
                $obj->description,
                base64_encode(serialize($obj->packages)),
                $obj->lastrefresh
            );
            $obj->id = $db_con->lastInsertId();
            $obj->save();
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }

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
     *  $id - ID.
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_id($id)
    {
        $stmt = qdb("SELECT `id`, `name`, `description`, `baseurl`, `pkgcache`, `lastrefresh` FROM `PREFIX_repositories` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: all
     * Gets all available repositories.
     *
     * Returns:
     *  Array of <Repository> objects.
     */
    public static function all()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `name`, `description`, `baseurl`, `pkgcache`, `lastrefresh` FROM `PREFIX_repositories` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    private function save()
    {
        qdb(
            "UPDATE `PREFIX_repositories` SET `baseurl` = ?, `name` = ?, `description` = ?, `pkgcache` = ?, `lastrefresh` = ? WHERE `id` = ?",
            $this->baseurl,
            $this->name,
            $this->description,
            base64_encode(serialize($this->packages)),
            $this->lastrefresh,
            $this->id
        );
    }

    /*
     * Function: delete
     * Delete the repository entry from the database.
     */
    public function delete()
    {
        qdb("DELETE FROM `PREFIX_repositories` WHERE `id` = ?", $this->id);
    }

    /*
     * Function: refresh
     * Refresh the package cache and the name and description.
     *
     * Parameters:
     *  $force - Force a refresh, even if the data was already fetched in the last 6 hours (default: False).
     *
     * Throws:
     *  <RepositoryUnreachableOrInvalid>
     */
    public function refresh($force = false)
    {
        if (($this->lastrefresh > (time() - (60*60*4))) and (!$force)) {
            return;
        }

        $repometa = @file_get_contents($this->baseurl . "/repometa", false, $this->stream_ctx);
        if ($repometa === false) {
            throw new RepositoryUnreachableOrInvalid();
        }
        $repometa = @unserialize($repometa);
        if ((!is_array($repometa)) or (!isset($repometa["name"])) or (!isset($repometa["description"]))) {
            throw new RepositoryUnreachableOrInvalid();
        }

        $this->name        = $repometa["name"];
        $this->description = $repometa["description"];
        $this->packages    = @unserialize(@file_get_contents($this->baseurl . "/packagelist", false, $this->stream_ctx));

        $this->lastrefresh = time();

        $this->save();
    }

    /*
     * Function: get_package_meta
     * Get metadata of a plugin package from this repository.
     *
     * Parameters:
     *  $pkgname - The name of the package.
     *
     * Throws:
     *  A <DoesNotExistError> Exception, if the package was not found.
     *
     * Returns:
     *  A <PluginPackageMeta> object
     */
    public function get_package_meta($pkgname)
    {
        $found = false;
        foreach ($this->packages as $p) {
            if ($p[0] == $pkgname) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new DoesNotExistError("Package not in package cache.");
        }

        $pkgmeta = @unserialize(@file_get_contents($this->baseurl . "/packages/" . urlencode($pkgname) . "/meta", false, $this->stream_ctx));

        if (!($pkgmeta instanceof PluginPackageMeta)) {
            throw new DoesNotExistError();
        }

        return $pkgmeta;
    }

    /*
     * Function: download_package
     * Download a package from the repository
     *
     * Parameters:
     *  $pkgname - Name of the package.
     *  $version - The version to download (defaults to "current").
     *
     * Throws:
     *  * A <DoesNotExistError> Exception, if the package was not found.
     *  * A <InvalidPackage> Exception, if the package was malformed.
     *
     * Returns:
     *  A <PluginPackage> object.
     */
    public function download_package($pkgname, $version = "current")
    {
        $found = false;
        foreach ($this->packages as $p) {
            if ($p[0] == $pkgname) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new DoesNotExistError("Package not in package cache.");
        }

        $raw = @file_get_contents($this->baseurl . "/packages/" . urlencode($pkgname) . "/versions/" . urlencode($version), false, $this->stream_ctx);
        if ($raw === false) {
            throw new DoesNotExistError();
        }

        return PluginPackage::load($raw);
    }
}

/*
 * Class: Article
 * Representation of an article
 */
class Article extends BySQLRowEnabled
{
    /** @var int */
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
        $this->section_obj = null;
    }

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id             = (int)$sqlrow["id"];
        $this->urlname        = $sqlrow["urlname"];
        $this->title          = Multilingual::by_id($sqlrow["title"]);
        $this->text           = Multilingual::by_id($sqlrow["text"]);
        $this->excerpt        = Multilingual::by_id($sqlrow["excerpt"]);
        $this->meta           = $sqlrow["meta"];
        $this->custom         = unserialize(base64_decode($sqlrow["custom"]));
        $this->article_image  = $sqlrow["article_image"] == 0 ? null : Image::by_id($sqlrow["article_image"]);
        $this->status         = $sqlrow["status"];
        $this->section_id     = $sqlrow["section"];
        $this->timestamp      = $sqlrow["timestamp"];
        $this->allow_comments = $sqlrow["allow_comments"] == 1;
    }

    /*
     * Function: get_id
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /*
     * Function: test_urlname
     * Test, if a urlname is a valid urlname.
     *
     * Parameters:
     *  $urlname - Urlname to test
     *
     * Returns:
     *  True, if the urlname is valid, False otherwise.
     */
    public static function test_urlname($urlname)
    {
        return (bool) preg_match('/^[a-zA-Z0-9-_]+$/', $urlname);
    }

    /*
     * Function: test_status
     * Test, if a status is valid.
     *
     * Parameters:
     *  $status - Status value to test.
     *
     * Returns:
     *  True, if the status is a valid status value, False otherwise.
     */
    public static function test_status($status)
    {
        return is_numeric($status) and ($status >= 0) and ($status <= 3);
    }

    /*
     * Constructor: create
     * Create a new Article object.
     *
     * Parameters:
     *  urlname - A unique URL name
     *
     * Throws:
     *  <AlreadyExistsError>, <InvalidDataError>
     */
    public static function create($urlname)
    {
        global $ratatoeskr_settings;
        global $db_con;

        if (!self::test_urlname($urlname)) {
            throw new InvalidDataError("invalid_urlname");
        }

        try {
            self::by_urlname($urlname);
        } catch (DoesNotExistError $e) {
            $obj = new self();
            $obj->urlname        = $urlname;
            $obj->title          = Multilingual::create();
            $obj->text           = Multilingual::create();
            $obj->excerpt        = Multilingual::create();
            $obj->meta           = "";
            $obj->custom         = [];
            $obj->article_image  = null;
            $obj->status         = ARTICLE_STATUS_HIDDEN;
            $obj->section_id     = $ratatoeskr_settings["default_section"];
            $obj->timestamp      = time();
            $obj->allow_comments = $ratatoeskr_settings["allow_comments_default"];

            qdb(
                "INSERT INTO `PREFIX_articles` (`urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments`) VALUES ('', ?, ?, ?, '', ?, 0, ?, ?, ?, ?)",
                $obj->title->get_id(),
                $obj->text->get_id(),
                $obj->excerpt->get_id(),
                base64_encode(serialize($obj->custom)),
                $obj->status,
                $obj->section_id,
                $obj->timestamp,
                $obj->allow_comments ? 1 : 0
            );
            $obj->id = $db_con->lastInsertId();
            return $obj;
        }

        throw new AlreadyExistsError();
    }

    /*
     * Constructor: by_id
     * Get by ID.
     *
     * Parameters:
     *  $id - The ID.
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_id($id)
    {
        $stmt = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: by_urlname
     * Get by urlname
     *
     * Parameters:
     *  $urlname - The urlname
     *
     * Throws:
     *  <DoesNotExistError>
     */
    public static function by_urlname($urlname)
    {
        $stmt = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `urlname` = ?", $urlname);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /*
     * Constructor: by_multi
     * Get Articles by multiple criterias
     *
     * Parameters:
     *  $criterias - Array that can have these keys: id (int) , urlname (string), section (<Section> object), status (int), onlyvisible, langavail(string), tag (<Tag> object)
     *  $sortby    - Sort by this field (id, urlname, timestamp or title)
     *  $sortdir   - Sorting directory (ASC or DESC)
     *  $count     - How many entries (NULL for unlimited)
     *  $offset    - How many entries should be skipped (NULL for none)
     *  $perpage   - How many entries per page (NULL for no paging)
     *  $page      - Page number (starting at 1, NULL for no paging)
     *  &$maxpage  - Number of pages will be written here, if paging is activated.
     *
     * Returns:
     *  Array of Article objects
     */
    public static function by_multi($criterias, $sortby, $sortdir, $count, $offset, $perpage, $page, &$maxpage)
    {
        $subqueries = [];
        $subparams = [];
        foreach ($criterias as $k => $v) {
            switch ($k) {
                case "id":
                    $subqueries[] = "`a`.`id` = ?";
                    $subparams[] = $v;
                    break;
                case "urlname":
                    $subqueries[] = "`a`.`urlname` = ?";
                    $subparams[] = $v;
                    break;
                case "section":
                    $subqueries[] = "`a`.`section` = ?";
                    $subparams[] = $v->get_id();
                    break;
                case "status":
                    $subqueries[] = "`a`.`status` = ?";
                    $subparams[] = $v;
                    break;
                case "onlyvisible":
                    $subqueries[] = "`a`.`status` != 0";
                    break;
                case "langavail":
                    $subqueries[] = "`b`.`language` = ?";
                    $subparams[] = $v;
                    break;
                case "tag":
                    $subqueries[] = "`c`.`tag` = ?";
                    $subparams[] = $v->get_id();
                    break;
            }
        }

        if (($sortdir != "ASC") and ($sortdir != "DESC")) {
            $sortdir = "ASC";
        }
        $sorting = "";
        switch ($sortby) {
            case "id":        $sorting = "ORDER BY `a`.`id` $sortdir";        break;
            case "urlname":   $sorting = "ORDER BY `a`.`urlname` $sortdir";   break;
            case "timestamp": $sorting = "ORDER BY `a`.`timestamp` $sortdir"; break;
            case "title":     $sorting = "ORDER BY `b`.`text` $sortdir";      break;
        }

        $stmt = prep_stmt("SELECT `a`.`id` AS `id`, `a`.`urlname` AS `urlname`, `a`.`title` AS `title`, `a`.`text` AS `text`, `a`.`excerpt` AS `excerpt`, `a`.`meta` AS `meta`, `a`.`custom` AS `custom`, `a`.`article_image` AS `article_image`, `a`.`status` AS `status`, `a`.`section` AS `section`, `a`.`timestamp` AS `timestamp`, `a`.`allow_comments` AS `allow_comments` FROM `PREFIX_articles` `a`
INNER JOIN `PREFIX_translations` `b` ON `a`.`title` = `b`.`multilingual`
LEFT OUTER JOIN `PREFIX_article_tag_relations` `c` ON `a`.`id` = `c`.`article`
WHERE " . implode(" AND ", $subqueries) . " $sorting");

        $stmt->execute($subparams);

        $rows = [];
        $fetched_ids = [];
        while ($sqlrow = $stmt->fetch()) {
            if (!in_array($sqlrow["id"], $fetched_ids)) {
                $rows[]        = $sqlrow;
                $fetched_ids[] = $sqlrow["id"];
            }
        }

        if ($count !== null) {
            $rows = array_slice($rows, 0, $count);
        }
        if ($offset !== null) {
            $rows = array_slice($rows, $offset);
        }
        if (($perpage !== null) and ($page !== null)) {
            $maxpage = ceil(count($rows) / $perpage);
            $rows = array_slice($rows, $perpage * ($page - 1), $perpage);
        }

        $rv = [];
        foreach ($rows as $r) {
            $rv[] = self::by_sqlrow($r);
        }
        return $rv;
    }

    /*
     * Constructor: all
     * Get all articles
     *
     * Returns:
     *  Array of Article objects
     */
    public static function all()
    {
        $rv = [];
        $stmt = qdb("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: get_comments
     * Getting comments for this article.
     *
     * Parameters:
     *  $limit_lang   - Get only comments in a language (empty string for no limitation, this is the default).
     *  $only_visible - Do you only want the visible comments? (Default: False)
     *
     * Returns:
     *  Array of <Comment> objects.
     */
    public function get_comments($limit_lang = "", $only_visible = false)
    {
        $rv = [];

        $conditions = ["`article` = ?"];
        $arguments = [$this->id];
        if ($limit_lang != "") {
            $conditions[] = "`language` = ?";
            $arguments[] = $limit_lang;
        }
        if ($only_visible) {
            $conditions[] = "`visible` = 1";
        }

        $stmt = prep_stmt("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE " . implode(" AND ", $conditions));
        $stmt->execute($arguments);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Comment::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: get_tags
     * Get all Tags of this Article.
     *
     * Returns:
     *  Array of <Tag> objects.
     */
    public function get_tags()
    {
        $rv = [];
        $stmt = qdb("SELECT `a`.`id` AS `id`, `a`.`name` AS `name`, `a`.`title` AS `title` FROM `PREFIX_tags` `a` INNER JOIN `PREFIX_article_tag_relations` `b` ON `a`.`id` = `b`.`tag` WHERE `b`.`article` = ?", $this->id);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Tag::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /*
     * Function: set_tags
     * Set the Tags that should be associated with this Article.
     *
     * Parameters:
     *  $tags - Array of <Tag> objects.
     */
    public function set_tags($tags)
    {
        $tx = new Transaction();
        try {
            foreach ($tags as $tag) {
                $tag->save();
            }

            qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `article`= ?", $this->id);

            $articleid = $this->id;
            if (!empty($tags)) {
                $stmt = prep_stmt(
                    "INSERT INTO `PREFIX_article_tag_relations` (`article`, `tag`) VALUES " .
                    implode(",", array_fill(0, count($tags), "(?,?)"))
                );
                $args = [];
                foreach ($tags as $tag) {
                    $args[] = $articleid;
                    $args[] = $tag->get_id();
                }
                $stmt->execute($args);
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /*
     * Function: get_section
     * Get the section of this article.
     *
     * Returns:
     *  A <Section> object.
     */
    public function get_section()
    {
        if ($this->section_obj === null) {
            $this->section_obj = Section::by_id($this->section_id);
        }
        return $this->section_obj;
    }

    /*
     * Function: set_section
     * Set the section of this article.
     *
     * Parameters:
     *  $section - A <Section> object.
     */
    public function set_section($section)
    {
        $this->section_id  = $section->get_id();
        $this->section_obj = $section;
    }

    /*
     * Function: get_extradata
     * Get the extradata for this article and the given plugin.
     *
     * Parameters:
     *  $plugin_id - The ID of the plugin.
     *
     * Returns:
     *  An <ArticleExtradata> object.
     */
    public function get_extradata($plugin_id)
    {
        return new ArticleExtradata($this->id, $plugin_id);
    }

    /*
     * Function: save
     *
     * Throws:
     *  <AlreadyExistsError>, <InvalidDataError>
     */
    public function save()
    {
        if (!self::test_urlname($this->urlname)) {
            throw new InvalidDataError("invalid_urlname");
        }

        if (!self::test_status($this->status)) {
            throw new InvalidDataError("invalid_article_status");
        }

        $tx = new Transaction();
        try {
            $stmt = qdb("SELECT COUNT(*) AS `n` FROM `PREFIX_articles` WHERE `urlname` = ? AND `id` != ?", $this->urlname, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $this->title->save();
            $this->text->save();
            $this->excerpt->save();

            qdb(
                "UPDATE `PREFIX_articles` SET `urlname` = ?, `title` = ?, `text` = ?, `excerpt` = ?, `meta` = ?, `custom` = ?, `article_image` = ?, `status` = ?, `section` = ?, `timestamp` = ?, `allow_comments` = ? WHERE `id` = ?",
                $this->urlname,
                $this->title->get_id(),
                $this->text->get_id(),
                $this->excerpt->get_id(),
                $this->meta,
                base64_encode(serialize($this->custom)),
                $this->article_image === null ? 0 : $this->article_image->get_id(),
                $this->status,
                $this->section_id,
                $this->timestamp,
                $this->allow_comments ? 1 : 0,
                $this->id
            );
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /*
     * Function: delete
     */
    public function delete()
    {
        $tx = new Transaction();
        try {
            $this->title->delete();
            $this->text->delete();
            $this->excerpt->delete();

            foreach ($this->get_comments() as $comment) {
                $comment->delete();
            }

            qdb("DELETE FROM `PREFIX_article_tag_relations` WHERE `article` = ?", $this->id);
            qdb("DELETE FROM `PREFIX_article_extradata` WHERE `article` = ?", $this->id);
            qdb("DELETE FROM `PREFIX_articles` WHERE `id` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }
}

/**
 * A Key-Value-Storage assigned to Articles for plugins to store additional data.
 * Can be accessed like an array.
 * Keys are strings and Values can be everything serialize() can process.
 */
class ArticleExtradata extends KVStorage
{
    /**
     * @param int|mixed $article_id The ID of the Article.
     * @param int|mixed $plugin_id The ID of the Plugin.
     * @param Database|null $db
     */
    public function __construct($article_id, $plugin_id, ?Database $db = null)
    {
        $this->init(
            "PREFIX_article_extradata",
            [
                "article" => (int)$article_id,
                "plugin" => (int)$plugin_id,
            ],
            $db ?? Env::getGlobal()->database()
        );
    }
}

/*
 * Function: dbversion
 * Get the version of the database structure currently used.
 *
 * Returns:
 *  The numerical version of the current database structure.
 */
function dbversion()
{
    global $config;

    /* Is the meta table present? If no, the version is 0. */
    $stmt = qdb(
        "SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema` = ? AND `table_name` = ?",
        $config["mysql"]["db"],
        sub_prefix("PREFIX_meta")
    );
    list($n) = $stmt->fetch();
    if ($n == 0) {
        return 0;
    }

    $stmt = qdb("SELECT `value` FROM `PREFIX_meta` WHERE `key` = 'dbversion'");
    $sqlrow = $stmt->fetch();
    return unserialize(base64_decode($sqlrow["value"]));
}

/*
 * Function: clean_database
 * Clean up the database
 */
function clean_database()
{
    global $ratatoeskr_settings;
    if ((!isset($ratatoeskr_settings["last_db_cleanup"])) or ($ratatoeskr_settings["last_db_cleanup"] < (time() - 86400))) {
        Plugin::clean_db();
        $ratatoeskr_settings["last_db_cleanup"] = time();
    }
}
