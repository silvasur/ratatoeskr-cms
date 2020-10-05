<?php

namespace r7r\cms\sys\models;

use DoesNotExistError;
use ArrayAccess;
use Iterator;
use Countable;
use r7r\cms\sys\Database;

/**
 * An abstract class for a KVStorage
 */
abstract class KVStorage implements Countable, ArrayAccess, Iterator
{
    private $keybuffer;
    private $counter;
    private $silent_mode;

    private $common_vals;

    private $stmt_get;
    private $stmt_unset;
    private $stmt_update;
    private $stmt_create;

    final protected function init(string $sqltable, array $common, Database $db)
    {
        $this->silent_mode = false;
        $this->keybuffer = [];

        $selector = "WHERE ";
        $fields = "";
        foreach ($common as $field => $val) {
            $selector .= "`$field` = ? AND ";
            $fields .= ", `$field`";
            $this->common_vals[] = $val;
        }

        $this->stmt_get = $db->prepStmt("SELECT `value` FROM `$sqltable` $selector `key` = ?");
        $this->stmt_unset = $db->prepStmt("DELETE FROM `$sqltable` $selector `key` = ?");
        $this->stmt_update = $db->prepStmt("UPDATE `$sqltable` SET `value` = ? $selector `key` = ?");
        $this->stmt_create = $db->prepStmt("INSERT INTO `$sqltable` (`key`, `value` $fields) VALUES (?,?" . str_repeat(",?", count($common)) . ")");

        $get_keys = $db->prepStmt("SELECT `key` FROM `$sqltable` $selector 1");
        $get_keys->execute($this->common_vals);
        while ($sqlrow = $get_keys->fetch()) {
            $this->keybuffer[] = $sqlrow["key"];
        }

        $this->counter = 0;
    }

    /**
     * Enable silent mode.
     *
     * If the silent mode is enabled, the KVStorage behaves even more like a PHP array, i.e. it just returns NULL,
     * if an unknown key was requested and does not throw an DoesNotExistError Exception.
     *
     * See also {@see KVStorage::disable_silent_mode()}
     */
    final public function enable_silent_mode()
    {
        $this->silent_mode = true;
    }

    /**
     * Disable silent mode.
     *
     * See also {@see KVStorage::enable_silent_mode()}
     */
    final public function disable_silent_mode()
    {
        $this->silent_mode = false;
    }

    /* Countable interface implementation */
    final public function count()
    {
        return count($this->keybuffer);
    }

    /* ArrayAccess interface implementation */
    final public function offsetExists($offset)
    {
        return in_array($offset, $this->keybuffer);
    }

    final public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            $this->stmt_get->execute(array_merge($this->common_vals, [$offset]));
            $sqlrow = $this->stmt_get->fetch();
            $this->stmt_get->closeCursor();
            return unserialize(base64_decode($sqlrow["value"]));
        } elseif ($this->silent_mode) {
            return null;
        } else {
            throw new DoesNotExistError();
        }
    }

    final public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->keybuffer[array_search($offset, $this->keybuffer)]);
            $this->keybuffer = array_merge($this->keybuffer);
            $this->stmt_unset->execute(array_merge($this->common_vals, [$offset]));
            $this->stmt_unset->closeCursor();
        }
    }

    final public function offsetSet($offset, $value)
    {
        if ($this->offsetExists($offset)) {
            $this->stmt_update->execute(array_merge([base64_encode(serialize($value))], $this->common_vals, [$offset]));
            $this->stmt_update->closeCursor();
        } else {
            $this->stmt_create->execute(array_merge([$offset, base64_encode(serialize($value))], $this->common_vals));
            $this->stmt_create->closeCursor();
            $this->keybuffer[] = $offset;
        }
    }

    /* Iterator interface implementation */
    final public function rewind()
    {
        return $this->counter = 0;
    }

    final public function current()
    {
        return $this->offsetGet($this->keybuffer[$this->counter]);
    }

    final public function key()
    {
        return $this->keybuffer[$this->counter];
    }

    final public function next()
    {
        ++$this->counter;
    }

    final public function valid()
    {
        return isset($this->keybuffer[$this->counter]);
    }
}
