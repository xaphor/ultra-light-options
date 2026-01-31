<?php
declare(strict_types=1);

/**
 * Cart Handler - Manages cart item data, pricing, and order integration.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Classes;

use ULO\Traits\Logger;
use ULO\Traits\Sanitization;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cart Handler class.
 */
final class Cart_Handler
{
    use Logger;
    use Sanitization;

    /**
     * Instance of this class.
     */
    private static ?Cart_Handler $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): Cart_Handler
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
        // Add custom data to cart item
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);

        // Validate before adding to cart
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);

        // Modify cart item price
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_cart_item_price'], 10, 1);

        // Display options in cart
        add_filter('woocommerce_cart_item_name', [$this, 'display_cart_item_options'], 10, 3);

        // Get cart item from session
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);

        // Save options to order meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Display in order emails
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'format_order_item_meta_key'], 10, 3);
    }

    /**
     * Validate add to cart for required fields.
     *
     * @param bool $valid Current validation status.
     * @param int $product_id Product ID.
     * @param int $quantity Quantity.
     * @return bool Validation result.
     */
    public function validate_add_to_cart(bool $valid, int $product_id, int $quantity): bool
    {
        if (!$valid) {
            return false;
        }

        // Get variation ID if applicable
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;

        // Get fields for this product
        $fields = Data_Handler::get_all_fields($product_id, $variation_id);

        if (empty($fields)) {
            return true;
        }

        // Get submitted options
        $submitted_options = isset($_POST['ulo']) && is_array($_POST['ulo']) ? $_POST['ulo'] : [];

        foreach ($fields as $field) {
            if (!isset($field['id'], $field['required'])) {
                continue;
            }

            if (!$field['required']) {
                continue;
            }

            // Check if field should be visible (based on conditions)
            if (!Condition_Engine::should_field_be_visible($field, $submitted_options)) {
                continue;
            }

            $field_id = $field['id'];
            $field_label = $field['label'] ?? $field_id;

            // Check if required field has value
            if (!isset($submitted_options[$field_id]) || $submitted_options[$field_id] === '') {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: field label */
                        __('"%s" is a required field.', 'ultra-light-options'),
                        esc_html($field_label)
                    ),
                    'error'
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Add custom data to cart item.
     *
     * @param array<string, mixed> $cart_item_data Cart item data.
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @return array<string, mixed> Modified cart item data.
     */
    public function add_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array
    {
        if (!isset($_POST['ulo']) || !is_array($_POST['ulo'])) {
            return $cart_item_data;
        }

        // Sanitize all option values
        $options = [];
        foreach ($_POST['ulo'] as $field_id => $value) {
            $field_id = sanitize_key($field_id);

            if (is_array($value)) {
                $value = array_map('sanitize_text_field', $value);
            } else {
                $value = sanitize_text_field($value);
            }

            if ($value !== '' && $value !== []) {
                $options[$field_id] = $value;
            }
        }

        if (!empty($options)) {
            $cart_item_data['ulo_options'] = $options;

            // Create unique cart item key to allow same product with different options
            $cart_item_data['ulo_unique_key'] = md5(wp_json_encode($options));
        }

        return $cart_item_data;
    }

    /**
     * Restore cart item data from session.
     *
     * @param array<string, mixed> $cart_item Cart item.
     * @param array<string, mixed> $values Session values.
     * @return array<string, mixed> Cart item.
     */
    public function get_cart_item_from_session(array $cart_item, array $values): array
    {
        if (isset($values['ulo_options'])) {
            $cart_item['ulo_options'] = $values['ulo_options'];
        }
        if (isset($values['ulo_unique_key'])) {
            $cart_item['ulo_unique_key'] = $values['ulo_unique_key'];
        }
        return $cart_item;
    }

    /**
     * Calculate cart item price with additional options.
     *
     * @param \WC_Cart $cart Cart object.
     */
    public function calculate_cart_item_price(\WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['ulo_options']) || !is_array($cart_item['ulo_options'])) {
                continue;
            }

            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;
            $quantity = $cart_item['quantity'];
            $selected_options = $cart_item['ulo_options'];

            /** @var \WC_Product $product */
            $product = $cart_item['data'];
            $base_price = (float) $product->get_price();

            // Get applicable fields
            $fields = Data_Handler::get_all_fields($product_id, $variation_id);

            if (empty($fields)) {
                continue;
            }

            // Calculate additional price
            $additional_price = Price_Calculator::get_total_additional_price(
                $fields,
                $selected_options,
                $base_price,
                $quantity,
                $product
            );

            if ($additional_price > 0) {
                // Set new price (base + additional)
                $product->set_price($base_price + $additional_price);

                self::log_debug('Cart item price updated', [
                    'cart_item_key' => $cart_item_key,
                    'base_price' => $base_price,
                    'additional_price' => $additional_price,
                    'new_price' => $base_price + $additional_price,
                ]);
            }
        }
    }

    /**
     * Display options in cart item name.
     *
     * @param string $name Product name.
     * @param array<string, mixed> $cart_item Cart item.
     * @param string $cart_item_key Cart item key.
     * @return string Modified name.
     */
    public function display_cart_item_options(string $name, array $cart_item, string $cart_item_key): string
    {
        if (!isset($cart_item['ulo_options']) || !is_array($cart_item['ulo_options'])) {
            return $name;
        }

        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'] ?? 0;
        $quantity = $cart_item['quantity'];
        $selected_options = $cart_item['ulo_options'];

        /** @var \WC_Product $product */
        $product = $cart_item['data'];
        $base_price = (float) $product->get_regular_price();

        // Get fields for labels
        $fields = Data_Handler::get_all_fields($product_id, $variation_id);

        // Get price breakdown
        $breakdown = Price_Calculator::get_price_breakdown(
            $fields,
            $selected_options,
            $base_price,
            $quantity,
            $product
        );

        if (empty($breakdown)) {
            return $name;
        }

        $options_html = '<div class="ulo-cart-options">';
        foreach ($breakdown as $item) {
            $options_html .= sprintf(
                '<div class="ulo-cart-option"><strong>%s:</strong> %s %s</div>',
                esc_html($item['label']),
                esc_html($item['value']),
                $item['price'] > 0 ? '<span class="ulo-cart-option-price">(+' . $item['formatted_price'] . ')</span>' : ''
            );
        }
        $options_html .= '</div>';

        return $name . $options_html;
    }

    /**
     * Save options to order item meta.
     *
     * @param \WC_Order_Item_Product $item Order item.
     * @param string $cart_item_key Cart item key.
     * @param array<string, mixed> $values Cart item values.
     * @param \WC_Order $order Order object.
     */
    public function save_order_item_meta(
        \WC_Order_Item_Product $item,
        string $cart_item_key,
        array $values,
        \WC_Order $order
    ): void {
        if (!isset($values['ulo_options']) || !is_array($values['ulo_options'])) {
            return;
        }

        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $selected_options = $values['ulo_options'];

        // Get fields for labels and proper display
        $fields = Data_Handler::get_all_fields($product_id, $variation_id);

        // Store raw options data for reference
        $item->add_meta_data('_ulo_options_raw', $selected_options, true);

        // Add readable meta for each selected option
        foreach ($fields as $field) {
            $field_id = $field['id'] ?? '';
            $field_label = $field['label'] ?? $field_id;

            if (!isset($selected_options[$field_id])) {
                continue;
            }

            // Check condition
            if (!Condition_Engine::should_field_be_visible($field, $selected_options)) {
                continue;
            }

            $value = $selected_options[$field_id];
            $display_value = self::get_display_value_for_order($field, $value);

            if ($display_value !== '') {
                $item->add_meta_data($field_label, $display_value);
            }
        }
    }

    /**
     * Get display value for order meta.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed $value Selected value.
     * @return string Display value.
     */
    private static function get_display_value_for_order(array $field, mixed $value): string
    {
        $field_type = $field['type'] ?? '';

        return match ($field_type) {
            'radio', 'radio_switch', 'select' => self::get_option_label($field, $value),
            'checkbox' => ($value === '1' || $value === true) ? __('Yes', 'ultra-light-options') : '',
            'checkbox_group' => is_array($value) ? implode(', ', array_map(
                fn($v) => self::get_option_label($field, $v),
                $value
            )) : '',
            'file' => self::format_file_value($value),
            default => (string) $value,
        };
    }

    /**
     * Get option label from field configuration.
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
     * Format file value for display.
     *
     * @param mixed $value File value (path or URL).
     * @return string Formatted display value.
     */
    private static function format_file_value(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Extract filename from path
        $filename = basename((string) $value);

        // Return linked filename if it's a URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return sprintf('<a href="%s" target="_blank">%s</a>', esc_url($value), esc_html($filename));
        }

        return esc_html($filename);
    }

    /**
     * Format order item meta key for display.
     *
     * @param string $display_key Display key.
     * @param \WC_Meta_Data $meta Meta object.
     * @param \WC_Order_Item $item Order item.
     * @return string Formatted key.
     */
    public function format_order_item_meta_key(string $display_key, \WC_Meta_Data $meta, \WC_Order_Item $item): string
    {
        // Hide raw options data from display
        if ($meta->key === '_ulo_options_raw') {
            return '';
        }

        return $display_key;
    }

    /**
     * Get additional price for a specific cart item.
     *
     * @param array<string, mixed> $cart_item Cart item.
     * @return float Additional price.
     */
    public static function get_cart_item_additional_price(array $cart_item): float
    {
        if (!isset($cart_item['ulo_options']) || !is_array($cart_item['ulo_options'])) {
            return 0.0;
        }

        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'] ?? 0;
        $quantity = $cart_item['quantity'];
        $selected_options = $cart_item['ulo_options'];

        /** @var \WC_Product $product */
        $product = $cart_item['data'];
        $base_price = (float) $product->get_regular_price();

        $fields = Data_Handler::get_all_fields($product_id, $variation_id);

        return Price_Calculator::get_total_additional_price(
            $fields,
            $selected_options,
            $base_price,
            $quantity,
            $product
        );
    }
}
