<?php

namespace OpenCart\CLI\Commands\Product;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DeleteCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('product:delete')
            ->setDescription('Delete a product')
            ->addArgument('product-id', InputArgument::REQUIRED, 'Product ID')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Confirm deletion without prompting');
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

        $productModel = $this->getAdminRuntime()->model('catalog/product');
        $product = $productModel->getProduct($productId);
        if (!$product) {
            $this->io->error("Product {$productId} was not found.");
            return 1;
        }

        if (!$this->input->getOption('force') && !$this->io->confirm("Delete product {$productId} ({$product['name']})?", false)) {
            $this->io->warning('Deletion cancelled.');
            return 1;
        }

        try {
            $productModel->deleteProduct($productId);
        } catch (\Throwable $e) {
            $this->io->error('Failed to delete product: ' . $e->getMessage());
            return 1;
        }

        $this->io->success("Product {$productId} deleted successfully.");
        return 0;
    }
}
