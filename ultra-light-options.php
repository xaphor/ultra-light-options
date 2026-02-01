<?php
declare(strict_types=1);

/**
 * Plugin Name: Ultra-Light Product Options
 * Plugin URI: https://github.com/xaphor/ultra-light-options
 * Description: A lightweight, GMC-compliant WooCommerce plugin for adding custom product options with conditional logic and advanced pricing (flat, quantity-based, tiered, formula, field value). Zero Layout Shift, No jQuery.
 * Version: 2.1.1
 * Author: Zaffarullah
 * Author URI: https://github.com/xaphor
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultra-light-options
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package UltraLightOptions
 * @version 2.1.1
 */


namespace ULO;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('ULO_VERSION', '2.1.1');
define('ULO_PLUGIN_FILE', __FILE__);
define('ULO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ULO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ULO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Declare WooCommerce HPOS compatibility.
 */
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ULO_PLUGIN_FILE, true);
    }
});

/**
 * Check if WooCommerce is active.
 */
function ulo_check_woocommerce_active(): bool
{
    return class_exists('WooCommerce');
}

/**
 * Display notice if WooCommerce is not active.
 */
function ulo_woocommerce_missing_notice(): void
{
    ?>
    <div class="notice notice-error">
        <p>
            <strong>
                <?php esc_html_e('Ultra-Light Product Options', 'ultra-light-options'); ?>:
            </strong>
            <?php esc_html_e('This plugin requires WooCommerce to be installed and active.', 'ultra-light-options'); ?>
        </p>
    </div>
    <?php
}

/**
 * Main plugin class using Singleton pattern.
 */
final class Ultra_Light_Options
{
    private static ?Ultra_Light_Options $instance = null;

