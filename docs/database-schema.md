# OpenCart Database Schema Reference

This document provides a comprehensive reference for the OpenCart database schema used by OC-CLI commands.

**Schema Source**: [tests/oc2x_and_3x_db_structure.sql](../tests/oc2x_and_3x_db_structure.sql)

## Product Tables Overview

The OpenCart product system uses multiple interconnected tables to store product information:

### Core Product Tables

#### `oc_product` - Main Product Table
The primary table containing core product information.

```sql
CREATE TABLE `oc_product` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `model` varchar(64) NOT NULL,
  `sku` varchar(64) NOT NULL,
  `upc` varchar(12) NOT NULL,
  `ean` varchar(14) NOT NULL,
  `jan` varchar(13) NOT NULL,
  `isbn` varchar(17) NOT NULL,
  `mpn` varchar(64) NOT NULL,
  `location` varchar(128) NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `stock_status_id` int NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `manufacturer_id` int NOT NULL,
  `shipping` tinyint(1) NOT NULL DEFAULT '1',
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `points` int NOT NULL DEFAULT '0',
  `tax_class_id` int NOT NULL,
  `date_available` date NOT NULL DEFAULT '0000-00-00',
  `weight` decimal(15,8) NOT NULL DEFAULT '0.00000000',
  `weight_class_id` int NOT NULL DEFAULT '0',
  `length` decimal(15,8) NOT NULL DEFAULT '0.00000000',
  `width` decimal(15,8) NOT NULL DEFAULT '0.00000000',
  `height` decimal(15,8) NOT NULL DEFAULT '0.00000000',
  `length_class_id` int NOT NULL DEFAULT '0',
  `subtract` tinyint(1) NOT NULL DEFAULT '1',
  `minimum` int NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `viewed` int NOT NULL DEFAULT '0',
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

**Key Fields for CLI**:
- `product_id`: Auto-increment primary key
- `model`: Product model/SKU (required, unique identifier)
- `price`: Product price (decimal 15,4)
- `status`: Product status (0=disabled, 1=enabled)
- `quantity`: Stock quantity
- `weight`: Product weight

**Required Fields (NOT NULL without defaults)**:
- `model`, `sku`, `upc`, `ean`, `jan`, `isbn`, `mpn`, `location`
- `stock_status_id`, `manufacturer_id`, `tax_class_id`
- `date_added`, `date_modified`

#### `oc_product_description` - Multi-language Product Info
Stores product names and descriptions in multiple languages.

```sql
CREATE TABLE `oc_product_description` (
  `product_id` int NOT NULL,
  `language_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `tag` text NOT NULL,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

**Key Fields**:
- `product_id`: Foreign key to `oc_product`
- `language_id`: Language identifier (1 = English by default)
- `name`: Product display name
- `description`: Product description
- `tag`: Product tags
- `meta_*`: SEO metadata

#### `oc_product_to_category` - Product-Category Relationships
Links products to categories (many-to-many relationship).

```sql
CREATE TABLE `oc_product_to_category` (
  `product_id` int NOT NULL,
  `category_id` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

### Category Tables

#### `oc_category` - Category Information
```sql
CREATE TABLE `oc_category` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `image` varchar(255) DEFAULT NULL,
  `parent_id` int NOT NULL DEFAULT '0',
  `top` tinyint(1) NOT NULL,
  `column` int NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

#### `oc_category_description` - Category Names/Descriptions
```sql
CREATE TABLE `oc_category_description` (
  `category_id` int NOT NULL,
  `language_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

### Language Support

#### `oc_language` - Available Languages
```sql
CREATE TABLE `oc_language` (
  `language_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `code` varchar(5) NOT NULL,
  `locale` varchar(255) NOT NULL,
  `image` varchar(64) NOT NULL,
  `directory` varchar(32) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

**Default Language**: ID 1 (English - 'en-gb')

## CLI Command to Database Field Mapping

### `product:create` Command Mapping

| CLI Option | Database Table | Field | Type | Required | Default |
|------------|----------------|-------|------|----------|---------|
| `name` | `oc_product_description` | `name` | varchar(255) | ✅ | - |
| `model` | `oc_product` | `model` | varchar(64) | ✅ | - |
| `price` | `oc_product` | `price` | decimal(15,4) | ✅ | 0.0000 |
| `--description` | `oc_product_description` | `description` | text | ❌ | '' |
| `--quantity` | `oc_product` | `quantity` | int | ❌ | 0 |
| `--status` | `oc_product` | `status` | tinyint(1) | ❌ | 0 |
| `--weight` | `oc_product` | `weight` | decimal(15,8) | ❌ | 0.00000000 |
| `--sku` | `oc_product` | `sku` | varchar(64) | ✅* | model value |
| `--category` | `oc_product_to_category` | `category_id` | int | ❌ | - |

*Required by database but can default to model value

### Required Database Fields (Auto-filled by CLI)

The CLI automatically provides these required fields:

| Field | Table | Default Value | Purpose |
|-------|-------|---------------|---------|
| `upc` | `oc_product` | `''` | Universal Product Code |
| `ean` | `oc_product` | `''` | European Article Number |
| `jan` | `oc_product` | `''` | Japanese Article Number |
| `isbn` | `oc_product` | `''` | International Standard Book Number |
| `mpn` | `oc_product` | `''` | Manufacturer Part Number |
| `location` | `oc_product` | `''` | Physical location |
| `manufacturer_id` | `oc_product` | `0` | No manufacturer |
| `stock_status_id` | `oc_product` | `7` | Default stock status |
| `tax_class_id` | `oc_product` | `0` | No tax class |
| `shipping` | `oc_product` | `1` | Shippable product |
| `date_available` | `oc_product` | `CURDATE()` | Current date |
| `date_added` | `oc_product` | `NOW()` | Current timestamp |
| `date_modified` | `oc_product` | `NOW()` | Current timestamp |
| `tag` | `oc_product_description` | `''` | No tags |
| `meta_title` | `oc_product_description` | `name` | Product name |
| `meta_description` | `oc_product_description` | `''` | No meta description |
| `meta_keyword` | `oc_product_description` | `''` | No meta keywords |
| `language_id` | `oc_product_description` | `1` | English language |

## Product Extended Tables

### Product Options and Variants

#### `oc_product_option` - Product Options
```sql
CREATE TABLE `oc_product_option` (
  `product_option_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `option_id` int NOT NULL,
  `value` text NOT NULL,
  `required` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

#### `oc_product_option_value` - Option Values
```sql
CREATE TABLE `oc_product_option_value` (
  `product_option_value_id` int NOT NULL AUTO_INCREMENT,
  `product_option_id` int NOT NULL,
  `product_id` int NOT NULL,
  `option_id` int NOT NULL,
  `option_value_id` int NOT NULL,
  `quantity` int NOT NULL,
  `subtract` tinyint(1) NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `price_prefix` varchar(1) NOT NULL,
  `points` int NOT NULL,
  `points_prefix` varchar(1) NOT NULL,
  `weight` decimal(15,8) NOT NULL,
  `weight_prefix` varchar(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

### Product Images

#### `oc_product_image` - Additional Product Images
```sql
CREATE TABLE `oc_product_image` (
  `product_image_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

### Product Pricing

#### `oc_product_special` - Special Prices
```sql
CREATE TABLE `oc_product_special` (
  `product_special_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `customer_group_id` int NOT NULL,
  `priority` int NOT NULL DEFAULT '1',
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `date_start` date NOT NULL DEFAULT '0000-00-00',
  `date_end` date NOT NULL DEFAULT '0000-00-00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

#### `oc_product_discount` - Volume Discounts
```sql
CREATE TABLE `oc_product_discount` (
  `product_discount_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `customer_group_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '1',
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `date_start` date NOT NULL DEFAULT '0000-00-00',
  `date_end` date NOT NULL DEFAULT '0000-00-00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

### Product Attributes

#### `oc_product_attribute` - Product Attributes
```sql
CREATE TABLE `oc_product_attribute` (
  `product_id` int NOT NULL,
  `attribute_id` int NOT NULL,
  `language_id` int NOT NULL,
  `text` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
```

## Database Constraints and Indexes

### Primary Keys
- `oc_product`: `product_id`
- `oc_product_description`: `(product_id, language_id)`
- `oc_product_to_category`: `(product_id, category_id)`
- `oc_category`: `category_id`
- `oc_category_description`: `(category_id, language_id)`

### Important Indexes
- `oc_product_description`: Index on `name` for search performance
- `oc_product_to_category`: Index on `category_id` for category filtering
- `oc_category_description`: Index on `name` for category lookup

## Version Compatibility Notes

### OpenCart 2.x (Current Schema)
- Uses MyISAM storage engine
- UTF8MB3 charset
- All tables documented above

### OpenCart 3.x/4.x Differences
**Note**: This CLI was developed against OpenCart 2.x schema. Potential differences in newer versions:
- Storage engine may be InnoDB
- Charset may be UTF8MB4
- Additional fields or modified field types
- New tables for enhanced features

## Database Connection Requirements

### MySQL Configuration
- **MySQL Version**: 5.7+ recommended
- **Character Set**: UTF8MB3 or UTF8MB4
- **Collation**: utf8_general_ci or utf8mb4_unicode_ci
- **SQL Mode**: Compatible with OpenCart requirements

### Connection Parameters
```php
// Example connection configuration
$config = [
    'db_hostname' => 'localhost',
    'db_username' => 'oc_user',
    'db_password' => 'password',
    'db_database' => 'opencart_db',
    'db_port' => 3306,
    'db_prefix' => 'oc_'
];
```

## CLI Database Integration

### Transaction Support
The CLI uses database transactions for product creation to ensure data integrity:
1. Begin transaction
2. Insert into `oc_product`
3. Insert into `oc_product_description`
4. Insert into `oc_product_to_category` (if category specified)
5. Commit or rollback on error

### Error Handling
- Foreign key constraint violations
- Duplicate model errors
- Required field validation
- Data type validation

### Performance Considerations
- Use prepared statements for all queries
- Limit query results with LIMIT clauses
- Use appropriate indexes for filtering
- Close database connections properly

## Future Enhancements

### Planned Database Features
- Product option management
- Product image handling
- Attribute management
- Special pricing support
- Multi-store support
- Advanced search capabilities

For the complete database structure, refer to: [tests/oc2x_and_3x_db_structure.sql](../tests/oc2x_and_3x_db_structure.sql)