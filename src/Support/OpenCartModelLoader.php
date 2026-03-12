<?php

namespace OpenCart\CLI\Support;

class OpenCartModelLoader
{
    /**
     * @var object
     */
    private $registry;

    /**
     * @var string
     */
    private $applicationDir;

    /**
     * @var string
     */
    private $systemDir;

    public function __construct($registry, string $applicationDir, string $systemDir)
    {
        $this->registry = $registry;
        $this->applicationDir = rtrim($applicationDir, '/\\') . '/';
        $this->systemDir = rtrim($systemDir, '/\\') . '/';
    }

    public function model(string $route): void
    {
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
        $registryKey = 'model_' . str_replace('/', '_', $route);

        if ($this->registry->has($registryKey)) {
            return;
        }

        $file = $this->applicationDir . 'model/' . $route . '.php';
        $class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', $route);

        if (!is_file($file)) {
            throw new \RuntimeException('Error: Could not load model ' . $route . '!');
        }

        require_once $file;

        if (!class_exists($class, false)) {
            throw new \RuntimeException('Error: Model class not found for route ' . $route . '!');
        }

        $proxy = new \Proxy();

        foreach (get_class_methods($class) as $method) {
            $proxy->{$method} = $this->callback($route . '/' . $method);
        }

        $this->registry->set($registryKey, $proxy);
    }

    /**
     * @return array<string, mixed>
     */
    public function language(string $route, string $key = ''): array
    {
        $language = $this->registry->get('language');

        return $language->load($route, $key);
    }

    public function helper(string $route): void
    {
        $file = $this->systemDir . 'helper/' . preg_replace('/[^a-zA-Z0-9_\/]/', '', $route) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException('Error: Could not load helper ' . $route . '!');
        }

        require_once $file;
    }

    public function library(string $route): void
    {
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
        $file = $this->systemDir . 'library/' . $route . '.php';
        $class = str_replace('/', '\\', $route);

        if (!is_file($file)) {
            throw new \RuntimeException('Error: Could not load library ' . $route . '!');
        }

        require_once $file;
        $this->registry->set(basename($route), new $class($this->registry));
    }

    private function callback(string $route): callable
    {
        return function (array $args) use ($route) {
            static $models = [];

            $class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', substr($route, 0, strrpos($route, '/')));
            $key = substr($route, 0, strrpos($route, '/'));

            if (!isset($models[$this->applicationDir][$key])) {
                $models[$this->applicationDir][$key] = new $class($this->registry);
            }

            $method = substr($route, strrpos($route, '/') + 1);
            $callable = [$models[$this->applicationDir][$key], $method];

            if (!is_callable($callable)) {
                throw new \RuntimeException('Error: Could not call model/' . $route . '!');
            }

            return call_user_func_array($callable, $args);
        };
    }
}
