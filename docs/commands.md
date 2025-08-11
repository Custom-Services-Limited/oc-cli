# Commands Reference

This document provides a comprehensive reference for all OC-CLI commands.

**OC-CLI is created and maintained by [Custom Services Limited](https://support.opencartgreece.gr/) - Your OpenCart experts.**

## Command Structure

OC-CLI commands follow this general structure:
```
oc <namespace>:<command> [options] [arguments]
```

For example:
```bash
oc product:list --limit=10 --format=json
```

## Global Options

Most commands support these global options:

- `--format=<format>` - Output format: `table` (default), `json`, `yaml`
- `--quiet` - Suppress output
- `--verbose` - Increase verbosity
- `--help` - Show help for the command

## Core Commands

### core:version
Display version information for OC-CLI and OpenCart.

**Usage:**
```bash
oc core:version [options]
```

**Options:**
- `--opencart, -o` - Show only OpenCart version
- `--format=<format>` - Output format

**Examples:**
```bash
# Show all version information
oc core:version

# Show only OpenCart version
oc core:version --opencart

# JSON output
oc core:version --format=json
```

### core:check-requirements
Check system requirements for OpenCart and OC-CLI.

**Usage:**
```bash
oc core:check-requirements
```

**Examples:**
```bash
oc core:check-requirements
```

### core:config
Manage OpenCart configuration settings.

**Usage:**
```bash
oc core:config <action> [key] [value]
```

**Actions:**
- `get` - Get configuration value
- `set` - Set configuration value
- `list` - List all configuration

**Examples:**
```bash
# Get a configuration value
oc core:config get config_name

# Set a configuration value
oc core:config set config_name "new value"

# List all configuration
oc core:config list
```

## Database Commands

### db:info
Display database connection information.

**Usage:**
```bash
oc db:info
```

### db:backup
Create a database backup.

**Usage:**
```bash
oc db:backup [filename] [options]
```

**Options:**
- `--compress` - Compress the backup using gzip
- `--tables=<tables>` - Backup specific tables only

**Examples:**
```bash
# Create backup with auto-generated filename
oc db:backup

# Create backup with specific filename
oc db:backup my-backup.sql

# Compressed backup
oc db:backup --compress
```

### db:restore
Restore database from backup.

**Usage:**
```bash
oc db:restore <filename> [options]
```

**Options:**
- `--force` - Skip confirmation prompt

**Examples:**
```bash
# Restore from backup
oc db:restore backup.sql

# Force restore without confirmation
oc db:restore backup.sql --force
```

## Product Commands

### product:list
List products in the store.

**Usage:**
```bash
oc product:list [options]
```

**Options:**
- `--limit=<number>` - Limit number of results
- `--status=<status>` - Filter by status (enabled/disabled)
- `--search=<term>` - Search products

**Examples:**
```bash
# List all products
oc product:list

# List first 10 products
oc product:list --limit=10

# Search for products
oc product:list --search="phone"
```

### product:create
Create a new product.

**Usage:**
```bash
oc product:create [options]
```

**Options:**
- `--name=<name>` - Product name (required)
- `--model=<model>` - Product model (required)
- `--price=<price>` - Product price
- `--description=<description>` - Product description
- `--status=<status>` - Product status (1 for enabled, 0 for disabled)

**Examples:**
```bash
# Create a basic product
oc product:create --name="New Product" --model="NP001" --price=29.99

# Create product with description
oc product:create --name="New Product" --model="NP001" --price=29.99 --description="Product description"
```

### product:update
Update an existing product.

**Usage:**
```bash
oc product:update <product_id> [options]
```

**Options:**
- `--name=<name>` - Product name
- `--model=<model>` - Product model
- `--price=<price>` - Product price
- `--description=<description>` - Product description
- `--status=<status>` - Product status

**Examples:**
```bash
# Update product name
oc product:update 1 --name="Updated Product Name"

# Update multiple fields
oc product:update 1 --name="New Name" --price=39.99
```

### product:delete
Delete a product.

**Usage:**
```bash
oc product:delete <product_id> [options]
```

**Options:**
- `--force` - Skip confirmation prompt

**Examples:**
```bash
# Delete product with confirmation
oc product:delete 1

# Force delete without confirmation
oc product:delete 1 --force
```

## Category Commands

### category:list
List product categories.

**Usage:**
```bash
oc category:list [options]
```

**Options:**
- `--parent=<id>` - Show categories under specific parent
- `--level=<number>` - Show categories at specific level

### category:create
Create a new category.

**Usage:**
```bash
oc category:create [options]
```

**Options:**
- `--name=<name>` - Category name (required)
- `--parent=<id>` - Parent category ID
- `--description=<description>` - Category description

## Order Commands

### order:list
List orders in the store.

**Usage:**
```bash
oc order:list [options]
```

**Options:**
- `--status=<status>` - Filter by order status
- `--limit=<number>` - Limit number of results
- `--customer=<customer_id>` - Filter by customer

### order:view
View detailed information about an order.

**Usage:**
```bash
oc order:view <order_id>
```

### order:update-status
Update order status.

**Usage:**
```bash
oc order:update-status <order_id> <status_id> [options]
```

**Options:**
- `--comment=<comment>` - Add comment to status update
- `--notify` - Notify customer of status change

## Extension Commands

### extension:list
List installed extensions.

**Usage:**
```bash
oc extension:list [type]
```

**Arguments:**
- `type` - Extension type (module, payment, shipping, etc.)

### extension:install
Install an extension.

**Usage:**
```bash
oc extension:install <extension> [options]
```

**Options:**
- `--activate` - Activate after installation

### extension:enable
Enable an extension.

**Usage:**
```bash
oc extension:enable <extension>
```

### extension:disable
Disable an extension.

**Usage:**
```bash
oc extension:disable <extension>
```

## Cache Commands

### cache:clear
Clear OpenCart caches.

**Usage:**
```bash
oc cache:clear [type]
```

**Arguments:**
- `type` - Cache type to clear (image, modification, etc.)

### cache:rebuild
Rebuild OpenCart caches.

**Usage:**
```bash
oc cache:rebuild
```

## User Commands

### user:list
List admin users.

**Usage:**
```bash
oc user:list
```

### user:create
Create a new admin user.

**Usage:**
```bash
oc user:create [options]
```

**Options:**
- `--username=<username>` - Username (required)
- `--password=<password>` - Password (required)
- `--email=<email>` - Email address (required)
- `--firstname=<name>` - First name
- `--lastname=<name>` - Last name

### user:delete
Delete an admin user.

**Usage:**
```bash
oc user:delete <user_id> [options]
```

**Options:**
- `--force` - Skip confirmation prompt

## Output Formats

### Table Format (Default)
```bash
oc product:list
+----+-------------+-------+-------+
| ID | Name        | Model | Price |
+----+-------------+-------+-------+
| 1  | Product One | P001  | 29.99 |
| 2  | Product Two | P002  | 39.99 |
+----+-------------+-------+-------+
```

### JSON Format
```bash
oc product:list --format=json
[
  {
    "id": 1,
    "name": "Product One",
    "model": "P001",
    "price": "29.99"
  }
]
```

### YAML Format
```bash
oc product:list --format=yaml
- id: 1
  name: "Product One"
  model: "P001"
  price: "29.99"
```