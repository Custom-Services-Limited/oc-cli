# Usage Examples

## Basic inspection

Run runtime-backed commands from a real OpenCart 3.x root:

```bash
oc core:version
oc core:check-requirements
oc product:list --limit=5
```

Run from outside the store by pointing at an OpenCart root:

```bash
oc core:version \
  --opencart-root=/var/www/opencart
```

Run DB-native commands from anywhere with direct DB flags:

```bash
oc db:info \
  --db-host=localhost \
  --db-user=oc_user \
  --db-pass=secret \
  --db-name=opencart
```

## Configuration

List settings:

```bash
oc core:config list
oc core:config list --format=json
```

Read and write values:

```bash
oc core:config get config_name
oc core:config set config_name "My Store"
oc core:config set config_maintenance 1
oc core:config set config_maintenance 0
```

Deprecated compatibility flag:

```bash
oc core:config list --admin
```

The command accepts the flag but warns that OpenCart settings are stored in shared rows.

## Database backups

Create backups:

```bash
oc db:backup
oc db:backup "backup_$(date +%Y%m%d_%H%M%S).sql"
oc db:backup nightly.sql --compress
oc db:backup products.sql --tables=oc_product,oc_product_description
```

Restore backups:

```bash
oc db:restore nightly.sql
oc db:restore nightly.sql --force
oc db:restore nightly.sql --ignore-errors
```

Maintenance commands:

```bash
oc db:check
oc db:repair oc_product
oc db:optimize product product_description
oc db:cleanup
```

Structured output for maintenance automation:

```bash
oc db:check --format=json | jq '.[].status'
oc db:cleanup --format=yaml
```

## Cache maintenance

These commands require a real OpenCart 3.x installation root.

Clear specific cache types:

```bash
oc cache:clear --type=data
oc cache:clear --type=theme
oc cache:clear --type=all
```

Refresh modification output and clear related caches:

```bash
oc cache:rebuild --type=modification
oc cache:rebuild --type=all
```

## Extensions and modifications

List enabled extension rows:

```bash
oc extension:list
oc extension:list payment
oc extension:list module --format=json
```

Enable and disable by identifier:

```bash
oc extension:enable payment:paypal
oc extension:disable payment:paypal
```

Disable by code when the code is unique:

```bash
oc extension:disable paypal
```

Import an OCMOD XML package:

```bash
oc extension:install ./upload.ocmod.xml
oc extension:install ./upload.ocmod.xml --activate
oc modification:list
```

## Categories

These commands require a real OpenCart 3.x installation root.

List categories:

```bash
oc category:list
oc category:list --name=Desktops
oc category:list --sort=name --order=desc --format=json
```

Create categories:

```bash
oc category:create "CLI Specials"
oc category:create "CLI Accessories" \
  --parent-id=20 \
  --description="Created by OC-CLI" \
  --keyword=cli-accessories \
  --store=0,1 \
  --status=enabled
```

## Product listing

Default listing:

```bash
oc product:list
```

Filter by status and limit:

```bash
oc product:list --status=enabled --limit=10
oc product:list --status=disabled --format=json
```

Search and category filtering:

```bash
oc product:list --search=iphone
oc product:list Desktops
oc product:list 20 --search=Mac
```

## Product creation

Basic creation:

```bash
oc product:create "Samsung Galaxy S21" "SGS21" "799.99"
```

Creation with metadata:

```bash
oc product:create "Samsung Galaxy S21" "SGS21" "799.99" \
  --description="Latest Samsung smartphone" \
  --category=Smartphones \
  --quantity=50 \
  --status=enabled \
  --weight=0.17 \
  --sku=SAMSUNG-SGS21 \
  --image=catalog/demo/samsung-galaxy-s21.jpg \
  --meta-title="Samsung Galaxy S21"
```

Interactive creation:

```bash
oc product:create --interactive
```

Structured output for automation:

```bash
oc product:create "API Product" "API-001" "99.99" --format=json
oc product:list --format=json | jq '.[].model'
```

## Product updates and deletion

Update a few fields:

```bash
oc product:update 100 --price=24.99 --quantity=12 --status=enabled
oc product:update 100 --name="CLI Product Updated" --description="Updated from OC-CLI"
```

Update assignment and inventory behavior:

```bash
oc product:update 100 \
  --category=20,24 \
  --sku=CLI-100 \
  --image=catalog/demo/cli-product.jpg \
  --subtract=1
```

Delete a product:

```bash
oc product:delete 100 --force
```

## Orders

These commands require a real OpenCart 3.x installation root.

List and inspect orders:

```bash
oc order:list
oc order:list --customer=John --status-id=1 --limit=10
oc order:view 1
oc order:view 1 --format=json | jq '.order.order_status_id'
```

Update order status:

```bash
oc order:update-status 1 Processing --comment="Validated by CLI" --notify
oc order:update-status 1 Complete --comment="Released for fulfillment" --override
```

## Admin users

These commands manage admin users, not storefront customers. They require a real OpenCart 3.x installation root.

List users:

```bash
oc user:list
oc user:list --status=enabled
oc user:list --group=1 --sort=date_added --order=desc --format=json
```

Create and delete users:

```bash
oc user:create cli-admin cli-admin@example.com 'StrongPass!123' \
  --firstname=CLI \
  --lastname=Admin \
  --group-id=1 \
  --status=enabled

oc user:delete cli-admin --force
```

## Maintenance snippets

Nightly backup:

```bash
0 2 * * * cd /var/www/opencart && /usr/bin/env oc db:backup "auto_backup_$(date +\%Y\%m\%d).sql" --compress
```

Requirements check:

```bash
oc core:check-requirements --format=yaml
```

Nightly maintenance:

```bash
0 3 * * * cd /var/www/opencart && /usr/bin/env oc cache:rebuild --type=all
15 3 * * * cd /var/www/opencart && /usr/bin/env oc db:cleanup
```
