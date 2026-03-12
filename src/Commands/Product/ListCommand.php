<?php

namespace OpenCart\CLI\Commands\Product;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('product:list')
            ->setDescription('List products')
            ->addArgument('category', InputArgument::OPTIONAL, 'Filter by category name or ID')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, yaml)', 'table')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Filter by status (enabled, disabled, all)', 'all')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of results', 50)
            ->addOption('search', null, InputOption::VALUE_REQUIRED, 'Search by product name or model');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $status = $this->normaliseFilterStatus($this->input->getOption('status'));
        if (!in_array($status, ['enabled', 'disabled', 'all'], true)) {
            $this->io->error('Status must be one of: enabled, disabled, all.');
            return 1;
        }

        $products = $this->getProducts();

        if (empty($products)) {
            $this->io->warning('No products found matching the criteria.');
            return 0;
        }

        $this->displayProducts($products);
        return 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProducts(): array
    {
        $productModel = $this->getAdminRuntime()->model('catalog/product');
        $category = $this->input->getArgument('category');
        $search = $this->input->getOption('search');
        $status = $this->normaliseFilterStatus($this->input->getOption('status'));
        $limit = max(1, (int) $this->input->getOption('limit'));

        $filterData = [
            'filter_name' => '',
            'filter_model' => '',
            'filter_status' => $status === 'all' ? '' : ($status === 'enabled' ? 1 : 0),
            'start' => 0,
            'limit' => $limit,
            'sort' => 'pd.name',
            'order' => 'ASC',
        ];

        if ($category) {
            if (is_numeric($category)) {
                $rows = $productModel->getProductsByCategoryId((int) $category);
            } else {
                $rows = $this->fetchBySearch($productModel, $filterData, $search);
                $rows = array_values(array_filter($rows, function (array $row) use ($category): bool {
                    return stripos((string) $this->resolveCategoryName((int) $row['product_id']), (string) $category) !== false;
                }));
            }
        } else {
            $rows = $this->fetchBySearch($productModel, $filterData, $search);
        }

        if ($search && $category && is_numeric($category)) {
            $rows = array_values(array_filter($rows, function (array $row) use ($search): bool {
                return stripos((string) $row['name'], (string) $search) !== false
                    || stripos((string) $row['model'], (string) $search) !== false;
            }));
        }

        $rows = array_slice($rows, 0, $limit);

        return array_map(function (array $row): array {
            return [
                'product_id' => (int) $row['product_id'],
                'name' => $row['name'] ?: 'N/A',
                'model' => $row['model'] ?: 'N/A',
                'price' => number_format((float) $row['price'], 2),
                'status' => (int) $row['status'] === 1 ? 'enabled' : 'disabled',
                'quantity' => (int) $row['quantity'],
                'category' => $this->resolveCategoryName((int) $row['product_id']) ?: 'N/A',
                'date_added' => $row['date_added'] ?? '',
            ];
        }, $rows);
    }

    /**
     * @param mixed $productModel
     * @param array<string, mixed> $filterData
     * @return array<int, array<string, mixed>>
     */
    private function fetchBySearch($productModel, array $filterData, ?string $search): array
    {
        if (!$search) {
            return $productModel->getProducts($filterData);
        }

        $byName = $productModel->getProducts(array_merge($filterData, ['filter_name' => (string) $search]));
        $byModel = $productModel->getProducts(array_merge($filterData, ['filter_model' => (string) $search]));

        $merged = [];
        foreach (array_merge($byName, $byModel) as $row) {
            $merged[(int) $row['product_id']] = $row;
        }

        return array_values($merged);
    }

    private function resolveCategoryName(int $productId): string
    {
        $runtime = $this->getAdminRuntime();
        $db = $runtime->database();
        $prefix = $runtime->getDatabasePrefix();
        $languageId = (int) $runtime->registry()->get('config')->get('config_language_id');

        $query = $db->query(
            "SELECT cd.name FROM `" . $prefix . "product_to_category` ptc " .
            "LEFT JOIN `" . $prefix . "category_description` cd ON (ptc.category_id = cd.category_id) " .
            "WHERE ptc.product_id = '" . $productId . "' AND cd.language_id = '" . $languageId . "' ORDER BY ptc.category_id ASC LIMIT 1"
        );

        return isset($query->row['name']) ? (string) $query->row['name'] : '';
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function displayProducts(array $products): void
    {
        $format = $this->input->getOption('format');

        if ($format === 'json') {
            $this->io->writeln(json_encode($products, JSON_PRETTY_PRINT));
            return;
        }

        if ($format === 'yaml') {
            foreach ($products as $index => $product) {
                $this->io->writeln("- product_{$index}:");
                foreach ($product as $key => $value) {
                    $this->io->writeln("    {$key}: {$value}");
                }
            }
            return;
        }

        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                $product['product_id'],
                $product['name'],
                $product['model'],
                '$' . $product['price'],
                $product['status'],
                $product['category'],
                $product['quantity'],
                substr((string) $product['date_added'], 0, 10),
            ];
        }

        $this->io->title('Products');
        $this->io->table(
            ['ID', 'Name', 'Model', 'Price', 'Status', 'Category', 'Qty', 'Date Added'],
            $rows
        );
    }

    /**
     * @param mixed $status
     */
    private function normaliseFilterStatus($status): string
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
