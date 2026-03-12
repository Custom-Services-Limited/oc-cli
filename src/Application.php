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

namespace OpenCart\CLI;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use OpenCart\CLI\Commands\Core\VersionCommand;
use OpenCart\CLI\Commands\Core\CheckRequirementsCommand;
use OpenCart\CLI\Commands\Core\ConfigCommand;
use OpenCart\CLI\Commands\Database\InfoCommand;
use OpenCart\CLI\Commands\Database\BackupCommand;
use OpenCart\CLI\Commands\Database\RestoreCommand;
use OpenCart\CLI\Commands\Database\CheckCommand as DatabaseCheckCommand;
use OpenCart\CLI\Commands\Database\RepairCommand as DatabaseRepairCommand;
use OpenCart\CLI\Commands\Database\OptimizeCommand as DatabaseOptimizeCommand;
use OpenCart\CLI\Commands\Database\CleanupCommand as DatabaseCleanupCommand;
use OpenCart\CLI\Commands\Cache\ClearCommand as CacheClearCommand;
use OpenCart\CLI\Commands\Cache\RebuildCommand as CacheRebuildCommand;
use OpenCart\CLI\Commands\Extension\ListCommand as ExtensionListCommand;
use OpenCart\CLI\Commands\Extension\InstallCommand;
use OpenCart\CLI\Commands\Extension\EnableCommand;
use OpenCart\CLI\Commands\Extension\DisableCommand;
use OpenCart\CLI\Commands\Extension\ModificationListCommand;
use OpenCart\CLI\Commands\Category\ListCommand as CategoryListCommand;
use OpenCart\CLI\Commands\Category\CreateCommand as CategoryCreateCommand;
use OpenCart\CLI\Commands\Order\ListCommand as OrderListCommand;
use OpenCart\CLI\Commands\Order\ViewCommand as OrderViewCommand;
use OpenCart\CLI\Commands\Order\UpdateStatusCommand as OrderUpdateStatusCommand;
use OpenCart\CLI\Commands\Product\ListCommand as ProductListCommand;
use OpenCart\CLI\Commands\Product\CreateCommand as ProductCreateCommand;
use OpenCart\CLI\Commands\Product\UpdateCommand as ProductUpdateCommand;
use OpenCart\CLI\Commands\Product\DeleteCommand as ProductDeleteCommand;
use OpenCart\CLI\Commands\User\ListCommand as UserListCommand;
use OpenCart\CLI\Commands\User\CreateCommand as UserCreateCommand;
use OpenCart\CLI\Commands\User\DeleteCommand as UserDeleteCommand;

class Application extends BaseApplication
{
    // Fallback version when no build metadata or Git tag can be resolved.
    public const VERSION = '0.0.0-dev';
    public const NAME = 'OC-CLI';

    /**
     * @var string|null
     */
    private static $resolvedVersion;

    public function __construct()
    {
        parent::__construct(self::NAME, self::resolveVersion());

        $this->addCommands($this->getDefaultCommands());
    }

    public static function resolveVersion(bool $refresh = false): string
    {
        if ($refresh || self::$resolvedVersion === null) {
            self::$resolvedVersion = self::detectVersion();
        }

        return self::$resolvedVersion;
    }

