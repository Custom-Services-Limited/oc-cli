<?php

namespace OpenCart\CLI\Commands\Cache;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputOption;

class ClearCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('cache:clear')
            ->setDescription('Clear OpenCart caches')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Cache type (data|theme|sass|all)', 'all');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $type = (string) $this->input->getOption('type');
        if (!in_array($type, ['data', 'theme', 'sass', 'all'], true)) {
            $this->io->error('Type must be one of: data, theme, sass, all.');
            return 1;
        }

        $adminRuntime = $this->getAdminRuntime();
        $cleared = [
            'data' => 0,
            'theme' => 0,
            'sass' => 0,
        ];

        if (in_array($type, ['data', 'all'], true)) {
            $adminRuntime->registry()->get('cache')->delete('*');
            $cleared['data'] = 1;
        }

        if (in_array($type, ['theme', 'all'], true)) {
            $cleared['theme'] = $this->clearThemeCache($adminRuntime->getCacheDir());
        }

        if (in_array($type, ['sass', 'all'], true)) {
            $cleared['sass'] = $this->clearSassCache($adminRuntime->getOpenCartRoot());
        }

        $this->io->success(
            sprintf(
                'Cache cleared successfully. data=%d theme=%d sass=%d',
                $cleared['data'],
                $cleared['theme'],
                $cleared['sass']
            )
        );

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
