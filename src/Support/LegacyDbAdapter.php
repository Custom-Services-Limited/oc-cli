<?php

namespace OpenCart\CLI\Support;

use Exception;
use mysqli;
use mysqli_result;
use mysqli_stmt;

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
     * @param array $params
     * @return object
     * @throws Exception
     */
    public function query($sql, array $params = [])
    {
        if (!empty($params)) {
            return $this->queryUsingPreparedStatement($sql, $params);
        }

        $result = $this->connection->query($sql);

        if ($result instanceof mysqli_result) {
            return $this->createResultFromResultSet($result);
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
     * Execute a prepared statement with parameter binding
     *
     * @param string $sql
     * @param array $params
     * @return object
     * @throws Exception
     */
    private function queryUsingPreparedStatement($sql, array $params)
    {
        $statement = $this->connection->prepare($sql);

        if (!$statement instanceof mysqli_stmt) {
            throw new Exception($this->connection->error ?: 'Failed to prepare statement');
        }

        if (!empty($params)) {
            [$types, $values] = $this->normaliseParameters($params);
            $this->bindStatementParameters($statement, $types, $values);
        }

        if (!$statement->execute()) {
            $error = $statement->error ?: $this->connection->error;
            $statement->close();
            throw new Exception($error ?: 'Unknown database error');
        }

        $this->affectedRows = $statement->affected_rows;

        $result = $statement->get_result();

        if ($result instanceof mysqli_result) {
            $finalResult = $this->createResultFromResultSet($result, $statement->affected_rows);
            $statement->close();
            return $finalResult;
        }

        $rows = $this->fetchRowsWithoutMysqlnd($statement);
        $statement->close();

        return (object) [
            'row' => $rows ? $rows[0] : [],
            'rows' => $rows,
            'num_rows' => count($rows),
        ];
    }

    /**
     * Create an OpenCart-style result object from a mysqli_result
     *
     * @param mysqli_result $result
     * @return object
     */
    private function createResultFromResultSet(mysqli_result $result, $affectedRows = null)
    {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();

        if ($affectedRows !== null) {
            $this->affectedRows = $affectedRows;
        } else {
            $this->affectedRows = $this->connection->affected_rows;
        }

        return (object) [
            'row' => $data ? $data[0] : [],
            'rows' => $data,
            'num_rows' => count($data),
        ];
    }

    /**
     * Prepare parameter types and values for binding
     *
     * @param array $params
     * @return array{0: string, 1: array}
     */
    private function normaliseParameters(array $params)
    {
        $types = '';
        $values = [];

        foreach ($params as $param) {
            if ($param === null) {
                $types .= 's';
                $values[] = null;
            } elseif (is_int($param) || is_bool($param)) {
                $types .= 'i';
                $values[] = (int)$param;
            } elseif (is_float($param)) {
                $types .= 'd';
                $values[] = $param;
            } else {
                $types .= 's';
                $values[] = (string)$param;
            }
        }

        return [$types, $values];
    }

    /**
     * Bind normalised parameters to the prepared statement
     *
     * @param mysqli_stmt $statement
     * @param string $types
     * @param array $values
     * @return void
     * @throws Exception
     */
    private function bindStatementParameters(mysqli_stmt $statement, $types, array $values)
    {
        $parameters = [];
        $parameters[] = $types;

        foreach ($values as $key => &$value) {
            $parameters[] = &$value;
        }

        if (!call_user_func_array([$statement, 'bind_param'], $parameters)) {
            throw new Exception($statement->error ?: 'Failed to bind parameters');
        }
    }

    /**
     * Fetch rows for environments where mysqlnd is not available
     *
     * @param mysqli_stmt $statement
     * @return array
     */
    private function fetchRowsWithoutMysqlnd(mysqli_stmt $statement)
    {
        $metadata = $statement->result_metadata();

        if (!$metadata) {
            return [];
        }

        $fields = $metadata->fetch_fields();
        $metadata->free();

        if (!$fields) {
            return [];
        }

        $row = [];
        $bindArguments = [];

        foreach ($fields as $field) {
            $row[$field->name] = null;
            $bindArguments[] = &$row[$field->name];
        }

        if (!call_user_func_array([$statement, 'bind_result'], $bindArguments)) {
            return [];
        }

        $rows = [];
        while ($statement->fetch()) {
            $current = [];
            foreach ($row as $column => $value) {
                $current[$column] = $value;
            }
            $rows[] = $current;
        }

        $statement->free_result();

        return $rows;
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
