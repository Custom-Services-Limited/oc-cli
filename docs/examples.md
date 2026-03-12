# Usage Examples

## Basic inspection

Run from an OpenCart root:

```bash
oc core:version
oc core:check-requirements
oc db:info
```

Run from outside the store with direct DB flags:

```bash
oc core:version \
  --opencart-root=/var/www/opencart
```

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
  --sku=SAMSUNG-SGS21
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

## Maintenance snippets

Nightly backup:

```bash
0 2 * * * cd /var/www/opencart && /usr/bin/env oc db:backup "auto_backup_$(date +\%Y\%m\%d).sql" --compress
```

Requirements check:

```bash
oc core:check-requirements --format=yaml
```
