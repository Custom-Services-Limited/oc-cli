# Installation Guide

## Requirements

- PHP 7.4 or newer
- Composer
- MySQLi-enabled PHP runtime
- An OpenCart installation or direct DB credentials

## Install methods

Global Composer install:

```bash
composer global require custom-services-limited/oc-cli
```

Project-local install:

```bash
cd /path/to/project
composer require --dev custom-services-limited/oc-cli
```

Run the local binary:

```bash
./vendor/bin/oc list
```

Manual install from this repository:

```bash
git clone https://github.com/Custom-Services-Limited/oc-cli.git
cd oc-cli
composer install
chmod +x bin/oc
```

## Verify the binary

```bash
php bin/oc list --raw
```

Typical first checks:

```bash
php bin/oc core:version
php bin/oc core:check-requirements
```

## How connection discovery works

OC-CLI supports two connection modes.

OpenCart-root discovery:

```bash
cd /path/to/opencart
oc core:version
```

Direct database flags:

```bash
oc db:info \
  --db-host=localhost \
  --db-user=oc_user \
  --db-pass=secret \
  --db-name=opencart \
  --db-prefix=oc_
```

Available DB flags:

- `--db-host`
- `--db-user`
- `--db-pass`
- `--db-name`
- `--db-port`
- `--db-prefix`
- `--db-driver`

## Troubleshooting

If `oc` is not found:

- Check Composer's global bin dir with `composer global config bin-dir --absolute`.
- Use the full path to the installed binary.
- For repo-local use, run `php bin/oc ...`.

If OpenCart is not detected:

- Run from the OpenCart root, or pass `--opencart-root=/path/to/store`.
- Verify that `config.php` and the standard OpenCart directories are readable.

If database connection fails:

- Check the credentials in `config.php`, or pass direct DB flags.
- Confirm the PHP MySQLi extension is available.

## Current limitations

- There is no `.oc-cli.yml` runtime configuration file in v1.
- `extension:install` only imports OCMOD XML into the modification table.
- `core:config --admin` is deprecated and has no effect.
