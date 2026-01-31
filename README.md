# Ultra-Light Product Options for WooCommerce

A lightweight, performance-focused WooCommerce plugin for adding custom product options with conditional logic and advanced pricing.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![License](https://img.shields.io/badge/License-GPL%20v2-green)

## Features

- **Custom Product Options** - Add text fields, textareas, radio buttons, checkboxes, dropdowns, date pickers, file uploads, and more
- **Conditional Logic** - Show/hide fields based on other field values or product variations
- **Advanced Pricing** - Flat pricing, quantity-based, percentage-based, and formula-based pricing
- **Attention-Grabbing Badges** - Add customizable badges to options (e.g., "RECOMMENDED", "BEST VALUE")
- **Zero Layout Shift** - Optimized CSS prevents visual jumping during page load
- **No jQuery Dependency** - Pure vanilla JavaScript for better performance
- **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
- **GMC Compliant** - Structured data compatible with Google Merchant Center

## Requirements

- WordPress 6.0 or higher
- WooCommerce 8.0 or higher
- PHP 8.2 or higher

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin

Or install via Composer:
```bash
composer require xaphor/ultra-light-options
```

## Usage

### Creating Option Groups

1. Go to **WooCommerce → Product Options**
2. Click "Add New Group"
3. Configure group settings:
   - Name and description
   - Associated products/categories
   - Display rules (variations, etc.)

### Adding Fields

Within each group, you can add:

| Field Type | Description |
|------------|-------------|
| Text | Single-line text input |
| Textarea | Multi-line text input |
| Number | Numeric input with min/max |
| Radio | Single selection from options |
| Checkbox | Toggle on/off |
| Checkbox Group | Multiple selections |
| Select | Dropdown menu |
| Radio Switch | Toggle between two options |
| Date | Native date picker |
| File Upload | Secure file uploads |
| HTML | Custom HTML content |

### Pricing Options

- **Flat** - Fixed price addition (e.g., +$10)
- **Quantity** - Price per unit (e.g., +$2/item)
- **Percentage** - Percentage of base price (e.g., +10%)
- **Formula** - Custom formula (e.g., `{qty} * 5 + 10`)

### Conditional Logic

Fields can be shown/hidden based on:
- Other field values
- Selected product variations
- Multiple conditions with AND/OR logic

## Hooks & Filters

```php
// Modify field output
add_filter('ulo_field_html', function($html, $field) {
    return $html;
}, 10, 2);

// Modify price calculation
add_filter('ulo_calculated_price', function($price, $field, $options) {
    return $price;
}, 10, 3);

// Before cart item added
add_action('ulo_before_add_to_cart', function($product_id, $options) {
    // Custom validation
}, 10, 2);
```

## Screenshots

*Coming soon*

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Author

**Zaffarullah**
- GitHub: [@xaphor](https://github.com/xaphor)
- Email: xaphor.emam@gmail.com

## Support

For bugs and feature requests, please [open an issue](https://github.com/xaphor/ultra-light-options/issues).
