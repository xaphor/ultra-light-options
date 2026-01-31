<?php
declare(strict_types=1);

/**
 * Frontend class - Handles public-facing display and AJAX.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Frontend;

use ULO\Classes\Data_Handler;
use ULO\Classes\Field_Renderer;
use ULO\Classes\Price_Calculator;
use ULO\Classes\Condition_Engine;
use ULO\Traits\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class.
 */
final class ULO_Frontend
{
    use Logger;

    /**
     * Instance of this class.
     */
    private static ?ULO_Frontend $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): ULO_Frontend
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
        // Display fields on product page
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_product_options'], 10);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Output custom CSS properties
        add_action('wp_head', [$this, 'output_custom_styles'], 20);

        // Register AJAX handlers
        add_action('wp_ajax_ulo_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_ulo_calculate_price', [$this, 'ajax_calculate_price']);

        add_action('wp_ajax_ulo_get_variation_fields', [$this, 'ajax_get_variation_fields']);
        add_action('wp_ajax_nopriv_ulo_get_variation_fields', [$this, 'ajax_get_variation_fields']);
    }

    /**
     * Output custom CSS properties based on admin settings.
     */
    public function output_custom_styles(): void
    {
        // Only output on product pages
        if (!is_product()) {
            return;
        }

        $settings = \ULO\Core\ULO_Core::get_settings();

        // Style settings with defaults
        $accent_color = $settings['accent_color'] ?? '#2271b1';
        $accent_bg = $settings['accent_bg_color'] ?? '#f0f7ff';
        $success_color = $settings['success_color'] ?? '#00a32a';
        $border_color = $settings['border_color'] ?? '#c3c4c7';
        $border_radius = intval($settings['border_radius'] ?? 8);
        $card_style = $settings['card_style'] ?? 'outlined';
        $enable_animations = !empty($settings['enable_animations']);
        $option_layout = $settings['option_layout'] ?? 'cards';
        $show_price_summary = !empty($settings['show_price_summary']);
        ?>
        <style id="ulo-custom-styles">
            .ulo-product-options {
                --ulo-accent-color:
                    <?php echo esc_attr($accent_color); ?>
                ;
                --ulo-accent-bg:
                    <?php echo esc_attr($accent_bg); ?>
                ;
                --ulo-success-color:
                    <?php echo esc_attr($success_color); ?>
                ;
                --ulo-border-color:
                    <?php echo esc_attr($border_color); ?>
                ;
                --ulo-radius:
                    <?php echo $border_radius; ?>
                    px;
                --ulo-card-style:
                    <?php echo esc_attr($card_style); ?>
                ;
                --ulo-animation-duration:
                    <?php echo $enable_animations ? '0.25s' : '0s'; ?>
                ;
            }

            <?php if (!$show_price_summary): ?>
                .ulo-price-summary {
                    display: none !important;
                }

            <?php endif; ?>
            <?php if ($option_layout === 'grid'): ?>
                .ulo-field-group .ulo-options-grid,
                .ulo-field-radio .ulo-options-list,
                .ulo-field-checkbox .ulo-options-list {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 12px;
                }

            <?php elseif ($option_layout === 'list'): ?>
                .ulo-option-card {
                    --ulo-card-style: minimal;
                }

            <?php endif; ?>
        </style>
        <?php
    }

    /**
     * Display product options on product page.
     */
    public function display_product_options(): void
    {
        try {
            global $product;

            // Ensure $product is a WC_Product object
            if (!$product instanceof \WC_Product) {
                return;
            }

            $product_id = $product->get_id();
            $variation_id = 0;

            // For variable products, render empty container that JS will populate
            if ($product->is_type('variable')) {
                if ($this->product_has_field_groups($product_id)) {
                    $this->render_options_container($product_id, $variation_id, true);
                }
                return;
            }

            // For simple products, get field groups
            $field_groups = Data_Handler::get_field_groups($product_id, $variation_id);

            if (empty($field_groups)) {
                return;
            }

            $this->render_options_container($product_id, $variation_id, false, $field_groups);
        } catch (\Throwable $e) {
            // Log error but don't break the page
            self::log_error('Error displaying product options: ' . $e->getMessage(), [
                'product_id' => $product_id ?? 0,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Check if product has any field groups.
     *
     * @param int $product_id Product ID.
     * @return bool True if has field groups.
     */
    private function product_has_field_groups(int $product_id): bool
    {
        try {
            $all_groups = Data_Handler::get_all_field_groups();

            foreach ($all_groups as $group) {
                // Validate group structure
                if (!is_array($group) || !isset($group['rules'])) {
                    continue;
                }

                // Check if group is active
                if (isset($group['active']) && !$group['active']) {
                    continue;
                }

                $rules = $group['rules'];

                // All products
                if (!empty($rules['all_products'])) {
                    return true;
                }

                // Specific product IDs
                if (!empty($rules['product_ids']) && is_array($rules['product_ids']) && in_array($product_id, $rules['product_ids'], true)) {
                    return true;
                }

                // Check variations
                if (!empty($rules['variation_ids']) && is_array($rules['variation_ids'])) {
                    $product = wc_get_product($product_id);
                    if ($product && $product->is_type('variable')) {
                        $variation_ids = $product->get_children();
                        $matching = array_intersect($rules['variation_ids'], $variation_ids);
                        if (!empty($matching)) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            self::log_error('Error checking field groups: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Render options container.
     *
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @param bool $empty Whether to render empty container.
     * @param array<string, array<string, mixed>> $field_groups Field groups.
     */
    private function render_options_container(
        int $product_id,
        int $variation_id,
        bool $empty = false,
        array $field_groups = []
    ): void {
        $product = wc_get_product($product_id);
        $base_price = $product ? (float) $product->get_price() : 0;

        // Get style settings
        $settings = \ULO\Core\ULO_Core::get_settings();
        $card_style = esc_attr($settings['card_style'] ?? 'outlined');
        $option_layout = esc_attr($settings['option_layout'] ?? 'cards');
        ?>
        <div class="ulo-product-options" data-product-id="<?php echo esc_attr((string) $product_id); ?>"
            data-variation-id="<?php echo esc_attr((string) $variation_id); ?>"
            data-base-price="<?php echo esc_attr((string) $base_price); ?>" data-card-style="<?php echo $card_style; ?>"
            data-layout="<?php echo $option_layout; ?>">

            <?php if (!$empty): ?>
                <?php $this->render_fields($field_groups, $product_id, $variation_id); ?>
            <?php endif; ?>

            <!-- GMC-Compliant Price Summary Box -->
            <div class="ulo-price-summary" style="display: none;">
                <div class="ulo-price-row ulo-price-base">
                    <span class="ulo-price-label"><?php esc_html_e('Base Price:', 'ultra-light-options'); ?></span>
                    <span class="ulo-price-value" data-base-price><?php echo wc_price($base_price); ?></span>
                </div>
                <div class="ulo-price-row ulo-price-options">
                    <span class="ulo-price-label"><?php esc_html_e('Options Total:', 'ultra-light-options'); ?></span>
                    <span class="ulo-price-value" data-options-price><?php echo wc_price(0); ?></span>
                </div>
                <div class="ulo-price-row ulo-price-final">
                    <span class="ulo-price-label"><?php esc_html_e('Final Total:', 'ultra-light-options'); ?></span>
                    <span class="ulo-price-value" data-final-price><?php echo wc_price($base_price); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render fields from field groups.
     *
     * @param array<string, array<string, mixed>> $field_groups Field groups.
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     */
    private function render_fields(array $field_groups, int $product_id, int $variation_id): void
    {
        foreach ($field_groups as $group) {
            if (!isset($group['fields']) || !is_array($group['fields'])) {
                continue;
            }

            foreach ($group['fields'] as $field) {
                echo Field_Renderer::render($field, $product_id, $variation_id);
            }
        }
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets(): void
    {
        if (!is_product()) {
            return;
        }

        global $product;

        // Ensure $product is a WC_Product object, not a string or other type
        if (!$product instanceof \WC_Product) {
            $product = wc_get_product(get_the_ID());
        }

        // Still not a valid product? Exit.
        if (!$product instanceof \WC_Product) {
            return;
        }

        // Check if product has options
        $product_id = $product->get_id();
        if (!$this->product_has_field_groups($product_id)) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style('ulo-frontend');

        // Enqueue JS
        wp_enqueue_script('ulo-frontend');
    }

    /**
     * AJAX handler for price calculation.
     */
    public function ajax_calculate_price(): void
    {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulo-ajax-nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultra-light-options')]);
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;
        $selected_options = isset($_POST['options']) ? (array) $_POST['options'] : [];

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'ultra-light-options')]);
        }

        // Get product
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'ultra-light-options')]);
        }

        $base_price = (float) $product->get_price();

        // Get fields
        $fields = Data_Handler::get_all_fields($product_id, $variation_id);

        // Calculate price
        $additional_price = Price_Calculator::get_total_additional_price(
            $fields,
            $selected_options,
            $base_price,
            $quantity,
            $product
        );

        // Get breakdown
        $breakdown = Price_Calculator::get_price_breakdown(
            $fields,
            $selected_options,
            $base_price,
            $quantity,
            $product
        );

        $final_price = $base_price + $additional_price;

        wp_send_json_success([
            'base_price' => $base_price,
            'base_price_formatted' => wc_price($base_price),
            'options_price' => $additional_price,
            'options_price_formatted' => wc_price($additional_price),
            'final_price' => $final_price,
            'final_price_formatted' => wc_price($final_price),
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * AJAX handler for getting fields for a variation.
     */
    public function ajax_get_variation_fields(): void
    {
        // Clean output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulo-ajax-nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultra-light-options')]);
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'ultra-light-options')]);
        }

        // Get field groups
        $field_groups = Data_Handler::get_field_groups($product_id, $variation_id);

        // Render fields
        ob_start();
        if (!empty($field_groups)) {
            $this->render_fields($field_groups, $product_id, $variation_id);
        }
        $fields_html = ob_get_clean();

        // Get base price for variation
        $product = wc_get_product($variation_id ?: $product_id);
        $base_price = $product ? (float) $product->get_price() : 0;

        wp_send_json_success([
            'html' => $fields_html,
            'base_price' => $base_price,
            'base_price_formatted' => wc_price($base_price),
            'has_fields' => !empty($fields_html),
        ]);
    }
}
