<?php

namespace OpenCart\CLI\Support;

use Exception;
use mysqli;
use mysqli_result;

/**
 * Lightweight adapter that mimics OpenCart's DB API using mysqli
 * when the full framework is not available.
 */
class LegacyDbAdapter
{
    /**
     * @var mysqli
     */
    private $connection;

    /**
     * @var int
     */
    private $affectedRows = 0;

    /**
     * @param array $config
     *
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $connection = mysqli_init();
        if (!$connection) {
            throw new Exception('Unable to initialise mysqli.');
        }

        ini_set('default_socket_timeout', '2');

        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $connection->options(MYSQLI_OPT_READ_TIMEOUT, 2);
        }

        $hostname = $config['db_hostname'] === 'localhost' ? '127.0.0.1' : $config['db_hostname'];

        if (
            !$connection->real_connect(
                $hostname,
                $config['db_username'],
                $config['db_password'],
                $config['db_database'],
                (int)$config['db_port']
            )
        ) {
            throw new Exception($connection->connect_error ?: 'Connection failed');
        }

        $this->connection = $connection;
    }

    /**
     * Execute a query and return an OpenCart-style result object.
     *
     * @param string $sql
     * @return object
     * @throws Exception
     */
    public function query($sql)
    {
        $result = $this->connection->query($sql);

        if ($result instanceof mysqli_result) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $result->free();

            $this->affectedRows = $this->connection->affected_rows;

            return (object) [
                'row' => $data ? $data[0] : [],
                'rows' => $data,
                'num_rows' => count($data),
            ];
        }

        if ($result === true) {
            $this->affectedRows = $this->connection->affected_rows;

            return (object) [
                'row' => [],
                'rows' => [],
                'num_rows' => 0,
            ];
        }

        throw new Exception($this->connection->error ?: 'Unknown database error');
    }

    /**
     * Escape a string value.
     *
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return $this->connection->real_escape_string($value);
    }

    /**
     * Get number of affected rows.
     *
     * @return int
     */
    public function countAffected()
    {
        return $this->affectedRows;
    }

    /**
     * Get last insert ID.
     *
     * @return int
     */
    public function getLastId()
    {
        return $this->connection->insert_id;
    }

    public function __destruct()
    {
        if ($this->connection instanceof mysqli) {
            $this->connection->close();
        }
    }
}
