<?php

namespace OpenCart\CLI\Commands\Order;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('order:list')
            ->setDescription('List orders')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Filter by order ID')
            ->addOption('customer', null, InputOption::VALUE_REQUIRED, 'Filter by customer name')
            ->addOption('status-id', null, InputOption::VALUE_REQUIRED, 'Filter by order status ID')
            ->addOption('date-added', null, InputOption::VALUE_REQUIRED, 'Filter by creation date (YYYY-MM-DD)')
            ->addOption('date-modified', null, InputOption::VALUE_REQUIRED, 'Filter by update date (YYYY-MM-DD)')
            ->addOption('total', null, InputOption::VALUE_REQUIRED, 'Filter by exact total')
            ->addOption(
                'sort',
                null,
                InputOption::VALUE_REQUIRED,
                'Sort field (order_id|customer|order_status|date_added|date_modified|total)',
                'order_id'
            )
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Sort order (asc|desc)', 'desc')
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
            'order_id' => 'o.order_id',
            'customer' => 'customer',
            'order_status' => 'order_status',
            'date_added' => 'o.date_added',
            'date_modified' => 'o.date_modified',
            'total' => 'o.total',
        ];

        $sort = (string) $this->input->getOption('sort');
        $order = strtoupper((string) $this->input->getOption('order'));
        if (!isset($sortMap[$sort]) || !in_array($order, ['ASC', 'DESC'], true)) {
            $this->io->error('Invalid sort or order option.');
            return 1;
        }

        $page = max(1, (int) $this->input->getOption('page'));
        $limit = max(1, (int) $this->input->getOption('limit'));

        $orders = $this->getAdminRuntime()->model('sale/order')->getOrders([
            'filter_order_id' => (string) ($this->input->getOption('id') ?: ''),
            'filter_customer' => (string) ($this->input->getOption('customer') ?: ''),
            'filter_order_status_id' => (string) ($this->input->getOption('status-id') ?: ''),
            'filter_date_added' => (string) ($this->input->getOption('date-added') ?: ''),
            'filter_date_modified' => (string) ($this->input->getOption('date-modified') ?: ''),
            'filter_total' => (string) ($this->input->getOption('total') ?: ''),
            'sort' => $sortMap[$sort],
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);

        $rows = array_map(function (array $orderRow): array {
            return [
                'order_id' => (int) $orderRow['order_id'],
                'customer' => $orderRow['customer'],
                'order_status' => $orderRow['order_status'] ?: 'Missing',
                'total' => number_format((float) $orderRow['total'], 2),
                'currency_code' => $orderRow['currency_code'],
                'date_added' => $orderRow['date_added'],
                'date_modified' => $orderRow['date_modified'],
            ];
        }, $orders);

        if (empty($rows)) {
            $this->io->warning('No orders found matching the criteria.');
            return 0;
        }

        $format = (string) $this->input->getOption('format');
        if ($format === 'json') {
            $this->io->writeln(json_encode($rows, JSON_PRETTY_PRINT));
            return 0;
        }

        if ($format === 'yaml') {
            foreach ($rows as $index => $row) {
                $this->io->writeln("- order_{$index}:");
                foreach ($row as $key => $value) {
                    $this->io->writeln("    {$key}: {$value}");
                }
            }
            return 0;
        }

        $this->io->title('Orders');
        $this->io->table(
            ['ID', 'Customer', 'Status', 'Total', 'Currency', 'Date Added', 'Date Modified'],
            array_map(function (array $row): array {
                return [
                    $row['order_id'],
                    $row['customer'],
                    $row['order_status'],
                    $row['total'],
                    $row['currency_code'],
                    substr((string) $row['date_added'], 0, 10),
                    substr((string) $row['date_modified'], 0, 10),
                ];
            }, $rows)
        );

        return 0;
    }
}
