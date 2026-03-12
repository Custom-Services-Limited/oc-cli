<?php

namespace OpenCart\CLI\Tests\Helpers;

class FakeDbResult
{
    /**
     * @var array<string, mixed>
     */
    public $row = [];

    /**
     * @var list<array<string, mixed>>
     */
    public $rows = [];

    /**
     * @var int
     */
    public $num_rows = 0;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
        $this->row = $this->rows[0] ?? [];
        $this->num_rows = count($this->rows);
    }
}
