# Contributing to OC-CLI

Thank you for your interest in contributing to OC-CLI! This document provides guidelines for contributing to the project.

**OC-CLI is created and maintained by [Custom Services Limited](https://support.opencartgreece.gr/) - Your OpenCart experts.**

## Development Setup

### Prerequisites

- PHP 7.0 or higher
- Composer
- Git
- OpenCart installation (for testing)

### Installation

1. Fork the repository
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

## Testing

OC-CLI has a comprehensive testing suite. All contributions must include appropriate tests.

### Running Tests

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration

# Run tests with coverage report
composer test:coverage

# Run tests for CI (with coverage in XML format)
composer test:ci
```

### Test Structure

```
tests/
├── Unit/                   # Unit tests for individual classes
├── Integration/            # Integration tests for command execution
├── Fixtures/              # Test data and fixtures
└── Helpers/               # Test helper utilities
```

### Writing Tests

#### Unit Tests

Unit tests should be placed in `tests/Unit/` and test individual classes in isolation:

```php
<?php
namespace OpenCart\CLI\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenCart\CLI\YourClass;

class YourClassTest extends TestCase
{
    public function testSomeFunctionality()
    {
        $instance = new YourClass();
        $result = $instance->someMethod();
        
        $this->assertEquals('expected', $result);
    }
}
```

#### Integration Tests

Integration tests should be placed in `tests/Integration/` and test command execution:

```php
<?php
namespace OpenCart\CLI\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use OpenCart\CLI\Commands\YourCommand;

class YourCommandTest extends TestCase
{
    public function testCommandExecution()
    {
        $application = new Application();
        $application->add(new YourCommand());

        $command = $application->find('your:command');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
```

#### Using Test Helpers

Use the `TestHelper` class for creating mock OpenCart installations:

```php
use OpenCart\CLI\Tests\Helpers\TestHelper;

// Create a temporary OpenCart installation
$tempDir = TestHelper::createTempOpenCartInstallation([
    'db_hostname' => 'localhost',
    'db_database' => 'test_db'
], '3.0.3.8');

// Clean up after test
TestHelper::cleanupTempDirectory($tempDir);
```

### Test Requirements

1. **Code Coverage**: Aim for at least 80% code coverage
2. **Test All Scenarios**: Include both success and failure cases
3. **Mock External Dependencies**: Don't rely on real databases or web services
4. **Clean Up**: Always clean up temporary files and directories
5. **Documentation**: Comment complex test scenarios

## Code Quality

### Code Style

We follow PSR-12 coding standards:

```bash
# Check code style
composer cs-check

# Fix code style issues
composer cs-fix
```

### Static Analysis

Run PHPStan for static analysis:

```bash
composer analyze
```

### Pre-commit Checklist

Before submitting a pull request, ensure:

- [ ] All tests pass (`composer test`)
- [ ] Code style is correct (`composer cs-check`)
- [ ] Static analysis passes (`composer analyze`)
- [ ] New functionality is properly tested
- [ ] Documentation is updated if needed

## Pull Request Process

1. **Create a Feature Branch**:
```bash
git checkout -b feature/your-feature-name
```

2. **Make Your Changes**:
   - Write your code
   - Add appropriate tests
   - Update documentation if needed

3. **Test Your Changes**:
```bash
composer test
composer cs-check
composer analyze
```

4. **Commit Your Changes**:
```bash
git add .
git commit -m "Add: Brief description of your changes"
```

5. **Push to Your Fork**:
```bash
git push origin feature/your-feature-name
```

6. **Create a Pull Request**:
   - Go to GitHub and create a pull request
   - Provide a clear description of your changes
   - Reference any related issues

## Continuous Integration

All pull requests are automatically tested using GitHub Actions on:

- **PHP Versions**: 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2, 8.3
- **Operating Systems**: Ubuntu, Windows, macOS
- **Code Quality**: PSR-12 compliance, PHPStan analysis
- **Security**: Dependency vulnerability scanning

Your changes must pass all CI checks before they can be merged.

## Command Development Guidelines

### Creating New Commands

1. **Command Structure**:
```php
<?php
namespace OpenCart\CLI\Commands\YourNamespace;

use OpenCart\CLI\Command;

class YourCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('namespace:command-name')
            ->setDescription('Command description')
            ->addArgument('argument', InputArgument::REQUIRED, 'Argument description')
            ->addOption('option', 'o', InputOption::VALUE_NONE, 'Option description');
    }

    protected function handle()
    {
        // Your command logic here
        return 0; // Success
    }
}
```

2. **Register Your Command** in `src/Application.php`:
```php
protected function getDefaultCommands()
{
    $commands = parent::getDefaultCommands();
    $commands[] = new YourCommand();
    return $commands;
}
```

3. **Error Handling**:
```php
protected function handle()
{
    try {
        // Command logic
        $this->io->success('Operation completed successfully!');
        return 0;
    } catch (\Exception $e) {
        $this->io->error('Error: ' . $e->getMessage());
        return 1;
    }
}
```

### Command Naming Conventions

- Use `namespace:action` format (e.g., `product:list`, `user:create`)
- Use lowercase with hyphens for multi-word commands
- Group related commands under the same namespace
- Provide aliases for commonly used commands

### Output Formatting

Support multiple output formats when appropriate:

```php
$format = $this->input->getOption('format');

switch ($format) {
    case 'json':
        $this->output->writeln(json_encode($data, JSON_PRETTY_PRINT));
        break;
    case 'yaml':
        // YAML output
        break;
    default:
        $this->io->table(['Column1', 'Column2'], $rows);
        break;
}
```

## Documentation

### Updating Documentation

When adding new commands or features:

1. Update `docs/commands.md` with command documentation
2. Add usage examples to `docs/examples.md`
3. Update `README.md` if adding major features
4. Include inline code documentation

### Documentation Standards

- Use clear, concise language
- Provide practical examples
- Include both basic and advanced usage
- Document all command options and arguments

## Security Guidelines

1. **Input Validation**: Always validate and sanitize user input
2. **Database Queries**: Use prepared statements
3. **File Operations**: Validate file paths to prevent directory traversal
4. **Sensitive Data**: Never log passwords or sensitive information
5. **Dependencies**: Keep dependencies up to date

## Performance Considerations

1. **Database Queries**: Use efficient queries with proper indexing
2. **Memory Usage**: Implement pagination for large datasets
3. **Caching**: Cache expensive operations when appropriate
4. **File I/O**: Minimize file system operations

## Getting Help

- **Issues**: [GitHub Issues](https://github.com/Custom-Services-Limited/oc-cli/issues)
- **Professional Support**: [Custom Services Limited](https://support.opencartgreece.gr/)

## License

By contributing to OC-CLI, you agree that your contributions will be licensed under the GPL v3 license.

## Recognition

Contributors will be recognized in the project's acknowledgments. Significant contributions may result in being added to the authors list.

Thank you for contributing to OC-CLI!