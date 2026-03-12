<?php

namespace OpenCart\CLI\Commands\Category;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('category:list')
            ->setDescription('List categories')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Filter by category name')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort field (name|sort_order)', 'sort_order')
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Sort order (asc|desc)', 'asc')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Page number', 1)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Results per page', 20)
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, yaml)', 'table');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $sortMap = [
            'name' => 'name',
            'sort_order' => 'sort_order',
        ];

        $sort = (string) $this->input->getOption('sort');
        $order = strtoupper((string) $this->input->getOption('order'));
        if (!isset($sortMap[$sort]) || !in_array($order, ['ASC', 'DESC'], true)) {
            $this->io->error('Invalid sort or order option.');
            return 1;
        }

        $page = max(1, (int) $this->input->getOption('page'));
        $limit = max(1, (int) $this->input->getOption('limit'));

        $rows = $this->getAdminRuntime()->model('catalog/category')->getCategories([
            'filter_name' => (string) ($this->input->getOption('name') ?: ''),
            'sort' => $sortMap[$sort],
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);

        $categories = array_map(function (array $row): array {
            return [
                'category_id' => (int) $row['category_id'],
                'name' => html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8'),
                'parent_id' => (int) $row['parent_id'],
                'sort_order' => (int) $row['sort_order'],
            ];
        }, $rows);

        if (empty($categories)) {
            $this->io->warning('No categories found matching the criteria.');
            return 0;
        }

        $format = (string) $this->input->getOption('format');
        if ($format === 'json') {
            $this->io->writeln(json_encode($categories, JSON_PRETTY_PRINT));
            return 0;
        }

        if ($format === 'yaml') {
            foreach ($categories as $index => $category) {
                $this->io->writeln("- category_{$index}:");
                foreach ($category as $key => $value) {
                    $this->io->writeln("    {$key}: {$value}");
                }
            }
            return 0;
        }

        $this->io->title('Categories');
        $this->io->table(
            ['ID', 'Name', 'Parent ID', 'Sort Order'],
            array_map(function (array $category): array {
                return [
                    $category['category_id'],
                    $category['name'],
                    $category['parent_id'],
                    $category['sort_order'],
                ];
            }, $categories)
        );

        return 0;
    }
}
