<?php

namespace OpenCart\CLI\Commands\Order;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class UpdateStatusCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('order:update-status')
            ->setDescription('Update the status of an order')
            ->addArgument('order-id', InputArgument::REQUIRED, 'Order ID')
            ->addArgument('status', InputArgument::REQUIRED, 'Status ID or exact current-language status name')
            ->addOption('comment', null, InputOption::VALUE_REQUIRED, 'Status history comment', '')
            ->addOption('notify', null, InputOption::VALUE_NONE, 'Mark the history entry as notified')
            ->addOption(
                'override',
                null,
                InputOption::VALUE_NONE,
                'Override fraud checks when moving into processing/complete states'
            );
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

        $catalogRuntime = $this->getCatalogRuntime();
        $orderModel = $catalogRuntime->model('checkout/order');
        $order = $orderModel->getOrder($orderId);
        if (!$order) {
            $this->io->error("Order {$orderId} was not found.");
            return 1;
        }

        $statusId = $this->resolveOrderStatusId((string) $this->input->getArgument('status'));
        if ($statusId === null) {
            $this->io->error('Could not resolve the requested order status.');
            return 1;
        }

        try {
            $catalogRuntime->registry()->get('load')->language('account/order');
            $orderModel->addOrderHistory(
                $orderId,
                $statusId,
                (string) $this->input->getOption('comment'),
                (bool) $this->input->getOption('notify'),
                (bool) $this->input->getOption('override')
            );
        } catch (\Throwable $e) {
            $this->io->error('Failed to update order status: ' . $e->getMessage());
            return 1;
        }

        $this->io->success("Order {$orderId} updated to status {$statusId}.");
        return 0;
    }

    private function resolveOrderStatusId(string $input): ?int
    {
        $runtime = $this->getCatalogRuntime();
        $db = $runtime->database();
        $prefix = $runtime->getDatabasePrefix();
        $languageId = (int) $runtime->registry()->get('config')->get('config_language_id');

        if (is_numeric($input)) {
            $query = $db->query(
                "SELECT order_status_id FROM `" . $prefix . "order_status` " .
                "WHERE order_status_id = '" . (int) $input . "' AND language_id = '" . $languageId . "'"
            );

            return !empty($query->row['order_status_id']) ? (int) $query->row['order_status_id'] : null;
        }

        $query = $db->query(
            "SELECT order_status_id FROM `" . $prefix . "order_status` " .
            "WHERE language_id = '" . $languageId . "' AND name = '" . $db->escape($input) . "'"
        );

        return !empty($query->row['order_status_id']) ? (int) $query->row['order_status_id'] : null;
    }
}
