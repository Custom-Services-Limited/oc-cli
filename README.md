# OC-CLI

OC-CLI is a command-line interface for OpenCart, built on Symfony Console. This repository currently ships a stable v1 surface of 13 OpenCart-focused commands for version checks, configuration, database maintenance, extensions, modifications, and basic product management.

Created by [Custom Services Limited](https://support.opencartgreece.gr/).

## Stable v1 scope

Stable commands in this repository:

- `core:version`
- `core:check-requirements`
- `core:config`
- `db:info`
- `db:backup`
- `db:restore`
- `extension:list`
- `extension:install`
- `extension:enable`
- `extension:disable`
- `modification:list`
- `product:list`
- `product:create`

Planned command families that are not part of the shipped v1 surface include broader product CRUD, order management, cache management, and user management.

## Requirements

- PHP 7.4 or newer
- Composer
- MySQL-compatible OpenCart database access
- An OpenCart installation, or direct database credentials passed on the command line

## Support notes

- The CLI reads OpenCart configuration from `config.php` and related installation files, or from direct database flags such as `--db-host` and `--db-name`.
- `core:config --admin` is deprecated. OpenCart stores configuration in shared rows, so the flag is accepted only for backward compatibility and has no effect.
- `extension:install` is limited to importing OCMOD XML packages into the `*_modification` table. It is not a generic marketplace/package installer.
- For unsupported schema/version combinations, extension commands now fail explicitly instead of guessing.

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

Run inside an OpenCart root:

```bash
oc core:version
oc core:check-requirements
oc product:list
```

Run from anywhere with direct DB credentials:

```bash
oc product:list \
  --db-host=localhost \
  --db-user=oc_user \
  --db-pass=secret \
  --db-name=opencart
```

## Common examples

Check versions:

```bash
oc core:version
oc core:version --opencart
```

Inspect configuration:

```bash
oc core:config list
oc core:config get config_name
oc core:config set config_maintenance 1
```

Back up and restore:

```bash
oc db:backup
oc db:backup nightly.sql --compress
oc db:restore nightly.sql --force
```

Manage enabled extension rows:

```bash
oc extension:list
oc extension:enable payment:paypal
oc extension:disable payment:paypal
```

Import an OCMOD XML file:

```bash
oc extension:install ./example.ocmod.xml --activate
oc modification:list
```

Work with products:

```bash
oc product:list --status=enabled --limit=10
oc product:list Desktops --format=json
oc product:create "Demo Product" "DEMO-001" "19.99" --quantity=5 --status=enabled
```

## Output formats

Commands that expose `--format` support:

- `table`
- `json`
- `yaml`

Example:

```bash
oc product:list --format=json
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
