# Commands Reference

OC-CLI commands use the form:

```bash
oc <namespace>:<command> [options] [arguments]
```

Most commands support:

- `--opencart-root=/path/to/store`
- direct DB flags: `--db-host`, `--db-user`, `--db-pass`, `--db-name`, `--db-port`, `--db-prefix`, `--db-driver`
- `--format=table|json|yaml` on commands that emit structured output

## Stable v1 commands

### `core:version`

Show OC-CLI and OpenCart version information.

```bash
oc core:version
oc core:version --opencart
oc core:version --format=json
```

Options:

- `--opencart`
- `--format`

### `core:check-requirements`

Check the local runtime and OpenCart installation requirements.

```bash
oc core:check-requirements
oc core:check-requirements --format=yaml
```

Options:

- `--format`

### `core:config`

Read and update configuration values stored in the shared OpenCart settings table.

```bash
oc core:config list
oc core:config get config_name
oc core:config set config_maintenance 1
oc core:config list --format=json
```

Arguments:

- `action` with `list` as the default
- `key`
- `value`

Options:

- `--format`
- `--admin`

Notes:

- `--admin` is deprecated and has no effect in v1.
- This command operates on shared settings rows, not separate catalog/admin stores.

### `db:info`

Show the resolved database connection details.

```bash
oc db:info
oc db:info --format=json
```

Options:

- `--format`

### `db:backup`

Create a SQL backup.

```bash
oc db:backup
oc db:backup nightly.sql
oc db:backup nightly.sql --compress
oc db:backup products.sql --tables=oc_product,oc_product_description
```

Arguments:

- `filename` optional

Options:

- `--compress`
- `--tables`
- `--output-dir`

### `db:restore`

Restore a SQL backup.

```bash
oc db:restore nightly.sql
oc db:restore nightly.sql --force
oc db:restore nightly.sql --ignore-errors
```

Arguments:

- `filename` required

Options:

- `--force`
- `--ignore-errors`

### `extension:list`

List enabled rows from the OpenCart extension table.

```bash
oc extension:list
oc extension:list payment
oc extension:list --format=json
```

Arguments:

- `type` optional, for example `payment`, `shipping`, or `module`

Options:

- `--format`

### `extension:install`

Import an OCMOD XML package into the modification table.

```bash
oc extension:install ./example.ocmod.xml
oc extension:install ./example.ocmod --activate
```

Arguments:

- `extension` required path to `.xml` or `.ocmod`

Options:

- `--activate`

Notes:

- This is not a generic marketplace installer.
- Unsupported package/version combinations fail explicitly.

### `extension:enable`

Insert an enabled extension row.

```bash
oc extension:enable payment:paypal
oc extension:enable shipping:flat
```

Arguments:

- `extension` required

Notes:

- Use `type:code` when enabling a disabled extension that is not already present.

### `extension:disable`

Delete an enabled extension row.

```bash
oc extension:disable payment:paypal
oc extension:disable paypal
```

Arguments:

- `extension` required

Notes:

- Use `type:code` when multiple rows share the same code.

### `modification:list`

List installed modifications.

```bash
oc modification:list
oc modification:list --format=yaml
```

Options:

- `--format`

### `product:list`

List products with filtering.

```bash
oc product:list
oc product:list --status=enabled --limit=10
oc product:list Desktops --search=Mac
oc product:list 20 --format=json
```

Arguments:

- `category` optional category name or ID

Options:

- `--format`
- `--status=enabled|disabled|all`
- `--limit`
- `--search`

### `product:create`

Create a product using positional arguments and optional metadata flags.

```bash
oc product:create "Demo Product" "DEMO-001" "19.99"
oc product:create "Demo Product" "DEMO-001" "19.99" --quantity=5 --status=enabled
oc product:create "Demo Product" "DEMO-001" "19.99" --category=Desktops --format=json
oc product:create --interactive
```

Arguments:

- `name`
- `model`
- `price`

Options:

- `--description`
- `--category`
- `--quantity`
- `--status`
- `--weight`
- `--sku`
- `--format`
- `--interactive`

Notes:

- `--status` accepts `enabled` or `disabled`. Numeric aliases are normalized internally for compatibility.
- If `--sku` is omitted, the command defaults it to the model value.

## Planned, not shipped in v1

These commands are intentionally not documented as available because they are not registered in the application:

- broader product CRUD such as `product:update` and `product:delete`
- order management commands
- cache management commands
- user management commands
- category commands
