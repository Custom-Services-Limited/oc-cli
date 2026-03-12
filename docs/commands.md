# Commands Reference

OC-CLI commands use the form:

```bash
oc <namespace>:<command> [options] [arguments]
```

Most commands support:

- `--opencart-root=/path/to/store`
- direct DB flags: `--db-host`, `--db-user`, `--db-pass`, `--db-name`, `--db-port`, `--db-prefix`, `--db-driver`
- `--format=table|json|yaml` on commands that emit structured output

Runtime-backed commands bootstrap OpenCart 3.x models and require a real OpenCart 3.x installation root. DB-native commands such as `db:info`, `db:backup`, `db:restore`, `db:check`, `db:repair`, `db:optimize`, and `db:cleanup` can run with direct DB credentials alone.

## Supported commands

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

- `--admin` is deprecated and has no effect.
- This command operates on shared settings rows, not separate catalog/admin scopes.

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

### `db:check`

Run table checks against specific tables or all OpenCart tables.

```bash
oc db:check
oc db:check product order
oc db:check oc_product oc_order --format=json
```

Arguments:

- `tables...` optional list of table names, with or without the configured prefix

Options:

- `--format`

### `db:repair`

Run table repair against specific tables or all OpenCart tables.

```bash
oc db:repair
oc db:repair oc_product_description
```

Arguments:

- `tables...` optional list of table names, with or without the configured prefix

Options:

- `--format`

### `db:optimize`

Run table optimization against specific tables or all OpenCart tables.

```bash
oc db:optimize
oc db:optimize product product_description order
```

Arguments:

- `tables...` optional list of table names, with or without the configured prefix

Options:

- `--format`

### `db:cleanup`

Delete only transient rows from `*_session`, `*_api_session`, and `*_customer_online`.

```bash
oc db:cleanup
oc db:cleanup --format=json
```

Options:

- `--format`

### `cache:clear`

Clear OpenCart cache artifacts. Requires a real OpenCart 3.x installation root.

```bash
oc cache:clear
oc cache:clear --type=data
oc cache:clear --type=theme
oc cache:clear --type=all
```

Options:

- `--type=data|theme|sass|all`

### `cache:rebuild`

Refresh the modification cache and optionally clear data, theme, and Sass cache artifacts. Requires a real OpenCart 3.x installation root.

```bash
oc cache:rebuild
oc cache:rebuild --type=modification
oc cache:rebuild --type=all
```

Options:

- `--type=modification|all`

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
oc extension:install ./example.ocmod.xml --activate
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

### `category:list`

List categories using the OpenCart admin category model. Requires a real OpenCart 3.x installation root.

```bash
oc category:list
oc category:list --name=Desktops
oc category:list --sort=name --order=desc --format=json
```

Options:

- `--name`
- `--sort=name|sort_order`
- `--order=asc|desc`
- `--page`
- `--limit`
- `--format`

### `category:create`

Create a category using the OpenCart admin category model. Requires a real OpenCart 3.x installation root.

```bash
oc category:create "CLI Specials"
oc category:create "Laptops / CLI" --parent-id=20 --description="Created from OC-CLI"
oc category:create "CLI Accessories" --store=0,1 --keyword=cli-accessories --status=enabled
```

Arguments:

- `name`

Options:

- `--parent-id`
- `--description`
- `--meta-title`
- `--status=enabled|disabled`
- `--sort-order`
- `--top`
- `--column`
- `--store`
- `--keyword`
- `--image`

### `product:list`

List products with filtering. Requires a real OpenCart 3.x installation root.

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

Create a product using positional arguments and optional metadata flags. Requires a real OpenCart 3.x installation root.

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
- `--image`
- `--meta-title`
- `--format`
- `--interactive`

### `product:update`

Update an existing product by loading the current OpenCart product payload, overlaying supported fields, and writing it back through the admin product model. Requires a real OpenCart 3.x installation root.

```bash
oc product:update 100 --price=24.99 --quantity=5
oc product:update 100 --name="Updated CLI Product" --description="Updated from the CLI"
oc product:update 100 --category=20,24 --status=enabled --image=catalog/demo/updated.jpg
```

Arguments:

- `product-id`

Options:

- `--name`
- `--model`
- `--price`
- `--quantity`
- `--status=enabled|disabled`
- `--sku`
- `--category`
- `--image`
- `--subtract=0|1`
- `--manufacturer-id`
- `--description`
- `--meta-title`

### `product:delete`

Delete a product through the OpenCart admin product model. Requires a real OpenCart 3.x installation root.

```bash
oc product:delete 100
oc product:delete 100 --force
```

Arguments:

- `product-id`

Options:

- `--force`

### `order:list`

List orders using the OpenCart admin order model. Requires a real OpenCart 3.x installation root.

```bash
oc order:list
oc order:list --customer=John --status-id=2 --limit=10
oc order:list --date-added=2026-03-01 --sort=total --order=desc --format=json
```

Options:

- `--id`
- `--customer`
- `--status-id`
- `--date-added`
- `--date-modified`
- `--total`
- `--sort=order_id|customer|order_status|date_added|date_modified|total`
- `--order=asc|desc`
- `--page`
- `--limit`
- `--format`

### `order:view`

Show one order, including products, totals, and recent history. Requires a real OpenCart 3.x installation root.

```bash
oc order:view 1
oc order:view 1 --format=json
```

Arguments:

- `order-id`

Options:

- `--format`

### `order:update-status`

Add order history and update an order status through the OpenCart checkout order model. Requires a real OpenCart 3.x installation root.

```bash
oc order:update-status 1 Processing
oc order:update-status 1 5 --comment="Approved from OC-CLI" --notify
oc order:update-status 1 Complete --comment="Released" --override
```

Arguments:

- `order-id`
- `status` as an integer ID or exact current-language order status name

Options:

- `--comment`
- `--notify`
- `--override`

### `user:list`

List admin users from the OpenCart admin user model. Requires a real OpenCart 3.x installation root.

```bash
oc user:list
oc user:list --status=enabled
oc user:list --group=1 --sort=date_added --order=desc --format=json
```

Options:

- `--group`
- `--status=enabled|disabled|all`
- `--sort=username|status|date_added`
- `--order=asc|desc`
- `--page`
- `--limit`
- `--format`

### `user:create`

Create an admin user through the OpenCart admin user model. Requires a real OpenCart 3.x installation root.

```bash
oc user:create cli-admin cli-admin@example.com 'StrongPass!123' --firstname=CLI --lastname=Admin
oc user:create merch-admin merch@example.com 'AnotherPass!123' --firstname=Merch --lastname=Team --group-id=1 --status=enabled
```

Arguments:

- `username`
- `email`
- `password`

Options:

- `--firstname`
- `--lastname`
- `--group-id`
- `--status=enabled|disabled`
- `--image`

### `user:delete`

Delete an admin user. The command protects the last enabled administrator-equivalent user unless `--force` is passed. Requires a real OpenCart 3.x installation root.

```bash
oc user:delete cli-admin
oc user:delete 7 --force
```

Arguments:

- `user` as a numeric user ID or exact username

Options:

- `--force`
