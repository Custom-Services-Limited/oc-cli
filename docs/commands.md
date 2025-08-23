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

- `--opencart-root=<path>` - Path to OpenCart installation directory (allows running commands from anywhere)
- `--format=<format>` - Output format: `table` (default), `json`, `yaml`
- `--quiet` - Suppress output
- `--verbose` - Increase verbosity
- `--help` - Show help for the command

## Database Connection Options

When OpenCart installation is not available, you can connect directly to the database:

- `--db-host=<hostname>` - Database hostname (default: localhost)
- `--db-user=<username>` - Database username
- `--db-pass=<password>` - Database password
- `--db-name=<database>` - Database name
- `--db-port=<port>` - Database port (default: 3306)
- `--db-prefix=<prefix>` - Database table prefix (default: oc_)

**Example:**
```bash
oc product:list --db-host=localhost --db-user=oc_user --db-pass=password --db-name=opencart_db
```

## Core Commands

### core:version
Display version information for OC-CLI and OpenCart.

**Usage:**
```bash
oc core:version [options]
```

**Options:**
- `--opencart-root=<path>` - Path to OpenCart installation directory
- `--opencart, -o` - Show only OpenCart version
- `--format=<format>` - Output format (table, json, yaml)

**Examples:**
```bash
# Show all version information
oc core:version

# Show only OpenCart version
oc core:version --opencart

# JSON output
oc core:version --format=json

# Check version from different directory
oc core:version --opencart-root=/var/www/opencart

# Show only OpenCart version from specific path
oc core:version --opencart --opencart-root=/path/to/opencart
```

### core:check-requirements
Check system requirements for OpenCart and OC-CLI.

**Usage:**
```bash
oc core:check-requirements [options]
```

**Options:**
- `--opencart-root=<path>` - Path to OpenCart installation directory
- `--format=<format>` - Output format (table, json, yaml)

**Examples:**
```bash
# Check requirements (basic)
oc core:check-requirements

# Check requirements with JSON output
oc core:check-requirements --format=json

# Check requirements for specific OpenCart installation
oc core:check-requirements --opencart-root=/var/www/opencart

# YAML output for automation scripts
oc core:check-requirements --format=yaml --opencart-root=/path/to/oc
```

**What it checks:**
- PHP version (>= 7.4) and configuration (memory limit, execution time)
- Required PHP extensions (curl, gd, mbstring, zip, zlib, json, openssl)
- Recommended PHP extensions (mysqli, pdo_mysql, iconv, mcrypt)
- OpenCart directory and file permissions
- Database connectivity (if OpenCart installation found)

### core:config
Manage OpenCart configuration settings stored in the database.

**Usage:**
```bash
oc core:config [action] [key] [value] [options]
```

**Actions:**
- `list` - List all configuration settings (default)
- `get` - Get specific configuration value
- `set` - Set configuration value

**Options:**
- `--opencart-root=<path>` - Path to OpenCart installation directory
- `--format=<format>` - Output format (table, json, yaml)
- `--admin, -a` - Use admin configuration instead of catalog

**Examples:**
```bash
# List all catalog configuration
oc core:config list

# List all configuration in JSON format
oc core:config list --format=json

# List admin configuration
oc core:config list --admin

# Get a specific configuration value
oc core:config get config_name

# Set a configuration value
oc core:config set config_name "new value"

# Work with specific OpenCart installation
oc core:config list --opencart-root=/var/www/opencart

# Set admin configuration from different directory
oc core:config set config_admin_setting "value" --admin --opencart-root=/path/to/oc

# Get configuration in YAML format for scripts
oc core:config get config_currency --format=yaml --opencart-root=/var/www/store
```

**Important Notes:**
- This command requires a valid OpenCart installation with database access
- Configuration changes are written directly to the database
- Use `--admin` flag to work with admin panel settings instead of catalog settings
- Some configuration changes may require cache clearing to take effect
- Be careful when setting configuration values - invalid values may break your store

**Common Configuration Keys:**
- `config_name` - Store name
- `config_owner` - Store owner
- `config_address` - Store address
- `config_email` - Store email
- `config_telephone` - Store telephone
- `config_currency` - Default currency
- `config_language` - Default language
- `config_admin_language` - Admin language (use with --admin)

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
List products in the store with powerful filtering and search capabilities.

**Usage:**
```bash
oc product:list [category] [options]
```

**Arguments:**
- `category` - Filter by category name or ID (optional)

**Options:**
- `--format=<format>` - Output format: `table` (default), `json`, `yaml`
- `--status=<status>` - Filter by status: `enabled`, `disabled`, `all` (default: all)
- `--limit=<number>` - Limit number of results (default: 50)
- `--search=<term>` - Search by product name or model

**Examples:**
```bash
# List all products (default table format)
oc product:list

# List first 10 products
oc product:list --limit=10

# List only enabled products
oc product:list --status=enabled

# Search for products containing "iPhone"
oc product:list --search="iPhone"

# List products in "Electronics" category
oc product:list Electronics

# List products in category ID 20
oc product:list 20

# Combine filters - enabled products in Desktops category, limit 5
oc product:list Desktops --status=enabled --limit=5

# JSON output for scripting
oc product:list --format=json --limit=3

# Using database connection directly
oc product:list --db-host=localhost --db-user=oc_user --db-pass=password --db-name=oc_db

# Search with database connection and YAML output
oc product:list --search="Samsung" --format=yaml --db-host=localhost --db-user=oc_user --db-pass=pass --db-name=opencart
```

