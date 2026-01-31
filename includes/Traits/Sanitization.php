<?php
declare(strict_types=1);

/**
 * Sanitization Trait - Shared sanitization methods.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Traits;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait for shared sanitization methods.
 */
trait Sanitization
{
    /**
     * Sanitize a field configuration.
     *
     * @param array<string, mixed> $field Field data.
     * @return array<string, mixed>|null Sanitized field or null if invalid.
     */
    public static function sanitize_field(array $field): ?array
    {
        if (!isset($field['id'], $field['type'], $field['label'])) {
            return null;
        }

        $sanitized = [
            'id' => sanitize_key($field['id']),
            'name' => isset($field['name']) ? sanitize_key($field['name']) : sanitize_key($field['id']),
            'type' => sanitize_key($field['type']),
            'label' => sanitize_text_field($field['label']),
            'required' => isset($field['required']) ? (bool) $field['required'] : false,
        ];

        // Handle pricing object (new structure)
        if (isset($field['pricing']) && is_array($field['pricing'])) {
            $sanitized['pricing'] = [
                'type' => isset($field['pricing']['type']) ? sanitize_key($field['pricing']['type']) : 'none',
                'price' => isset($field['pricing']['price']) ? (float) $field['pricing']['price'] : 0.0,
                'formula' => isset($field['pricing']['formula']) ? self::sanitize_formula($field['pricing']['formula']) : '',
                'multiplier' => isset($field['pricing']['multiplier']) ? (float) $field['pricing']['multiplier'] : 1.0,
            ];
        }

        // Handle options for radio, radio_switch, select, checkbox_group
        if (in_array($field['type'], ['radio', 'radio_switch', 'select', 'checkbox_group'], true)) {
            if (isset($field['options']) && is_array($field['options'])) {
                $sanitized['options'] = [];
                foreach ($field['options'] as $option) {
                    $sanitized['options'][] = self::sanitize_option($option);
                }
            }
        }

        // Handle price for checkbox
        if ($field['type'] === 'checkbox') {
            $sanitized['price'] = isset($field['price']) ? (float) $field['price'] : 0.0;
            $sanitized['price_type'] = isset($field['price_type']) ? sanitize_key($field['price_type']) : 'flat';

            // Handle formula for formula pricing type
            if ($sanitized['price_type'] === 'formula' && isset($field['formula'])) {
                $sanitized['formula'] = self::sanitize_formula($field['formula']);
            }

            // Handle multiplier for field_value pricing type
            if ($sanitized['price_type'] === 'field_value' && isset($field['multiplier'])) {
                $sanitized['multiplier'] = (float) $field['multiplier'];
            }
        }

        // Handle text field with field_value pricing
        if (in_array($field['type'], ['text', 'number'], true)) {
            if (isset($field['price_type'])) {
                $sanitized['price_type'] = sanitize_key($field['price_type']);
                if ($sanitized['price_type'] === 'field_value' && isset($field['multiplier'])) {
                    $sanitized['multiplier'] = (float) $field['multiplier'];
                }
            }
            if (isset($field['min'])) {
                $sanitized['min'] = (float) $field['min'];
            }
            if (isset($field['max'])) {
                $sanitized['max'] = (float) $field['max'];
            }
            if (isset($field['input_type'])) {
                $sanitized['input_type'] = sanitize_key($field['input_type']);
            }
        }

        // Handle condition
        if (isset($field['condition']) && is_array($field['condition'])) {
            $sanitized['condition'] = self::sanitize_condition($field['condition']);
        }

        // Handle description/help text
        if (isset($field['description'])) {
            $sanitized['description'] = wp_kses_post($field['description']);
        }

        // Handle placeholder
        if (isset($field['placeholder'])) {
            $sanitized['placeholder'] = sanitize_text_field($field['placeholder']);
        }

        // Handle file upload settings
        if ($field['type'] === 'file') {
            if (isset($field['allowed_types']) && is_array($field['allowed_types'])) {
                $sanitized['allowed_types'] = array_map('sanitize_key', $field['allowed_types']);
            }
            if (isset($field['max_size'])) {
                $sanitized['max_size'] = absint($field['max_size']);
            }
        }

        // Handle badges
        if (isset($field['badge'])) {
            $sanitized['badge'] = sanitize_text_field($field['badge']);
            $sanitized['badge_color'] = isset($field['badge_color']) ? sanitize_hex_color($field['badge_color']) : '#ef4444';
        }

        return $sanitized;
    }

