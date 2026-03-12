<?php

namespace OpenCart\CLI\Support;

class OpenCartRuntime
{
    public const SCOPE_ADMIN = 'admin';
    public const SCOPE_CATALOG = 'catalog';

    /**
     * @var string
     */
    private $openCartRoot;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var array<string, mixed>
     */
    private $config;

    /**
     * @var object|null
     */
    private $registry;

    /**
     * @var object|null
     */
    private $database;

    /**
     * @var array<string, mixed>|null
     */
    private $definitionCache;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(string $openCartRoot, string $scope, array $config)
    {
        $this->openCartRoot = rtrim($openCartRoot, '/\\');
        $this->scope = $scope;
        $this->config = $config;
    }

    /**
     * @return object
     */
    public function registry()
    {
        if ($this->registry !== null) {
            return $this->registry;
        }

        $this->loadCoreClasses();
        $this->defineConstants();

        $registry = new \Registry();
        $config = new \Config();

        foreach ($this->loadDefaultConfig() as $key => $value) {
            $config->set($key, $value);
        }

        foreach ($this->loadScopeConfig() as $key => $value) {
            $config->set($key, $value);
        }

        $config->set('db_engine', $this->config['db_driver']);
        $config->set('db_hostname', $this->config['db_hostname']);
        $config->set('db_username', $this->config['db_username']);
        $config->set('db_password', $this->config['db_password']);
        $config->set('db_database', $this->config['db_database']);
        $config->set('db_port', (int) $this->config['db_port']);
        $config->set('cache_engine', 'file');
        $config->set('cache_expire', 3600);
        $registry->set('config', $config);

        /** @phpstan-ignore-next-line OpenCart defines its own global Event runtime class. */
        $registry->set('event', new \Event($registry));
        $registry->set('db', $this->database());
        $registry->set('cache', new \Cache($config->get('cache_engine'), (int) $config->get('cache_expire')));
        $registry->set('request', $this->createRequest());
        $registry->set('response', new \stdClass());
        $registry->set('session', (object) ['data' => []]);

        $loader = new OpenCartModelLoader($registry, $this->getApplicationDir(), $this->getSystemDir());
        $registry->set('load', $loader);

        $this->loadSettings($registry);
        $this->configureLanguage($registry, $loader);

        $this->registry = $registry;

        return $this->registry;
    }

    /**
     * @return object
     */
    public function database()
    {
        if ($this->database !== null) {
            return $this->database;
        }

        $this->loadCoreClasses();
        $this->defineConstants();

        require_once $this->getSystemDir() . 'library/db.php';
        $driver = $this->config['db_driver'] ?: 'mysqli';
        $driverFile = $this->getSystemDir() . 'library/db/' . $driver . '.php';

        if (!is_file($driverFile)) {
            throw new \RuntimeException('OpenCart database driver not found: ' . $driverFile);
        }

        require_once $driverFile;

        $this->database = new \DB(
            $driver,
            (string) $this->config['db_hostname'],
            (string) $this->config['db_username'],
            (string) $this->config['db_password'],
            (string) $this->config['db_database'],
            (int) $this->config['db_port']
        );

        return $this->database;
    }

