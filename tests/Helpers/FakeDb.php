<?php

namespace OpenCart\CLI\Tests\Helpers;

class FakeDb
{
    /**
     * @var list<string>
     */
    public $queries = [];

    /**
     * @var callable
     */
    private $handler;

    /**
     * @var int
     */
    private $affectedRows = 0;

    /**
     * @var int
     */
    private $lastInsertId = 0;

    /**
     * @param callable $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;

        $response = ($this->handler)($sql, $this);

        if ($response instanceof FakeDbResult) {
            $this->affectedRows = $response->num_rows;
            return $response;
        }

        if (is_array($response)) {
            $result = new FakeDbResult($response);
            $this->affectedRows = $result->num_rows;
            return $result;
        }

        if (is_int($response)) {
            $this->affectedRows = $response;
            return new FakeDbResult([]);
        }

        $this->affectedRows = 0;

        return new FakeDbResult([]);
    }

    public function escape($value)
    {
        return addslashes((string) $value);
    }

    public function countAffected()
    {
        return $this->affectedRows;
    }

    public function getLastId()
    {
        return $this->lastInsertId;
    }

    public function setLastId($lastInsertId)
    {
        $this->lastInsertId = (int) $lastInsertId;
    }
}
