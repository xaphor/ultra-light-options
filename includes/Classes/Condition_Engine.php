<?php
declare(strict_types=1);

/**
 * Condition Engine - Handles conditional logic validation.
 *
 * Supports operators:
 * - equals: Field value equals condition value
 * - not_equals: Field value does not equal condition value
 * - contains: Field value contains condition value
 * - not_contains: Field value does not contain condition value
 * - empty: Field value is empty
 * - not_empty: Field value is not empty
 * - greater_than: Field value is greater than condition value
 * - less_than: Field value is less than condition value
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
 * Condition Engine class.
 */
final class Condition_Engine
{
    use Logger;

    /**
     * Supported operators.
     */
    public const OPERATOR_EQUALS = 'equals';
    public const OPERATOR_NOT_EQUALS = 'not_equals';
    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_NOT_CONTAINS = 'not_contains';
    public const OPERATOR_EMPTY = 'empty';
    public const OPERATOR_NOT_EMPTY = 'not_empty';
    public const OPERATOR_GREATER_THAN = 'greater_than';
    public const OPERATOR_LESS_THAN = 'less_than';

    /**
     * Condition actions.
     */
    public const ACTION_SHOW = 'show';
    public const ACTION_HIDE = 'hide';

    /**
     * Check if a field should be visible based on conditions.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param array<string, mixed> $selected_options All selected option values.
     * @return bool True if field should be visible.
     */
    public static function should_field_be_visible(array $field, array $selected_options): bool
    {
        // No condition means always visible
        if (!isset($field['condition']) || !is_array($field['condition'])) {
            return true;
        }

        $condition = $field['condition'];

        // Check if condition has rules
        if (!isset($condition['rules']) || !is_array($condition['rules']) || empty($condition['rules'])) {
            return true;
        }

        // Get action (default to 'show')
        $action = $condition['action'] ?? self::ACTION_SHOW;

        // Evaluate all rules (AND logic - all must pass)
        $rules_pass = self::evaluate_rules($condition['rules'], $selected_options);

        // Apply action
        if ($action === self::ACTION_SHOW) {
            return $rules_pass; // Show if rules pass
        } else {
            return !$rules_pass; // Hide if rules pass (so show if rules don't pass)
        }
    }

