# Development Guide

## Local setup

- PHP 7.4 or newer
- Composer
- Git

Clone and install:

```bash
git clone https://github.com/Custom-Services-Limited/oc-cli.git
cd oc-cli
composer install
```

Useful commands:

```bash
composer test
composer test:e2e
composer cs-check
composer analyze
php bin/oc list --raw
```

## Project structure

```text
bin/oc                  CLI entry point
src/Application.php     Command registration
src/Command.php         Shared OpenCart and DB helpers
src/Commands/           Shipped command implementations
src/Support/            Shared support helpers
tests/                  Unit and integration tests
docs/                   User-facing documentation
```

## Shipped command surface

The v1 branch stabilizes the commands registered in `src/Application.php`. Do not document or test unregistered roadmap commands as if they exist.

Current registered commands:

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

## Command conventions

- Extend `OpenCart\\CLI\\Command`.
- Put command registration in `src/Application.php`.
- Keep help text aligned with actual behavior.
- Prefer explicit capability checks for OpenCart schema/version differences.
- Fail fast for unsupported combinations instead of issuing ambiguous SQL errors.

Minimal command skeleton:

```php
<?php

namespace OpenCart\CLI\Commands\Custom;

use OpenCart\CLI\Command;
use Symfony\Component\Console\Input\InputArgument;

class ExampleCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('custom:example')
            ->setDescription('Example command')
            ->addArgument('name', InputArgument::REQUIRED, 'Example argument');
    }

    protected function handle()
    {
        if (!$this->requireOpenCart()) {
            return 1;
        }

        $db = $this->getDatabaseConnection();
        if (!$db) {
            $this->io->error('Could not connect to database.');
            return 1;
        }

        $this->io->success('Example command ran.');

        return 0;
    }
}
```

## OpenCart and DB helpers

The base command supports:

- OpenCart root detection via `--opencart-root` or upward directory search
- direct DB credentials via CLI options
- access to parsed OpenCart config through `getOpenCartConfig()`
- DB access through `getDatabaseConnection()`
- schema capability checks through shared helpers such as `tableExists()`

Use direct DB flags when a local `config.php` is not available:

```bash
php bin/oc product:list \
  --db-host=localhost \
  --db-user=oc_user \
  --db-pass=secret \
  --db-name=opencart
```

## Testing expectations

- Add unit tests for command wiring, validation, and unsupported-path handling.
- Add DB-backed success-path tests when behavior depends on SQL generation.
- Add or update the OpenCart 3.0.5.0 E2E harness when changes affect real installer, database, or binary behavior.
- Keep `composer test` free of risky tests and runtime deprecations.
- Keep `composer test:e2e` passing against the committed OpenCart fixture and its seeded demo data.
- Add smoke coverage for CLI entry points when runtime signatures or bootstrapping changes.

Run targeted suites:

```bash
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Integration
```

## Documentation expectations

- Build docs from the registered command list, not from roadmap intent.
- Keep examples consistent with actual arguments and options.
- Do not reintroduce `.oc-cli.yml` documentation unless runtime support is added.
- Treat `extension:install` as OCMOD XML import only unless the implementation changes.
