<?php
declare(strict_types=1);

/**
 * Modern Cart Compatibility
 *
 * Ensures Ultra Light Options works correctly with Modern Cart Starter plugin.
 *
 * @package UltraLightOptions
 * @since 2.1.1
 */

namespace ULO\Compatibility;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modern Cart compatibility class.
 */
final class Modern_Cart
{
    /**
     * Instance of this class.
     */
    private static ?Modern_Cart $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): Modern_Cart
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
        // Only run if Modern Cart is active
        if (!$this->is_modern_cart_active()) {
            return;
        }

        // Force price recalculation on Modern Cart AJAX requests
        add_action('wp_ajax_modern_cart_get_cart', [$this, 'force_price_calculation'], 5);
        add_action('wp_ajax_nopriv_modern_cart_get_cart', [$this, 'force_price_calculation'], 5);

        // Ensure cart fragments include updated prices
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'add_cart_fragments'], 20, 1);
    }

    /**
     * Check if Modern Cart is active.
     *
     * @return bool
     */
    private function is_modern_cart_active(): bool
    {
        return class_exists('Modern_Cart') ||
            defined('MODERN_CART_VERSION') ||
            function_exists('modern_cart_init');
    }

    /**
     * Force price calculation before Modern Cart AJAX response.
     */
    public function force_price_calculation(): void
    {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        // Trigger price recalculation
        WC()->cart->calculate_totals();
    }

    /**
     * Add cart fragments for Modern Cart.
     *
     * @param array<string, string> $fragments Cart fragments.
     * @return array<string, string> Modified fragments.
     */
    public function add_cart_fragments(array $fragments): array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return $fragments;
        }

        // Add subtotal fragment
        ob_start();
        wc_cart_totals_subtotal_html();
        $fragments['.cart-subtotal .woocommerce-Price-amount'] = ob_get_clean();

        // Add total fragment  
        ob_start();
        echo wp_kses_post(WC()->cart->get_total());
        $fragments['.order-total .woocommerce-Price-amount'] = ob_get_clean();

        return $fragments;
    }
}
