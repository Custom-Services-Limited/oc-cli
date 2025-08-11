<?php

/**
 * OC-CLI - OpenCart Command Line Interface
 *
 * @author    Custom Services Limited <info@opencartgreece.gr>
 * @copyright 2024 Custom Services Limited
 * @license   GPL-3.0-or-later
 * @link      https://support.opencartgreece.gr/
 * @link      https://github.com/Custom-Services-Limited/oc-cli
 */

namespace OpenCart\CLI\Tests\Helpers;

class TestHelper
{
    /**
     * Create a temporary OpenCart installation for testing
     *
     * @param array $config Database configuration
     * @param string $version OpenCart version to simulate
     * @return string Path to temporary directory
     */
    public static function createTempOpenCartInstallation(array $config = [], $version = '3.0.3.8')
    {
        $tempDir = sys_get_temp_dir() . '/oc-cli-test-' . uniqid();

        // Create directory structure
        $directories = [
            'system',
            'system/config',
            'admin',
            'catalog',
            'image',
            'upload'
        ];

        foreach ($directories as $dir) {
            mkdir($tempDir . '/' . $dir, 0755, true);
        }

        // Create OpenCart indicator files
        self::createStartupFile($tempDir, $version);
        self::createConfigFile($tempDir, $config);
        self::createAdminConfigFile($tempDir, $config);
        self::createIndexFile($tempDir);

        return $tempDir;
    }

    /**
     * Create system/startup.php file
     */
    private static function createStartupFile($tempDir, $version)
    {
        $content = "<?php\n";
        $content .= "define('VERSION', '$version');\n";
        $content .= "// OpenCart startup file\n";

        file_put_contents($tempDir . '/system/startup.php', $content);
    }

    /**
     * Create config.php file
     */
    private static function createConfigFile($tempDir, array $config)
    {
        $defaultConfig = [
            'db_hostname' => 'localhost',
            'db_username' => 'test_user',
            'db_password' => 'test_pass',
            'db_database' => 'test_db',
            'db_port' => '3306',
            'db_prefix' => 'oc_',
            'http_server' => 'http://localhost/',
            'https_server' => 'https://localhost/'
        ];

        $config = array_merge($defaultConfig, $config);

        $content = "<?php\n";
        $content .= "// Database\n";
        $content .= "define('DB_HOSTNAME', '{$config['db_hostname']}');\n";
        $content .= "define('DB_USERNAME', '{$config['db_username']}');\n";
        $content .= "define('DB_PASSWORD', '{$config['db_password']}');\n";
        $content .= "define('DB_DATABASE', '{$config['db_database']}');\n";
        $content .= "define('DB_PORT', '{$config['db_port']}');\n";
        $content .= "define('DB_PREFIX', '{$config['db_prefix']}');\n";
        $content .= "\n// HTTP\n";
        $content .= "define('HTTP_SERVER', '{$config['http_server']}');\n";
        $content .= "define('HTTPS_SERVER', '{$config['https_server']}');\n";
        $content .= "define('HTTP_CATALOG', '{$config['http_server']}');\n";
        $content .= "define('HTTPS_CATALOG', '{$config['https_server']}');\n";

        file_put_contents($tempDir . '/config.php', $content);
    }

    /**
     * Create admin/config.php file
     */
    private static function createAdminConfigFile($tempDir, array $config)
    {
        $defaultConfig = [
            'db_hostname' => 'localhost',
            'db_username' => 'test_user',
            'db_password' => 'test_pass',
            'db_database' => 'test_db',
            'db_port' => '3306',
            'db_prefix' => 'oc_',
            'http_server' => 'http://localhost/admin/',
            'https_server' => 'https://localhost/admin/'
        ];

        $config = array_merge($defaultConfig, $config);

        $content = "<?php\n";
        $content .= "// Database\n";
        $content .= "define('DB_HOSTNAME', '{$config['db_hostname']}');\n";
        $content .= "define('DB_USERNAME', '{$config['db_username']}');\n";
        $content .= "define('DB_PASSWORD', '{$config['db_password']}');\n";
        $content .= "define('DB_DATABASE', '{$config['db_database']}');\n";
        $content .= "define('DB_PORT', '{$config['db_port']}');\n";
        $content .= "define('DB_PREFIX', '{$config['db_prefix']}');\n";
        $content .= "\n// HTTP\n";
        $content .= "define('HTTP_SERVER', '{$config['http_server']}');\n";
        $content .= "define('HTTPS_SERVER', '{$config['https_server']}');\n";
        $content .= "define('HTTP_CATALOG', 'http://localhost/');\n";
        $content .= "define('HTTPS_CATALOG', 'https://localhost/');\n";

        file_put_contents($tempDir . '/admin/config.php', $content);
    }

    /**
     * Create index.php file
     */
    private static function createIndexFile($tempDir)
    {
        $content = "<?php\n";
        $content .= "// OpenCart index file\n";
        $content .= "require_once('system/startup.php');\n";

        file_put_contents($tempDir . '/index.php', $content);
    }

    /**
     * Create sample database data
     */
    public static function createSampleDatabaseData()
    {
        return [
            'products' => [
                [
                    'product_id' => 1,
                    'name' => 'Test Product 1',
                    'model' => 'TEST001',
                    'price' => '29.99',
                    'status' => 1
                ],
                [
                    'product_id' => 2,
                    'name' => 'Test Product 2',
                    'model' => 'TEST002',
                    'price' => '39.99',
                    'status' => 1
                ]
            ],
            'categories' => [
                [
                    'category_id' => 1,
                    'name' => 'Test Category 1',
                    'status' => 1,
                    'parent_id' => 0
                ]
            ],
            'orders' => [
                [
                    'order_id' => 1,
                    'customer_id' => 1,
                    'total' => '29.99',
                    'order_status_id' => 1,
                    'date_added' => '2024-01-01 00:00:00'
                ]
            ]
        ];
    }

    /**
     * Clean up temporary directory
     *
     * @param string $dir Directory path
     */
    public static function cleanupTempDirectory($dir)
    {
        if (is_dir($dir)) {
            self::rrmdir($dir);
        }
    }

    /**
     * Recursively remove directory
     *
     * @param string $dir Directory path
     */
    private static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        self::rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Create a mock database connection result
     *
     * @param array $data Sample data to return
     * @return MockResult
     */
    public static function createMockDatabaseResult(array $data)
    {
        return new MockResult($data);
    }
}

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

    public function fetch_all($type = MYSQLI_BOTH)
    {
        return $this->data;
    }

    public function fetch_assoc()
    {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return null;
    }

    public function num_rows()
    {
        return count($this->data);
    }
}
