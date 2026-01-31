# Changelog

All notable changes to Ultra-Light Product Options will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
