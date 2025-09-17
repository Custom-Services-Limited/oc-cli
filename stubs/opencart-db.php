<?php

namespace {
    /**
     * @phpstan-type DBRow array<string, mixed>
     */
    if (!class_exists('DB', false)) {
        class DB
        {
        /**
         * @param string $driver
         * @param string $hostname
         * @param string $username
         * @param string $password
         * @param string $database
         * @param string $port
         */
        public function __construct(string $driver, string $hostname, string $username, string $password, string $database, string $port = '')
        {
        }

        /**
         * @return \DB\QueryResult|false
         */
        public function query(string $sql)
        {
        }

        /**
         * @return string
         */
        public function escape(string $value)
        {
        }

        /**
         * @return int
         */
        public function countAffected(): int
        {
        }

        /**
         * @return int
         */
        public function getLastId(): int
        {
        }
        }
    }
}

namespace DB {
    if (!class_exists(QueryResult::class, false)) {
        class QueryResult
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
        }
    }
}
