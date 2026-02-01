# Changelog

All notable changes to Ultra-Light Product Options will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.1] - 2026-02-01

### Fixed
- **Modern Cart Compatibility** - Fixed custom option prices not displaying correctly in Modern Cart slide-out drawer
  - Prices now set via `custom_price` key immediately when items are added to cart
  - Works with any AJAX-based cart that follows the `custom_price` convention
  - Removed separate compatibility layer in favor of proper WooCommerce integration
- **Tiered Pricing Calculation** - Fixed issue where tiered prices were double-counted in cart totals
- **Price Summary Display** - Fixed "undefined" error in price breakdown
- **Live Price Updates** - Main product price now updates dynamically when options change

### Changed
- Refactored `Cart_Handler.php` for cleaner price calculation flow
- Added `woocommerce_add_cart_item` filter for immediate price application
- Improved session restoration to recalculate prices correctly

## [2.1.0] - 2026-02-01

### Added
- **Tiered/Volume Pricing** - New pricing type for quantity-based discounts
  - Intuitive tier table builder UI with live preview
  - Define unlimited quantity tiers with per-unit pricing
  - Minimum price floor to ensure minimum charge
  - Works seamlessly with radio, checkbox, and other field types

### Fixed
- Tiered pricing now correctly integrates with WooCommerce cart (no double-multiplication)
- Field-level pricing type now takes priority over option-level for radio/select fields
- Preview calculation matches actual cart behavior

## [2.0.0] - 2026-01-31


### Added
- **Attention-Grabbing Badges** - Add customizable badges to fields and options with color picker and pulse animation
- **Conditional Logic for Variations** - Show/hide fields based on selected product variations
- **Admin Style Customization** - Customize accent colors, border radius, card styles from settings
- **Premium UI Styling** - Modern card-based layouts with smooth animations
- **Radio Switch Field** - iOS-style toggle switch for binary choices
- **File Upload Security** - Secure file handling with temp directory cleanup

### Changed
- Refactored Field_Renderer for better performance
- Optimized CSS animations (disabled shine by default)
- Improved checkbox/toggle styling with inline badges
- Enhanced mobile responsiveness

### Fixed
- Duplicate label rendering for checkbox fields
- Duplicate badge rendering for checkbox fields
- Conditional logic with variation products
- Field persistence when saving option groups
- Product search in admin field builder

## [1.0.0] - 2025-12-01

### Added
- Initial release
- Custom product options (text, textarea, radio, checkbox, select, etc.)
- Conditional logic engine
- Advanced pricing (flat, quantity, percentage, formula)
- WooCommerce HPOS compatibility
- Translation ready