    /**
     * @return mixed
     */
    public function model(string $route)
    {
        $registry = $this->registry();
        $registry->get('load')->model($route);

        return $registry->get('model_' . str_replace('/', '_', $route));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionConstants(): array
    {
        if ($this->definitionCache !== null) {
            return $this->definitionCache;
        }

        $catalog = $this->parseConfigFile($this->openCartRoot . '/config.php');
        $admin = $this->parseConfigFile($this->openCartRoot . '/admin/config.php');

        $this->definitionCache = [
            'catalog' => $catalog,
            'admin' => $admin,
        ];

        return $this->definitionCache;
    }

    public function getSystemDir(): string
    {
        $definitions = $this->getDefinitionConstants();
        $source = $definitions[$this->scope];

        if (!empty($source['DIR_SYSTEM'])) {
            return rtrim((string) $source['DIR_SYSTEM'], '/\\') . '/';
        }

        return $this->openCartRoot . '/system/';
    }

    public function getOpenCartRoot(): string
    {
        return $this->openCartRoot;
    }

    public function getDatabasePrefix(): string
    {
        return (string) $this->config['db_prefix'];
    }

    public function getApplicationDir(): string
    {
        $definitions = $this->getDefinitionConstants();
        $source = $definitions[$this->scope];

        if (!empty($source['DIR_APPLICATION'])) {
            return rtrim((string) $source['DIR_APPLICATION'], '/\\') . '/';
        }

        return $this->openCartRoot . '/' . $this->scope . '/';
    }

    public function getLanguageDir(): string
    {
        $definitions = $this->getDefinitionConstants();
        $source = $definitions[$this->scope];

        if (!empty($source['DIR_LANGUAGE'])) {
            return rtrim((string) $source['DIR_LANGUAGE'], '/\\') . '/';
        }

        return $this->getApplicationDir() . 'language/';
    }

    public function getCacheDir(): string
    {
        $definitions = $this->getDefinitionConstants();
        $catalog = $definitions['catalog'];

        if (!empty($catalog['DIR_CACHE'])) {
            return rtrim((string) $catalog['DIR_CACHE'], '/\\') . '/';
        }

        return $this->openCartRoot . '/system/storage/cache/';
    }

    public function getModificationDir(): string
    {
        $definitions = $this->getDefinitionConstants();
        $catalog = $definitions['catalog'];

        if (!empty($catalog['DIR_MODIFICATION'])) {
            return rtrim((string) $catalog['DIR_MODIFICATION'], '/\\') . '/';
        }

        return $this->openCartRoot . '/system/storage/modification/';
    }

    public function getLogsDir(): string
    {
        $definitions = $this->getDefinitionConstants();
        $catalog = $definitions['catalog'];

        if (!empty($catalog['DIR_LOGS'])) {
            return rtrim((string) $catalog['DIR_LOGS'], '/\\') . '/';
        }

        return $this->openCartRoot . '/system/storage/logs/';
    }

    /**
     * @return array<string, string>
     */
    private function parseConfigFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OpenCart config file not found: ' . $path);
        }

