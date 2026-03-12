<?php

namespace OpenCart\CLI\Commands\Category;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('category:create')
            ->setDescription('Create a category')
            ->addArgument('name', InputArgument::REQUIRED, 'Category name')
            ->addOption('parent-id', null, InputOption::VALUE_REQUIRED, 'Parent category ID', 0)
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Category description', '')
            ->addOption('meta-title', null, InputOption::VALUE_REQUIRED, 'Meta title')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Category status (enabled|disabled)', 'enabled')
            ->addOption('sort-order', null, InputOption::VALUE_REQUIRED, 'Sort order', 0)
            ->addOption('top', null, InputOption::VALUE_NONE, 'Show in top navigation')
            ->addOption('column', null, InputOption::VALUE_REQUIRED, 'Number of top navigation columns', 1)
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Comma-separated store IDs', '0')
            ->addOption('keyword', null, InputOption::VALUE_REQUIRED, 'SEO keyword for the current language')
            ->addOption('image', null, InputOption::VALUE_REQUIRED, 'Category image path relative to image/');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $name = trim((string) $this->input->getArgument('name'));
        if ($name === '') {
            $this->io->error('Category name is required.');
            return 1;
        }

        $status = $this->normaliseStatus($this->input->getOption('status'));
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $this->io->error('Status must be either "enabled" or "disabled".');
            return 1;
        }

        $parentId = (int) $this->input->getOption('parent-id');
        $categoryModel = $this->getAdminRuntime()->model('catalog/category');
        if ($parentId > 0 && !$categoryModel->getCategory($parentId)) {
            $this->io->error("Parent category {$parentId} was not found.");
            return 1;
        }

        $registry = $this->getAdminRuntime()->registry();
        $languageId = (int) $registry->get('config')->get('config_language_id');
        $stores = $this->parseIds((string) $this->input->getOption('store'));
        if (empty($stores)) {
            $stores = [0];
        }

        $payload = [
            'parent_id' => $parentId,
            'top' => $this->input->getOption('top') ? 1 : 0,
            'column' => (int) $this->input->getOption('column'),
            'sort_order' => (int) $this->input->getOption('sort-order'),
            'status' => $status === 'enabled' ? 1 : 0,
            'image' => (string) ($this->input->getOption('image') ?: ''),
            'category_description' => [
                $languageId => [
                    'name' => $name,
                    'description' => (string) $this->input->getOption('description'),
                    'meta_title' => (string) ($this->input->getOption('meta-title') ?: $name),
                    'meta_description' => '',
                    'meta_keyword' => '',
                ],
            ],
            'category_filter' => [],
            'category_store' => $stores,
            'category_seo_url' => [],
            'category_layout' => [],
        ];

        $keyword = trim((string) ($this->input->getOption('keyword') ?: ''));
        if ($keyword !== '') {
            $payload['category_seo_url'] = [
                0 => [
                    $languageId => $keyword,
                ],
            ];
        }

        try {
            $categoryId = $categoryModel->addCategory($payload);
        } catch (\Throwable $e) {
            $this->io->error('Failed to create category: ' . $e->getMessage());
            return 1;
        }

        $this->io->success("Category {$categoryId} created successfully.");
        return 0;
    }

    /**
     * @return int[]
     */
    private function parseIds(string $value): array
    {
        $parts = preg_split('/\s*,\s*/', $value);
        $ids = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $ids[] = (int) $part;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $status
     */
    private function normaliseStatus($status): string
    {
        $normalised = strtolower(trim((string) $status));

        if (in_array($normalised, ['1', 'true', 'enabled'], true)) {
            return 'enabled';
        }

        if (in_array($normalised, ['0', 'false', 'disabled'], true)) {
            return 'disabled';
        }

        return $normalised;
    }
}
