<?php
declare(strict_types=1);

/**
 * Data Handler - Manages field groups storage and retrieval.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Classes;

use ULO\Traits\Sanitization;
use ULO\Traits\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Handler class.
 */
final class Data_Handler
{
    use Sanitization;
    use Logger;

    /**
     * Option name for storing field groups.
     */
    public const OPTION_NAME = 'ulo_field_groups';

    /**
     * Legacy option name (for migration).
     */
    public const LEGACY_OPTION_NAME = 'vpo_field_groups';

    /**
     * Cache key prefix.
     */
    private const CACHE_PREFIX = 'ulo_groups_';

    /**
     * Cache group.
     */
    private const CACHE_GROUP = 'ulo_data';

    /**
     * Get all field groups.
     *
     * @return array<string, array<string, mixed>> All field groups.
     */
    public static function get_all_field_groups(): array
    {
        $cache_key = self::CACHE_PREFIX . 'all';
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $groups = get_option(self::OPTION_NAME, []);

        // Fallback to legacy option
        if (empty($groups)) {
            $groups = get_option(self::LEGACY_OPTION_NAME, []);
        }

        $groups = is_array($groups) ? $groups : [];

        wp_cache_set($cache_key, $groups, self::CACHE_GROUP, 3600);

        return $groups;
    }

    /**
     * Get a single field group by ID.
     *
     * @param string $group_id Group ID.
     * @return array<string, mixed>|null Field group data or null if not found.
     */
    public static function get_field_group(string $group_id): ?array
    {
        $groups = self::get_all_field_groups();
        return $groups[$group_id] ?? null;
    }

    /**
     * Get field groups applicable to a product/variation.
     *
     * @param int $product_id Product ID.
     * @param int $variation_id Optional variation ID.
     * @return array<string, array<string, mixed>> Applicable field groups.
     */
    public static function get_field_groups(int $product_id, int $variation_id = 0): array
    {
        $cache_key = self::CACHE_PREFIX . "product_{$product_id}_{$variation_id}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $all_groups = self::get_all_field_groups();
        $applicable_groups = [];

        // Get product categories for category-based matching
        $product_categories = wc_get_product_term_ids($product_id, 'product_cat');

        foreach ($all_groups as $group_id => $group) {
            // Skip groups that are inactive or don't have required structure
            if (!is_array($group)) {
                continue;
            }

            // Check if group is active (default to true for backwards compatibility)
            if (isset($group['active']) && !$group['active']) {
                continue;
            }

            // Must have rules to determine applicability
            if (!isset($group['rules'])) {
                continue;
            }

            // Fields can be empty but must exist as array if present
            if (!isset($group['fields'])) {
                $group['fields'] = [];
            }

            if (self::group_applies_to_product($group['rules'], $product_id, $variation_id, $product_categories)) {
                $applicable_groups[$group_id] = $group;
            }
        }

        wp_cache_set($cache_key, $applicable_groups, self::CACHE_GROUP, 1800);

        return $applicable_groups;
    }

