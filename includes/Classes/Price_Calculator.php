<?php
declare(strict_types=1);

/**
 * Price Calculator - Handles all pricing calculations.
 *
 * Supports 4 pricing types:
 * 1. Flat Fee - Fixed price addition
 * 2. Quantity-Based Flat Fee - Price multiplied by quantity
 * 3. Formula/Calculation-Based Fee - Custom formula evaluation
 * 4. Amount & Field Value - User-entered value × multiplier
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Classes;

use ULO\Traits\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Price Calculator class.
 */
final class Price_Calculator
{
    use Logger;

    /**
     * Pricing types constants.
     */
    public const PRICE_TYPE_FLAT = 'flat';
    public const PRICE_TYPE_QUANTITY_FLAT = 'quantity_flat';
    public const PRICE_TYPE_FORMULA = 'formula';
    public const PRICE_TYPE_FIELD_VALUE = 'field_value';
    public const PRICE_TYPE_TIERED = 'tiered';

    /**
     * Calculate price for a single field based on its pricing type.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $value Selected value.
     * @param float $base_price Product base price.
     * @param int $quantity Product quantity.
     * @param array<string, float> $product_data Product dimensions/weight.
     * @param array<string, mixed> $all_field_values All field values for formula context.
     * @return float Calculated additional price.
     */
    public static function calculate_field_price(
        array $field,
        mixed $value,
        float $base_price,
        int $quantity,
        array $product_data = [],
        array $all_field_values = []
    ): float {
        $field_type = $field['type'] ?? '';
        $field_id = $field['id'] ?? 'unknown';

        // Skip if no value selected
        if ($value === '' || $value === null || $value === false) {
            return 0.0;
        }

        // Check for field-level pricing configuration (new structure)
        // This takes priority over option-level pricing
        $field_pricing_type = $field['pricing']['type'] ?? null;

        // If tiered pricing is set at field level, use it directly
        if ($field_pricing_type === self::PRICE_TYPE_TIERED) {
            return self::calculate_tiered_price(
                $field['pricing']['tiers'] ?? [],
                $quantity,
                (float) ($field['pricing']['base_price'] ?? 0)
            );
        }

        $calculated_price = 0.0;

        // Get price based on field type
        $base_field_price = match ($field_type) {
            'radio', 'radio_switch', 'select' => self::get_option_price($field, $value),
            'checkbox' => ($value === '1' || $value === true) ? (float) ($field['price'] ?? 0) : 0.0,
            'text', 'number', 'textarea' => self::calculate_text_field_price($field, $value),
            default => 0.0,
        };

        // Determine pricing type - check field-level first, then option-level, then fallback
        $price_type = $field_pricing_type ?? $field['price_type'] ?? self::PRICE_TYPE_FLAT;

        // Apply pricing type calculation
        $price_config = [
            'price' => $base_field_price,
            'price_type' => $price_type,
            'formula' => $field['pricing']['formula'] ?? $field['formula'] ?? '',
            'multiplier' => (float) ($field['pricing']['multiplier'] ?? $field['multiplier'] ?? 1),
        ];

        // For options-based fields, get price_type from selected option ONLY if not set at field level
        if (in_array($field_type, ['radio', 'radio_switch', 'select'], true) && $field_pricing_type === null) {
            $option_config = self::get_option_config($field, $value);
            if ($option_config) {
                $price_config['price_type'] = $option_config['price_type'] ?? self::PRICE_TYPE_FLAT;
                $price_config['formula'] = $option_config['formula'] ?? '';
                $price_config['multiplier'] = (float) ($option_config['multiplier'] ?? 1);
            }
        }

        // Build context for formula evaluation
        $context = self::build_formula_context(
            $base_price,
            $quantity,
            $product_data,
            $all_field_values
        );

        // Calculate based on pricing type
        $calculated_price = match ($price_config['price_type']) {
            self::PRICE_TYPE_FLAT => $price_config['price'],
            self::PRICE_TYPE_QUANTITY_FLAT => $price_config['price'] * $quantity,
            self::PRICE_TYPE_FORMULA => self::evaluate_formula_safely(
                $price_config['formula'],
                $context
            ),
            self::PRICE_TYPE_FIELD_VALUE => self::calculate_field_value_price(
                $value,
                $price_config['multiplier'],
                (float) ($field['min'] ?? 0),
                (float) ($field['max'] ?? PHP_FLOAT_MAX)
            ),
            self::PRICE_TYPE_TIERED => self::calculate_tiered_price(
                $field['pricing']['tiers'] ?? [],
                $quantity,
                (float) ($field['pricing']['base_price'] ?? 0)
            ),
            default => $price_config['price'],
        };


        self::log_price_calculation($field_id, $price_config['price_type'], $calculated_price, [
            'base_field_price' => $base_field_price,
            'quantity' => $quantity,
            'value' => $value,
        ]);

        return max(0.0, $calculated_price);
    }

