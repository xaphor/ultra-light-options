<?php
declare(strict_types=1);

/**
 * AJAX Handler - Handles all AJAX endpoints.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Frontend;

use ULO\Classes\Data_Handler;
use ULO\Classes\Formula_Parser;
use ULO\Traits\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler class.
 */
final class ULO_Ajax_Handler
{
    use Logger;

    /**
     * Instance of this class.
     */
    private static ?ULO_Ajax_Handler $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): ULO_Ajax_Handler
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks(): void
    {
        // Admin AJAX handlers
        add_action('wp_ajax_ulo_save_field_group', [$this, 'ajax_save_field_group']);
        add_action('wp_ajax_ulo_delete_field_group', [$this, 'ajax_delete_field_group']);
        add_action('wp_ajax_ulo_duplicate_field_group', [$this, 'ajax_duplicate_field_group']);
        add_action('wp_ajax_ulo_get_field_group', [$this, 'ajax_get_field_group']);
        add_action('wp_ajax_ulo_get_all_field_groups', [$this, 'ajax_get_all_field_groups']);
        add_action('wp_ajax_ulo_validate_formula', [$this, 'ajax_validate_formula']);
        add_action('wp_ajax_ulo_test_formula', [$this, 'ajax_test_formula']);
        add_action('wp_ajax_ulo_export_groups', [$this, 'ajax_export_groups']);
        add_action('wp_ajax_ulo_import_groups', [$this, 'ajax_import_groups']);
        add_action('wp_ajax_ulo_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_ulo_search_variations', [$this, 'ajax_search_variations']);
    }

    /**
     * Save field group.
     */
    public function ajax_save_field_group(): void
    {
        $this->verify_admin_nonce();

        $group_data = isset($_POST['group']) ? $_POST['group'] : [];

        if (empty($group_data)) {
            wp_send_json_error(['message' => __('No group data provided.', 'ultra-light-options')]);
        }

        // Parse JSON if string
        if (is_string($group_data)) {
            $group_data = json_decode(stripslashes($group_data), true);
        }

        $group_id = Data_Handler::save_field_group($group_data);

        if ($group_id) {
            wp_send_json_success([
                'message' => __('Field group saved successfully.', 'ultra-light-options'),
                'group_id' => $group_id,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save field group.', 'ultra-light-options')]);
        }
    }

    /**
     * Delete field group.
     */
    public function ajax_delete_field_group(): void
    {
        $this->verify_admin_nonce();

        $group_id = isset($_POST['group_id']) ? sanitize_key($_POST['group_id']) : '';

        if (empty($group_id)) {
            wp_send_json_error(['message' => __('No group ID provided.', 'ultra-light-options')]);
        }

        $result = Data_Handler::delete_field_group($group_id);

        if ($result) {
            wp_send_json_success(['message' => __('Field group deleted.', 'ultra-light-options')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete field group.', 'ultra-light-options')]);
        }
    }

    /**
     * Duplicate field group.
     */
    public function ajax_duplicate_field_group(): void
    {
        $this->verify_admin_nonce();

        $group_id = isset($_POST['group_id']) ? sanitize_key($_POST['group_id']) : '';

        if (empty($group_id)) {
            wp_send_json_error(['message' => __('No group ID provided.', 'ultra-light-options')]);
        }

        $new_group_id = Data_Handler::duplicate_field_group($group_id);

        if ($new_group_id) {
            wp_send_json_success([
                'message' => __('Field group duplicated.', 'ultra-light-options'),
                'group_id' => $new_group_id,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to duplicate field group.', 'ultra-light-options')]);
        }
    }

    /**
     * Get single field group.
     */
    public function ajax_get_field_group(): void
    {
        $this->verify_admin_nonce();

        $group_id = isset($_POST['group_id']) ? sanitize_key($_POST['group_id']) : '';

        if (empty($group_id)) {
            wp_send_json_error(['message' => __('No group ID provided.', 'ultra-light-options')]);
        }

        $group = Data_Handler::get_field_group($group_id);

        if ($group) {
            wp_send_json_success(['group' => $group]);
        } else {
            wp_send_json_error(['message' => __('Field group not found.', 'ultra-light-options')]);
        }
    }

    /**
     * Get all field groups.
     */
    public function ajax_get_all_field_groups(): void
    {
        $this->verify_admin_nonce();

        $groups = Data_Handler::get_all_field_groups();

        wp_send_json_success(['groups' => $groups]);
    }

    /**
     * Validate formula syntax.
     */
    public function ajax_validate_formula(): void
    {
        $this->verify_admin_nonce();

        $formula = isset($_POST['formula']) ? sanitize_text_field($_POST['formula']) : '';

        if (empty($formula)) {
            wp_send_json_error(['message' => __('No formula provided.', 'ultra-light-options')]);
        }

        $is_valid = Formula_Parser::validate($formula);

        if ($is_valid) {
            wp_send_json_success(['message' => __('Formula is valid.', 'ultra-light-options')]);
        } else {
            wp_send_json_error(['message' => __('Invalid formula syntax.', 'ultra-light-options')]);
        }
    }

    /**
     * Test formula with sample values.
     */
    public function ajax_test_formula(): void
    {
        $this->verify_admin_nonce();

        $formula = isset($_POST['formula']) ? sanitize_text_field($_POST['formula']) : '';
        $variables = isset($_POST['variables']) ? (array) $_POST['variables'] : [];

        if (empty($formula)) {
            wp_send_json_error(['message' => __('No formula provided.', 'ultra-light-options')]);
        }

        // Sanitize variables
        $clean_vars = [];
        foreach ($variables as $key => $value) {
            $clean_vars[sanitize_key($key)] = (float) $value;
        }

        try {
            $result = Formula_Parser::evaluate($formula, $clean_vars);
            wp_send_json_success([
                'result' => $result,
                'formatted' => wc_price($result),
                'message' => sprintf(
                    /* translators: %s: calculated result */
                    __('Result: %s', 'ultra-light-options'),
                    wc_price($result)
                ),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Export field groups.
     */
    public function ajax_export_groups(): void
    {
        $this->verify_admin_nonce();

        $group_ids = isset($_POST['group_ids']) ? (array) $_POST['group_ids'] : null;

        $json = Data_Handler::export_groups($group_ids);

        wp_send_json_success([
            'json' => $json,
            'filename' => 'ulo-field-groups-' . gmdate('Y-m-d') . '.json',
        ]);
    }

    /**
     * Import field groups.
     */
    public function ajax_import_groups(): void
    {
        $this->verify_admin_nonce();

        $json = isset($_POST['json']) ? stripslashes($_POST['json']) : '';
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';

        if (empty($json)) {
            wp_send_json_error(['message' => __('No data provided.', 'ultra-light-options')]);
        }

        $results = Data_Handler::import_groups($json, $overwrite);

        if ($results['success'] > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %d: number of imported groups */
                    __('Successfully imported %d field group(s).', 'ultra-light-options'),
                    $results['success']
                ),
                'results' => $results,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Import failed.', 'ultra-light-options'),
                'errors' => $results['errors'],
            ]);
        }
    }

    /**
     * Search products for admin UI.
     */
    public function ajax_search_products(): void
    {
        $this->verify_admin_nonce();

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        if (strlen($search) < 2) {
            wp_send_json_success(['products' => []]);
        }

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $search,
            'posts_per_page' => 20,
        ];

        $query = new \WP_Query($args);
        $products = [];

        foreach ($query->posts as $post) {
            $products[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
            ];
        }

        wp_send_json_success(['products' => $products]);
    }

    /**
     * Search variations for a product.
     */
    public function ajax_search_variations(): void
    {
        $this->verify_admin_nonce();

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $variations = [];

        // If product ID provided, get its variations
        if ($product_id) {
            $product = wc_get_product($product_id);

            if ($product && $product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $attributes = $variation->get_variation_attributes();
                        $attr_string = implode(', ', array_filter($attributes));
                        $variations[] = [
                            'id' => $variation_id,
                            'title' => $product->get_title() . ' - ' . $attr_string,
                            'attributes' => $attributes,
                        ];
                    }
                }
            }
        }
        // If search term provided, global search for parent products first
        elseif (!empty($search)) {
            $args = [
                'post_type' => 'product', // Search products, not variations directly
                'post_status' => 'publish',
                's' => $search,
                'posts_per_page' => 10,
            ];

            $query = new \WP_Query($args);

            foreach ($query->posts as $post) {
                $product = wc_get_product($post->ID);

                // Only process variable products
                if ($product && $product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation) {
                            $attributes = $variation->get_variation_attributes();
                            $attr_string = implode(', ', array_filter($attributes));

                            $variations[] = [
                                'id' => $variation_id,
                                'title' => $product->get_title() . ' - ' . $attr_string,
                                'attributes' => $attributes,
                            ];
                        }
                    }
                }
            }
            // Limit results to avoid massive response
            $variations = array_slice($variations, 0, 50);
        }

        wp_send_json_success(['variations' => $variations]);
    }

    /**
     * Verify admin nonce.
     */
    private function verify_admin_nonce(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulo-admin-nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultra-light-options')]);
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'ultra-light-options')]);
        }
    }
}

// Initialize
ULO_Ajax_Handler::get_instance();