    /**
     * Check if a group applies to a product.
     *
     * @param array<string, mixed> $rules Group rules.
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @param array<int, int> $product_categories Product category IDs.
     * @return bool True if group applies.
     */
    private static function group_applies_to_product(
        array $rules,
        int $product_id,
        int $variation_id,
        array $product_categories
    ): bool {
        // All products
        if (!empty($rules['all_products'])) {
            return true;
        }

        // Specific product IDs
        if (!empty($rules['product_ids']) && is_array($rules['product_ids'])) {
            if (in_array($product_id, $rules['product_ids'], true)) {
                return true;
            }
        }

        // Specific variation IDs
        if ($variation_id > 0 && !empty($rules['variation_ids']) && is_array($rules['variation_ids'])) {
            if (in_array($variation_id, $rules['variation_ids'], true)) {
                return true;
            }
        }

        // Product categories
        if (!empty($rules['category_ids']) && is_array($rules['category_ids'])) {
            $matching_categories = array_intersect($rules['category_ids'], $product_categories);
            if (!empty($matching_categories)) {
                return true;
            }
        }

        // Check if variation's parent matches
        if ($variation_id > 0) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->get_parent_id() === $product_id) {
                if (!empty($rules['product_ids']) && is_array($rules['product_ids'])) {
                    if (in_array($product_id, $rules['product_ids'], true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all fields from applicable groups.
     *
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @return array<int, array<string, mixed>> All fields.
     */
    public static function get_all_fields(int $product_id, int $variation_id = 0): array
    {
        $groups = self::get_field_groups($product_id, $variation_id);
        $all_fields = [];

        foreach ($groups as $group) {
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $all_fields[] = $field;
                }
            }
        }

        return $all_fields;
    }

    /**
     * Save field group.
     *
     * @param array<string, mixed> $group_data Field group data.
     * @return string|null Group ID on success, null on failure.
     */
    public static function save_field_group(array $group_data): ?string
    {
        // Generate ID if not provided
        if (empty($group_data['group_id'])) {
            $group_data['group_id'] = 'ulo_group_' . uniqid();
        }

        $group_id = sanitize_key($group_data['group_id']);

        // Sanitize group data
        $group = [
            'group_id' => $group_id,
            'name' => sanitize_text_field($group_data['name'] ?? ''),
            'active' => !empty($group_data['active']),
            'rules' => self::sanitize_rules($group_data['rules'] ?? []),
            'fields' => [],
            'priority' => (int) ($group_data['priority'] ?? 10),
            'created_at' => $group_data['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        // Sanitize fields
        if (isset($group_data['fields']) && is_array($group_data['fields'])) {
            foreach ($group_data['fields'] as $field) {
                $sanitized_field = self::sanitize_field($field);
                if ($sanitized_field) {
                    $group['fields'][] = $sanitized_field;
                }
            }
        }

        // Get all groups and update
        $all_groups = self::get_all_field_groups();
        $all_groups[$group_id] = $group;

        // Sort by priority
        uasort($all_groups, static function (array $a, array $b): int {
            return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
        });

        // Save to database
        $result = update_option(self::OPTION_NAME, $all_groups);

        // Clear cache
        self::clear_cache();

        if ($result) {
            self::log_info('Field group saved', ['group_id' => $group_id]);
            return $group_id;
        }

        // Check if data actually exists (update_option returns false if unchanged)
        $verify = get_option(self::OPTION_NAME, []);
        if (isset($verify[$group_id])) {
            return $group_id;
        }

        self::log_error('Failed to save field group', ['group_id' => $group_id]);
        return null;
    }

    /**
     * Delete field group.
     *
     * @param string $group_id Group ID.
     * @return bool True on success.
     */
    public static function delete_field_group(string $group_id): bool
    {
        $group_id = sanitize_key($group_id);
        $all_groups = self::get_all_field_groups();

        if (!isset($all_groups[$group_id])) {
            return false;
        }

        unset($all_groups[$group_id]);

        $result = update_option(self::OPTION_NAME, $all_groups);

        // Clear cache
        self::clear_cache();

        if ($result) {
            self::log_info('Field group deleted', ['group_id' => $group_id]);
        }

        return $result;
    }

    /**
     * Duplicate field group.
     *
     * @param string $group_id Group ID to duplicate.
     * @return string|null New group ID on success.
     */
    public static function duplicate_field_group(string $group_id): ?string
    {
        $group = self::get_field_group($group_id);

        if (!$group) {
            return null;
        }

        // Create new group with modified name and ID
        $new_group = $group;
        $new_group['group_id'] = 'ulo_group_' . uniqid();
        $new_group['name'] = sprintf(__('%s (Copy)', 'ultra-light-options'), $group['name'] ?? '');
        $new_group['created_at'] = current_time('mysql');
        $new_group['updated_at'] = current_time('mysql');

        return self::save_field_group($new_group);
    }

    /**
     * Export field groups as JSON.
     *
     * @param array<int, string>|null $group_ids Specific group IDs or null for all.
     * @return string JSON string.
     */
    public static function export_groups(?array $group_ids = null): string
    {
        $all_groups = self::get_all_field_groups();

        if ($group_ids !== null) {
            $all_groups = array_filter(
                $all_groups,
                static fn(string $key): bool => in_array($key, $group_ids, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        $export_data = [
            'version' => ULO_VERSION,
            'exported_at' => current_time('mysql'),
            'groups' => $all_groups,
        ];

        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }

    /**
     * Import field groups from JSON.
     *
     * @param string $json JSON string.
     * @param bool $overwrite Whether to overwrite existing groups.
     * @return array{success: int, failed: int, errors: array<int, string>} Import results.
     */
    public static function import_groups(string $json, bool $overwrite = false): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $data = json_decode($json, true);

        if (!$data || !isset($data['groups'])) {
            $results['errors'][] = __('Invalid JSON format', 'ultra-light-options');
            return $results;
        }

        foreach ($data['groups'] as $group_id => $group) {
            // Check if group exists
            if (!$overwrite && self::get_field_group($group_id)) {
                // Generate new ID
                $group['group_id'] = 'ulo_group_' . uniqid();
            }

            $saved_id = self::save_field_group($group);

            if ($saved_id) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    __('Failed to import group: %s', 'ultra-light-options'),
                    $group['name'] ?? $group_id
                );
            }
        }

        return $results;
    }

    /**
     * Clear all caches.
     */
    public static function clear_cache(): void
    {
        wp_cache_delete(self::CACHE_PREFIX . 'all', self::CACHE_GROUP);
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Get field by ID from any group.
     *
     * @param string $field_id Field ID.
     * @param int|null $product_id Optional product ID to limit search.
     * @param int|null $variation_id Optional variation ID.
     * @return array<string, mixed>|null Field configuration or null.
     */
    public static function get_field_by_id(string $field_id, ?int $product_id = null, ?int $variation_id = null): ?array
    {
        if ($product_id !== null) {
            $groups = self::get_field_groups($product_id, $variation_id ?? 0);
        } else {
            $groups = self::get_all_field_groups();
        }

        foreach ($groups as $group) {
            if (!isset($group['fields']) || !is_array($group['fields'])) {
                continue;
            }

            foreach ($group['fields'] as $field) {
                if (($field['id'] ?? '') === $field_id) {
                    return $field;
                }
            }
        }

        return null;
    }
}
