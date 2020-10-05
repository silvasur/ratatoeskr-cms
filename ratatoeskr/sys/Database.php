<?php


namespace r7r\cms\sys;

use PDO;
use PDOStatement;

class Database
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $prefix;

    /**
     * @param PDO $pdo
     * @param string $prefix
     */
    public function __construct(PDO $pdo, string $prefix)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    /**
     * Create a Database object from a config.
     *
     * @param array $config
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        $pdo = new PDO(
            "mysql:host=" . $config["mysql"]["server"] . ";dbname=" . $config["mysql"]["db"] . ";charset=utf8",
            $config["mysql"]["user"],
            $config["mysql"]["passwd"],
            [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ]
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new self($pdo, $config["mysql"]["prefix"]);
    }

    /**
     * Gets the wrapped PDO object.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get the table prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Substitutes "PREFIX_" in the input string with the prefix from the config.
     *
     * @param string $query
     * @return string
     */
    public function subPrefix(string $query): string // \mystuff\TODO: or can we make this private?
    {
        return str_replace("PREFIX_", $this->prefix, $query);
    }

    /**
     * Prepares a SQL statement for usage with the database.
     * This will also replace "PREFIX_" with the prefix defined in 'config.php'.
     *
     * @param string $query The query / statement to prepare.
     * @return PDOStatement
     */
    public function prepStmt(string $query): PDOStatement // \mystuff\TODO: or can we make this private?
    {
        return $this->pdo->prepare($this->subPrefix($query));
    }

    /**
     * Prepares a query with {@see Database::prepStmt()} and executes it with the remaining arguments.
     *
     * @param string $query
     * @param mixed ...$args
     * @return PDOStatement
     */
    public function query(string $query, ...$args): PDOStatement
    {
        $stmt = $this->prepStmt($query);
        $stmt->execute($args);

        return $stmt;
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }
}