    /**
     * Get the default commands for the application
     *
     * @return array
     */
    protected function getDefaultCommands(): array
    {
        // When running in PHAR, manually add only safe default commands to avoid filesystem issues
        if (\Phar::running()) {
            $commands = [
                new \Symfony\Component\Console\Command\HelpCommand(),
                new \Symfony\Component\Console\Command\ListCommand(),
            ];
        } else {
            $commands = parent::getDefaultCommands();
        }

        $commands[] = new VersionCommand();
        $commands[] = new CheckRequirementsCommand();
        $commands[] = new ConfigCommand();
        $commands[] = new InfoCommand();
        $commands[] = new BackupCommand();
        $commands[] = new RestoreCommand();
        $commands[] = new DatabaseCheckCommand();
        $commands[] = new DatabaseRepairCommand();
        $commands[] = new DatabaseOptimizeCommand();
        $commands[] = new DatabaseCleanupCommand();
        $commands[] = new CacheClearCommand();
        $commands[] = new CacheRebuildCommand();
        $commands[] = new ExtensionListCommand();
        $commands[] = new InstallCommand();
        $commands[] = new EnableCommand();
        $commands[] = new DisableCommand();
        $commands[] = new ModificationListCommand();
        $commands[] = new CategoryListCommand();
        $commands[] = new CategoryCreateCommand();
        $commands[] = new OrderListCommand();
        $commands[] = new OrderViewCommand();
        $commands[] = new OrderUpdateStatusCommand();
        $commands[] = new ProductListCommand();
        $commands[] = new ProductCreateCommand();
        $commands[] = new ProductUpdateCommand();
        $commands[] = new ProductDeleteCommand();
        $commands[] = new UserListCommand();
        $commands[] = new UserCreateCommand();
        $commands[] = new UserDeleteCommand();

        return $commands;
    }

    /**
     * Get the long version of the application
     *
     * @return string
     */
    public function getLongVersion()
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment>',
            $this->getName(),
            $this->getVersion()
        );
    }

    /**
     * Run the application
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        // In test environment, ensure we have proper input/output to prevent hanging
        if (getenv('APP_ENV') === 'testing') {
            if ($input === null) {
                $input = new ArrayInput(['command' => 'list']);
            }
            if ($output === null) {
                $output = new BufferedOutput();
            }
        }

        return parent::run($input, $output);
    }

    /**
     * Detect OpenCart installation in current directory
     *
     * @param string $path
     * @return bool
     */
    public function detectOpenCart($path = '.')
    {
        $indicators = [
            'system/startup.php',
            'system/config/catalog.php',
            'admin/config.php',
            'config.php'
        ];

        foreach ($indicators as $indicator) {
            if (file_exists($path . '/' . $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get OpenCart root directory
     *
     * @param string $startPath
     * @return string|null
     */
    public function getOpenCartRoot($startPath = '.')
    {
        $path = realpath($startPath);

        if (!$path) {
            return null;
        }

        // Default behavior: search upwards from current directory or provided path
        while ($path && $path !== '/') {
            if ($this->detectOpenCart($path)) {
                return $path;
            }
            $path = dirname($path);
        }

        return null;
    }

    private static function detectVersion(): string
    {
        $envVersion = getenv('OC_CLI_VERSION');
        if (is_string($envVersion) && trim($envVersion) !== '') {
            return self::normalizeVersion($envVersion);
        }

        if (class_exists(__NAMESPACE__ . '\\BuildVersion')) {
            /** @var class-string $className */
            $className = __NAMESPACE__ . '\\BuildVersion';
            if (defined($className . '::VERSION')) {
                /** @var string $buildVersion */
                $buildVersion = constant($className . '::VERSION');

                return self::normalizeVersion($buildVersion);
            }
        }

        $gitVersion = self::detectVersionFromGit();
        if ($gitVersion !== null) {
            return $gitVersion;
        }

        return self::VERSION;
    }

    private static function detectVersionFromGit(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $projectRoot = dirname(__DIR__);
        if (!is_dir($projectRoot . '/.git') && !is_file($projectRoot . '/.git')) {
            return null;
        }

        $command = 'git -C ' . escapeshellarg($projectRoot)
            . " describe --tags --match 'v[0-9]*' --dirty --always 2>/dev/null";

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        return self::normalizeVersion((string) $output[0]);
    }

    private static function normalizeVersion(string $version): string
    {
        $version = trim($version);

        if ($version === '') {
            return self::VERSION;
        }

        if (preg_match('/^v(?=\d)/', $version) === 1) {
            $version = substr($version, 1);
        }

        $version = preg_replace('/[^0-9A-Za-z.+-]/', '', $version);

        return $version !== '' ? $version : self::VERSION;
    }
}