**Sample Output (Table):**
```
Products
========

 ---- -------------------------- ------------ ----------- ----------- ------------------------- ------ ------------ 
  ID   Name                       Model        Price       Status      Category                  Qty    Date Added  
 ---- -------------------------- ------------ ----------- ----------- ------------------------- ------ ------------ 
  49   Samsung Galaxy Tab 10.1    SAM1         $199.99     ✓ Enabled   Tablets                   0      2011-04-26  
  48   iPod Classic               product 20   $100.00     ✓ Enabled   MP3 Players               995    2009-02-08  
  47   HP LP3065                  Product 21   $100.00     ✓ Enabled   Monitors                  1000   2009-02-03  
 ---- -------------------------- ------------ ----------- ----------- ------------------------- ------ ------------ 
```

### product:create
Create a new product with comprehensive options and validation.

**Usage:**
```bash
oc product:create [name] [model] [price] [options]
```

**Arguments:**
- `name` - Product name (optional, will prompt if not provided)
- `model` - Product model/SKU (optional, will prompt if not provided)
- `price` - Product price (optional, will prompt if not provided)

**Options:**
- `--description=<text>` - Product description
- `--category=<name|id>` - Category name or ID to assign product to
- `--quantity=<number>` - Stock quantity (default: 0)
- `--status=<status>` - Product status: `enabled` or `disabled` (default: enabled)
- `--weight=<number>` - Product weight (default: 0)
- `--sku=<sku>` - Product SKU (defaults to model if not provided)
- `--format=<format>` - Output format: `table` (default), `json`, `yaml`
- `--interactive` - Interactive mode - prompt for missing values

**Examples:**
```bash
# Basic product creation
oc product:create "Test Product" "TEST-001" "29.99"

# Create product with full details
oc product:create "Advanced Widget" "ADV-001" "149.99" \
  --description="High-quality advanced widget" \
  --category="Electronics" \
  --quantity=50 \
  --status=enabled \
  --weight=2.5 \
  --sku="ADV-WIDGET-001"

# Interactive mode (prompts for required fields)
oc product:create --interactive

# Create product with JSON output
oc product:create "API Product" "API-001" "99.99" --format=json

# Create product using database connection
oc product:create "Remote Product" "REM-001" "199.99" \
  --description="Created via remote connection" \
  --category="Desktops" \
  --db-host=localhost \
  --db-user=oc_user \
  --db-pass=password \
  --db-name=opencart_db

# Create disabled product
oc product:create "Draft Product" "DRAFT-001" "0.00" --status=disabled

# Create product in specific category by ID
oc product:create "Category Test" "CAT-001" "25.00" --category=20
```

**Sample Output (Table):**
```
[OK] Product created successfully!

 ------------ -------------------- 
  Field        Value               
 ------------ -------------------- 
  Product ID   51                  
  Name         Test Product        
  Model        TEST-001            
  Price        $29.99              
  Status       Enabled             
  Quantity     0                   
  Weight       0                   
 ------------ -------------------- 
```

**Sample Output (JSON):**
```json
{
    "product_id": 52,
    "name": "API Product",
    "model": "API-001",
    "price": "99.99",
    "status": "enabled",
    "quantity": 0,
    "weight": 0
}
```

**Database Fields Created:**
- `oc_product` table: Core product information
- `oc_product_description` table: Product name, description, and SEO fields
- `oc_product_to_category` table: Category assignment (if specified)

**Validation:**
- Checks for duplicate model numbers
- Validates price format (must be numeric and positive)
- Validates status values (enabled/disabled only)
- Ensures required fields are provided (name, model, price)

**Interactive Mode:**
When using `--interactive`, the command will prompt for:
1. Product name (required)
2. Product model/SKU (required)
3. Product price (required)
4. Product description (optional)
5. Category assignment (optional)

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

## Database Schema Reference

For detailed information about the OpenCart database structure used by these commands, see:
- **[Database Schema Documentation](database-schema.md)** - Comprehensive reference for all product-related tables
- **[OpenCart 2.x / 3.x Structure](../tests/oc2x_and_3x_db_structure.sql)** - Complete database schema SQL file

## Error Handling

### Common Errors and Solutions

**Database Connection Failed:**
```bash
[ERROR] Could not connect to database.
```
- Verify database credentials and connectivity
- Check that OpenCart installation exists or provide `--db-*` options
- Ensure MySQL service is running

**Product Model Already Exists:**
```bash
[ERROR] Product with model 'TEST-001' already exists.
```
- Use a unique model number
- Check existing products with `oc product:list --search="TEST-001"`

**Invalid OpenCart Installation:**
```bash
[ERROR] This command must be run from an OpenCart installation directory or provide database connection options.
```
- Navigate to OpenCart root directory, or
- Use `--opencart-root=/path/to/opencart`, or
- Use direct database connection with `--db-host`, `--db-user`, etc.

**Permission Denied:**
```bash
[ERROR] Permission denied accessing OpenCart files.
```
- Check file/directory permissions
- Run with appropriate user permissions
- Verify OpenCart directory ownership