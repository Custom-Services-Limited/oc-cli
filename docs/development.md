# Development Guide

This guide helps developers contribute to OC-CLI or create custom commands.

**OC-CLI is created and maintained by [Custom Services Limited](https://support.opencartgreece.gr/) - Your OpenCart experts.**

## Development Setup

### Prerequisites

- PHP 7.0 or higher
- Composer
- Git
- OpenCart installation for testing

### Setup Development Environment

1. Fork the repository on GitHub
2. Clone your fork:
```bash
git clone https://github.com/yourusername/oc-cli.git
cd oc-cli
```

3. Install dependencies:
```bash
composer install
```

4. Make the binary executable:
```bash
chmod +x bin/oc
```

5. Run tests to ensure everything works:
```bash
composer test
```

## Project Structure

```
oc-cli/
├── bin/                    # Executable files
│   └── oc                  # Main CLI entry point
├── src/                    # Source code
│   ├── Application.php     # Main application class
│   ├── Command.php         # Base command class
│   ├── Commands/           # Command implementations
│   │   ├── Core/          # Core commands
│   │   ├── Product/       # Product management
│   │   ├── Order/         # Order management
│   │   ├── Extension/     # Extension management
│   │   └── Database/      # Database operations
│   └── Utils/             # Utility classes
├── tests/                  # Test files
├── docs/                   # Documentation
├── config/                 # Configuration files
└── templates/              # Code templates
```

## Creating Custom Commands

### Basic Command Structure

Create a new command by extending the base `Command` class:

```php
<?php

namespace OpenCart\CLI\Commands\Custom;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ExampleCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('custom:example')
            ->setDescription('An example custom command')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name argument'
            )
            ->addOption(
                'uppercase',
                'u',
                InputOption::VALUE_NONE,
                'Convert output to uppercase'
            );
    }

    protected function handle()
    {
        $name = $this->input->getArgument('name');
        $uppercase = $this->input->getOption('uppercase');

        if ($uppercase) {
            $name = strtoupper($name);
        }

        $this->io->success("Hello, {$name}!");

        return 0;
    }
}
```

### Register Custom Commands

Add your command to the Application class in `src/Application.php`:

```php
protected function getDefaultCommands()
{
    $commands = parent::getDefaultCommands();
    
    // Add your custom command
    $commands[] = new \OpenCart\CLI\Commands\Custom\ExampleCommand();

    return $commands;
}
```

### Command Naming Conventions

- Use namespace:command format (e.g., `product:list`, `user:create`)
- Use lowercase with hyphens for multi-word commands
- Group related commands under the same namespace
- Provide aliases for commonly used commands

### Working with OpenCart Data

The base `Command` class provides helper methods:

```php
// Check if we're in an OpenCart installation
if (!$this->requireOpenCart()) {
    return 1;
}

// Get OpenCart configuration
$config = $this->getOpenCartConfig();

// Get database connection
$connection = $this->getDatabaseConnection();

// Execute database queries
$result = $this->query("SELECT * FROM {$config['db_prefix']}product LIMIT 10");
```

### Input and Output

Use Symfony Console's input/output features:

```php
// Get arguments and options
$productId = $this->input->getArgument('product_id');
$format = $this->input->getOption('format');

// Output styles
$this->io->success('Operation completed successfully!');
$this->io->error('Something went wrong!');
$this->io->warning('This is a warning message.');
$this->io->note('Additional information.');

// Tables
$this->io->table(['ID', 'Name', 'Price'], $rows);

// Progress bars
$progressBar = $this->io->createProgressBar(count($items));
foreach ($items as $item) {
    // Process item
    $progressBar->advance();
}
$progressBar->finish();
```

### Error Handling

Always handle errors gracefully:

```php
protected function handle()
{
    try {
        // Your command logic here
        
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $connection = $this->getDatabaseConnection();
        if (!$connection) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        // Success
        return 0;
        
    } catch (\Exception $e) {
        $this->io->error('An error occurred: ' . $e->getMessage());
        return 1;
    }
}
```

## Testing

### Writing Tests

Create test files in the `tests/` directory:

```php
<?php

namespace OpenCart\CLI\Tests\Commands;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use OpenCart\CLI\Commands\Core\VersionCommand;

class VersionCommandTest extends TestCase
{
    public function testVersionCommand()
    {
        $application = new Application();
        $application->add(new VersionCommand());

        $command = $application->find('core:version');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('OC-CLI', $commandTester->getDisplay());
    }
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test
./vendor/bin/phpunit tests/Commands/VersionCommandTest.php

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Code Standards

### PHP CodeSniffer

We use PHP CodeSniffer to maintain code quality:

```bash
# Check code style
composer cs-check

# Fix code style issues
composer cs-fix
```

### Coding Standards

- Follow PSR-4 autoloading standards
- Use PSR-2 coding style
- Write meaningful variable and method names
- Add docblocks for all public methods
- Keep methods focused and small
- Use type hints where possible (PHP 7.0+ compatible)

### Example of Good Code Style

```php
<?php

namespace OpenCart\CLI\Commands\Product;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * List products command
 */
class ListCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('product:list')
            ->setDescription('List products in the store')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit number of results',
                10
            );
    }

    /**
     * Handle the command execution
     *
     * @return int
     */
    protected function handle()
    {
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $limit = (int) $this->input->getOption('limit');
        $products = $this->getProducts($limit);

        $this->displayProducts($products);

        return 0;
    }

    /**
     * Get products from database
     *
     * @param int $limit
     * @return array
     */
    private function getProducts($limit)
    {
        $config = $this->getOpenCartConfig();
        $sql = "SELECT product_id, name, model, price, status 
                FROM {$config['db_prefix']}product_description pd
                LEFT JOIN {$config['db_prefix']}product p ON pd.product_id = p.product_id
                WHERE pd.language_id = 1
                LIMIT ?";

        $result = $this->query($sql, [$limit]);
        
        if (!$result) {
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Display products in table format
     *
     * @param array $products
     */
    private function displayProducts(array $products)
    {
        if (empty($products)) {
            $this->io->warning('No products found.');
            return;
        }

        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                $product['product_id'],
                $product['name'],
                $product['model'],
                $product['price'],
                $product['status'] ? 'Enabled' : 'Disabled'
            ];
        }

        $this->io->table(['ID', 'Name', 'Model', 'Price', 'Status'], $rows);
    }
}
```

## Contributing

### Pull Request Process

1. Create a feature branch:
```bash
git checkout -b feature/my-new-feature
```

2. Make your changes and commit:
```bash
git add .
git commit -m "Add new feature: description"
```

3. Run tests and code style checks:
```bash
composer test
composer cs-check
```

4. Push to your fork:
```bash
git push origin feature/my-new-feature
```

5. Create a Pull Request on GitHub

### Commit Message Guidelines

- Use present tense ("Add feature" not "Added feature")
- Use imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters or less
- Reference issues and pull requests liberally after the first line

### Code Review

All contributions require code review. Please:

- Ensure your code follows the project's coding standards
- Add tests for new functionality
- Update documentation as needed
- Respond promptly to review feedback

## Debugging

### Debug Mode

Enable debug mode for verbose output:

```bash
oc command:name --verbose
```

### Logging

Add logging to your commands:

```php
if ($this->output->isVerbose()) {
    $this->io->note('Debug: Processing item ' . $item['id']);
}
```

### Common Issues

1. **OpenCart not detected**: Check file permissions and directory structure
2. **Database connection fails**: Verify config.php settings
3. **Command not found**: Ensure proper autoloading and registration

## Performance Considerations

- Use database indexes for queries
- Implement pagination for large datasets
- Cache expensive operations when possible
- Use prepared statements for database queries
- Avoid loading unnecessary data

## Security

- Always validate and sanitize input
- Use prepared statements for database queries
- Don't log sensitive information
- Follow OpenCart's security practices
- Validate file paths to prevent directory traversal