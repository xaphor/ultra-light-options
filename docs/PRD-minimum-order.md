# PRD: WooCommerce Minimum Order Plugin

**Status:** Planned  
**Priority:** Future Development  
**Created:** 2026-01-31

---

## Problem Statement

When selling products like grass/turf in bulk (sqm), small orders are not feasible for delivery/installation. Customers need to meet either:
- A **minimum quantity** (e.g., 30 sqm for "Supply Only")
- A **minimum cart value** (e.g., 700 AED)

These constraints should be configurable at the **product variation level**.

---

## Requirements

### Functional Requirements

1. **Admin Settings (Per Variation)**
   - Minimum quantity field
   - Minimum cart value field
   - Option to enable/disable per variation

2. **Validation**
   - Block add-to-cart if quantity is below minimum
   - Show clear error messages to customer
   - Validate both single product and cart totals

3. **Frontend Display**
   - Show minimum requirements on product page
   - Disable quantity input below minimum
   - Display notice if requirements not met

### Non-Functional Requirements

- Lightweight (~150-200 lines of PHP)
- No external dependencies
- Compatible with WooCommerce 8.x+
- Uses native WooCommerce variation fields

---

## Technical Approach

### Recommended: Standalone Plugin

**Rationale:**
- Clean separation from Ultra Light Options
- Different domain (purchase restrictions vs product customization)
- Faster development and deployment
- Uses standard WooCommerce hooks

### Key Hooks

| Hook | Purpose |
|------|---------|
| `woocommerce_variation_options` | Add admin fields |
| `woocommerce_save_product_variation` | Save values |
| `woocommerce_add_to_cart_validation` | Validate before adding |
| `woocommerce_available_variation` | Pass data to frontend |

### File Structure

```
wc-minimum-order/
├── wc-minimum-order.php      # Main plugin (~150 lines)
└── assets/
    └── js/
        └── frontend.js       # Disable button until min met
```

---

## Acceptance Criteria

- [ ] Admin can set min qty per variation
- [ ] Admin can set min value per variation
- [ ] Customer sees clear error if minimum not met
- [ ] Quantity input starts at minimum value
- [ ] Works with variable products

---

## Developer Notes

- Consider caching variation data for performance
- Use `wp_add_inline_script` for minimal JS footprint
- Follow WordPress coding standards

---

*This feature will be developed after Ultra Light Options is published.*
