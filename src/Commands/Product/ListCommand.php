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
            ->addArgument(
                'category',
                InputArgument::OPTIONAL,
                'Filter by category name or ID'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (table, json, yaml)',
                'table'
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_REQUIRED,
                'Filter by status (enabled, disabled, all)',
                'all'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit number of results',
                50
            )
            ->addOption(
                'search',
                null,
                InputOption::VALUE_REQUIRED,
                'Search by product name or model'
            );
    }

    protected function handle()
    {
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        $category = $this->input->getArgument('category');
        $status = $this->input->getOption('status');
        $limit = (int)$this->input->getOption('limit');
        $search = $this->input->getOption('search');

        $products = $this->getProducts($connection, $category, $status, $limit, $search);

        if (empty($products)) {
            $this->io->warning('No products found matching the criteria.');
            $connection->close();
            return 0;
        }

        $this->displayProducts($products);
        $connection->close();
        return 0;
    }

    private function getProducts($connection, $category = null, $status = 'all', $limit = 50, $search = null)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $sql = "
            SELECT DISTINCT
                p.product_id,
                pd.name,
                p.model,
                p.price,
                p.status,
                cd.name as category_name,
                p.quantity,
                p.date_added
            FROM {$prefix}product p
            LEFT JOIN {$prefix}product_description pd ON (p.product_id = pd.product_id AND pd.language_id = 1)
            LEFT JOIN {$prefix}product_to_category ptc ON (p.product_id = ptc.product_id)
            LEFT JOIN {$prefix}category_description cd ON (ptc.category_id = cd.category_id AND cd.language_id = 1)
            WHERE 1=1
        ";

        $params = [];
        $types = '';

        if ($status !== 'all') {
            $statusValue = ($status === 'enabled') ? 1 : 0;
            $sql .= " AND p.status = ?";
            $params[] = $statusValue;
            $types .= 'i';
        }

        if ($category) {
            if (is_numeric($category)) {
                $sql .= " AND ptc.category_id = ?";
                $params[] = (int)$category;
                $types .= 'i';
            } else {
                $sql .= " AND cd.name LIKE ?";
                $params[] = '%' . $category . '%';
                $types .= 's';
            }
        }

        if ($search) {
            $sql .= " AND (pd.name LIKE ? OR p.model LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'ss';
        }

        $sql .= " ORDER BY p.product_id DESC";

        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }

        $products = [];
        if (!empty($params)) {
            $stmt = $connection->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                return [];
            }
        } else {
            $result = $connection->query($sql);
        }

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $products[] = [
                    'product_id' => $row['product_id'],
                    'name' => $row['name'] ?: 'N/A',
                    'model' => $row['model'] ?: 'N/A',
                    'price' => number_format((float)$row['price'], 2),
                    'status' => $row['status'] ? 'enabled' : 'disabled',
                    'category' => $row['category_name'] ?: 'N/A',
                    'quantity' => $row['quantity'],
                    'date_added' => $row['date_added']
                ];
            }
        }

        return $products;
    }

    private function displayProducts($products)
    {
        $format = $this->input->getOption('format');

        switch ($format) {
            case 'json':
                $this->io->writeln(json_encode($products, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                foreach ($products as $i => $product) {
                    $this->io->writeln("- product_{$i}:");
                    foreach ($product as $key => $value) {
                        $this->io->writeln("    {$key}: {$value}");
                    }
                }
                break;
            default:
                $this->io->title('Products');

                $rows = [];
                foreach ($products as $product) {
                    $statusIcon = $product['status'] === 'enabled' ? '✓' : '✗';
                    $rows[] = [
                        $product['product_id'],
                        $product['name'],
                        $product['model'],
                        '$' . $product['price'],
                        $statusIcon . ' ' . ucfirst($product['status']),
                        $product['category'],
                        $product['quantity'],
                        substr($product['date_added'], 0, 10)
                    ];
                }

                $this->io->table(
                    ['ID', 'Name', 'Model', 'Price', 'Status', 'Category', 'Qty', 'Date Added'],
                    $rows
                );
                break;
        }
    }
}
