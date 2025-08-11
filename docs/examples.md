# Usage Examples

This document provides practical examples of using OC-CLI for common OpenCart management tasks.

**OC-CLI is created and maintained by [Custom Services Limited](https://support.opencartgreece.gr/) - Your OpenCart experts.**

## Getting Started

### Check Your Installation

First, verify that OC-CLI can detect your OpenCart installation:

```bash
# Navigate to your OpenCart directory
cd /path/to/your/opencart

# Check version information
oc core:version
```

Expected output:
```
Version Information
===================

 Component   Version
 ----------- --------
 Oc-cli      1.0.0
 Php         7.4.16
 Os          Linux 5.4.0
 Opencart    3.0.3.8

! [NOTE] OpenCart root: /var/www/opencart
```

## Product Management

### Listing Products

```bash
# List all products (default limit: 20)
oc product:list

# List first 10 products
oc product:list --limit=10

# Search for specific products
oc product:list --search="iphone"

# List only enabled products
oc product:list --status=enabled

# Export product list to JSON
oc product:list --format=json > products.json
```

### Creating Products

```bash
# Create a simple product
oc product:create \
  --name="Samsung Galaxy S21" \
  --model="SGS21" \
  --price=799.99

# Create a product with full details
oc product:create \
  --name="Samsung Galaxy S21" \
  --model="SGS21" \
  --price=799.99 \
  --description="Latest Samsung smartphone with advanced features" \
  --status=1 \
  --quantity=50 \
  --sku="SAMSUNG-SGS21"

# Create product interactively (prompts for missing fields)
oc product:create
```

### Updating Products

```bash
# Update product name
oc product:update 42 --name="Samsung Galaxy S21 Ultra"

# Update multiple fields
oc product:update 42 \
  --name="Samsung Galaxy S21 Ultra" \
  --price=899.99 \
  --quantity=25

# Update product status (enable/disable)
oc product:update 42 --status=0  # Disable
oc product:update 42 --status=1  # Enable
```

### Bulk Product Operations

```bash
# Export all products to CSV
oc product:export products.csv

# Import products from CSV
oc product:import products.csv

# Update prices for all products (increase by 10%)
oc product:bulk-update --price-increase=10

# Disable all out-of-stock products
oc product:bulk-update --disable-out-of-stock
```

## Category Management

### Working with Categories

```bash
# List all categories
oc category:list

# List categories in tree format
oc category:list --tree

# Create a new category
oc category:create \
  --name="Smartphones" \
  --description="Mobile phones and accessories"

# Create a subcategory
oc category:create \
  --name="Android Phones" \
  --parent=25 \
  --description="Android-based smartphones"

# Move products between categories
oc product:move-category --from=25 --to=30
```

## Order Management

### Viewing Orders

```bash
# List recent orders
oc order:list

# List orders by status
oc order:list --status="Processing"

# List orders for specific customer
oc order:list --customer=123

# View detailed order information
oc order:view 1001
```

### Order Processing

```bash
# Update order status
oc order:update-status 1001 5  # 5 = Shipped

# Update order status with notification
oc order:update-status 1001 5 \
  --notify \
  --comment="Your order has been shipped via UPS"

# Generate shipping labels for pending orders
oc order:generate-labels --status="Processing"

# Export orders for accounting
oc order:export --date-from="2023-01-01" --format=csv
```

## Database Operations

### Backup and Restore

```bash
# Create a full database backup
oc db:backup

# Create backup with custom filename
oc db:backup "backup_$(date +%Y%m%d_%H%M%S).sql"

# Create compressed backup
oc db:backup --compress backup.sql.gz

# Backup specific tables only
oc db:backup --tables="oc_product,oc_category" products_backup.sql

# Restore from backup
oc db:restore backup.sql

# Restore with force (skip confirmation)
oc db:restore backup.sql --force
```

### Database Maintenance

```bash
# Show database information
oc db:info

# Check database tables
oc db:check

# Repair database tables
oc db:repair

# Optimize database tables
oc db:optimize

# Clean up old sessions and logs
oc db:cleanup --days=30
```

## Extension Management

### Managing Extensions

```bash
# List all installed extensions
oc extension:list

# List extensions by type
oc extension:list payment
oc extension:list shipping
oc extension:list module

# Install an extension
oc extension:install paypal_express

# Enable an extension
oc extension:enable paypal_express

# Disable an extension
oc extension:disable paypal_express

# Update extension settings
oc extension:config paypal_express --status=1 --test_mode=0
```

## Cache Management

### Cache Operations

```bash
# Clear all caches
oc cache:clear

# Clear specific cache types
oc cache:clear image
oc cache:clear modification

# Rebuild caches
oc cache:rebuild

# Show cache statistics
oc cache:stats
```

## User Management

### Admin Users

```bash
# List admin users
oc user:list

# Create new admin user
oc user:create \
  --username="john.doe" \
  --password="secure_password" \
  --email="john@example.com" \
  --firstname="John" \
  --lastname="Doe"

# Reset user password
oc user:reset-password john.doe

# Enable/disable user
oc user:enable john.doe
oc user:disable john.doe

# Grant admin permissions
oc user:grant-permissions john.doe --all
```

### Customer Management

```bash
# List customers
oc customer:list

# Search customers
oc customer:list --search="john@example.com"

# View customer details
oc customer:view 123

# Create customer account
oc customer:create \
  --email="jane@example.com" \
  --firstname="Jane" \
  --lastname="Smith" \
  --password="customer_password"
```

## Configuration Management

### System Configuration

```bash
# View current configuration
oc core:config list

# Get specific configuration value
oc core:config get config_name

# Set configuration value
oc core:config set config_name "new_value"

# Enable maintenance mode
oc core:config set config_maintenance 1

# Disable maintenance mode
oc core:config set config_maintenance 0

# Update store settings
oc core:config set config_name "My Store"
oc core:config set config_email "admin@mystore.com"
```

## Maintenance Tasks

### Regular Maintenance

```bash
# Daily maintenance script
#!/bin/bash
echo "Starting daily maintenance..."

# Clear old caches
oc cache:clear

# Clean up database
oc db:cleanup --days=30

# Backup database
oc db:backup "daily_backup_$(date +%Y%m%d).sql" --compress

# Check for errors
oc core:check-requirements

echo "Maintenance completed!"
```

### Performance Optimization

```bash
# Optimize database
oc db:optimize

# Rebuild search index
oc search:rebuild-index

# Generate image cache
oc image:generate-cache

# Compress images
oc image:compress --quality=85

# Update sitemap
oc seo:generate-sitemap
```

## Troubleshooting

### Common Issues

```bash
# Check system requirements
oc core:check-requirements

# Validate installation
oc core:validate

# Test database connection
oc db:test-connection

# Check file permissions
oc core:check-permissions

# Verify configuration
oc core:config validate
```

### Debug Mode

```bash
# Run commands in verbose mode
oc product:list --verbose

# Enable debug output
oc --verbose core:version

# Check logs
oc log:view --lines=50

# Clear error logs
oc log:clear
```

## Automation and Scripting

### Cron Jobs

```bash
# Add to crontab for automated tasks
# Daily backup at 2 AM
0 2 * * * cd /var/www/opencart && oc db:backup "auto_backup_$(date +\%Y\%m\%d).sql"

# Clear cache every hour
0 * * * * cd /var/www/opencart && oc cache:clear

# Weekly database optimization
0 3 * * 0 cd /var/www/opencart && oc db:optimize
```

### Batch Processing

```bash
# Process multiple products
for id in {1..100}; do
  oc product:update $id --status=1
done

# Bulk email to customers
oc customer:list --format=json | jq -r '.[].email' | while read email; do
  oc email:send --to="$email" --template="newsletter"
done

# Import from CSV with error handling
if oc product:import products.csv --validate; then
  echo "Import successful"
else
  echo "Import failed - check error log"
  oc log:view --level=error
fi
```

## Advanced Usage

### Custom Output Formats

```bash
# JSON output for API integration
oc product:list --format=json | jq '.[] | select(.price > 100)'

# YAML output for configuration
oc core:config list --format=yaml > config.yml

# CSV output for spreadsheets
oc order:list --format=csv > orders.csv
```

### Pipeline Operations

```bash
# Chain multiple commands
oc product:list --status=disabled --format=json | \
  jq -r '.[].id' | \
  xargs -I {} oc product:delete {} --force

# Export and process data
oc customer:list --format=json | \
  jq '.[] | select(.orders > 10)' | \
  jq -r '.email' > valuable_customers.txt
```

These examples demonstrate the power and flexibility of OC-CLI for managing OpenCart installations. Adapt them to your specific needs and combine commands to create powerful automation scripts.