    /**
     * Get plugin instance.
     */
    public static function get_instance(): Ultra_Light_Options
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton.
     */
    private function __construct()
    {
        // Constructor is empty - initialization happens in init()
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization.
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Load plugin dependencies.
     */
    private function load_dependencies(): void
    {
        // Load Traits first
        require_once ULO_PLUGIN_DIR . 'includes/Traits/Sanitization.php';
        require_once ULO_PLUGIN_DIR . 'includes/Traits/Logger.php';

        // Load Core Classes
        require_once ULO_PLUGIN_DIR . 'includes/Classes/Formula_Parser.php';
        require_once ULO_PLUGIN_DIR . 'includes/Classes/Price_Calculator.php';
        require_once ULO_PLUGIN_DIR . 'includes/Classes/Condition_Engine.php';
        require_once ULO_PLUGIN_DIR . 'includes/Classes/Field_Renderer.php';
        require_once ULO_PLUGIN_DIR . 'includes/Classes/Data_Handler.php';
        require_once ULO_PLUGIN_DIR . 'includes/Classes/Cart_Handler.php';
        require_once ULO_PLUGIN_DIR . 'includes/Classes/File_Upload_Handler.php';

        // Load Frontend (always needed for AJAX)
        require_once ULO_PLUGIN_DIR . 'includes/Frontend/class-ulo-frontend.php';
        require_once ULO_PLUGIN_DIR . 'includes/Frontend/class-ulo-ajax-handler.php';

        // Load Admin
        if (is_admin()) {
            require_once ULO_PLUGIN_DIR . 'includes/Admin/class-ulo-admin.php';
            require_once ULO_PLUGIN_DIR . 'includes/Admin/class-ulo-field-builder.php';
            require_once ULO_PLUGIN_DIR . 'includes/Admin/class-ulo-settings.php';
        }

        // Core initialization
        require_once ULO_PLUGIN_DIR . 'includes/class-ulo-core.php';
    }

    /**
     * Initialize plugin (called on plugins_loaded).
     */
    public function init(): void
    {
        // Check WooCommerce dependency.
        if (!ulo_check_woocommerce_active()) {
            add_action('admin_notices', '\ULO\ulo_woocommerce_missing_notice');
            return;
        }

        // Load plugin files.
        $this->load_dependencies();

        // Initialize hooks.
        $this->init_hooks();

        // Run migration if needed
        $this->maybe_migrate_from_vpo();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks(): void
    {
        // Load text domain.
        add_action('init', [$this, 'load_textdomain']);

        // Initialize core functionality.
        add_action('wp_loaded', [$this, 'init_core'], 10);
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'ultra-light-options',
            false,
            dirname(ULO_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize core functionality.
     */
    public function init_core(): void
    {
        // Initialize core
        if (class_exists('\ULO\Core\ULO_Core')) {
            \ULO\Core\ULO_Core::get_instance();
        }

        // Initialize Cart Handler (always needed)
        if (class_exists('\ULO\Classes\Cart_Handler')) {
            \ULO\Classes\Cart_Handler::get_instance();
        }

        // Initialize Admin
        if (is_admin() && !wp_doing_ajax()) {
            if (class_exists('\ULO\Admin\ULO_Admin')) {
                \ULO\Admin\ULO_Admin::get_instance();
            }
        }

        // Initialize Frontend (for AJAX and frontend)
        if (!is_admin() || wp_doing_ajax()) {
            if (class_exists('\ULO\Frontend\ULO_Frontend')) {
                \ULO\Frontend\ULO_Frontend::get_instance();
            }
        }
    }

    /**
     * Migrate data from old VPO plugin if exists.
     */
    private function maybe_migrate_from_vpo(): void
    {
        // Check if already migrated
        if (get_option('ulo_migration_from_vpo')) {
            return;
        }

        // Check if old data exists
        $old_groups = get_option('vpo_field_groups', []);
        if (empty($old_groups)) {
            return;
        }

        // Migrate data
        $new_groups = [];
        foreach ($old_groups as $group_id => $group) {
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as &$field) {
                    // Add price_type to options
                    if (isset($field['options']) && is_array($field['options'])) {
                        foreach ($field['options'] as &$option) {
                            if (!isset($option['price_type'])) {
                                $option['price_type'] = 'flat';
                            }
                        }
                    }
                    // Add price_type to checkboxes
                    if ($field['type'] === 'checkbox' && !isset($field['price_type'])) {
                        $field['price_type'] = 'flat';
                    }
                    // Convert old condition format to new
                    if (isset($field['condition']['field']) && !empty($field['condition']['field'])) {
                        $field['condition'] = [
                            'rules' => [
                                [
                                    'field' => $field['condition']['field'],
                                    'operator' => 'equals',
                                    'value' => $field['condition']['value'] ?? ''
                                ]
                            ],
                            'action' => 'show'
                        ];
                    }
                }
            }
            $new_groups[$group_id] = $group;
        }

        // Save migrated data
        update_option('ulo_field_groups', $new_groups);
        update_option('ulo_migration_from_vpo', '1.0.2');
        update_option('ulo_migration_date', current_time('mysql'));

        // Add admin notice
        add_action('admin_notices', static function (): void {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>
                        <?php esc_html_e('Ultra-Light Product Options', 'ultra-light-options'); ?>:
                    </strong>
                    <?php esc_html_e('Data successfully migrated from Variation Product Options.', 'ultra-light-options'); ?>
                </p>
            </div>
            <?php
        });
    }
}

/**
 * Plugin activation handler.
 */
function ulo_activate(): void
{
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(ULO_PLUGIN_BASENAME);
        wp_die(
            esc_html__(
                'Ultra-Light Product Options requires WooCommerce to be installed and active. Please install and activate WooCommerce first.',
                'ultra-light-options'
            )
        );
    }

    // Create secure upload directory
    $upload_dir = wp_upload_dir();
    $secure_dir = $upload_dir['basedir'] . '/ulo-secure';
    $temp_dir = $upload_dir['basedir'] . '/ulo-temp';

    if (!file_exists($secure_dir)) {
        wp_mkdir_p($secure_dir);
        // Add .htaccess to prevent direct access
        $htaccess_content = "Order deny,allow\nDeny from all";
        file_put_contents($secure_dir . '/.htaccess', $htaccess_content);
        // Add index.php
        file_put_contents($secure_dir . '/index.php', '<?php // Silence is golden');
    }

    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
        file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden');
    }

    // Set default options
    if (!get_option('ulo_settings')) {
        update_option('ulo_settings', [
            'max_file_size' => 5242880, // 5MB
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'formula_max_length' => 500,
            'formula_timeout_ms' => 100
        ]);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation handler.
 */
function ulo_deactivate(): void
{
    // Clean up scheduled events
    wp_clear_scheduled_hook('ulo_cleanup_temp_files');

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register activation and deactivation hooks.
register_activation_hook(ULO_PLUGIN_FILE, '\ULO\ulo_activate');
register_deactivation_hook(ULO_PLUGIN_FILE, '\ULO\ulo_deactivate');

/**
 * Schedule cleanup cron job.
 */
add_action('init', static function (): void {
    if (!wp_next_scheduled('ulo_cleanup_temp_files')) {
        wp_schedule_event(time(), 'daily', 'ulo_cleanup_temp_files');
    }
});

/**
 * Initialize the plugin after WooCommerce is loaded.
 */
add_action('plugins_loaded', static function (): void {
    Ultra_Light_Options::get_instance()->init();

    // Declare HPOS compatibility.
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ULO_PLUGIN_FILE, true);
    }
}, 20); // Priority 20 ensures WooCommerce loads first