    /**
     * Evaluate multiple rules with AND logic.
     *
     * @param array<int, array<string, mixed>> $rules Rules to evaluate.
     * @param array<string, mixed> $selected_options Selected option values.
     * @return bool True if all rules pass.
     */
    public static function evaluate_rules(array $rules, array $selected_options): bool
    {
        foreach ($rules as $rule) {
            if (!self::evaluate_single_rule($rule, $selected_options)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate a single rule.
     *
     * @param array<string, mixed> $rule Rule configuration.
     * @param array<string, mixed> $selected_options Selected option values.
     * @return bool True if rule passes.
     */
    public static function evaluate_single_rule(array $rule, array $selected_options): bool
    {
        $field_id = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? self::OPERATOR_EQUALS;
        $expected_value = $rule['value'] ?? '';

        if (empty($field_id)) {
            return true; // Empty field ID means no condition
        }

        // Get actual value from selected options
        $actual_value = $selected_options[$field_id] ?? null;

        // Evaluate based on operator
        return match ($operator) {
            self::OPERATOR_EQUALS => self::evaluate_equals($actual_value, $expected_value),
            self::OPERATOR_NOT_EQUALS => !self::evaluate_equals($actual_value, $expected_value),
            self::OPERATOR_CONTAINS => self::evaluate_contains($actual_value, $expected_value),
            self::OPERATOR_NOT_CONTAINS => !self::evaluate_contains($actual_value, $expected_value),
            self::OPERATOR_EMPTY => self::evaluate_empty($actual_value),
            self::OPERATOR_NOT_EMPTY => !self::evaluate_empty($actual_value),
            self::OPERATOR_GREATER_THAN => self::evaluate_greater_than($actual_value, $expected_value),
            self::OPERATOR_LESS_THAN => self::evaluate_less_than($actual_value, $expected_value),
            default => self::evaluate_equals($actual_value, $expected_value),
        };
    }

    /**
     * Evaluate equals condition.
     *
     * @param mixed $actual Actual value.
     * @param mixed $expected Expected value.
     * @return bool True if equal.
     */
    private static function evaluate_equals(mixed $actual, mixed $expected): bool
    {
        // Handle null/undefined
        if ($actual === null) {
            return $expected === '' || $expected === null;
        }

        // Handle boolean for checkboxes
        if (is_bool($actual)) {
            $actual = $actual ? '1' : '';
        }

        // String comparison
        return (string) $actual === (string) $expected;
    }

    /**
     * Evaluate contains condition.
     *
     * @param mixed $actual Actual value.
     * @param mixed $expected Substring to find.
     * @return bool True if contains.
     */
    private static function evaluate_contains(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === '') {
            return false;
        }

        return str_contains((string) $actual, (string) $expected);
    }

    /**
     * Evaluate empty condition.
     *
     * @param mixed $actual Actual value.
     * @return bool True if empty.
     */
    private static function evaluate_empty(mixed $actual): bool
    {
        if ($actual === null) {
            return true;
        }

        if (is_array($actual)) {
            return empty($actual);
        }

        return (string) $actual === '';
    }

    /**
     * Evaluate greater than condition.
     *
     * @param mixed $actual Actual value.
     * @param mixed $expected Expected value.
     * @return bool True if actual > expected.
     */
    private static function evaluate_greater_than(mixed $actual, mixed $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        return (float) $actual > (float) $expected;
    }

    /**
     * Evaluate less than condition.
     *
     * @param mixed $actual Actual value.
     * @param mixed $expected Expected value.
     * @return bool True if actual < expected.
     */
    private static function evaluate_less_than(mixed $actual, mixed $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        return (float) $actual < (float) $expected;
    }

    /**
     * Get data attributes for frontend condition handling.
     *
     * @param array<string, mixed>|null $condition Condition configuration.
     * @return string HTML data attributes string.
     */
    public static function get_data_attributes(?array $condition): string
    {
        if (!$condition || !isset($condition['rules']) || empty($condition['rules'])) {
            return '';
        }

        // Encode condition as JSON for JavaScript
        $condition_json = wp_json_encode($condition);

        return sprintf(
            ' data-ulo-condition="%s"',
            esc_attr($condition_json)
        );
    }

    /**
     * Get available operators for admin UI.
     *
     * @return array<string, string> Operator => label.
     */
    public static function get_operators(): array
    {
        return [
            self::OPERATOR_EQUALS => __('equals', 'ultra-light-options'),
            self::OPERATOR_NOT_EQUALS => __('not equals', 'ultra-light-options'),
            self::OPERATOR_CONTAINS => __('contains', 'ultra-light-options'),
            self::OPERATOR_NOT_CONTAINS => __('does not contain', 'ultra-light-options'),
            self::OPERATOR_EMPTY => __('is empty', 'ultra-light-options'),
            self::OPERATOR_NOT_EMPTY => __('is not empty', 'ultra-light-options'),
            self::OPERATOR_GREATER_THAN => __('is greater than', 'ultra-light-options'),
            self::OPERATOR_LESS_THAN => __('is less than', 'ultra-light-options'),
        ];
    }

    /**
     * Get operators that don't require a value.
     *
     * @return array<int, string> Operators without value.
     */
    public static function get_valueless_operators(): array
    {
        return [
            self::OPERATOR_EMPTY,
            self::OPERATOR_NOT_EMPTY,
        ];
    }

    /**
     * Validate condition configuration.
     *
     * @param array<string, mixed> $condition Condition configuration.
     * @return bool True if valid.
     */
    public static function validate_condition(array $condition): bool
    {
        if (!isset($condition['rules']) || !is_array($condition['rules'])) {
            return false;
        }

        foreach ($condition['rules'] as $rule) {
            if (!isset($rule['field']) || empty($rule['field'])) {
                return false;
            }

            $operator = $rule['operator'] ?? self::OPERATOR_EQUALS;
            $valid_operators = array_keys(self::get_operators());

            if (!in_array($operator, $valid_operators, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get fields that a given field depends on (for topological sorting).
     *
     * @param array<string, mixed> $field Field configuration.
     * @return array<int, string> Field IDs this field depends on.
     */
    public static function get_field_dependencies(array $field): array
    {
        $dependencies = [];

        if (!isset($field['condition']['rules'])) {
            return $dependencies;
        }

        foreach ($field['condition']['rules'] as $rule) {
            if (isset($rule['field']) && !empty($rule['field'])) {
                $dependencies[] = $rule['field'];
            }
        }

        return array_unique($dependencies);
    }
}
