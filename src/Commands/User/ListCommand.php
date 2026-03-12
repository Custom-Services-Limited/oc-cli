<?php

namespace OpenCart\CLI\Commands\User;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:list')
            ->setDescription('List admin users')
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Filter by user group ID')
            ->addOption(
                'status',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter by status (enabled|disabled|all)',
                'all'
            )
            ->addOption(
                'sort',
                null,
                InputOption::VALUE_REQUIRED,
                'Sort field (username|status|date_added)',
                'username'
            )
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

        $status = $this->normaliseStatusFilter($this->input->getOption('status'));
        if (!in_array($status, ['enabled', 'disabled', 'all'], true)) {
            $this->io->error('Status must be one of: enabled, disabled, all.');
            return 1;
        }

        $sort = (string) $this->input->getOption('sort');
        $order = strtoupper((string) $this->input->getOption('order'));
        if (!in_array($sort, ['username', 'status', 'date_added'], true) || !in_array($order, ['ASC', 'DESC'], true)) {
            $this->io->error('Invalid sort or order option.');
            return 1;
        }

        $page = max(1, (int) $this->input->getOption('page'));
        $limit = max(1, (int) $this->input->getOption('limit'));
        $groupId = $this->input->getOption('group');

        $users = $this->getAdminRuntime()->model('user/user')->getUsers([
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);

        $filtered = array_values(array_filter($users, function (array $user) use ($status, $groupId): bool {
            if ($status !== 'all' && (int) $user['status'] !== ($status === 'enabled' ? 1 : 0)) {
                return false;
            }

            if ($groupId !== null && $groupId !== '' && (int) $user['user_group_id'] !== (int) $groupId) {
                return false;
            }

            return true;
        }));

        $rows = array_map(function (array $user): array {
            return [
                'user_id' => (int) $user['user_id'],
                'username' => $user['username'],
                'user_group_id' => (int) $user['user_group_id'],
                'status' => (int) $user['status'] === 1 ? 'enabled' : 'disabled',
                'date_added' => $user['date_added'],
            ];
        }, $filtered);

        if (empty($rows)) {
            $this->io->warning('No users found matching the criteria.');
            return 0;
        }

        $format = (string) $this->input->getOption('format');
        if ($format === 'json') {
            $this->io->writeln(json_encode($rows, JSON_PRETTY_PRINT));
            return 0;
        }

        if ($format === 'yaml') {
            foreach ($rows as $index => $row) {
                $this->io->writeln("- user_{$index}:");
                foreach ($row as $key => $value) {
                    $this->io->writeln("    {$key}: {$value}");
                }
            }
            return 0;
        }

        $this->io->title('Admin Users');
        $this->io->table(
            ['ID', 'Username', 'Group', 'Status', 'Date Added'],
            array_map(function (array $row): array {
                return [
                    $row['user_id'],
                    $row['username'],
                    $row['user_group_id'],
                    $row['status'],
                    substr((string) $row['date_added'], 0, 10),
                ];
            }, $rows)
        );

        return 0;
    }

    /**
     * @param mixed $status
     */
    private function normaliseStatusFilter($status): string
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