    /**
     * Get price from selected option.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $selected_value Selected value.
     * @return float Option price.
     */
    private static function get_option_price(array $field, mixed $selected_value): float
    {
        if (!isset($field['options']) || !is_array($field['options'])) {
            return 0.0;
        }

        foreach ($field['options'] as $option) {
            if (($option['value'] ?? '') === (string) $selected_value) {
                return (float) ($option['price'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Get full option configuration for selected value.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $selected_value Selected value.
     * @return array<string, mixed>|null Option configuration or null.
     */
    private static function get_option_config(array $field, mixed $selected_value): ?array
    {
        if (!isset($field['options']) || !is_array($field['options'])) {
            return null;
        }

        foreach ($field['options'] as $option) {
            if (($option['value'] ?? '') === (string) $selected_value) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Calculate price for text/number field with field_value pricing.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $value Field value.
     * @return float Extracted numeric value or 0.
     */
    private static function calculate_text_field_price(array $field, mixed $value): float
    {
        $price_type = $field['price_type'] ?? '';

        if ($price_type !== self::PRICE_TYPE_FIELD_VALUE) {
            return 0.0;
        }

        // The actual calculation happens in calculate_field_value_price
        return 0.0;
    }

    /**
     * Calculate price based on field value and multiplier.
     *
     * @param mixed $value User-entered value.
     * @param float $multiplier Price multiplier.
     * @param float $min Minimum allowed value.
     * @param float $max Maximum allowed value.
     * @return float Calculated price.
     */
    private static function calculate_field_value_price(
        mixed $value,
        float $multiplier,
        float $min = 0,
        float $max = PHP_FLOAT_MAX
    ): float {
        $numeric_value = self::extract_numeric_value((string) $value);

        // Apply min/max constraints
        $numeric_value = max($min, min($max, $numeric_value));

        return $numeric_value * $multiplier;
    }

    /**
     * Extract numeric value from string input.
     *
     * @param string $input User input (e.g., "25 meters", "10.5", "25").
     * @return float Extracted numeric value.
     */
    public static function extract_numeric_value(string $input): float
    {
        // Remove any whitespace
        $input = trim($input);

        if ($input === '') {
            return 0.0;
        }

        // Try to extract first numeric value (including decimals and negative)
        if (preg_match('/(-?\d+\.?\d*)/', $input, $matches)) {
            return (float) $matches[1];
        }

        return 0.0;
    }

    /**
     * Build context array for formula evaluation.
     *
     * @param float $base_price Product base price.
     * @param int $quantity Product quantity.
     * @param array<string, float> $product_data Product dimensions/weight.
     * @param array<string, mixed> $field_values All field values.
     * @return array<string, float> Context variables.
     */
    public static function build_formula_context(
        float $base_price,
        int $quantity,
        array $product_data = [],
        array $field_values = []
    ): array {
        $context = [
            'base_price' => $base_price,
            'quantity' => (float) $quantity,
            'product.width' => (float) ($product_data['width'] ?? 0),
            'product.height' => (float) ($product_data['height'] ?? 0),
            'product.length' => (float) ($product_data['length'] ?? 0),
            'product.weight' => (float) ($product_data['weight'] ?? 0),
        ];

        // Add field values to context
        foreach ($field_values as $field_id => $value) {
            $numeric_value = self::extract_numeric_value((string) $value);
            $context[$field_id] = $numeric_value;
        }

        /**
         * Filter formula context variables.
         *
         * @param array<string, float> $context Context variables.
         * @param array<string, mixed> $field_values Field values.
         */
        return apply_filters('ulo_formula_context', $context, $field_values);
    }

    /**
     * Safely evaluate formula with error handling.
     *
     * @param string $formula Formula string.
     * @param array<string, float> $context Variable context.
     * @return float Calculated result or 0 on error.
     */
    public static function evaluate_formula_safely(string $formula, array $context): float
    {
        if (empty($formula)) {
            return 0.0;
        }

        try {
            return Formula_Parser::evaluate($formula, $context);
        } catch (\Exception $e) {
            self::log_error('Formula evaluation failed', [
                'formula' => $formula,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);
            return 0.0; // Fail gracefully to 0
        }
    }

    /**
     * Calculate total additional price for all fields.
     *
     * @param array<int, array<string, mixed>> $fields Field configurations.
     * @param array<string, mixed> $selected_options Selected option values.
     * @param float $base_price Product base price.
     * @param int $quantity Product quantity.
     * @param \WC_Product|null $product WooCommerce product object.
     * @return float Total additional price.
     */
    public static function get_total_additional_price(
        array $fields,
        array $selected_options,
        float $base_price,
        int $quantity,
        ?\WC_Product $product = null
    ): float {
        $total = 0.0;

        // Get product data for formulas
        $product_data = self::get_product_data($product);

        foreach ($fields as $field) {
            $field_id = $field['id'] ?? '';

            if (!isset($selected_options[$field_id])) {
                continue;
            }

            // Check conditions
            if (!Condition_Engine::should_field_be_visible($field, $selected_options)) {
                continue;
            }

            $field_price = self::calculate_field_price(
                $field,
                $selected_options[$field_id],
                $base_price,
                $quantity,
                $product_data,
                $selected_options
            );

            $total += $field_price;
        }

        /**
         * Filter total additional price.
         *
         * @param float $total Total additional price.
         * @param array $fields Field configurations.
         * @param array $selected_options Selected values.
         */
        return apply_filters('ulo_total_additional_price', $total, $fields, $selected_options);
    }

    /**
     * Get product data for formula context.
     *
     * @param \WC_Product|null $product Product object.
     * @return array<string, float> Product data.
     */
    public static function get_product_data(?\WC_Product $product): array
    {
        if (!$product) {
            return [];
        }

        return [
            'width' => (float) $product->get_width(),
            'height' => (float) $product->get_height(),
            'length' => (float) $product->get_length(),
            'weight' => (float) $product->get_weight(),
        ];
    }

    /**
     * Get price breakdown for display.
     *
     * @param array<int, array<string, mixed>> $fields Field configurations.
     * @param array<string, mixed> $selected_options Selected option values.
     * @param float $base_price Product base price.
     * @param int $quantity Product quantity.
     * @param \WC_Product|null $product Product object.
     * @return array<int, array{field: string, label: string, value: string, price: float, formatted_price: string}> Price breakdown.
     */
    public static function get_price_breakdown(
        array $fields,
        array $selected_options,
        float $base_price,
        int $quantity,
        ?\WC_Product $product = null
    ): array {
        $breakdown = [];
        $product_data = self::get_product_data($product);

        foreach ($fields as $field) {
            $field_id = $field['id'] ?? '';
            $field_label = $field['label'] ?? $field_id;

            if (!isset($selected_options[$field_id])) {
                continue;
            }

            if (!Condition_Engine::should_field_be_visible($field, $selected_options)) {
                continue;
            }

            $value = $selected_options[$field_id];
            $price = self::calculate_field_price(
                $field,
                $value,
                $base_price,
                $quantity,
                $product_data,
                $selected_options
            );

            if ($price > 0) {
                $display_value = self::get_display_value($field, $value);
                $breakdown[] = [
                    'field' => $field_id,
                    'label' => $field_label,
                    'value' => $display_value,
                    'price' => $price,
                    'formatted_price' => wc_price($price),
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get display value for a field.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $value Selected value.
     * @return string Display value.
     */
    private static function get_display_value(array $field, mixed $value): string
    {
        $field_type = $field['type'] ?? '';

        return match ($field_type) {
            'radio', 'radio_switch', 'select' => self::get_option_label($field, $value),
            'checkbox' => ($value === '1' || $value === true) ? __('Yes', 'ultra-light-options') : __('No', 'ultra-light-options'),
            'text', 'number', 'textarea' => (string) $value,
            'date', 'time' => (string) $value,
            default => (string) $value,
        };
    }

    /**
     * Get option label for display.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $selected_value Selected value.
     * @return string Option label.
     */
    private static function get_option_label(array $field, mixed $selected_value): string
    {
        if (!isset($field['options']) || !is_array($field['options'])) {
            return (string) $selected_value;
        }

        foreach ($field['options'] as $option) {
            if (($option['value'] ?? '') === (string) $selected_value) {
                return $option['label'] ?? (string) $selected_value;
            }
        }

        return (string) $selected_value;
    }

    /**
     * Get available pricing types.
     *
     * @return array<string, string> Pricing type => label.
     */
    public static function get_pricing_types(): array
    {
        return [
            self::PRICE_TYPE_FLAT => __('Flat Fee', 'ultra-light-options'),
            self::PRICE_TYPE_QUANTITY_FLAT => __('Quantity-Based Flat Fee', 'ultra-light-options'),
            self::PRICE_TYPE_FORMULA => __('Formula/Calculation', 'ultra-light-options'),
            self::PRICE_TYPE_FIELD_VALUE => __('Amount × Field Value', 'ultra-light-options'),
            self::PRICE_TYPE_TIERED => __('Tiered Pricing', 'ultra-light-options'),
        ];
    }

    /**
     * Calculate tiered pricing based on quantity.
     *
     * Returns a PER-UNIT additional price (not total).
     * WooCommerce will multiply this by quantity for line total.
     *
     * Base price acts as a FLOOR (minimum total), converted to per-unit.
     * Formula: MAX(base_price / qty, tier_price_per_unit)
     *
     * @param array $tiers Array of tier configurations.
     * @param int $quantity Product quantity.
     * @param float $base_price Minimum floor price (total, not per-unit).
     * @return float Per-unit additional price.
     */
    private static function calculate_tiered_price(
        array $tiers,
        int $quantity,
        float $base_price = 0.0
    ): float {
        if ($quantity <= 0) {
            return 0.0;
        }

        if (empty($tiers)) {
            // Return per-unit base price
            return $base_price / $quantity;
        }

        // Sort tiers by qty_from ascending
        usort($tiers, fn($a, $b) => ($a['qty_from'] ?? 1) <=> ($b['qty_from'] ?? 1));

        // Find applicable tier
        $applicable_tier = null;
        foreach ($tiers as $tier) {
            $from = (int) ($tier['qty_from'] ?? 1);
            $to = isset($tier['qty_to']) && $tier['qty_to'] !== null && $tier['qty_to'] !== ''
                ? (int) $tier['qty_to']
                : PHP_INT_MAX;

            if ($quantity >= $from && $quantity <= $to) {
                $applicable_tier = $tier;
                break;
            }
        }

        // Fall back to last tier if quantity exceeds all defined ranges
        if ($applicable_tier === null && !empty($tiers)) {
            $applicable_tier = end($tiers);
        }

        if ($applicable_tier === null) {
            return $base_price / $quantity;
        }

        $price_per_unit = (float) ($applicable_tier['price_per_unit'] ?? 0);

        // Calculate what per-unit price would give the floor (base_price) as total
        $floor_per_unit = $base_price / $quantity;

        // Return the GREATER of: tier per-unit OR floor per-unit
        // This ensures the total (when multiplied by qty) meets the minimum
        $calculated_per_unit = max($floor_per_unit, $price_per_unit);

        self::log_price_calculation('tiered', self::PRICE_TYPE_TIERED, $calculated_per_unit, [
            'base_price' => $base_price,
            'quantity' => $quantity,
            'tier_price_per_unit' => $price_per_unit,
            'floor_per_unit' => $floor_per_unit,
            'calculated_per_unit' => $calculated_per_unit,
            'tier' => $applicable_tier,
        ]);

        return max(0.0, $calculated_per_unit);
    }

}