    /**
     * Sanitize an option.
     *
     * @param array<string, mixed> $option Option data.
     * @return array<string, mixed> Sanitized option.
     */
    public static function sanitize_option(array $option): array
    {
        $sanitized = [
            'label' => isset($option['label']) ? sanitize_text_field($option['label']) : '',
            'value' => isset($option['value']) ? sanitize_text_field((string) $option['value']) : '',
            'price' => isset($option['price']) ? (float) $option['price'] : 0.0,
            'price_type' => isset($option['price_type']) ? sanitize_key($option['price_type']) : 'flat',
        ];

        // Handle visual swatch image
        if (isset($option['image'])) {
            $sanitized['image'] = esc_url_raw($option['image']);
        }

        // Handle formula
        if ($sanitized['price_type'] === 'formula' && isset($option['formula'])) {
            $sanitized['formula'] = self::sanitize_formula($option['formula']);
        }

        // Handle multiplier for field_value pricing
        if ($sanitized['price_type'] === 'field_value' && isset($option['multiplier'])) {
            $sanitized['multiplier'] = (float) $option['multiplier'];
        }

        // Handle badges
        if (isset($option['badge'])) {
            $sanitized['badge'] = sanitize_text_field($option['badge']);
            $sanitized['badge_color'] = isset($option['badge_color']) ? sanitize_hex_color($option['badge_color']) : '#ef4444';
        }

        return $sanitized;
    }

    /**
     * Sanitize condition configuration.
     *
     * @param array<string, mixed> $condition Condition data.
     * @return array<string, mixed>|null Sanitized condition or null.
     */
    public static function sanitize_condition(array $condition): ?array
    {
        // Handle new format with rules array
        if (isset($condition['rules']) && is_array($condition['rules'])) {
            $sanitized_rules = [];
            foreach ($condition['rules'] as $rule) {
                if (isset($rule['field']) && !empty($rule['field'])) {
                    $sanitized_rules[] = [
                        'field' => sanitize_key($rule['field']),
                        'operator' => isset($rule['operator']) ? sanitize_key($rule['operator']) : 'equals',
                        'value' => isset($rule['value']) ? sanitize_text_field((string) $rule['value']) : '',
                    ];
                }
            }
            if (!empty($sanitized_rules)) {
                return [
                    'rules' => $sanitized_rules,
                    'action' => isset($condition['action']) && $condition['action'] === 'hide' ? 'hide' : 'show',
                ];
            }
        }

        // Handle legacy format
        if (isset($condition['field']) && !empty($condition['field'])) {
            return [
                'rules' => [
                    [
                        'field' => sanitize_key($condition['field']),
                        'operator' => 'equals',
                        'value' => isset($condition['value']) ? sanitize_text_field((string) $condition['value']) : '',
                    ]
                ],
                'action' => 'show',
            ];
        }

        return null;
    }

    /**
     * Sanitize formula string.
     *
     * @param string $formula Formula string.
     * @return string Sanitized formula.
     */
    public static function sanitize_formula(string $formula): string
    {
        // Remove any potentially dangerous characters
        // Only allow: numbers, operators, parentheses, variable placeholders, dots, spaces
        $sanitized = preg_replace('/[^0-9+\-*\/().{}\w\s]/', '', $formula);

        // Limit length
        $max_length = (int) apply_filters('ulo_formula_max_length', 500);
        if (strlen($sanitized) > $max_length) {
            $sanitized = substr($sanitized, 0, $max_length);
        }

        return $sanitized;
    }

    /**
     * Sanitize field group rules.
     *
     * @param array<string, mixed> $rules Rules data.
     * @return array<string, mixed> Sanitized rules.
     */
    public static function sanitize_rules(array $rules): array
    {
        return [
            'all_products' => isset($rules['all_products']) ? (bool) $rules['all_products'] : false,
            'product_ids' => isset($rules['product_ids']) && is_array($rules['product_ids'])
                ? array_map('absint', $rules['product_ids'])
                : [],
            'variation_ids' => isset($rules['variation_ids']) && is_array($rules['variation_ids'])
                ? array_map('absint', $rules['variation_ids'])
                : [],
            'category_ids' => isset($rules['category_ids']) && is_array($rules['category_ids'])
                ? array_map('absint', $rules['category_ids'])
                : [],
        ];
    }
}
