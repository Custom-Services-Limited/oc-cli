<?php

namespace OpenCart\CLI\Commands\User;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DeleteCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:delete')
            ->setDescription('Delete an admin user')
            ->addArgument('user', InputArgument::REQUIRED, 'User ID or username')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Delete without confirmation and bypass the last-admin guard'
            );
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $runtime = $this->getAdminRuntime();
        $userModel = $runtime->model('user/user');
        $identifier = (string) $this->input->getArgument('user');

        $user = is_numeric($identifier)
            ? $userModel->getUser((int) $identifier)
            : $userModel->getUserByUsername($identifier);

        if (!$user) {
            $this->io->error("User '{$identifier}' was not found.");
            return 1;
        }

        if (
            !$this->input->getOption('force')
            && $this->isProtectedLastAdministrator((int) $user['user_id'])
        ) {
            $this->io->error(
                'Refusing to delete the last enabled administrator-equivalent user. '
                . 'Use --force to override.'
            );
            return 1;
        }

        if (!$this->input->getOption('force') && !$this->io->confirm("Delete user {$user['username']}?", false)) {
            $this->io->warning('Deletion cancelled.');
            return 1;
        }

        try {
            $userModel->deleteUser((int) $user['user_id']);
        } catch (\Throwable $e) {
            $this->io->error('Failed to delete user: ' . $e->getMessage());
            return 1;
        }

        $this->io->success("User {$user['user_id']} deleted successfully.");
        return 0;
    }

    private function isProtectedLastAdministrator(int $userId): bool
    {
        $runtime = $this->getAdminRuntime();
        $db = $runtime->database();
        $prefix = $runtime->getDatabasePrefix();
        $query = $db->query(
            "SELECT COUNT(*) AS total FROM `" . $prefix . "user` u " .
            "LEFT JOIN `" . $prefix . "user_group` ug ON (u.user_group_id = ug.user_group_id) " .
            "WHERE u.status = '1' AND u.user_id <> '" . $userId . "' AND (" .
            "u.user_group_id = '1' OR ug.name = 'Administrator' OR ug.permission LIKE '%common/dashboard%')"
        );

        return (int) ($query->row['total'] ?? 0) === 0;
    }
}
