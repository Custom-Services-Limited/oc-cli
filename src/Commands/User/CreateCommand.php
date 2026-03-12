<?php

namespace OpenCart\CLI\Commands\User;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:create')
            ->setDescription('Create an admin user')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Last name')
            ->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'User group ID', 1)
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'User status (enabled|disabled)', 'enabled')
            ->addOption('image', null, InputOption::VALUE_REQUIRED, 'User image path relative to image/');
    }

    protected function handle()
    {
        if (!$this->requireOpenCartThreeRuntime()) {
            return 1;
        }

        $username = trim((string) $this->input->getArgument('username'));
        $email = trim((string) $this->input->getArgument('email'));
        $password = (string) $this->input->getArgument('password');
        $firstName = trim((string) $this->input->getOption('firstname'));
        $lastName = trim((string) $this->input->getOption('lastname'));
        $status = $this->normaliseStatus($this->input->getOption('status'));

        if ($username === '' || $email === '' || $password === '' || $firstName === '' || $lastName === '') {
            $this->io->error('Username, email, password, firstname, and lastname are required.');
            return 1;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->io->error('A valid email address is required.');
            return 1;
        }

        if (!in_array($status, ['enabled', 'disabled'], true)) {
            $this->io->error('Status must be either "enabled" or "disabled".');
            return 1;
        }

        $userModel = $this->getAdminRuntime()->model('user/user');
        if ($userModel->getUserByUsername($username)) {
            $this->io->error("A user with username '{$username}' already exists.");
            return 1;
        }

        if ($userModel->getUserByEmail($email)) {
            $this->io->error("A user with email '{$email}' already exists.");
            return 1;
        }

        try {
            $userId = $userModel->addUser([
                'username' => $username,
                'user_group_id' => (int) $this->input->getOption('group-id'),
                'password' => $password,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
                'image' => (string) ($this->input->getOption('image') ?: ''),
                'status' => $status === 'enabled' ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            $this->io->error('Failed to create user: ' . $e->getMessage());
            return 1;
        }

        $this->io->success("User {$userId} created successfully.");
        return 0;
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
