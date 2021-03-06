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

require_once(dirname(__FILE__) . "/utils.php");
require_once(dirname(__FILE__) . "/textprocessors.php");
require_once(dirname(__FILE__) . "/pluginpackage.php");

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

/**
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

    /**
     * Get an instance of this class.
     * All instances are equal (ie. this is a singleton), so you can also use
     * the global $ratatoeskr_settings instance.
     *
     * @param Database|null $db
     * @return self
     */
    public static function get_instance(?Database $db = null): self
    {
        $db = $db ?? Env::getGlobal()->database();

        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /** @var Database */
    private $db;

    private $buffer;
    private $to_be_deleted;
    private $to_be_created;
    private $to_be_updated;

    private function __construct(Database $db)
    {
        $this->db = $db;

        $this->buffer = [];
        $stmt = $this->db->query("SELECT `key`, `value` FROM `PREFIX_settings_kvstorage` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $this->buffer[$sqlrow["key"]] = unserialize(base64_decode($sqlrow["value"]));
        }

        $this->to_be_created = [];
        $this->to_be_deleted = [];
        $this->to_be_updated = [];
    }

    public function save()
    {
        $tx = new DbTransaction($this->db);
        try {
            foreach ($this->to_be_deleted as $k) {
                $this->db->query("DELETE FROM `PREFIX_settings_kvstorage` WHERE `key` = ?", $k);
            }
            foreach ($this->to_be_updated as $k) {
                $this->db->query("UPDATE `PREFIX_settings_kvstorage` SET `value` = ? WHERE `key` = ?", base64_encode(serialize($this->buffer[$k])), $k);
            }
            foreach ($this->to_be_created as $k) {
                $this->db->query("INSERT INTO `PREFIX_settings_kvstorage` (`key`, `value`) VALUES (?, ?)", $k, base64_encode(serialize($this->buffer[$k])));
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

/**
 * The representation of a plugin in the database.
 */
class Plugin
{
    /** @var int */
    private $id;

    /** @var string Plugin name. */
    public $name;

    /** @var string Plugin code. */
    public $code;

    /** @var string Main class of the plugin. */
    public $classname;

    /** @var bool Is the plugin activated? */
    public $active;

    /** @var string Author of the plugin. */
    public $author;

    /** @var string Version (text) */
    public $versiontext;

    /** @var int Version (counter) */
    public $versioncount;

    /** @var string A short description. */
    public $short_description;

    /** @var string URL for updates. */
    public $updatepath;

    /** @var string Webpage of the plugin. */
    public $web;

    /** @var string Help page. */
    public $help;

    /** @var string License text. */
    public $license;

    /** @var bool Is this plugin installed? Used during the installation process. */
    public $installed;

    /** @var bool Should the plugin be updated at next start? */
    public $update;

    /** @var int The API version this Plugin needs. */
    public $api;

    private function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * Performs some datadase cleanup jobs on the plugin table.
     * @param Database|null $db
     */
    public static function clean_db(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();
        $db->query("DELETE FROM `PREFIX_plugins` WHERE `installed` = 0 AND `added` < ?", (time() - (60*5)));
    }

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Creates a new, empty plugin database entry
     * @param PluginPackage $pkg Must be a valid package, see {@see PluginPackage::validate()}.
     * @param Database|null $db
     * @return self
     * @throws InvalidPackage
     */
    public static function create(PluginPackage $pkg, ?Database $db = null): self
    {
        $pkg->validate();

        $db = $db ?? Env::getGlobal()->database();

        $db->query(
            "INSERT INTO `PREFIX_plugins` SET
                `name` = ?,
                `author` = ?,
                `versiontext` = ?,
                `versioncount` = ?,
                `short_description` = ?,
                `updatepath` = ?,
                `web` = ?,
                `license` = ?,
                `help` = ?,
                `code` = ?,
                `classname` = ?,
                `active` = 0,
                `installed` = 0,
                `added` = ?,
                `update` = ?,
                `api` = ?
            ",
            $pkg->name,
            $pkg->author,
            $pkg->versiontext,
            $pkg->versioncount,
            $pkg->short_description,
            $pkg->updatepath ?? '',
            $pkg->web ?? '',
            $pkg->license ?? '',
            $pkg->help ?? '',
            $pkg->code,
            $pkg->classname,
            time(),
            0,
            $pkg->api
        );

        $obj = new self($db->lastInsertId());
        $obj->fill_from_pluginpackage($pkg);
        return $obj;
    }

    /**
     * Fills plugin data from an PluginPackage object.
     * @param PluginPackage $pkg
     */
    public function fill_from_pluginpackage(PluginPackage $pkg): void
    {
        $this->name              = (string)$pkg->name;
        $this->code              = (string)$pkg->code;
        $this->classname         = (string)$pkg->classname;
        $this->author            = (string)$pkg->author;
        $this->versiontext       = (string)$pkg->versiontext;
        $this->versioncount      = (int)$pkg->versioncount;
        $this->short_description = (string)$pkg->short_description;
        $this->updatepath        = (string)$pkg->updatepath;
        $this->web               = (string)$pkg->web;
        $this->license           = (string)$pkg->license;
        $this->help              = (string)$pkg->help;
        $this->api               = (int)$pkg->api;

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

    private static function by_sqlrow(array $row): self
    {
        $obj = new self((int)$row["id"]);
        $obj->name              = (string)$row["name"];
        $obj->code              = (string)$row["code"];
        $obj->classname         = (string)$row["classname"];
        $obj->active            = ($row["active"] == 1);
        $obj->author            = (string)$row["author"];
        $obj->versiontext       = (string)$row["versiontext"];
        $obj->versioncount      = (int)$row["versioncount"];
        $obj->short_description = (string)$row["short_description"];
        $obj->updatepath        = (string)$row["updatepath"];
        $obj->web               = (string)$row["web"];
        $obj->help              = (string)$row["help"];
        $obj->license           = (string)$row["license"];
        $obj->installed         = ($row["installed"] == 1);
        $obj->update            = ($row["update"] == 1);
        $obj->api               = (string)$row["api"];

        return $obj;
    }

    /**
     * Gets plugin by ID.
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name`, `author`, `versiontext`, `versioncount`, `short_description`, `updatepath`, `web`, `help`, `code`, `classname`, `active`, `license`, `installed`, `update`, `api` FROM `PREFIX_plugins` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Gets all Plugins
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `name`, `author`, `versiontext`, `versioncount`, `short_description`, `updatepath`, `web`, `help`, `code`, `classname`, `active`, `license`, `installed`, `update`, `api` FROM `PREFIX_plugins` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query(
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

    /**
     * Delete a directory and all of its content.
     * @param string $dir
     */
    private static function delete_directory(string $dir): void
    {
        $dir_content = scandir($dir);
        foreach ($dir_content as $f) {
            if (($f == "..") or ($f == ".")) {
                continue;
            }

            $f = "$dir/$f";

            if (is_dir($f)) {
                self::delete_directory($f);
            } else {
                unlink($f);
            }
        }
        rmdir($dir);
    }

    public function delete(?Database $db = null, ?Env $env = null): void
    {
        $env = $env ?? Env::getGlobal();
        $db = $db ?? $env->database();

        $tx = new DbTransaction($db);
        try {
            $db->query("DELETE FROM `PREFIX_plugins` WHERE `id` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_plugin_kvstorage` WHERE `plugin` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_article_extradata` WHERE `plugin` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }

        if (is_dir($env->siteBasePath() . "/ratatoeskr/plugin_extradata/private/" . $this->id)) {
            self::delete_directory($env->siteBasePath() . "/ratatoeskr/plugin_extradata/private/" . $this->id);
        }
        if (is_dir($env->siteBasePath() . "/ratatoeskr/plugin_extradata/public/" . $this->id)) {
            self::delete_directory($env->siteBasePath() . "/ratatoeskr/plugin_extradata/public/" . $this->id);
        }
        if (is_dir($env->siteBasePath() . "/ratatoeskr/templates/src/plugintemplates/" . $this->id)) {
            self::delete_directory($env->siteBasePath() . "/ratatoeskr/templates/src/plugintemplates/" . $this->id);
        }
    }
}

/**
 * Representing a section
 */
class Section extends BySQLRowEnabled
{
    /** @var int */
    private $id;

    /** @var string The name of the section. */
    public $name;

    /** @var Multilingual The title of the section. */
    public $title;

    /** @var string Name of the template. */
    public $template;

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id       = (int)$sqlrow["id"];
        $this->name     = (string)$sqlrow["name"];
        $this->title    = Multilingual::by_id($sqlrow["title"]); // FIXME: Right now, we can't pass a $db to Multilingual::by_id here, as this violates the populate_by_sqlrow function signature :(
        $this->template = (string)$sqlrow["template"];
    }

    /**
     * Tests, if a name is a valid section name.
     *
     * @param string|mixed $name The name to test.
     * @return bool
     */
    public static function test_name($name): bool
    {
        return preg_match("/^[a-zA-Z0-9\\-_]+$/", (string)$name) != 0;
    }

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Creates a new section.
     *
     * @param string|mixed $name The name of the new section.
     * @param Database|null $db
     * @return Section
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public static function create($name, ?Database $db = null): self
    {
        $name = (string)$name;
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_name($name)) {
            throw new InvalidDataError("invalid_section_name");
        }

        try {
            self::by_name($name, $db);
        } catch (DoesNotExistError $e) {
            $obj           = new self();
            $obj->name     = $name;
            $obj->title    = Multilingual::create($db);
            $obj->template = "";

            $db->query("INSERT INTO `PREFIX_sections` (`name`, `title`, `template`) VALUES (?, ?, '')", $name, $obj->title->get_id());

            $obj->id = $db->lastInsertId();

            return $obj;
        }

        throw new AlreadyExistsError();
    }

    /**
     * Gets section by ID.
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

        $stmt = $db->query("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Gets section by name.
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

        $stmt = $db->query("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE `name` = ?", $name);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Gets all sections.
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `name`, `title`, `template` FROM `PREFIX_sections` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Get all styles associated with this section.
     *
     * @param Database|null $db
     * @return Style[]
     */
    public function get_styles(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `a`.`id` AS `id`, `a`.`name` AS `name`, `a`.`code` AS `code` FROM `PREFIX_styles` `a` INNER JOIN `PREFIX_section_style_relations` `b` ON `a`.`id` = `b`.`style` WHERE `b`.`section` = ?", $this->id);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Style::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Add a style to this section.
     *
     * @param Style $style
     * @param Database|null $db
     */
    public function add_style(Style $style, ?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $stmt = $db->query("SELECT COUNT(*) AS `n` FROM `PREFIX_section_style_relations` WHERE `style` = ? AND `section` = ?", $style->get_id(), $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] == 0) {
                $db->query("INSERT INTO `PREFIX_section_style_relations` (`section`, `style`) VALUES (?, ?)", $this->id, $style->get_id());
            }
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /**
     * Remove a style from this section.
     *
     * @param Style $style
     * @param Database|null $db
     */
    public function remove_style(Style $style, ?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query("DELETE FROM `PREFIX_section_style_relations` WHERE `section` = ? AND `style` = ?", $this->id, $style->get_id());
    }

    /**
     * Save the object to database.
     *
     * @param Database|null $db
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_name($this->name)) {
            throw new InvalidDataError("invalid_section_name");
        }

        $tx = new DbTransaction($db);
        try {
            $stmt = $db->query("SELECT COUNT(*) AS `n` FROM `PREFIX_sections` WHERE `name` = ? AND `id` != ?", $this->name, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $this->title->save();
            $db->query(
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

    /**
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $this->title->delete();
            $db->query("DELETE FROM `PREFIX_sections` WHERE `id` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_section_style_relations` WHERE `section` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }

    /**
     * Get all articles in this section.

     * @param Database|null $db
     * @return Article[]
     */
    public function get_articles(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `section` = ?", $this->id);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Article::by_sqlrow($sqlrow);
        }
        return $rv;
    }
}

/**
 * Representation of a tag
 */
class Tag extends BySQLRowEnabled
{
    /** @var int */
    private $id;

    /** @var string The name of the tag */
    public $name;

    /** @var Multilingual The title */
    public $title;

    /**
     * Test, if a name is a valid tag name.
     * @param string|mixed $name
     * @return bool
     */
    public static function test_name($name): bool
    {
        $name = (string)$name;

        return (strpos($name, ",") === false) and (strpos($name, " ") === false);
    }

    public function get_id(): int
    {
        return $this->id;
    }

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id    = $sqlrow["id"];
        $this->name  = $sqlrow["name"];
        $this->title = Multilingual::by_id($sqlrow["title"]);
    }

    /**
     * Create a new tag.
     *
     * @param string|null $name
     * @param Database|null $db
     * @return self
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public static function create($name, ?Database $db = null): self
    {
        $name = (string)$name;
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_name($name)) {
            throw new InvalidDataError("invalid_tag_name");
        }

        try {
            self::by_name($name, $db);
        } catch (DoesNotExistError $e) {
            $obj = new self();

            $obj->name  = $name;
            $obj->title = Multilingual::create($db);

            $db->query(
                "INSERT INTO `PREFIX_tags` (`name`, `title`) VALUES (?, ?)",
                $name,
                $obj->title->get_id()
            );
            $obj->id = $db->lastInsertId();

            return $obj;
        }
        throw new AlreadyExistsError();
    }

    /**
     * Get tag by ID
     *
     * @param int|null $id
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get tag by name
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

        $stmt = $db->query("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE `name` = ?", $name);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get all tags
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `name`, `title` FROM `PREFIX_tags` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Get all articles that are tagged with this tag
     *
     * @param Database|null $db
     * @return Article[]
     */
    public function get_articles(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query(
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

    /**
     * Count the articles that are tagged with this tag.
     *
     * @param Database|null $db
     * @return int
     */
    public function count_articles(?Database $db = null): int
    {
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT COUNT(*) AS `num` FROM `PREFIX_article_tag_relations` WHERE `tag` = ?", $this->id);
        $sqlrow = $stmt->fetch();
        return (int)$sqlrow["num"];
    }

    /**
     * @param Database|null $db
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_name($this->name)) {
            throw new InvalidDataError("invalid_tag_name");
        }

        $tx = new DbTransaction($db);
        try {
            $stmt = $db->query("SELECT COUNT(*) AS `n` FROM `PREFIX_tags` WHERE `name` = ? AND `id` != ?", $this->name, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $this->title->save();
            $db->query(
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

    /**
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $this->title->delete();
            $db->query("DELETE FROM `PREFIX_article_tag_relations` WHERE `tag` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_tags` WHERE `id` = ?", $this->id);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
    }
}

/**
 * Exception that will be thrown, if a input file has an unsupported file format.
 */
class UnknownFileFormat extends Exception
{
}

/**
 * This Exception is thrown, if a IO-Error occurs (file not available, no read/write acccess...).
 */
class IOError extends Exception
{
}

/**
 * Representation of an image entry.
 */
class Image extends BySQLRowEnabled
{
    private const PRE_MAXW = 150;
    private const PRE_MAXH = 100;

    /**
     * Array of default file extensions for most IMAGETYPE_* constants
     */
    private const IMAGETYPE_FILE_EXTENSIONS = [
        IMAGETYPE_GIF     => "gif",
        IMAGETYPE_JPEG    => "jpg",
        IMAGETYPE_PNG     => "png",
        IMAGETYPE_BMP     => "bmp",
        IMAGETYPE_TIFF_II => "tif",
        IMAGETYPE_TIFF_MM => "tif",
    ];

    /** @var int */
    private $id;

    /** @var string */
    private $filename;

    /** @var string The image name */
    public $name;

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id   = (int)$sqlrow["id"];
        $this->name = (string)$sqlrow["name"];
        $this->filename = (string)$sqlrow["file"];
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_filename(): string
    {
        return $this->filename;
    }

    /**
     * Create a new image
     *
     * @param string|mixed $name - The name for the image
     * @param string|mixed $file - An uploaded image file (move_uploaded_file must be able to move the file!).
     * @param Database|null $db
     * @return self
     * @throws IOError
     * @throws UnknownFileFormat
     */
    public static function create($name, $file, ?Database $db = null): self
    {
        $name = (string)$name;
        $file = (string)$file;

        $db = $db ?? Env::getGlobal()->database();

        $obj = new self();
        $obj->name = $name;
        $obj->filename = "0";

        $tx = new DbTransaction($db);
        try {
            $db->query("INSERT INTO `PREFIX_images` (`name`, `file`) VALUES (?, '0')", $name);
            $obj->id = $db->lastInsertId();
            $obj->exchange_image($file, $db);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }
        return $obj;
    }

    /**
     * Get image by ID.
     *
     * @param int|mixed $id
     * @param Database|null $db
     * @return Image
     * @throws DoesNotExistError
     */
    public static function by_id($id, ?Database $db = null): self
    {
        $id = (int)$id;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `name`, `file` FROM `PREFIX_images` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Gets all images.
     *
     * @param Database|null $db
     * @return self[]
     */
    public function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `name`, `file` FROM `PREFIX_images` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Exchanges image file. Also saves object to database.
     *
     * @param string|mixed $file - Location of new image (move_uploaded_file must be able to move the file!)
     * @param Database|null $db
     * @param Env|null $env
     * @throws IOError
     * @throws UnknownFileFormat
     */
    public function exchange_image($file, ?Database $db = null, ?Env $env = null): void
    {
        $env = $env ?? Env::getGlobal();
        $db = $db ?? $env->database();

        $file = (string)$file;

        if (!is_file($file)) {
            throw new IOError("\"$file\" is not available");
        }
        $imageinfo = getimagesize($file);
        if ($imageinfo === false) {
            throw new UnknownFileFormat();
        }
        if (!isset(self::IMAGETYPE_FILE_EXTENSIONS[$imageinfo[2]])) {
            throw new UnknownFileFormat();
        }
        if (is_file($env->siteBasePath() . "/images/" . $this->filename)) {
            unlink($env->siteBasePath() . "/images/" . $this->filename);
        }
        $new_fn = $this->id . "." . self::IMAGETYPE_FILE_EXTENSIONS[$imageinfo[2]];
        if (!move_uploaded_file($file, $env->siteBasePath() . "/images/" . $new_fn)) {
            throw new IOError("Can not move file.");
        }
        $this->filename = $new_fn;
        $this->save($db);

        /* make preview image */
        switch ($imageinfo[2]) {
            case IMAGETYPE_GIF:  $img = imagecreatefromgif($env->siteBasePath() . "/images/" . $new_fn); break;
            case IMAGETYPE_JPEG: $img = imagecreatefromjpeg($env->siteBasePath() . "/images/" . $new_fn); break;
            case IMAGETYPE_PNG:  $img = imagecreatefrompng($env->siteBasePath() . "/images/" . $new_fn); break;
            default: $img = imagecreatetruecolor(40, 40); imagefill($img, 1, 1, imagecolorallocate($img, 127, 127, 127)); break;
        }
        $w_orig = imagesx($img);
        $h_orig = imagesy($img);
        if (($w_orig > self::PRE_MAXW) || ($h_orig > self::PRE_MAXH)) {
            $ratio = $w_orig / $h_orig;
            if ($ratio > 1) {
                $w_new = round(self::PRE_MAXW);
                $h_new = round(self::PRE_MAXW / $ratio);
            } else {
                $h_new = round(self::PRE_MAXH);
                $w_new = round(self::PRE_MAXH * $ratio);
            }
            $preview = imagecreatetruecolor($w_new, $h_new);
            imagecopyresized($preview, $img, 0, 0, 0, 0, $w_new, $h_new, $w_orig, $h_orig);
            imagepng($preview, $env->siteBasePath() . "/images/previews/{$this->id}.png");
        } else {
            imagepng($img, $env->siteBasePath() . "/images/previews/{$this->id}.png");
        }
    }

    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query(
            "UPDATE `PREFIX_images` SET `name` = ?, `file` = ? WHERE `id` = ?",
            $this->name,
            $this->filename,
            $this->id
        );
    }

    public function delete(?Database $db = null, ?Env $env = null): void
    {
        $env = $env ?? Env::getGlobal();
        $db = $db ?? $env->database();

        $db->query("DELETE FROM `PREFIX_images` WHERE `id` = ?", $this->id);
        if (is_file($env->siteBasePath() . "/images/" . $this->filename)) {
            unlink($env->siteBasePath() . "/images/" . $this->filename);
        }
        if (is_file($env->siteBasePath() . "/images/previews/{$this->id}.png")) {
            unlink($env->siteBasePath() . "/images/previews/{$this->id}.png");
        }
    }
}

/**
 * An exception that will be thrown, if the repository is unreachable or seems to be an invalid repository.
 */
class RepositoryUnreachableOrInvalid extends Exception
{
}

/**
 * Representation of an plugin repository.
 */
class Repository extends BySQLRowEnabled
{
    /** @var int */
    private $id;

    /** @var string */
    private $baseurl;

    /** @var string */
    private $name;

    /** @var string */
    private $description;

    /** @var int Unix-timestamp of last refresh */
    private $lastrefresh;

    /** @var resource */
    private $stream_ctx;

    /**
     * Array with all packages from this repository. An entry itself is an array: array(name, versioncounter, description)
     * @var array[]
     */
    public $packages;

    protected function __construct()
    {
        $this->stream_ctx = stream_context_create(["http" => ["timeout" => 5]]);
    }

    /**
     * Get internal ID.
     *
     * @return int
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Get the baseurl of the repository.
     *
     * @return string
     */
    public function get_baseurl(): string
    {
        return $this->baseurl;
    }

    /**
     * Get repository name.
     *
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get repository description.
     *
     * @return string
     */
    public function get_description(): string
    {
        return $this->description;
    }

    /**
     * Create a new repository entry from a base url.
     *
     * @param $baseurl
     * @param Database|null $db
     * @return Repository
     * @throws RepositoryUnreachableOrInvalid In this case, nothing will be written to the database.
     */
    public static function create($baseurl, ?Database $db = null): self
    {
        $baseurl = (string)$baseurl;
        $db = $db ?? Env::getGlobal()->database();

        $obj = new self();

        if (preg_match('/^(http[s]?:\\/\\/.*?)[\\/]?$/', $baseurl, $matches) == 0) {
            throw new RepositoryUnreachableOrInvalid();
        }

        $obj->baseurl = $matches[1];
        $obj->refresh(true, $db);

        $tx = new DbTransaction($db);
        try {
            $db->query(
                "INSERT INTO PREFIX_repositories (baseurl, name, description, pkgcache, lastrefresh) VALUES (?, ?, ?, ?, ?)",
                $obj->baseurl,
                $obj->name,
                $obj->description,
                base64_encode(serialize($obj->packages)),
                $obj->lastrefresh
            );
            $obj->id = $db->lastInsertId();
            $obj->save($db);
            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();
            throw $e;
        }

        return $obj;
    }

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id          = (int)$sqlrow["id"];
        $this->name        = (string)$sqlrow["name"];
        $this->description = (string)$sqlrow["description"];
        $this->baseurl     = (string)$sqlrow["baseurl"];
        $this->packages    = unserialize(base64_decode($sqlrow["pkgcache"]));
        $this->lastrefresh = (int)$sqlrow["lastrefresh"];
    }

    /**
     * Get a repository entry by ID.
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

        $stmt = $db->query("SELECT `id`, `name`, `description`, `baseurl`, `pkgcache`, `lastrefresh` FROM `PREFIX_repositories` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if (!$sqlrow) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Gets all available repositories.
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `name`, `description`, `baseurl`, `pkgcache`, `lastrefresh` FROM `PREFIX_repositories` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    private function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query(
            "UPDATE `PREFIX_repositories` SET `baseurl` = ?, `name` = ?, `description` = ?, `pkgcache` = ?, `lastrefresh` = ? WHERE `id` = ?",
            $this->baseurl,
            $this->name,
            $this->description,
            base64_encode(serialize($this->packages)),
            $this->lastrefresh,
            $this->id
        );
    }

    /**
     * Delete the repository entry from the database.
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $db->query("DELETE FROM `PREFIX_repositories` WHERE `id` = ?", $this->id);
    }

    /**
     * Refresh the package cache and the name and description.
     *
     * @param bool $force Force a refresh, even if the data was already fetched in the last 6 hours (default: false)
     * @param Database|null $db
     * @throws RepositoryUnreachableOrInvalid
     */
    public function refresh($force = false, ?Database $db = null): void
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

        $this->save($db);
    }

    /**
     * Get metadata of a plugin package from this repository.
     *
     * @param string $pkgname The name of the package.
     * @return PluginPackageMeta
     * @throws DoesNotExistError If the package was not found
     */
    public function get_package_meta($pkgname): PluginPackageMeta
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

    /**
     * Download a package from the repository
     *
     * @param string $pkgname Name of the package.
     * @param string $version The version to download (defaults to "current").
     * @return PluginPackage
     * @throws DoesNotExistError If the package was not found.
     * @throws InvalidPackage If the package was malformed.
     */
    public function download_package($pkgname, $version = "current"): PluginPackage
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

/**
 * Representation of an article
 */
class Article extends BySQLRowEnabled
{
    /** @var int Article is hidden (Numeric: 0) */
    public const STATUS_HIDDEN = 0;

    /** @var int Article is visible / live (Numeric: 1) */
    public const STATUS_LIVE = 1;

    /** @var int Article is sticky (Numeric: 2) */
    public const STATUS_STICKY = 2;

    /** @var int */
    private $id;

    /** @var int */
    private $section_id;

    /** @var null|Section */
    private $section_obj = null;

    /** @var string URL name */
    public $urlname;

    /** @var Multilingual Title */
    public $title;

    /** @var Multilingual The text */
    public $text;

    /** @var Multilingual Excerpt */
    public $excerpt;

    /** @var string Keywords, comma separated */
    public $meta;// TODO: Seems to be unused

    /** @var array Custom fields */
    public $custom;

    /** @var Image|null The article image. If none: null */
    public $article_image = null;

    /** @var int One of the self::STATUS_* constants */
    public $status;

    /** @var int Timestamp */
    public $timestamp;

    /** @var bool Are comments allowed? */
    public $allow_comments;

    protected function populate_by_sqlrow($sqlrow)
    {
        $this->id             = (int)$sqlrow["id"];
        $this->urlname        = (string)$sqlrow["urlname"];
        $this->title          = Multilingual::by_id($sqlrow["title"]);
        $this->text           = Multilingual::by_id($sqlrow["text"]);
        $this->excerpt        = Multilingual::by_id($sqlrow["excerpt"]);
        $this->meta           = (string)$sqlrow["meta"];
        $this->custom         = unserialize(base64_decode($sqlrow["custom"]));
        $this->article_image  = $sqlrow["article_image"] == 0 ? null : Image::by_id($sqlrow["article_image"]);
        $this->status         = (int)$sqlrow["status"];
        $this->section_id     = (int)$sqlrow["section"];
        $this->timestamp      = (int)$sqlrow["timestamp"];
        $this->allow_comments = $sqlrow["allow_comments"] == 1;
    }

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Test, if a urlname is a valid urlname.
     *
     * @param string|mixed $urlname
     * @return bool
     */
    public static function test_urlname($urlname): bool
    {
        $urlname = (string)$urlname;
        return (bool) preg_match('/^[a-zA-Z0-9-_]+$/', $urlname);
    }

    /**
     * Test, if a status is valid.
     *
     * @param mixed $status Status value to test.
     * @return bool
     */
    public static function test_status($status): bool
    {
        return is_numeric($status) && ($status >= 0) && ($status <= 3);
    }

    /**
     * Create a new Article object.
     *
     * @param string|mixed $urlname
     * @param Database|null $db
     * @return self
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public static function create($urlname, ?Database $db = null): self
    {
        global $ratatoeskr_settings;

        $urlname = (string)$urlname;
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_urlname($urlname)) {
            throw new InvalidDataError("invalid_urlname");
        }

        try {
            self::by_urlname($urlname, $db);
        } catch (DoesNotExistError $e) {
            $obj = new self();
            $obj->urlname        = $urlname;
            $obj->title          = Multilingual::create($db);
            $obj->text           = Multilingual::create($db);
            $obj->excerpt        = Multilingual::create($db);
            $obj->meta           = "";
            $obj->custom         = [];
            $obj->article_image  = null;
            $obj->status         = self::STATUS_HIDDEN;
            $obj->section_id     = $ratatoeskr_settings["default_section"];
            $obj->timestamp      = time();
            $obj->allow_comments = $ratatoeskr_settings["allow_comments_default"];

            $db->query(
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
            $obj->id = $db->lastInsertId();
            return $obj;
        }

        throw new AlreadyExistsError();
    }

    /**
     * Get by ID.
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

        $stmt = $db->query("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `id` = ?", $id);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get by urlname
     *
     * @param string|mixed $urlname
     * @param Database|null $db
     * @return self
     * @throws DoesNotExistError
     */
    public static function by_urlname($urlname, ?Database $db = null): self
    {
        $urlname = (string)$urlname;
        $db = $db ?? Env::getGlobal()->database();

        $stmt = $db->query("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE `urlname` = ?", $urlname);
        $sqlrow = $stmt->fetch();
        if ($sqlrow === false) {
            throw new DoesNotExistError();
        }

        return self::by_sqlrow($sqlrow);
    }

    /**
     * Get Articles by multiple criterias
     *
     * @param array|mixed $criterias Array that can have these keys: id (int) , urlname (string), section (<Section> object), status (int), onlyvisible, langavail(string), tag (<Tag> object)
     * @param string|mixed $sortby Sort by this field (id, urlname, timestamp or title)
     * @param string|mixed $sortdir Sorting directory (ASC or DESC)
     * @param int|null|mixed $count How many entries (NULL for unlimited)
     * @param int|null|mixed $offset How many entries should be skipped (NULL for none)
     * @param int|null|mixed $perpage How many entries per page (NULL for no paging)
     * @param int|null|mixed $page Page number (starting at 1, NULL for no paging)
     * @param int $maxpage Number of pages will be written here, if paging is activated.
     * @param Database|null $db
     * @return self[]
     */
    public static function by_multi($criterias, $sortby, $sortdir, $count, $offset, $perpage, $page, &$maxpage, ?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $subqueries = [];
        $subparams = [];
        foreach ($criterias as $k => $v) {
            switch ($k) {
                case "id":
                    $subqueries[] = "`a`.`id` = ?";
                    $subparams[] = (int)$v;
                    break;
                case "urlname":
                    $subqueries[] = "`a`.`urlname` = ?";
                    $subparams[] = (string)$v;
                    break;
                case "section":
                    if (!($v instanceof Section)) {
                        throw new InvalidArgumentException("criterias[section] must be a  " . Section::class);
                    }

                    $subqueries[] = "`a`.`section` = ?";
                    $subparams[] = $v->get_id();
                    break;
                case "status":
                    $subqueries[] = "`a`.`status` = ?";
                    $subparams[] = (int)$v;
                    break;
                case "onlyvisible":
                    $subqueries[] = "`a`.`status` != 0";
                    break;
                case "langavail":
                    $subqueries[] = "`b`.`language` = ?";
                    $subparams[] = (string)$v;
                    break;
                case "tag":
                    if (!($v instanceof Tag)) {
                        throw new InvalidArgumentException("criterias[tag] must be a  " . Tag::class);
                    }

                    $subqueries[] = "`c`.`tag` = ?";
                    $subparams[] = $v->get_id();
                    break;
            }
        }

        if (($sortdir != "ASC") && ($sortdir != "DESC")) {
            $sortdir = "ASC";
        }
        $sorting = "";
        switch ($sortby) {
            case "id":        $sorting = "ORDER BY `a`.`id` $sortdir";        break;
            case "urlname":   $sorting = "ORDER BY `a`.`urlname` $sortdir";   break;
            case "timestamp": $sorting = "ORDER BY `a`.`timestamp` $sortdir"; break;
            case "title":     $sorting = "ORDER BY `b`.`text` $sortdir";      break;
        }

        $stmt = $db->prepStmt("SELECT `a`.`id` AS `id`, `a`.`urlname` AS `urlname`, `a`.`title` AS `title`, `a`.`text` AS `text`, `a`.`excerpt` AS `excerpt`, `a`.`meta` AS `meta`, `a`.`custom` AS `custom`, `a`.`article_image` AS `article_image`, `a`.`status` AS `status`, `a`.`section` AS `section`, `a`.`timestamp` AS `timestamp`, `a`.`allow_comments` AS `allow_comments` FROM `PREFIX_articles` `a`
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

    /**
     * Get all articles
     *
     * @param Database|null $db
     * @return self[]
     */
    public static function all(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `id`, `urlname`, `title`, `text`, `excerpt`, `meta`, `custom`, `article_image`, `status`, `section`, `timestamp`, `allow_comments` FROM `PREFIX_articles` WHERE 1");
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = self::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Getting comments for this article.
     *
     * Parameters:
     * @param string $limit_lang Get only comments in a language (empty string for no limitation, this is the default).
     * @param bool $only_visible Do you only want the visible comments? (Default: False)
     * @param Database|null $db
     * @return Comment[]
     */
    public function get_comments($limit_lang = "", $only_visible = false, ?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

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

        $stmt = $db->prepStmt("SELECT `id`, `article`, `language`, `author_name`, `author_mail`, `text`, `timestamp`, `visible`, `read_by_admin` FROM `PREFIX_comments` WHERE " . implode(" AND ", $conditions));
        $stmt->execute($arguments);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Comment::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Get all Tags of this Article.
     *
     * @return Tag[]
     */
    public function get_tags(?Database $db = null): array
    {
        $db = $db ?? Env::getGlobal()->database();

        $rv = [];
        $stmt = $db->query("SELECT `a`.`id` AS `id`, `a`.`name` AS `name`, `a`.`title` AS `title` FROM `PREFIX_tags` `a` INNER JOIN `PREFIX_article_tag_relations` `b` ON `a`.`id` = `b`.`tag` WHERE `b`.`article` = ?", $this->id);
        while ($sqlrow = $stmt->fetch()) {
            $rv[] = Tag::by_sqlrow($sqlrow);
        }
        return $rv;
    }

    /**
     * Set the Tags that should be associated with this Article.
     *
     * @param Tag[] $tags
     * @param Database|null $db
     */
    public function set_tags($tags, ?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            foreach ($tags as $tag) {
                $tag->save($db);
            }

            $db->query("DELETE FROM `PREFIX_article_tag_relations` WHERE `article`= ?", $this->id);

            $articleid = $this->id;
            if (!empty($tags)) {
                $stmt = $db->prepStmt(
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

    /**
     * Get the section of this article.
     *
     * @param Database|null $db
     * @return Section
     */
    public function get_section(?Database $db = null): Section
    {
        if ($this->section_obj === null) {
            $this->section_obj = Section::by_id($this->section_id, $db);
        }
        return $this->section_obj;
    }

    /**
     * Set the section of this article.
     *
     * @param Section $section
     */
    public function set_section(Section $section): void
    {
        $this->section_id  = $section->get_id();
        $this->section_obj = $section;
    }

    /**
     * Get the extradata for this article and the given plugin.
     *
     * @param int|mixed $plugin_id The ID of the plugin.
     * @param Database|null $db
     * @return ArticleExtradata
     */
    public function get_extradata($plugin_id, ?Database $db = null): ArticleExtradata
    {
        return new ArticleExtradata($this->id, (int)$plugin_id, $db);
    }

    /**
     * @param Database|null $db
     * @throws AlreadyExistsError
     * @throws InvalidDataError
     */
    public function save(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        if (!self::test_urlname($this->urlname)) {
            throw new InvalidDataError("invalid_urlname");
        }

        if (!self::test_status($this->status)) {
            throw new InvalidDataError("invalid_article_status");
        }

        $tx = new DbTransaction($db);
        try {
            $stmt = $db->query("SELECT COUNT(*) AS `n` FROM `PREFIX_articles` WHERE `urlname` = ? AND `id` != ?", $this->urlname, $this->id);
            $sqlrow = $stmt->fetch();
            if ($sqlrow["n"] > 0) {
                throw new AlreadyExistsError();
            }

            $this->title->save($db);
            $this->text->save($db);
            $this->excerpt->save($db);

            $db->query(
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

    /**
     * @param Database|null $db
     */
    public function delete(?Database $db = null): void
    {
        $db = $db ?? Env::getGlobal()->database();

        $tx = new DbTransaction($db);
        try {
            $this->title->delete($db);
            $this->text->delete($db);
            $this->excerpt->delete($db);

            foreach ($this->get_comments() as $comment) {
                $comment->delete($db);
            }

            $db->query("DELETE FROM `PREFIX_article_tag_relations` WHERE `article` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_article_extradata` WHERE `article` = ?", $this->id);
            $db->query("DELETE FROM `PREFIX_articles` WHERE `id` = ?", $this->id);
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
