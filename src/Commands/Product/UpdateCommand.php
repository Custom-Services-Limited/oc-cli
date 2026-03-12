<?php

namespace OpenCart\CLI\Commands\Product;

use OpenCart\CLI\Command;
use OpenCart\CLI\Support\ProductPayloadBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class UpdateCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('product:update')
            ->setDescription('Update an existing product')
            ->addArgument('product-id', InputArgument::REQUIRED, 'Product ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Product name')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Product model')
            ->addOption('price', null, InputOption::VALUE_REQUIRED, 'Product price')
            ->addOption('quantity', null, InputOption::VALUE_REQUIRED, 'Product quantity')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Product status (enabled|disabled)')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Product SKU')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Comma-separated category IDs')
            ->addOption('image', null, InputOption::VALUE_REQUIRED, 'Product image path relative to image/')
            ->addOption('subtract', null, InputOption::VALUE_REQUIRED, 'Subtract stock (0|1)')
            ->addOption('manufacturer-id', null, InputOption::VALUE_REQUIRED, 'Manufacturer ID')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Product description')
            ->addOption('meta-title', null, InputOption::VALUE_REQUIRED, 'Meta title');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $productId = (int) $this->input->getArgument('product-id');
        if ($productId <= 0) {
            $this->io->error('Product ID must be a positive integer.');
            return 1;
        }

        $updates = $this->collectUpdates();
        if ($updates === null) {
            return 1;
        }

        $runtime = $this->getAdminRuntime();
        $builder = new ProductPayloadBuilder($runtime);
        $productModel = $builder->productModel();
        $payload = $builder->loadEditablePayload($productId);

        if ($payload === null) {
            $this->io->error("Product {$productId} was not found.");
            return 1;
        }

        if (isset($updates['model']) && $this->modelExists($productModel, (string) $updates['model'], $productId)) {
            $this->io->error("Product model '{$updates['model']}' is already in use.");
            return 1;
        }

        $payload = $builder->applyUpdates($payload, $updates);

        try {
            $productModel->editProduct($productId, $payload);
        } catch (\Throwable $e) {
            $this->io->error('Failed to update product: ' . $e->getMessage());
            return 1;
        }

        $this->io->success("Product {$productId} updated successfully.");
        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collectUpdates(): ?array
    {
        $status = $this->input->getOption('status');
        $subtract = $this->input->getOption('subtract');

        if ($status !== null) {
            $status = $this->normaliseStatus($status);
            if (!in_array($status, ['enabled', 'disabled'], true)) {
                $this->io->error('Status must be either "enabled" or "disabled".');
                return null;
            }
        }

        if ($subtract !== null && !in_array((string) $subtract, ['0', '1'], true)) {
            $this->io->error('Subtract must be either 0 or 1.');
            return null;
        }

        if ($this->input->getOption('price') !== null && (!is_numeric($this->input->getOption('price')) || (float) $this->input->getOption('price') < 0)) {
            $this->io->error('Price must be a valid positive number.');
            return null;
        }

        return [
            'name' => $this->input->getOption('name'),
            'model' => $this->input->getOption('model'),
            'price' => $this->input->getOption('price') !== null ? (float) $this->input->getOption('price') : null,
            'quantity' => $this->input->getOption('quantity') !== null ? (int) $this->input->getOption('quantity') : null,
            'status' => $status !== null ? ($status === 'enabled' ? 1 : 0) : null,
            'sku' => $this->input->getOption('sku'),
            'category' => $this->input->getOption('category'),
            'image' => $this->input->getOption('image'),
            'subtract' => $subtract !== null ? (int) $subtract : null,
            'manufacturer_id' => $this->input->getOption('manufacturer-id') !== null ? (int) $this->input->getOption('manufacturer-id') : null,
            'description' => $this->input->getOption('description'),
            'meta_title' => $this->input->getOption('meta-title'),
        ];
    }

    /**
     * @param mixed $productModel
     */
    private function modelExists($productModel, string $model, int $excludeProductId): bool
    {
        $products = $productModel->getProducts([
            'filter_model' => $model,
            'start' => 0,
            'limit' => 5,
        ]);

        foreach ($products as $product) {
            if ((string) $product['model'] === $model && (int) $product['product_id'] !== $excludeProductId) {
                return true;
            }
        }

        return false;
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
