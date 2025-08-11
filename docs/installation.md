# Installation Guide

This guide covers different methods to install OC-CLI on your system.

**OC-CLI is created and maintained by [Custom Services Limited](https://support.opencartgreece.gr/) - Your OpenCart experts.**

## System Requirements

- PHP 7.0 or higher
- OpenCart 2.x, 3.x, or 4.x installation
- MySQL/MySQLi extension for PHP
- Composer (recommended for installation)

## Installation Methods

### Method 1: Composer Global Installation (Recommended)

This is the easiest way to install OC-CLI and makes it available system-wide.

```bash
composer global require custom-services-limited/oc-cli
```

Make sure your global Composer vendor binaries directory is in your PATH. You can check this with:

```bash
composer global config bin-dir --absolute
```

Add the returned path to your shell's PATH if it's not already there.

### Method 2: Per-Project Installation

Install OC-CLI as a development dependency in your OpenCart project:

```bash
cd /path/to/your/opencart
composer require --dev custom-services-limited/oc-cli
```

Then run commands using:

```bash
./vendor/bin/oc command:name
```

### Method 3: Manual Installation

1. Clone the repository:
```bash
git clone https://github.com/Custom-Services-Limited/oc-cli.git
cd oc-cli
```

2. Install dependencies:
```bash
composer install
```

3. Make the binary executable:
```bash
chmod +x bin/oc
```

4. (Optional) Add to your PATH:
```bash
echo 'export PATH="$PATH:'$(pwd)'/bin"' >> ~/.bashrc
source ~/.bashrc
```

### Method 4: Download Phar (Coming Soon)

We plan to provide a downloadable Phar file for easy installation without Composer.

## Verification

After installation, verify that OC-CLI is working correctly:

```bash
oc --version
```

You should see output similar to:
```
OC-CLI version 1.0.0
```

## First Steps

1. Navigate to your OpenCart installation:
```bash
cd /path/to/your/opencart
```

2. Check if OC-CLI detects your installation:
```bash
oc core:version
```

3. List all available commands:
```bash
oc list
```

## Troubleshooting

### Command Not Found

If you get a "command not found" error:

1. Check that Composer's global bin directory is in your PATH
2. Try running with the full path: `/path/to/composer/vendor/bin/oc`
3. For manual installation, check that the `bin/oc` file is executable

### PHP Version Issues

If you get PHP version errors:

1. Check your PHP version: `php --version`
2. Ensure you have PHP 7.0 or higher
3. On some systems, you might need to use `php7` or `php8` instead of `php`

### OpenCart Detection Issues

If OC-CLI cannot detect your OpenCart installation:

1. Make sure you're in the OpenCart root directory
2. Check that essential files exist: `config.php`, `system/startup.php`
3. Verify file permissions allow reading these files

### Database Connection Issues

If you get database connection errors:

1. Check your `config.php` file for correct database credentials
2. Ensure your database server is running
3. Verify that the PHP MySQLi extension is installed

## Next Steps

- Read the [Commands Reference](commands.md) to learn about available commands
- Check out [Usage Examples](examples.md) for common tasks
- See [Development Guide](development.md) if you want to contribute