<?php

namespace {
    if (!class_exists('Registry', false)) {
        class Registry
        {
            /**
             * @param string $key
             * @param mixed $value
             */
            public function set($key, $value): void
            {
            }

            /**
             * @param string $key
             * @return mixed
             */
            public function get($key)
            {
            }

            public function has(string $key): bool
            {
                return false;
            }
        }
    }

    if (!class_exists('Config', false)) {
        class Config
        {
            /**
             * @param string $key
             * @param mixed $value
             */
            public function set($key, $value): void
            {
            }

            /**
             * @param string $key
             * @return mixed
             */
            public function get($key)
            {
            }
        }
    }

    if (!class_exists('Proxy', false)) {
        class Proxy extends \stdClass
        {
        }
    }

    if (!class_exists('Event', false)) {
        class Event
        {
            /**
             * @param mixed $registry
             */
            public function __construct($registry)
            {
            }
        }
    }

    if (!class_exists('Cache', false)) {
        class Cache
        {
            /**
             * @param string $adaptor
             * @param int $expire
             */
            public function __construct($adaptor, $expire = 3600)
            {
            }

            public function delete(string $key): void
            {
            }
        }
    }

    if (!class_exists('Request', false)) {
        class Request
        {
            /**
             * @var array<string, mixed>
             */
            public $server = [];
        }
    }
}
