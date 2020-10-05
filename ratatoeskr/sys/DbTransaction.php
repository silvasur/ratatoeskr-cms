<?php


namespace r7r\cms\sys;

class DbTransaction
{
    /** @var Database */
    private $db;

    /** @var bool */
    private $startedHere;

    /**
     * Start a new transaction.
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;

        $this->startedHere = !$this->db->getPdo()->inTransaction();
        if ($this->startedHere) {
            $this->db->getPdo()->beginTransaction();
        }
    }

    /**
     * Commit the transaction.
     */
    public function commit(): void
    {
        if ($this->startedHere) {
            $this->db->getPdo()->commit();
        }
    }

    /**
     * Roll the transaction back.
     */
    public function rollback(): void
    {
        if ($this->startedHere) {
            $this->db->getPdo()->rollBack();
        }
    }
}
