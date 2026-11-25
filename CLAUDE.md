# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

**WooSync (ecommerceng)** - A Dolibarr module for bidirectional synchronization with WooCommerce. The module synchronizes products, categories, companies/customers, contacts, orders, invoices, and stock between Dolibarr ERP and WooCommerce stores.

- **Module ID**: 107100
- **Dolibarr Compatibility**: v14 to v21
- **PHP Compatibility**: v7.0 to v8.2
- **Dependencies**: modSociete, modProduct, modCategorie, modWebServices

## Architecture

### Layer Structure

```
class/
├── business/           # Sync orchestration logic
│   ├── eCommerceSynchro.class.php      # Main sync orchestrator (7400+ lines)
│   ├── eCommerceUtils.class.php        # Utilities & cron handlers
│   └── eCommercePendingWebHook.class.php
├── client/             # API clients (Guzzle-based)
│   ├── eCommerceClientApi.class.php
│   ├── eCommerceClientWooCommerceApi.class.php
│   └── eCommerceClientWordpressApi.class.php
└── data/               # Entity mapping & persistence
    ├── eCommerceSite.class.php         # Site configuration
    ├── eCommerceProduct.class.php      # Product ↔ WooCommerce mapping
    ├── eCommerceSociete.class.php      # Company ↔ WooCommerce mapping
    ├── eCommerceCommande.class.php     # Order mapping
    ├── eCommerceFacture.class.php      # Invoice mapping
    └── woocommerce/
        └── eCommerceRemoteAccessWoocommerce.class.php  # WooCommerce adapter
```

### Key Integration Points

- **core/modules/modECommerceNg.class.php** - Module descriptor (permissions, menus, dictionaries)
- **core/triggers/interface_90_modECommerceng_ECommerceng.class.php** - Dolibarr event triggers
- **class/actions_ecommerceng.class.php** - Hook handler for UI integration
- **webhooks.php** - Public endpoint for WooCommerce webhooks

### Data Flow

1. **WooCommerce → Dolibarr**: Via manual sync (`site.php`) or webhooks (`webhooks.php`)
2. **Dolibarr → WooCommerce**: Via triggers on entity changes or cron jobs
3. **Webhook Processing**: Events queued in `llx_ecommerce_pending_webhooks`, processed asynchronously

### Mapping Tables Pattern

All entity mappings follow this schema:
- `rowid` (PK)
- `fk_[entity]` (FK to Dolibarr entity)
- `fk_site` (FK to ecommerce_site)
- `remote_id` (WooCommerce entity ID)
- `lang`, `last_update`, `other_data` (JSON)

## Configuration Constants

Key global constants (set in Dolibarr configuration):
- `ECOMMERCENG_DEBUG` - Enable WS request logging
- `ECOMMERCENG_MAXSIZE_MULTICALL` - Batch size (default: 400)
- `ECOMMERCENG_MAXRECORD_PERSYNC` - Max records per sync (default: 2000)
- `ECOMMERCENG_NO_COUNT_UPDATE` - Skip count updates for performance
- `ECOMMERCENG_WOOCOMMERCE_NO_VERIFY_SSL` - Skip SSL verification (self-signed certs)

## Coding Conventions

### Error Handling Pattern
```php
public $error;           // Single error message
public $errors = [];     // Multiple errors
public $warnings = [];
public function errorsToString() { ... }
```

### Include Patterns
```php
require_once DOL_DOCUMENT_ROOT . '/path/to/class.php';  // Dolibarr core
dol_include_once('/ecommerceng/path/to/class.php');     // Module classes
```

### Database Access
- Uses Dolibarr's `$this->db` handler with manual SQL queries
- Transactions: `$db->begin()` / `commit()` / `rollback()`
- Table prefix: `MAIN_DB_PREFIX` (typically `llx_`)

### Localization
- Primary language: French (fr_FR)
- Files in `/langs/{lang_code}/`
- Usage: `$langs->load('ecommerce@ecommerceng')`

## Admin Interface

Configuration organized in tabs (see `lib/eCommerce.lib.php`):
1. Parameters - Site URL, authentication, timeouts
2. ThirdParty - Company category mapping
3. Product - Categories, price levels, attributes, stock sync
4. Stock - Warehouse mapping, sync direction
5. Order/Invoice - Status mapping, payment methods

## Scripts

Maintenance scripts in `/scripts/`:
- `syncronize_to_dolibarr.php` - Manual sync from WooCommerce
- `syncronize_products_to_ecommerce.php` - Push products to WooCommerce
- `check_movement_stock_dolibarr.php` - Stock movement verification

## Hooks Registered

UI hooks: `expeditioncard`, `invoicecard`, `productcard`, `productdocuments`, `thirdpartycard`, `categorycard`, `contactcard`, `mouvementstock`, various list pages

## External Dependencies

- `guzzlehttp/guzzle` ^6.3 - HTTP client with retry middleware
- Custom OAuth1 implementation in `/includes/oauth-subscriber-woocommerce/`

## Version Management

- Version stored in `VERSION` file (read by module descriptor)
- Compatibility info in `.opendsi_info.json`
- Changelog in `ChangeLog.md`
