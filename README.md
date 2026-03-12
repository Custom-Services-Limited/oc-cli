# OC-CLI

OC-CLI is a Symfony Console CLI for OpenCart. The current workspace ships a stable command surface for store inspection, configuration, database operations, extensions, modifications, products, categories, orders, cache maintenance, and admin-user management.

Created by [Custom Services Limited](https://support.opencartgreece.gr/).

## Stable command surface

Registered OpenCart-focused commands in this repository:

### Core

- `core:version`
- `core:check-requirements`
- `core:config`

### Database

- `db:info`
- `db:backup`
- `db:restore`
- `db:check`
- `db:repair`
- `db:optimize`
- `db:cleanup`

### Cache

- `cache:clear`
- `cache:rebuild`

### Extensions and modifications

- `extension:list`
- `extension:install`
- `extension:enable`
- `extension:disable`
- `modification:list`

### Catalog

- `category:list`
- `category:create`
- `product:list`
- `product:create`
- `product:update`
- `product:delete`

### Orders

- `order:list`
- `order:view`
- `order:update-status`

### Admin users

- `user:list`
- `user:create`
- `user:delete`

## Requirements

- PHP 7.4 or newer
- Composer
- MySQL or MariaDB access for the target OpenCart database
- A real OpenCart installation root for runtime-backed commands

## Support notes

- OC-CLI reads OpenCart connection details from `config.php` and related installation files, or from direct DB flags such as `--db-host`, `--db-user`, and `--db-name`.
- Runtime-backed commands bootstrap real OpenCart 3.x models and cache paths. These commands require `--opencart-root=/path/to/opencart` or execution from inside a real OpenCart 3.x installation root:
  - `cache:*`
  - `category:*`
  - `order:*`
  - `product:*`
  - `user:*`
- `core:config --admin` is deprecated. OpenCart stores settings in shared rows, so the flag is accepted only for backward compatibility and has no effect.
- `extension:install` imports OCMOD XML packages into the `*_modification` table. It is not a generic marketplace or ZIP installer.
- Unsupported schema or version combinations fail explicitly instead of falling back to guessed SQL behavior.

## Installation

Global install:

```bash
composer global require custom-services-limited/oc-cli
```

Project-local install:

```bash
composer require --dev custom-services-limited/oc-cli
```

From this repository:

```bash
git clone https://github.com/Custom-Services-Limited/oc-cli.git
cd oc-cli
composer install
chmod +x bin/oc
```

## Quick start

Run inside a real OpenCart 3.x root:

```bash
oc core:version
oc core:check-requirements
oc product:list --limit=5
```

Run DB-native commands from anywhere with direct DB credentials:

```bash
oc db:info \
  --db-host=localhost \
  --db-user=oc_user \
  --db-pass=secret \
  --db-name=opencart
```

Run runtime-backed commands from anywhere by pointing at a store root:

```bash
oc product:list --opencart-root=/var/www/opencart --status=enabled --limit=10
```

## Common examples

Version and configuration:

```bash
oc core:version --opencart
oc core:config list
oc core:config get config_name
oc core:config set config_maintenance 1
```

Database maintenance:

```bash
oc db:backup nightly.sql --compress
oc db:check
oc db:optimize oc_product oc_order
oc db:cleanup
```

Extensions and modifications:

```bash
oc extension:list payment
oc extension:disable shipping:flat
oc extension:enable shipping:flat
oc extension:install ./build/test.ocmod.xml --activate
oc modification:list
```

Categories and products:

```bash
oc category:list --name=Desktops
oc category:create "CLI Specials" --parent-id=20 --keyword=cli-specials
oc product:create "CLI Demo Product" "CLI-DEMO-001" "19.99" --category=20 --status=enabled
oc product:update 100 --price=24.99 --quantity=8 --status=enabled
oc product:delete 100 --force
```

Orders:

```bash
oc order:list --customer=John --limit=10
oc order:view 1
oc order:update-status 1 Processing --comment="Approved from CLI" --notify
```

Cache and admin users:

```bash
oc cache:clear --type=all
oc cache:rebuild --type=all
oc user:list --status=enabled
oc user:create cli-admin cli-admin@example.com 'StrongPass!123' --firstname=CLI --lastname=Admin
oc user:delete cli-admin --force
```

## Output formats

Commands that expose `--format` support:

- `table`
- `json`
- `yaml`

Example:

```bash
oc order:list --format=json
```

## Development

Useful checks:

```bash
composer test
composer test:e2e
composer cs-check
composer analyze
```

Additional documentation:

- [Installation guide](docs/installation.md)
- [Commands reference](docs/commands.md)
- [Examples](docs/examples.md)
- [Development guide](docs/development.md)
- [Database schema notes](docs/database-schema.md)
