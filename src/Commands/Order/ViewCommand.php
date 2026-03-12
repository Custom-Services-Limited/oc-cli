<?php

namespace OpenCart\CLI\Commands\Order;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ViewCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('order:view')
            ->setDescription('View an order')
            ->addArgument('order-id', InputArgument::REQUIRED, 'Order ID')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, yaml)', 'table');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $orderId = (int) $this->input->getArgument('order-id');
        if ($orderId <= 0) {
            $this->io->error('Order ID must be a positive integer.');
            return 1;
        }

        $orderModel = $this->getAdminRuntime()->model('sale/order');
        $order = $orderModel->getOrder($orderId);
        if (!$order) {
            $this->io->error("Order {$orderId} was not found.");
            return 1;
        }

        $payload = [
            'order' => $order,
            'products' => $orderModel->getOrderProducts($orderId),
            'totals' => $orderModel->getOrderTotals($orderId),
            'histories' => $orderModel->getOrderHistories($orderId, 0, 20),
        ];

        $format = (string) $this->input->getOption('format');
        if ($format === 'json') {
            $this->io->writeln(json_encode($payload, JSON_PRETTY_PRINT));
            return 0;
        }

        if ($format === 'yaml') {
            $this->io->writeln("order_id: {$order['order_id']}");
            $this->io->writeln("customer: {$order['customer']}");
            $this->io->writeln("status: {$order['order_status']}");
            $this->io->writeln("total: {$order['total']}");
            return 0;
        }

        $this->io->title('Order ' . $order['order_id']);
        $this->io->table(
            ['Field', 'Value'],
            [
                ['Customer', $order['customer']],
                ['Email', $order['email']],
                ['Status', $order['order_status']],
                ['Total', $order['currency_code'] . ' ' . number_format((float) $order['total'], 2)],
                ['Payment Method', $order['payment_method']],
                ['Shipping Method', $order['shipping_method']],
                ['Date Added', $order['date_added']],
                ['Date Modified', $order['date_modified']],
            ]
        );

        if (!empty($payload['products'])) {
            $this->io->section('Products');
            $this->io->table(
                ['Product ID', 'Name', 'Model', 'Qty', 'Price', 'Total'],
                array_map(function (array $product): array {
                    return [
                        $product['product_id'],
                        $product['name'],
                        $product['model'],
                        $product['quantity'],
                        $product['price'],
                        $product['total'],
                    ];
                }, $payload['products'])
            );
        }

        if (!empty($payload['totals'])) {
            $this->io->section('Totals');
            $this->io->table(
                ['Code', 'Title', 'Value'],
                array_map(function (array $total): array {
                    return [$total['code'], $total['title'], $total['value']];
                }, $payload['totals'])
            );
        }

        if (!empty($payload['histories'])) {
            $this->io->section('History');
            $this->io->table(
                ['Status', 'Notify', 'Comment', 'Date Added'],
                array_map(function (array $history): array {
                    return [
                        $history['status'],
                        $history['notify'] ? 'yes' : 'no',
                        $history['comment'],
                        $history['date_added'],
                    ];
                }, $payload['histories'])
            );
        }

        return 0;
    }
}
