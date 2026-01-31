<?php
declare(strict_types=1);

/**
 * Settings class - Plugin settings management.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Admin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class.
 */
final class ULO_Settings
{
    /**
     * Instance of this class.
     */
    private static ?ULO_Settings $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): ULO_Settings
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
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register plugin settings.
     */
    public function register_settings(): void
    {
        register_setting('ulo_settings', 'ulo_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * Sanitize settings.
     *
     * @param array<string, mixed> $settings Input settings.
     * @return array<string, mixed> Sanitized settings.
     */
    public function sanitize_settings(array $settings): array
    {
        $sanitized = [];

        // === STYLE SETTINGS ===

        // Accent color (hex format)
        if (isset($settings['accent_color'])) {
            $sanitized['accent_color'] = sanitize_hex_color($settings['accent_color']) ?: '#2271b1';
        }

        // Accent background color (hex format)
        if (isset($settings['accent_bg_color'])) {
            $sanitized['accent_bg_color'] = sanitize_hex_color($settings['accent_bg_color']) ?: '#f0f7ff';
        }

        // Success/savings color (hex format)
        if (isset($settings['success_color'])) {
            $sanitized['success_color'] = sanitize_hex_color($settings['success_color']) ?: '#00a32a';
        }

        // Border color (hex format)
        if (isset($settings['border_color'])) {
            $sanitized['border_color'] = sanitize_hex_color($settings['border_color']) ?: '#c3c4c7';
        }

        // Border radius (0-24)
        if (isset($settings['border_radius'])) {
            $sanitized['border_radius'] = min(24, max(0, absint($settings['border_radius'])));
        }

        // Card style (minimal, outlined, filled, elegant)
        if (isset($settings['card_style'])) {
            $valid_styles = ['minimal', 'outlined', 'filled', 'elegant'];
            $sanitized['card_style'] = in_array($settings['card_style'], $valid_styles, true)
                ? $settings['card_style']
                : 'outlined';
        }

        // Option layout (cards, list, grid)
        if (isset($settings['option_layout'])) {
            $valid_layouts = ['cards', 'list', 'grid'];
            $sanitized['option_layout'] = in_array($settings['option_layout'], $valid_layouts, true)
                ? $settings['option_layout']
                : 'cards';
        }

        // Enable animations (checkbox)
        $sanitized['enable_animations'] = !empty($settings['enable_animations']);

        // Show price summary (checkbox)
        $sanitized['show_price_summary'] = !empty($settings['show_price_summary']);

        // === ORIGINAL SETTINGS ===

        // Max file size
        $sanitized['max_file_size'] = isset($settings['max_file_size'])
            ? absint($settings['max_file_size'])
            : 5242880;

        // Allowed file types
        if (isset($settings['allowed_file_types'])) {
            if (is_string($settings['allowed_file_types'])) {
                $types = explode(',', $settings['allowed_file_types']);
                $sanitized['allowed_file_types'] = array_map('trim', array_map('sanitize_key', $types));
            } elseif (is_array($settings['allowed_file_types'])) {
                $sanitized['allowed_file_types'] = array_map('sanitize_key', $settings['allowed_file_types']);
            }
        } else {
            $sanitized['allowed_file_types'] = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        }

        // Formula max length
        $sanitized['formula_max_length'] = isset($settings['formula_max_length'])
            ? min(1000, max(50, absint($settings['formula_max_length'])))
            : 500;

        // Formula timeout
        $sanitized['formula_timeout_ms'] = isset($settings['formula_timeout_ms'])
            ? min(1000, max(10, absint($settings['formula_timeout_ms'])))
            : 100;

        // Debug logging
        $sanitized['enable_debug_log'] = !empty($settings['enable_debug_log']);

        return $sanitized;
    }
}

// Initialize
ULO_Settings::get_instance();
