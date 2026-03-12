<?php

namespace OpenCart\CLI\Tests\E2E;

use mysqli;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class OpenCart3050EndToEndTest extends TestCase
{
    /**
     * @var string
     */
    private static $repoRoot;

    /**
     * @var string
     */
    private static $openCartRoot;

    /**
     * @var string
     */
    private static $artifactsDir;

    /**
     * @var array<string, string|int>
     */
    private static $dbConfig;

    public static function setUpBeforeClass(): void
    {
        if (!getenv('OC_E2E_ROOT') || !getenv('OC_E2E_DB_PREFIX')) {
            self::markTestSkipped('E2E environment not provisioned. Run composer test:e2e.');
        }

        self::$repoRoot = dirname(__DIR__, 2);
        self::$openCartRoot = (string) getenv('OC_E2E_ROOT');
        self::$artifactsDir = (string) getenv('OC_E2E_ARTIFACTS_DIR');
        self::$dbConfig = [
            'host' => (string) getenv('OC_E2E_DB_HOST'),
            'name' => (string) getenv('OC_E2E_DB_NAME'),
            'user' => (string) getenv('OC_E2E_DB_USER'),
            'pass' => (string) getenv('OC_E2E_DB_PASS'),
            'port' => (int) getenv('OC_E2E_DB_PORT'),
            'prefix' => (string) getenv('OC_E2E_DB_PREFIX'),
        ];
    }

    public function testOcCliAgainstProvisionedOpenCart3050(): void
    {
        $versionOutput = trim($this->runOc([
            'core:version',
            '--opencart',
            '--opencart-root=' . self::$openCartRoot,
        ]));
        $this->assertSame('3.0.5.0', $versionOutput);

        $versionJson = $this->runOcJson([
            'core:version',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame('3.0.5.0', $versionJson['opencart']);

        $requirements = $this->runOcJson([
            'core:check-requirements',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($requirements['permissions']);
        $this->assertTrue($this->findRequirementStatus($requirements['database'], 'Database Connection'));

        $dbInfo = $this->runOcJson([
            'db:info',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame('Connected', $dbInfo['connection_status']);
        $this->assertSame(self::$dbConfig['name'], $dbInfo['database']);
        $this->assertSame(self::$dbConfig['prefix'], $dbInfo['prefix']);

        $configList = $this->runOcJson([
            'core:config',
            'list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertArrayHasKey('config_name', $configList);
        $this->assertArrayHasKey('config_email', $configList);

        $adminOutput = $this->runOc([
            'core:config',
            'list',
            '--admin',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('deprecated', strtolower($adminOutput));

        $baselineStoreName = trim($this->runOc([
            'core:config',
            'get',
            'config_name',
            '--opencart-root=' . self::$openCartRoot,
        ]));
        $this->assertNotSame('', $baselineStoreName);

        $productList = $this->runOcJson([
            'product:list',
            '--format=json',
            '--limit=10',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertNotEmpty($productList);

        $iphoneResults = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=iPhone',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertContains('iPhone', array_column($iphoneResults, 'name'));

        $macbookResults = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=MacBook',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertContains('MacBook', array_column($macbookResults, 'name'));

        $extensions = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasExtension($extensions, 'payment', 'cod'));
        $this->assertTrue($this->hasExtension($extensions, 'shipping', 'flat'));
        $this->assertTrue($this->hasExtension($extensions, 'module', 'featured'));

        $backupPath = self::$artifactsDir . '/baseline.sql';
        $backupOutput = $this->runOc([
            'db:backup',
            basename($backupPath),
            '--output-dir=' . self::$artifactsDir,
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertFileExists($backupPath);
        $this->assertStringContainsString('Backup created successfully', $backupOutput);

        $newStoreName = 'OC-CLI E2E Store';
        $setConfigOutput = $this->runOc([
            'core:config',
            'set',
            'config_name',
            $newStoreName,
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('Configuration', $setConfigOutput);
        $this->assertSame(
            $newStoreName,
            trim($this->runOc([
                'core:config',
                'get',
                'config_name',
                '--opencart-root=' . self::$openCartRoot,
            ]))
        );

        $disableOutput = $this->runOc([
            'extension:disable',
            'shipping:flat',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('disabled successfully', strtolower($disableOutput));
        $extensionsAfterDisable = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertFalse($this->hasExtension($extensionsAfterDisable, 'shipping', 'flat'));

        $enableOutput = $this->runOc([
            'extension:enable',
            'shipping:flat',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('enabled successfully', strtolower($enableOutput));
        $extensionsAfterEnable = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasExtension($extensionsAfterEnable, 'shipping', 'flat'));

        $productModel = 'E2E-RESTORE-001';
        $createProductJson = $this->runOcJson([
            'product:create',
            'OC-CLI Restore Product',
            $productModel,
            '19.99',
            '--quantity=5',
            '--status=enabled',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertSame('OC-CLI Restore Product', $createProductJson['name']);
        $this->assertSame($productModel, $createProductJson['model']);

        $productRow = $this->fetchSingleRow(
            "SELECT product_id, sku FROM `" . self::$dbConfig['prefix'] . "product` WHERE model = ? LIMIT 1",
            [$productModel]
        );
        $this->assertNotNull($productRow);
        $this->assertSame($productModel, $productRow['sku']);

        $searchResults = $this->runOcJson([
            'product:list',
            '--format=json',
            '--search=' . $productModel,
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertCount(1, $searchResults);
        $this->assertSame('OC-CLI Restore Product', $searchResults[0]['name']);

        $modificationFile = self::$artifactsDir . '/demo.ocmod.xml';
        file_put_contents(
            $modificationFile,
            <<<XML
<modification>
  <name>OC-CLI E2E Modification</name>
  <code>oc_cli_e2e_mod</code>
  <version>1.0.0</version>
  <author>OC-CLI</author>
</modification>
XML
        );

        $installOutput = $this->runOc([
            'extension:install',
            $modificationFile,
            '--activate',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('Imported modification', $installOutput);

        $modifications = $this->runOcJson([
            'modification:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasModification($modifications, 'oc_cli_e2e_mod', 'enabled'));

        $restoreOutput = $this->runOc([
            'db:restore',
            $backupPath,
            '--force',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('Database restored successfully', $restoreOutput);

        $restoredStoreName = trim($this->runOc([
            'core:config',
            'get',
            'config_name',
            '--opencart-root=' . self::$openCartRoot,
        ]));
        $this->assertSame($baselineStoreName, $restoredStoreName);

        $postRestoreProductList = $this->runOc(
            [
                'product:list',
                '--search=' . $productModel,
                '--opencart-root=' . self::$openCartRoot,
            ],
            0
        );
        $this->assertStringContainsString('No products found', $postRestoreProductList);
        $this->assertNull(
            $this->fetchSingleRow(
                "SELECT product_id FROM `" . self::$dbConfig['prefix'] . "product` WHERE model = ? LIMIT 1",
                [$productModel]
            )
        );

        $postRestoreModifications = $this->runOc([
            'modification:list',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertStringContainsString('No modifications found', $postRestoreModifications);

        $postRestoreExtensions = $this->runOcJson([
            'extension:list',
            '--format=json',
            '--opencart-root=' . self::$openCartRoot,
        ]);
        $this->assertTrue($this->hasExtension($postRestoreExtensions, 'shipping', 'flat'));
    }

    private function runOc(array $arguments, int $expectedExitCode = 0): string
    {
        $process = new Process(
            array_merge([PHP_BINARY, 'bin/oc'], $arguments),
            self::$repoRoot,
            ['APP_ENV' => '']
        );
        $process->setTimeout(120);
        $process->run();

        $combinedOutput = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame($expectedExitCode, $process->getExitCode(), $combinedOutput);

        return $combinedOutput;
    }

    /**
     * @return array<mixed>
     */
    private function runOcJson(array $arguments, int $expectedExitCode = 0): array
    {
        $output = $this->runOc($arguments, $expectedExitCode);
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded, $output);

        return $decoded;
    }

    /**
     * @param array<array<string, mixed>> $requirements
     */
    private function findRequirementStatus(array $requirements, string $name): bool
    {
        foreach ($requirements as $requirement) {
            if (($requirement['name'] ?? null) === $name) {
                return (bool) $requirement['status'];
            }
        }

        return false;
    }

    /**
     * @param array<array<string, mixed>> $extensions
     */
    private function hasExtension(array $extensions, string $type, string $code): bool
    {
        foreach ($extensions as $extension) {
            if (($extension['type'] ?? null) === $type && ($extension['code'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array<string, mixed>> $modifications
     */
    private function hasModification(array $modifications, string $code, string $status): bool
    {
        foreach ($modifications as $modification) {
            if (($modification['code'] ?? null) === $code && ($modification['status'] ?? null) === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $parameters
     * @return array<string, string>|null
     */
    private function fetchSingleRow(string $sql, array $parameters): ?array
    {
        $connection = $this->openDatabaseConnection();
        $escapedParameters = array_map([$connection, 'real_escape_string'], $parameters);

        foreach ($escapedParameters as $parameter) {
            $quoted = "'" . $parameter . "'";
            $sql = preg_replace('/\?/', $quoted, $sql, 1);
        }

        $this->assertIsString($sql);
        $result = $connection->query($sql);
        $this->assertNotFalse($result, (string) $connection->error);
        $row = $result instanceof \mysqli_result ? $result->fetch_assoc() : null;

        if ($result instanceof \mysqli_result) {
            $result->free();
        }
        $connection->close();

        return $row ?: null;
    }

    private function openDatabaseConnection(): mysqli
    {
        $connection = mysqli_init();
        $this->assertInstanceOf(mysqli::class, $connection);

        $connected = $connection->real_connect(
            (string) self::$dbConfig['host'],
            (string) self::$dbConfig['user'],
            (string) self::$dbConfig['pass'],
            (string) self::$dbConfig['name'],
            (int) self::$dbConfig['port']
        );

        $this->assertTrue($connected, (string) $connection->connect_error);
        $connection->set_charset('utf8mb4');

        return $connection;
    }
}
