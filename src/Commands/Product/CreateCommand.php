<?php

namespace OpenCart\CLI\Commands\Product;

use OpenCart\CLI\Command;
use OpenCart\CLI\Support\ProductPayloadBuilder;
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
            ->addArgument('name', InputArgument::OPTIONAL, 'Product name')
            ->addArgument('model', InputArgument::OPTIONAL, 'Product model/SKU')
            ->addArgument('price', InputArgument::OPTIONAL, 'Product price')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Product description')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Category name or ID')
            ->addOption('quantity', null, InputOption::VALUE_REQUIRED, 'Product quantity', 0)
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Product status (enabled|disabled)', 'enabled')
            ->addOption('weight', 'w', InputOption::VALUE_REQUIRED, 'Product weight', 0)
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Product SKU')
            ->addOption('image', null, InputOption::VALUE_REQUIRED, 'Product image path relative to image/')
            ->addOption('meta-title', null, InputOption::VALUE_REQUIRED, 'Product meta title')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, yaml)', 'table')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive mode - prompt for missing values');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $productData = $this->getProductData();
        if (!$productData || !$this->validateProductData($productData)) {
            return 1;
        }

        $runtime = $this->getAdminRuntime();
        $builder = new ProductPayloadBuilder($runtime);
        $productModel = $builder->productModel();

        if ($this->modelExists($productModel, $productData['model'])) {
            $this->io->error("Product with model '{$productData['model']}' already exists.");
            return 1;
        }

        $payload = $builder->buildCreatePayload([
            'name' => $productData['name'],
            'model' => $productData['model'],
            'price' => $productData['price'],
            'description' => $productData['description'],
            'category' => $productData['category'],
            'quantity' => $productData['quantity'],
            'status' => $productData['status'] === 'enabled' ? 1 : 0,
            'weight' => $productData['weight'],
            'sku' => $productData['sku'],
            'image' => $productData['image'],
            'meta_title' => $productData['meta_title'],
        ]);

        try {
            $productId = $productModel->addProduct($payload);
        } catch (\Throwable $e) {
            $this->io->error('Failed to create product: ' . $e->getMessage());
            return 1;
        }

        $this->displayResult((int) $productId, $productData);

        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getProductData(): ?array
    {
        $data = [
            'name' => $this->input->getArgument('name'),
            'model' => $this->input->getArgument('model'),
            'price' => $this->input->getArgument('price'),
            'description' => $this->input->getOption('description') ?: '',
            'category' => $this->input->getOption('category'),
            'quantity' => (int) $this->input->getOption('quantity'),
            'status' => $this->normaliseStatus($this->input->getOption('status')),
            'weight' => (float) $this->input->getOption('weight'),
            'sku' => $this->input->getOption('sku') ?: ($this->input->getArgument('model') ?: ''),
            'image' => $this->input->getOption('image') ?: '',
            'meta_title' => $this->input->getOption('meta-title') ?: null,
        ];

        if ($this->input->getOption('interactive') || !$data['name'] || !$data['model'] || !$data['price']) {
            $data = $this->promptForMissingData($data);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function promptForMissingData(array $data): array
    {
        $helper = $this->getHelper('question');

        if (!$data['name']) {
            $question = new Question('Product name: ');
            $question->setValidator(function ($value) {
                if (empty(trim((string) $value))) {
                    throw new \RuntimeException('Product name cannot be empty.');
                }

                return $value;
            });
            $data['name'] = $helper->ask($this->input, $this->output, $question);
        }

        if (!$data['model']) {
            $question = new Question('Product model/SKU: ');
            $question->setValidator(function ($value) {
                if (empty(trim((string) $value))) {
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

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateProductData(array $data): bool
    {
        if (empty(trim((string) ($data['name'] ?? '')))) {
            $this->io->error('Product name is required.');
            return false;
        }

        if (empty(trim((string) ($data['model'] ?? '')))) {
            $this->io->error('Product model is required.');
            return false;
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            $this->io->error('Price must be a valid positive number.');
            return false;
        }

        if (!in_array($data['status'], ['enabled', 'disabled'], true)) {
            $this->io->error('Status must be either "enabled" or "disabled".');
            return false;
        }

        return true;
    }

    /**
     * @param mixed $productModel
     */
    private function modelExists($productModel, string $model): bool
    {
        $products = $productModel->getProducts([
            'filter_model' => $model,
            'start' => 0,
            'limit' => 1,
        ]);

        return !empty($products) && (string) $products[0]['model'] === $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function displayResult(int $productId, array $data): void
    {
        $format = $this->input->getOption('format');
        $result = [
            'product_id' => $productId,
            'name' => $data['name'],
            'model' => $data['model'],
            'price' => number_format((float) $data['price'], 2),
            'status' => $data['status'],
            'quantity' => (int) $data['quantity'],
            'weight' => (float) $data['weight'],
            'sku' => $data['sku'] ?: $data['model'],
        ];

        if ($format === 'json') {
            $this->io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return;
        }

        if ($format === 'yaml') {
            $this->io->writeln("product:");
            foreach ($result as $key => $value) {
                $this->io->writeln("  {$key}: {$value}");
            }
            return;
        }

        $this->io->success("Product created successfully (ID {$productId}).");
        $this->io->table(
            ['Field', 'Value'],
            [
                ['Product ID', $result['product_id']],
                ['Name', $result['name']],
                ['Model', $result['model']],
                ['SKU', $result['sku']],
                ['Price', '$' . $result['price']],
                ['Status', ucfirst((string) $result['status'])],
                ['Quantity', $result['quantity']],
                ['Weight', $result['weight']],
            ]
        );
    }

    /**
     * @param mixed $status
     */
    private function normaliseStatus($status): ?string
    {
        if ($status === null) {
            return null;
        }

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
