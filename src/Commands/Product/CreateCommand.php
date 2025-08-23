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
use Symfony\Component\Console\Question\Question;

class CreateCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('product:create')
            ->setDescription('Create a new product')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Product name'
            )
            ->addArgument(
                'model',
                InputArgument::OPTIONAL,
                'Product model/SKU'
            )
            ->addArgument(
                'price',
                InputArgument::OPTIONAL,
                'Product price'
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Product description'
            )
            ->addOption(
                'category',
                'c',
                InputOption::VALUE_REQUIRED,
                'Category name or ID'
            )
            ->addOption(
                'quantity',
                null,
                InputOption::VALUE_REQUIRED,
                'Product quantity',
                0
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_REQUIRED,
                'Product status (enabled|disabled)',
                'enabled'
            )
            ->addOption(
                'weight',
                'w',
                InputOption::VALUE_REQUIRED,
                'Product weight',
                0
            )
            ->addOption(
                'sku',
                null,
                InputOption::VALUE_REQUIRED,
                'Product SKU'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (table, json, yaml)',
                'table'
            )
            ->addOption(
                'interactive',
                'i',
                InputOption::VALUE_NONE,
                'Interactive mode - prompt for missing values'
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

        // Get product data
        $productData = $this->getProductData();

        if (!$productData) {
            $connection->close();
            return 1;
        }

        // Validate required fields
        if (!$this->validateProductData($productData)) {
            $connection->close();
            return 1;
        }

        // Check for duplicate model
        if ($this->modelExists($connection, $productData['model'])) {
            $this->io->error("Product with model '{$productData['model']}' already exists.");
            $connection->close();
            return 1;
        }

        // Create the product
        $productId = $this->createProduct($connection, $productData);

        if (!$productId) {
            $this->io->error('Failed to create product.');
            $connection->close();
            return 1;
        }

        $this->displayResult($productId, $productData);
        $connection->close();
        return 0;
    }

    private function getProductData()
    {
        $data = [
            'name' => $this->input->getArgument('name'),
            'model' => $this->input->getArgument('model'),
            'price' => $this->input->getArgument('price'),
            'description' => $this->input->getOption('description') ?: '',
            'category' => $this->input->getOption('category'),
            'quantity' => (int)$this->input->getOption('quantity'),
            'status' => $this->input->getOption('status'),
            'weight' => (float)$this->input->getOption('weight'),
            'sku' => $this->input->getOption('sku') ?: ''
        ];

        // Interactive mode or missing required fields
        if ($this->input->getOption('interactive') || !$data['name'] || !$data['model'] || !$data['price']) {
            $data = $this->promptForMissingData($data);
        }

        return $data;
    }

    private function promptForMissingData($data)
    {
        $helper = $this->getHelper('question');

        if (!$data['name']) {
            $question = new Question('Product name: ');
            $question->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('Product name cannot be empty.');
                }
                return $value;
            });
            $data['name'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$data['model']) {
            $question = new Question('Product model/SKU: ');
            $question->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('Product model cannot be empty.');
                }
                return $value;
            });
            $data['model'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$data['price']) {
            $question = new Question('Product price: ');
            $question->setValidator(function ($value) {
                if (!is_numeric($value) || $value < 0) {
                    throw new \RuntimeException('Price must be a valid positive number.');
                }
                return $value;
            });
            $data['price'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$data['description']) {
            $question = new Question('Product description (optional): ', '');
            $data['description'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$data['category']) {
            $question = new Question('Category name or ID (optional): ', '');
            $data['category'] = $helper->ask($this->input, $this->output, $question);
        }

        return $data;
    }

    private function validateProductData($data)
    {
        if (empty($data['name'])) {
            $this->io->error('Product name is required.');
            return false;
        }

        if (empty($data['model'])) {
            $this->io->error('Product model is required.');
            return false;
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            $this->io->error('Price must be a valid positive number.');
            return false;
        }

        if (!in_array($data['status'], ['enabled', 'disabled'])) {
            $this->io->error('Status must be either "enabled" or "disabled".');
            return false;
        }

        return true;
    }

    private function modelExists($connection, $model)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        $sql = "SELECT COUNT(*) as count FROM {$prefix}product WHERE model = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('s', $model);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['count'] > 0;
    }

    private function createProduct($connection, $data)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // Start transaction
        $connection->autocommit(false);

        try {
            // Insert into oc_product
            $sql = "INSERT INTO {$prefix}product (
                model, sku, upc, ean, jan, isbn, mpn, location,
                price, quantity, status, weight, manufacturer_id,
                stock_status_id, shipping, tax_class_id, 
                date_available, date_added, date_modified
            ) VALUES (?, ?, '', '', '', '', '', '', ?, ?, ?, ?, 0, 7, 1, 0, CURDATE(), NOW(), NOW())";

            $stmt = $connection->prepare($sql);
            $status = $data['status'] === 'enabled' ? 1 : 0;

            $stmt->bind_param(
                'ssdiid',
                $data['model'],
                $data['sku'],
                $data['price'],
                $data['quantity'],
                $status,
                $data['weight']
            );

            if (!$stmt->execute()) {
                throw new \Exception('Failed to insert product: ' . $stmt->error);
            }

            $productId = $connection->insert_id;

            // Insert into oc_product_description
            $sql = "INSERT INTO {$prefix}product_description (
                product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword
            ) VALUES (?, 1, ?, ?, '', ?, '', '')";

            $stmt = $connection->prepare($sql);
            $stmt->bind_param(
                'isss',
                $productId,
                $data['name'],
                $data['description'],
                $data['name']
            );

            if (!$stmt->execute()) {
                throw new \Exception('Failed to insert product description: ' . $stmt->error);
            }

            // Insert into oc_product_to_category if category is specified
            if (!empty($data['category'])) {
                $categoryId = $this->getCategoryId($connection, $data['category']);
                if ($categoryId) {
                    $sql = "INSERT INTO {$prefix}product_to_category (product_id, category_id) VALUES (?, ?)";
                    $stmt = $connection->prepare($sql);
                    $stmt->bind_param('ii', $productId, $categoryId);
                    $stmt->execute();
                }
            }

            // Commit transaction
            $connection->commit();
            return $productId;
        } catch (\Exception $e) {
            // Rollback transaction
            $connection->rollback();
            $this->io->error('Transaction failed: ' . $e->getMessage());
            return false;
        } finally {
            $connection->autocommit(true);
        }
    }

    private function getCategoryId($connection, $category)
    {
        $config = $this->getOpenCartConfig();
        $prefix = $config['db_prefix'];

        // If category is numeric, assume it's an ID
        if (is_numeric($category)) {
            $sql = "SELECT category_id FROM {$prefix}category WHERE category_id = ?";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param('i', $category);
        } else {
            // Search by name
            $sql = "SELECT cd.category_id FROM {$prefix}category_description cd 
                    WHERE cd.name = ? AND cd.language_id = 1";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param('s', $category);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row ? $row['category_id'] : null;
    }

    private function displayResult($productId, $data)
    {
        $format = $this->input->getOption('format');

        $result = [
            'product_id' => $productId,
            'name' => $data['name'],
            'model' => $data['model'],
            'price' => number_format((float)$data['price'], 2),
            'status' => $data['status'],
            'quantity' => $data['quantity'],
            'weight' => $data['weight']
        ];

        switch ($format) {
            case 'json':
                $this->io->writeln(json_encode($result, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                $this->io->writeln("product:");
                foreach ($result as $key => $value) {
                    $this->io->writeln("  {$key}: {$value}");
                }
                break;
            default:
                $this->io->success("Product created successfully!");
                $this->io->table(
                    ['Field', 'Value'],
                    [
                        ['Product ID', $result['product_id']],
                        ['Name', $result['name']],
                        ['Model', $result['model']],
                        ['Price', '$' . $result['price']],
                        ['Status', ucfirst($result['status'])],
                        ['Quantity', $result['quantity']],
                        ['Weight', $result['weight']]
                    ]
                );
                break;
        }
    }
}