        $content = (string) file_get_contents($path);
        preg_match_all(
            '/define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*,\s*(?:[\'"]([^\'"]*)[\'"]|([0-9]+)|\s*(true|false))\s*\)/i',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        $constants = [];
        foreach ($matches as $match) {
            if ($match[2] !== '') {
                $constants[$match[1]] = $match[2];
            } elseif ($match[3] !== '') {
                $constants[$match[1]] = $match[3];
            } else {
                $constants[$match[1]] = strtolower($match[4]) === 'true' ? '1' : '';
            }
        }

        return $constants;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDefaultConfig(): array
    {
        $_ = [];
        require $this->getSystemDir() . 'config/default.php';

        return $_;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadScopeConfig(): array
    {
        $definitions = $this->getDefinitionConstants();
        $scopeDefinitions = $definitions[$this->scope];

        foreach ($scopeDefinitions as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }

        $_ = [];
        require $this->getSystemDir() . 'config/' . $this->scope . '.php';

        return $_;
    }

    private function loadCoreClasses(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $systemDir = $this->getSystemDir();

        require_once $systemDir . 'engine/model.php';
        require_once $systemDir . 'engine/proxy.php';
        require_once $systemDir . 'engine/registry.php';
        require_once $systemDir . 'engine/event.php';
        require_once $systemDir . 'engine/action.php';
        require_once $systemDir . 'library/config.php';
        require_once $systemDir . 'library/cache.php';
        require_once $systemDir . 'library/cache/file.php';
        require_once $systemDir . 'library/request.php';
        require_once $systemDir . 'helper/general.php';
        require_once $systemDir . 'helper/utf8.php';

        $loaded = true;
    }

    private function defineConstants(): void
    {
        if (!defined('DB_PREFIX')) {
            define('DB_PREFIX', $this->config['db_prefix']);
        }

        if (!defined('DIR_SYSTEM')) {
            define('DIR_SYSTEM', $this->getSystemDir());
        }

        if (!defined('DIR_CACHE')) {
            define('DIR_CACHE', $this->getCacheDir());
        }
    }

    /**
     * @return object
     */
    private function createRequest()
    {
        $request = new \Request();
        $request->server['REMOTE_ADDR'] = $request->server['REMOTE_ADDR'] ?? '127.0.0.1';
        $request->server['HTTPS'] = $request->server['HTTPS'] ?? false;
        $request->server['HTTP_HOST'] = $request->server['HTTP_HOST'] ?? 'localhost';
        $request->server['PHP_SELF'] = $request->server['PHP_SELF'] ?? '/index.php';
        $request->server['REQUEST_URI'] = $request->server['REQUEST_URI'] ?? '/';
        $request->server['HTTP_ACCEPT_LANGUAGE'] = $request->server['HTTP_ACCEPT_LANGUAGE'] ?? 'en-GB,en;q=0.9';

        return $request;
    }

    private function loadSettings($registry): void
    {
        $db = $registry->get('db');
        $config = $registry->get('config');
        $prefix = $this->getDatabasePrefix();
        $storeId = 0;

        $sql = "SELECT * FROM `" . $prefix . "setting` WHERE store_id = '0'";
        if ($this->scope === self::SCOPE_CATALOG) {
            $sql = "SELECT * FROM `" . $prefix . "setting` WHERE store_id = '0' "
                . "OR store_id = '" . (int) $storeId . "' ORDER BY store_id ASC";
        }

        $query = $db->query($sql);
        foreach ($query->rows as $setting) {
            if (!$setting['serialized']) {
                $config->set($setting['key'], $setting['value']);
            } else {
                $config->set($setting['key'], json_decode($setting['value'], true));
            }
        }

        if ($this->scope === self::SCOPE_CATALOG) {
            $config->set('config_store_id', $storeId);
            if (!$config->get('config_url')) {
                $definitions = $this->getDefinitionConstants();
                $config->set('config_url', $definitions['catalog']['HTTP_SERVER'] ?? '');
                $config->set('config_ssl', $definitions['catalog']['HTTPS_SERVER'] ?? '');
            }
        }

        if ($config->get('config_timezone')) {
            date_default_timezone_set($config->get('config_timezone'));
            $db->query("SET time_zone = '" . $db->escape(date('P')) . "'");
        }
    }

    private function configureLanguage($registry, OpenCartModelLoader $loader): void
    {
        $config = $registry->get('config');
        $languageCode = $this->scope === self::SCOPE_ADMIN
            ? (string) ($config->get('config_admin_language') ?: 'en-gb')
            : (string) ($config->get('config_language') ?: 'en-gb');

        $language = new OpenCartLanguage($this->getLanguageDir(), $languageCode);
        $language->load($languageCode);
        $registry->set('language', $language);

        $languageRoute = $this->scope === self::SCOPE_ADMIN
            ? 'localisation/language'
            : 'localisation/language';

        $loader->model($languageRoute);
        $languageModel = $registry->get('model_' . str_replace('/', '_', $languageRoute));

        if ($this->scope === self::SCOPE_ADMIN) {
            $query = $registry->get('db')->query(
                "SELECT * FROM `" . $this->getDatabasePrefix() . "language` WHERE code = '" .
                $registry->get('db')->escape($languageCode) . "'"
            );

            if ($query->num_rows) {
                $config->set('config_language_id', (int) $query->row['language_id']);
            }
        } else {
            $languages = $languageModel->getLanguages();
            if (isset($languages[$languageCode])) {
                $config->set('config_language_id', (int) $languages[$languageCode]['language_id']);
            }
        }
    }
}
