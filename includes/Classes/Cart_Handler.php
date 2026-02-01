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

    private function log_to_file($message)
    {
        $log_file = dirname(__DIR__, 2) . '/debug_log.txt';
        $entry = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
        file_put_contents($log_file, $entry, FILE_APPEND);
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
        // Add custom data to cart item (with custom_price for AJAX carts like Modern Cart)
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);

        // Apply custom_price to cart item structure after it's created
        add_filter('woocommerce_add_cart_item', [$this, 'add_cart_item'], 20, 2);

        // Validate before adding to cart
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);

        // Modify cart item price during cart calculation
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_cart_item_price'], 9, 1);

        // Display options in cart
        add_filter('woocommerce_cart_item_name', [$this, 'display_cart_item_options'], 10, 3);

        // Restore cart item from session (with custom_price for AJAX carts like Modern Cart)
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 3);

        // Save options to order meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Display in order emails
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'format_order_item_meta_key'], 10, 3);

        // CRITICAL: After cart is fully loaded, inject custom_price into cart_contents for Modern Cart compatibility
        add_action('woocommerce_cart_loaded_from_session', [$this, 'inject_custom_prices_into_cart'], 20, 1);
    }

    /**
     * Apply calculated price to a product object based on cart item options.
     *
     * @param \WC_Product $product Product object.
     * @param array $cart_item Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return \WC_Product Modified product object.
     */
    public function apply_price_to_product_object(\WC_Product $product, array $cart_item, string $cart_item_key): \WC_Product
    {
        if (!isset($cart_item['ulo_options']) || !is_array($cart_item['ulo_options'])) {
            return $product;
        }

        // Avoid double calculation if already applied
        if (isset($product->ulo_price_applied) && $product->ulo_price_applied === true) {
            return $product;
        }

        $base_price = (float) $product->get_price();
        $quantity = $cart_item['quantity'] ?? 1;
        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'] ?? 0;

        $fields = Data_Handler::get_all_fields($product_id, $variation_id);

        $additional_price = Price_Calculator::get_total_additional_price(
            $fields,
            $cart_item['ulo_options'],
            $base_price,
            $quantity,
            $product
        );

        if ($additional_price > 0) {
            $new_price = $base_price + $additional_price;
            $product->set_price($new_price);
            $product->ulo_price_applied = true;

            // Log for debugging
            $this->log_to_file("apply_price: Applied new price $new_price (Base: $base_price + Add: $additional_price) to item $cart_item_key");
        }

        return $product;
    }

    /**
     * Inject custom prices directly into WC cart_contents.
     * This runs after the cart is fully restored from session.
     * Critical for plugins like Modern Cart that read cart data before calculate_totals.
     *
     * @param \WC_Cart $cart WooCommerce cart object.
     */
    public function inject_custom_prices_into_cart($cart): void
    {
        if (!$cart || empty($cart->cart_contents)) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/plugins/ultra-light-options/debug_log.txt';
        $timestamp = current_time('mysql');

        foreach ($cart->cart_contents as $cart_item_key => &$cart_item) {
            if (!isset($cart_item['ulo_options']) || !is_array($cart_item['ulo_options'])) {
                continue;
            }

            // Get the product object
            if (!isset($cart_item['data']) || !($cart_item['data'] instanceof \WC_Product)) {
                continue;
            }

            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;

            // Debug log
            if (file_exists($log_file)) {
                $msg = "$timestamp - inject_custom_prices: Product=$product_id, Variation=$variation_id\n";
                // Check fields count
                $fields = Data_Handler::get_all_fields($product_id, $variation_id);
                $msg .= "$timestamp - inject_custom_prices: Found " . count($fields) . " fields. Field IDs: " . implode(', ', array_column($fields, 'id')) . "\n";
                file_put_contents($log_file, $msg, FILE_APPEND);
            }

            // Apply price to product object
            $product = $this->apply_price_to_product_object($product, $cart_item, $cart_item_key);

            // Set custom_price directly in cart_contents
            if (isset($product->ulo_price_applied) && $product->ulo_price_applied) {
                $cart->cart_contents[$cart_item_key]['custom_price'] = (float) $product->get_price();
            }
        }
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
        // DEBUG: Log all POST data to understand what Modern Cart sends
        $this->log_to_file('add_cart_item_data: POST keys = ' . print_r(array_keys($_POST), true));

        if (isset($_POST['formEntries'])) {
            $this->log_to_file('add_cart_item_data: formEntries type = ' . gettype($_POST['formEntries']));
            $this->log_to_file('add_cart_item_data: formEntries content = ' . print_r($_POST['formEntries'], true));
        } else {
            $this->log_to_file('add_cart_item_data: formEntries is missing');
        }

        $submitted_ulo = [];

        // Check standard location (WooCommerce default)
        if (isset($_POST['ulo']) && is_array($_POST['ulo'])) {
            $submitted_ulo = $_POST['ulo'];
            $this->log_to_file('add_cart_item_data: Found ulo data in $_POST');
        }
        // Check Modern Cart's nested location
        elseif (isset($_POST['formEntries']) && is_array($_POST['formEntries'])) {
            // Check for serialized data (Robust method handling DOM issues)
            if (isset($_POST['formEntries']['ulo_serialized']) && !empty($_POST['formEntries']['ulo_serialized'])) {
                $decoded = json_decode(stripslashes($_POST['formEntries']['ulo_serialized']), true);
                if (is_array($decoded)) {
                    $submitted_ulo = $decoded;
                    $this->log_to_file('add_cart_item_data: Found and decoded ulo_serialized data');
                }
            }

            // Fallback: Check for array wrapper (Standard Modern Cart behavior if fields were caught)
            if (empty($submitted_ulo) && isset($_POST['formEntries']['ulo'])) {
                $raw_ulo = $_POST['formEntries']['ulo'];
                $this->log_to_file('add_cart_item_data: Found ulo data in $_POST[formEntries]');

                // Modern Cart's JS parser wraps the object in an array: [ { field_id: val } ]
                if (is_array($raw_ulo) && isset($raw_ulo[0]) && is_array($raw_ulo[0])) {
                    $submitted_ulo = $raw_ulo[0];
                    $this->log_to_file('add_cart_item_data: Unwrapped Modern Cart array structure');
                } else {
                    $submitted_ulo = $raw_ulo;
                }
            }
        }

        if (empty($submitted_ulo)) {
            $this->log_to_file('add_cart_item_data: No ulo data found');
            return $cart_item_data;
        }

        $this->log_to_file('add_cart_item_data: Processing ulo data');

        // Sanitize all option values
        $options = [];
        foreach ($submitted_ulo as $field_id => $value) {
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

            // Calculate and store custom_price immediately for AJAX carts (Modern Cart, etc.)
            $product = wc_get_product($variation_id ?: $product_id);
            if ($product) {
                $base_price = (float) $product->get_price();

                // Get quantity from root POST or formEntries (for Modern Cart)
                $quantity = 1;
                if (isset($_POST['quantity'])) {
                    $quantity = max(1, (int) $_POST['quantity']);
                } elseif (isset($_POST['formEntries']['quantity'])) {
                    $quantity = max(1, (int) $_POST['formEntries']['quantity']);
                }

                $fields = Data_Handler::get_all_fields($product_id, $variation_id);

                // DIAGNOSTICS: Deep dive into current configuration
                $all_groups_db = Data_Handler::get_all_field_groups();
                $applicable_groups = Data_Handler::get_field_groups($product_id, $variation_id);
                $this->log_to_file("DIAGNOSTIC: DB has " . count($all_groups_db) . " groups total.");
                $this->log_to_file("DIAGNOSTIC: Applicable groups: " . implode(', ', array_keys($applicable_groups)));
                $this->log_to_file("DIAGNOSTIC: Fields retrieved count: " . count($fields));
                $this->log_to_file("DIAGNOSTIC: Field IDs retrieved: " . implode(', ', array_map(fn($f) => $f['id'] ?? '??', $fields)));

                foreach ($fields as $field) {
                    $fid = $field['id'] ?? 'unknown';
                    if (isset($options[$fid])) {
                        $this->log_to_file("DIAGNOSTIC: Config for selected field [$fid]: " . print_r($field, true));
                    }
                }


                $this->log_to_file('add_cart_item_data: Calculating price. Base: ' . $base_price . ' Qty: ' . $quantity);

                $additional_price = Price_Calculator::get_total_additional_price(
                    $fields,
                    $options,
                    $base_price,
                    $quantity,
                    $product
                );

                $this->log_to_file('add_cart_item_data: Additional price: ' . $additional_price);

                if ($additional_price > 0) {
                    $cart_item_data['custom_price'] = $base_price + $additional_price;
                    $this->log_to_file('add_cart_item_data: Set custom_price to ' . $cart_item_data['custom_price']);
                }
            }
        }

        return $cart_item_data;
    }

    /**
     * Apply custom_price to cart item after it's created.
     * This ensures the product object also has the correct price.
     *
     * @param array<string, mixed> $cart_item_data Cart item data (includes 'data' product object).
     * @param string $cart_item_key Cart item key.
     * @return array<string, mixed> Modified cart item data.
     */
    public function add_cart_item(array $cart_item_data, string $cart_item_key): array
    {
        if (!isset($cart_item_data['ulo_options']) || !is_array($cart_item_data['ulo_options'])) {
            return $cart_item_data;
        }

        // If custom_price was already calculated in add_cart_item_data, apply it to product
        if (isset($cart_item_data['custom_price']) && isset($cart_item_data['data'])) {
            $this->log_to_file('add_cart_item: Applying pre-calculated custom_price: ' . $cart_item_data['custom_price']);
            $cart_item_data['data']->set_price($cart_item_data['custom_price']);
            $cart_item_data['data']->ulo_price_applied = true;
            return $cart_item_data;
        }

        // Fallback: calculate if not already done
        if (isset($cart_item_data['data']) && $cart_item_data['data'] instanceof \WC_Product) {
            $product = $cart_item_data['data'];
            $base_price = (float) $product->get_price();
            $quantity = $cart_item_data['quantity'] ?? 1;
            $fields = Data_Handler::get_all_fields(
                $cart_item_data['product_id'],
                $cart_item_data['variation_id'] ?? 0
            );

            $this->log_to_file('add_cart_item: Calculating fallback price.');

            $additional_price = Price_Calculator::get_total_additional_price(
                $fields,
                $cart_item_data['ulo_options'],
                $base_price,
                $quantity,
                $product
            );

            if ($additional_price > 0) {
                $new_price = $base_price + $additional_price;
                $cart_item_data['custom_price'] = $new_price;
                $product->set_price($new_price);
                $product->ulo_price_applied = true;
                $this->log_to_file('add_cart_item: Set new custom_price: ' . $new_price);
            }
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
    public function get_cart_item_from_session(array $cart_item, array $values, string $key = ''): array
    {
        if (isset($values['ulo_options'])) {
            $cart_item['ulo_options'] = $values['ulo_options'];
        }
        if (isset($values['ulo_unique_key'])) {
            $cart_item['ulo_unique_key'] = $values['ulo_unique_key'];
        }

        // Recalculate and set custom_price for AJAX carts (Modern Cart, etc.)
        if (isset($cart_item['ulo_options']) && isset($cart_item['data']) && $cart_item['data'] instanceof \WC_Product) {
            $product = $cart_item['data'];
            $base_price = (float) $product->get_price();
            $quantity = $cart_item['quantity'] ?? 1;

            $this->log_to_file('session: Restoring item. Base: ' . $base_price);

            $fields = Data_Handler::get_all_fields(
                $cart_item['product_id'],
                $cart_item['variation_id'] ?? 0
            );

            $additional_price = Price_Calculator::get_total_additional_price(
                $fields,
                $cart_item['ulo_options'],
                $base_price,
                $quantity,
                $product
            );

            if ($additional_price > 0) {
                $new_price = $base_price + $additional_price;
                $cart_item['custom_price'] = $new_price;
                $product->set_price($new_price);
                $product->ulo_price_applied = true;
                $this->log_to_file('session: Applied custom_price: ' . $new_price);
            }
        }

        return $cart_item;
    }

    /**
     * Calculate additional price for cart item.
     * Helper method used by calculate_cart_item_price.
     *
     * @param \WC_Product $product Product object.
     * @param array $cart_item Cart item data.
     * @return float Additional price amount.
     */
    private function calculate_additional_price(\WC_Product $product, array $cart_item): float
    {
        if (!isset($cart_item['ulo_options']) || !is_array($cart_item['ulo_options'])) {
            return 0.0;
        }

        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'] ?? 0;
        $quantity = $cart_item['quantity'] ?? 1;
        $selected_options = $cart_item['ulo_options'];

        $fields = Data_Handler::get_all_fields($product_id, $variation_id);
        if (empty($fields)) {
            return 0.0;
        }

        $base_price = (float) $product->get_price();

        return Price_Calculator::get_total_additional_price(
            $fields,
            $selected_options,
            $base_price,
            $quantity,
            $product
        );
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

        if (did_action('woocommerce_before_calculate_totals') >= 20) {
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

            // Avoid double calculation if already applied via woocommerce_cart_item_product filter
            if (isset($product->ulo_price_applied) && $product->ulo_price_applied === true) {
                // Ensure custom_price is set for compatibility (plugins like Modern Cart)
                if (isset($cart->cart_contents[$cart_item_key])) {
                    $cart->cart_contents[$cart_item_key]['custom_price'] = (float) $product->get_price();
                }
                continue;
            }

            $this->log_to_file('calculate: Calculating price for ' . $product_id);

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
                $new_price = $base_price + $additional_price;
                $product->set_price($new_price);
                $this->log_to_file('calculate: Set price to ' . $new_price);

                // Compatibility: Set custom_price for plugins like Modern Cart
                if (isset($cart->cart_contents[$cart_item_key])) {
                    $cart->cart_contents[$cart_item_key]['custom_price'] = $new_price;
                    $this->log_to_file('calculate: Set custom_price for cart contents');
                }

                self::log_debug('Cart item price updated', [
                    'cart_item_key' => $cart_item_key,
                    'base_price' => $base_price,
                    'additional_price' => $additional_price,
                    'new_price' => $new_price,
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
