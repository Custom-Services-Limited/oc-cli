<?php

namespace OpenCart\CLI\Tests\Helpers;

/**
 * Mock database result class for testing
 */
class MockResult
{
    private $data;
    private $position = 0;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function fetchAll($type = MYSQLI_BOTH)
    {
        return $this->data;
    }

    public function fetchAssoc()
    {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return null;
    }

    public function numRows()
    {
        return count($this->data);
    }
}
