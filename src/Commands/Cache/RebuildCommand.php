<?php

namespace OpenCart\CLI\Commands\Cache;

use OpenCart\CLI\Command;
use OpenCart\CLI\Support\ModificationRefresher;
use Symfony\Component\Console\Input\InputOption;

class RebuildCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:rebuild')
            ->setDescription('Rebuild OpenCart modification cache')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Rebuild target (modification|all)', 'all');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $type = (string) $this->input->getOption('type');
        if (!in_array($type, ['modification', 'all'], true)) {
            $this->io->error('Type must be one of: modification, all.');
            return 1;
        }

        $adminRuntime = $this->getAdminRuntime();
        $refresher = new ModificationRefresher($adminRuntime);

        try {
            $result = $refresher->refresh();
            $themeCleared = 0;
            $sassCleared = 0;
            $dataCleared = 0;

            if ($type === 'all') {
                $adminRuntime->registry()->get('cache')->delete('*');
                $dataCleared = 1;
                $themeCleared = $this->clearThemeCache($adminRuntime->getCacheDir());
                $sassCleared = $this->clearSassCache($adminRuntime->getOpenCartRoot());
            }
        } catch (\Throwable $e) {
            $this->io->error('Failed to rebuild cache: ' . $e->getMessage());
            return 1;
        }

        $message = sprintf(
            'Cache rebuilt successfully. modifications=%d written_files=%d',
            $result['processed_modifications'],
            $result['written_files']
        );

        if ($type === 'all') {
            $message .= sprintf(' data=%d theme=%d sass=%d', $dataCleared, $themeCleared, $sassCleared);
        }

        $this->io->success($message);
        return 0;
    }

    private function clearThemeCache(string $cacheDir): int
    {
        $deleted = 0;
        $directories = glob(rtrim($cacheDir, '/\\') . '/template/*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $deleted++;
                }
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        return $deleted;
    }

    private function clearSassCache(string $root): int
    {
        $deleted = 0;

        $adminBootstrap = $root . '/admin/view/stylesheet/bootstrap.css';
        $adminSassSource = $root . '/admin/view/stylesheet/sass/_bootstrap.scss';
        if (is_file($adminBootstrap) && is_file($adminSassSource)) {
            unlink($adminBootstrap);
            $deleted++;
        }

        $catalogSources = glob($root . '/catalog/view/theme/*/stylesheet/sass/_bootstrap.scss') ?: [];
        foreach ($catalogSources as $source) {
            $target = substr($source, 0, -21) . '/bootstrap.css';
            if (is_file($target)) {
                unlink($target);
                $deleted++;
            }
        }

        return $deleted;
    }
}
