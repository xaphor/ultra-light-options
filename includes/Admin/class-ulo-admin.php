<?php
declare(strict_types=1);

/**
 * Admin class - Main admin interface.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Admin;

use ULO\Classes\Data_Handler;
use ULO\Classes\Field_Renderer;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class.
 */
final class ULO_Admin
{
    /**
     * Instance of this class.
     */
    private static ?ULO_Admin $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): ULO_Admin
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
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Add product data tab
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);

        // Save product meta
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Add plugin action links
        add_filter('plugin_action_links_' . ULO_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Product Options', 'ultra-light-options'),
            __('Product Options', 'ultra-light-options'),
            'manage_woocommerce',
            'ulo-global-options',
            [$this, 'render_global_options_page']
        );

        add_submenu_page(
            'woocommerce',
            __('Options Settings', 'ultra-light-options'),
            __('Options Settings', 'ultra-light-options'),
            'manage_woocommerce',
            'ulo-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Add product data tab.
     *
     * @param array<string, array<string, mixed>> $tabs Product data tabs.
     * @return array<string, array<string, mixed>> Modified tabs.
     */
    public function add_product_data_tab(array $tabs): array
    {
        $tabs['ulo_options'] = [
            'label' => __('Custom Options', 'ultra-light-options'),
            'target' => 'ulo_options_panel',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 80,
        ];

        return $tabs;
    }

    /**
     * Render product data panel.
     */
    public function render_product_data_panel(): void
    {
        global $post;

        $product_id = $post->ID;
        $field_groups = Data_Handler::get_field_groups($product_id, 0);
        ?>
        <div id="ulo_options_panel" class="panel woocommerce_options_panel">
            <div class="options_group">
                <div class="ulo-admin-panel">
                    <h3><?php esc_html_e('Product Custom Options', 'ultra-light-options'); ?></h3>

                    <?php if (!empty($field_groups)): ?>
                        <div class="ulo-applied-groups">
                            <p><strong><?php esc_html_e('Applied Field Groups:', 'ultra-light-options'); ?></strong></p>
                            <ul>
                                <?php foreach ($field_groups as $group_id => $group): ?>
                                    <li>
                                        <?php echo esc_html($group['name'] ?? $group_id); ?>
                                        <span class="ulo-field-count">
                                            (<?php printf(
                                                /* translators: %d: number of fields */
                                                esc_html__('%d fields', 'ultra-light-options'),
                                                count($group['fields'] ?? [])
                                            ); ?>)
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p class="ulo-no-groups">
                            <?php esc_html_e('No field groups are applied to this product.', 'ultra-light-options'); ?>
                        </p>
                    <?php endif; ?>

                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ulo-global-options')); ?>"
                           class="button">
                            <?php esc_html_e('Manage Global Options', 'ultra-light-options'); ?>
                        </a>
                    </p>

                    <!-- Product-specific override option -->
                    <div class="ulo-product-override">
                        <p class="form-field">
                            <label>
                                <input type="checkbox"
                                       name="ulo_disable_global_options"
                                       value="1"
                                       <?php checked(get_post_meta($product_id, '_ulo_disable_global_options', true), '1'); ?>>
                                <?php esc_html_e('Disable global options for this product', 'ultra-light-options'); ?>
                            </label>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save product meta.
     *
     * @param int $product_id Product ID.
     */
    public function save_product_meta(int $product_id): void
    {
        // Verify nonce
        if (!isset($_POST['woocommerce_meta_nonce']) ||
            !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }

        // Save disable global options flag
        $disable_global = isset($_POST['ulo_disable_global_options']) ? '1' : '';
        update_post_meta($product_id, '_ulo_disable_global_options', $disable_global);
    }

    /**
     * Render global options page.
     */
    public function render_global_options_page(): void
    {
        $groups = Data_Handler::get_all_field_groups();
        ?>
        <div class="wrap ulo-admin-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Product Options', 'ultra-light-options'); ?>
            </h1>
            <a href="#" class="page-title-action ulo-add-group-btn" id="ulo-add-new-group">
                <?php esc_html_e('Add New', 'ultra-light-options'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="ulo-admin-container">
                <!-- Groups List -->
                <div class="ulo-field-groups-list">
                    <?php if (empty($groups)): ?>
                        <div class="ulo-empty-state">
                            <p><?php esc_html_e('No field groups yet. Create your first one!', 'ultra-light-options'); ?></p>
                        </div>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'ultra-light-options'); ?></th>
                                    <th><?php esc_html_e('Fields', 'ultra-light-options'); ?></th>
                                    <th><?php esc_html_e('Applied To', 'ultra-light-options'); ?></th>
                                    <th><?php esc_html_e('Actions', 'ultra-light-options'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groups as $group_id => $group): ?>
                                    <tr data-group-id="<?php echo esc_attr($group_id); ?>">
                                        <td>
                                            <strong>
                                                <a href="#" class="ulo-edit-group" data-group-id="<?php echo esc_attr($group_id); ?>">
                                                    <?php echo esc_html($group['name'] ?? $group_id); ?>
                                                </a>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php echo count($group['fields'] ?? []); ?>
                                        </td>
                                        <td>
                                            <?php echo $this->get_rules_summary($group['rules'] ?? []); ?>
                                        </td>
                                        <td>
                                            <a href="#" class="ulo-edit-group" data-group-id="<?php echo esc_attr($group_id); ?>">
                                                <?php esc_html_e('Edit', 'ultra-light-options'); ?>
                                            </a> |
                                            <a href="#" class="ulo-duplicate-group" data-group-id="<?php echo esc_attr($group_id); ?>">
                                                <?php esc_html_e('Duplicate', 'ultra-light-options'); ?>
                                            </a> |
                                            <a href="#" class="ulo-delete-group" data-group-id="<?php echo esc_attr($group_id); ?>">
                                                <?php esc_html_e('Delete', 'ultra-light-options'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // Render the field builder modal
        $this->render_field_builder();
        ?>
        <?php
    }

    /**
     * Render field builder interface.
     */
    private function render_field_builder(): void
    {
        ?>
        <div class="ulo-builder-header">
            <h2 id="ulo-builder-title"><?php esc_html_e('New Field Group', 'ultra-light-options'); ?></h2>
            <button type="button" class="ulo-builder-close">&times;</button>
        </div>

        <form id="ulo-field-group-form" class="ulo-builder-form">
            <input type="hidden" name="group_id" id="ulo-group-id" value="">

            <!-- Group Name -->
            <div class="ulo-form-row">
                <label for="ulo-group-name"><?php esc_html_e('Group Name', 'ultra-light-options'); ?></label>
                <input type="text" id="ulo-group-name" name="name" required>
            </div>

            <!-- Rules -->
            <div class="ulo-form-section">
                <h3><?php esc_html_e('Apply To', 'ultra-light-options'); ?></h3>
                <div class="ulo-rules-container">
                    <label>
                        <input type="checkbox" name="rules[all_products]" value="1" id="ulo-all-products">
                        <?php esc_html_e('All Products', 'ultra-light-options'); ?>
                    </label>

                    <div class="ulo-specific-products ulo-rule-products-search" id="ulo-specific-products-wrapper">
                        <label><?php esc_html_e('Specific Products:', 'ultra-light-options'); ?></label>
                        <div class="ulo-search-input-wrapper">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text" id="ulo-product-search-input" placeholder="<?php esc_attr_e('Search products...', 'ultra-light-options'); ?>">
                            <div id="ulo-product-search-results" class="ulo-search-results"></div>
                        </div>
                        <div id="ulo-selected-products" class="ulo-selected-products"></div>
                    </div>

                    <div class="ulo-specific-variations ulo-rule-variations-search" id="ulo-specific-variations-wrapper">
                        <label><?php esc_html_e('Specific Variations:', 'ultra-light-options'); ?></label>
                        <div class="ulo-search-input-wrapper">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text" id="ulo-variation-search-input" placeholder="<?php esc_attr_e('Search variations...', 'ultra-light-options'); ?>">
                            <div id="ulo-variation-search-results" class="ulo-search-results"></div>
                        </div>
                        <div id="ulo-selected-variations" class="ulo-selected-variations"></div>
                    </div>
                </div>
            </div>

            <!-- Fields -->
            <div class="ulo-form-section">
                <h3><?php esc_html_e('Fields', 'ultra-light-options'); ?></h3>
                <div id="ulo-fields-container" class="ulo-fields-container">
                    <!-- Fields will be added here dynamically -->
                </div>
                <button type="button" id="ulo-add-field" class="button">
                    <?php esc_html_e('Add Field', 'ultra-light-options'); ?>
                </button>
            </div>

            <!-- Actions -->
            <div class="ulo-form-actions">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save Field Group', 'ultra-light-options'); ?>
                </button>
                <button type="button" class="button ulo-builder-cancel">
                    <?php esc_html_e('Cancel', 'ultra-light-options'); ?>
                </button>
            </div>
        </form>

        <!-- Field Template -->
        <script type="text/template" id="ulo-field-template">
            <?php echo $this->get_field_template(); ?>
        </script>

        <!-- Option Template -->
        <script type="text/template" id="ulo-option-template">
            <?php echo $this->get_option_template(); ?>
        </script>
        <?php
    }

    /**
     * Get field template HTML.
     *
     * @return string HTML template.
     */
    private function get_field_template(): string
    {
        ob_start();
        ?>
        <div class="ulo-field-item" data-field-index="{{index}}">
            <div class="ulo-field-header">
                <span class="ulo-field-drag-handle">≡</span>
                <span class="ulo-field-title">{{label}}</span>
                <button type="button" class="ulo-field-toggle">▼</button>
                <button type="button" class="ulo-field-remove">&times;</button>
            </div>
            <div class="ulo-field-content">
                <div class="ulo-field-row">
                    <label><?php esc_html_e('Label', 'ultra-light-options'); ?></label>
                    <input type="text" name="fields[{{index}}][label]" value="{{label}}" required>
                </div>
                <div class="ulo-field-row">
                    <label><?php esc_html_e('Field ID', 'ultra-light-options'); ?></label>
                    <input type="text" name="fields[{{index}}][id]" value="{{id}}" required pattern="[a-z0-9_]+">
                    <small><?php esc_html_e('Lowercase letters, numbers, and underscores only', 'ultra-light-options'); ?></small>
                </div>
                <div class="ulo-field-row">
                    <label><?php esc_html_e('Type', 'ultra-light-options'); ?></label>
                    <select name="fields[{{index}}][type]" class="ulo-field-type-select">
                        <?php foreach (Field_Renderer::get_field_types() as $type => $type_label): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ulo-field-row">
                    <label>
                        <input type="checkbox" name="fields[{{index}}][required]" value="1">
                        <?php esc_html_e('Required', 'ultra-light-options'); ?>
                    </label>
                </div>
                <div class="ulo-field-options-container" style="display: none;">
                    <label><?php esc_html_e('Options', 'ultra-light-options'); ?></label>
                    <div class="ulo-options-list"></div>
                    <button type="button" class="button ulo-add-option">
                        <?php esc_html_e('Add Option', 'ultra-light-options'); ?>
                    </button>
                </div>
                <div class="ulo-field-pricing" style="display: none;">
                    <label><?php esc_html_e('Price', 'ultra-light-options'); ?></label>
                    <input type="number" name="fields[{{index}}][price]" value="0" step="0.01">
                    <select name="fields[{{index}}][price_type]" class="ulo-price-type-select">
                        <option value="flat"><?php esc_html_e('Flat Fee', 'ultra-light-options'); ?></option>
                        <option value="quantity_flat"><?php esc_html_e('Quantity-Based', 'ultra-light-options'); ?></option>
                        <option value="formula"><?php esc_html_e('Formula', 'ultra-light-options'); ?></option>
                        <option value="field_value"><?php esc_html_e('Field Value', 'ultra-light-options'); ?></option>
                    </select>
                </div>
                <div class="ulo-field-condition">
                    <label><?php esc_html_e('Conditional Logic', 'ultra-light-options'); ?></label>
                    <div class="ulo-condition-rules"></div>
                    <button type="button" class="button ulo-add-condition">
                        <?php esc_html_e('Add Condition', 'ultra-light-options'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get option template HTML.
     *
     * @return string HTML template.
     */
    private function get_option_template(): string
    {
        ob_start();
        ?>
        <div class="ulo-option-item" data-option-index="{{optionIndex}}">
            <input type="text" name="fields[{{fieldIndex}}][options][{{optionIndex}}][label]" placeholder="<?php esc_attr_e('Label', 'ultra-light-options'); ?>" value="{{label}}">
            <input type="text" name="fields[{{fieldIndex}}][options][{{optionIndex}}][value]" placeholder="<?php esc_attr_e('Value', 'ultra-light-options'); ?>" value="{{value}}">
            <input type="number" name="fields[{{fieldIndex}}][options][{{optionIndex}}][price]" placeholder="<?php esc_attr_e('Price', 'ultra-light-options'); ?>" value="{{price}}" step="0.01">
            <select name="fields[{{fieldIndex}}][options][{{optionIndex}}][price_type]">
                <option value="flat"><?php esc_html_e('Flat', 'ultra-light-options'); ?></option>
                <option value="quantity_flat"><?php esc_html_e('Qty', 'ultra-light-options'); ?></option>
                <option value="formula"><?php esc_html_e('Formula', 'ultra-light-options'); ?></option>
            </select>
            <button type="button" class="ulo-remove-option">&times;</button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void
    {
        $settings = \ULO\Core\ULO_Core::get_settings();
        ?>
        <div class="wrap ulo-admin-wrap">
            <h1><?php esc_html_e('Ultra-Light Options Settings', 'ultra-light-options'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('ulo_settings'); ?>

                <!-- Style Settings Section -->
                <h2 class="ulo-settings-section-title">
                    <?php esc_html_e('Frontend Style Settings', 'ultra-light-options'); ?>
                </h2>
                <p class="description"><?php esc_html_e('Customize the appearance of product options on your store.', 'ultra-light-options'); ?></p>

                <table class="form-table ulo-style-settings">
                    <tr>
                        <th scope="row">
                            <label for="ulo_accent_color"><?php esc_html_e('Accent Color', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="ulo_accent_color" name="ulo_settings[accent_color]"
                                   value="<?php echo esc_attr($settings['accent_color']); ?>" class="ulo-color-picker">
                            <input type="text" class="ulo-color-text" value="<?php echo esc_attr($settings['accent_color']); ?>" readonly>
                            <p class="description"><?php esc_html_e('Primary accent color for selected options and buttons.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_accent_bg_color"><?php esc_html_e('Accent Background', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="ulo_accent_bg_color" name="ulo_settings[accent_bg_color]"
                                   value="<?php echo esc_attr($settings['accent_bg_color']); ?>" class="ulo-color-picker">
                            <input type="text" class="ulo-color-text" value="<?php echo esc_attr($settings['accent_bg_color']); ?>" readonly>
                            <p class="description"><?php esc_html_e('Background color for selected/highlighted option cards.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_success_color"><?php esc_html_e('Success/Savings Color', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="ulo_success_color" name="ulo_settings[success_color]"
                                   value="<?php echo esc_attr($settings['success_color']); ?>" class="ulo-color-picker">
                            <input type="text" class="ulo-color-text" value="<?php echo esc_attr($settings['success_color']); ?>" readonly>
                            <p class="description"><?php esc_html_e('Color for success states and savings badges.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_border_color"><?php esc_html_e('Border Color', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="ulo_border_color" name="ulo_settings[border_color]"
                                   value="<?php echo esc_attr($settings['border_color']); ?>" class="ulo-color-picker">
                            <input type="text" class="ulo-color-text" value="<?php echo esc_attr($settings['border_color']); ?>" readonly>
                            <p class="description"><?php esc_html_e('Border color for option cards and inputs.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_border_radius"><?php esc_html_e('Border Radius', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="range" id="ulo_border_radius" name="ulo_settings[border_radius]"
                                   value="<?php echo esc_attr((string) $settings['border_radius']); ?>" 
                                   min="0" max="24" step="2" class="ulo-range-slider">
                            <span class="ulo-range-value"><?php echo esc_html((string) $settings['border_radius']); ?>px</span>
                            <p class="description"><?php esc_html_e('Corner roundness for cards and buttons.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Card Style', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <div class="ulo-card-style-options">
                                <label class="ulo-card-style-option <?php echo $settings['card_style'] === 'minimal' ? 'selected' : ''; ?>">
                                    <input type="radio" name="ulo_settings[card_style]" value="minimal" 
                                           <?php checked($settings['card_style'], 'minimal'); ?>>
                                    <span class="ulo-card-preview ulo-style-minimal">
                                        <span class="ulo-preview-text">Aa</span>
                                    </span>
                                    <span class="ulo-style-name"><?php esc_html_e('Minimal', 'ultra-light-options'); ?></span>
                                </label>
                                <label class="ulo-card-style-option <?php echo $settings['card_style'] === 'outlined' ? 'selected' : ''; ?>">
                                    <input type="radio" name="ulo_settings[card_style]" value="outlined" 
                                           <?php checked($settings['card_style'], 'outlined'); ?>>
                                    <span class="ulo-card-preview ulo-style-outlined">
                                        <span class="ulo-preview-text">Aa</span>
                                    </span>
                                    <span class="ulo-style-name"><?php esc_html_e('Outlined', 'ultra-light-options'); ?></span>
                                </label>
                                <label class="ulo-card-style-option <?php echo $settings['card_style'] === 'filled' ? 'selected' : ''; ?>">
                                    <input type="radio" name="ulo_settings[card_style]" value="filled" 
                                           <?php checked($settings['card_style'], 'filled'); ?>>
                                    <span class="ulo-card-preview ulo-style-filled">
                                        <span class="ulo-preview-text">Aa</span>
                                    </span>
                                    <span class="ulo-style-name"><?php esc_html_e('Filled', 'ultra-light-options'); ?></span>
                                </label>
                                <label class="ulo-card-style-option <?php echo $settings['card_style'] === 'elegant' ? 'selected' : ''; ?>">
                                    <input type="radio" name="ulo_settings[card_style]" value="elegant" 
                                           <?php checked($settings['card_style'], 'elegant'); ?>>
                                    <span class="ulo-card-preview ulo-style-elegant">
                                        <span class="ulo-preview-text">Aa</span>
                                    </span>
                                    <span class="ulo-style-name"><?php esc_html_e('Elegant', 'ultra-light-options'); ?></span>
                                </label>
                            </div>
                            <p class="description"><?php esc_html_e('Choose between different card styles for option display.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Option Layout', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <select name="ulo_settings[option_layout]" id="ulo_option_layout">
                                <option value="cards" <?php selected($settings['option_layout'], 'cards'); ?>><?php esc_html_e('Cards (Premium)', 'ultra-light-options'); ?></option>
                                <option value="list" <?php selected($settings['option_layout'], 'list'); ?>><?php esc_html_e('List (Classic)', 'ultra-light-options'); ?></option>
                                <option value="grid" <?php selected($settings['option_layout'], 'grid'); ?>><?php esc_html_e('Grid (2 columns)', 'ultra-light-options'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How options are arranged on the product page.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_enable_animations"><?php esc_html_e('Enable Animations', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <label class="ulo-toggle-switch">
                                <input type="checkbox" id="ulo_enable_animations" name="ulo_settings[enable_animations]"
                                       value="1" <?php checked($settings['enable_animations'], true); ?>>
                                <span class="ulo-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Enable smooth transitions and micro-animations.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_show_price_summary"><?php esc_html_e('Show Price Summary', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <label class="ulo-toggle-switch">
                                <input type="checkbox" id="ulo_show_price_summary" name="ulo_settings[show_price_summary]"
                                       value="1" <?php checked($settings['show_price_summary'], true); ?>>
                                <span class="ulo-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Display a price breakdown summary showing options total.', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr>

                <!-- General Settings Section -->
                <h2 class="ulo-settings-section-title">
                    <?php esc_html_e('General Settings', 'ultra-light-options'); ?>
                </h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ulo_max_file_size"><?php esc_html_e('Max File Upload Size', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ulo_max_file_size" name="ulo_settings[max_file_size]"
                                   value="<?php echo esc_attr((string) $settings['max_file_size']); ?>">
                            <p class="description"><?php esc_html_e('Maximum file size in bytes (default: 5242880 = 5MB)', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_allowed_file_types"><?php esc_html_e('Allowed File Types', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ulo_allowed_file_types" name="ulo_settings[allowed_file_types]"
                                   value="<?php echo esc_attr(implode(', ', $settings['allowed_file_types'])); ?>" class="large-text">
                            <p class="description"><?php esc_html_e('Comma-separated list of allowed file extensions', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_formula_max_length"><?php esc_html_e('Max Formula Length', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ulo_formula_max_length" name="ulo_settings[formula_max_length]"
                                   value="<?php echo esc_attr((string) $settings['formula_max_length']); ?>">
                            <p class="description"><?php esc_html_e('Maximum characters allowed in pricing formulas', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ulo_enable_debug_log"><?php esc_html_e('Enable Debug Logging', 'ultra-light-options'); ?></label>
                        </th>
                        <td>
                            <label class="ulo-toggle-switch">
                                <input type="checkbox" id="ulo_enable_debug_log" name="ulo_settings[enable_debug_log]"
                                       value="1" <?php checked($settings['enable_debug_log'], true); ?>>
                                <span class="ulo-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Log debug information to WooCommerce logs', 'ultra-light-options'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Import / Export', 'ultra-light-options'); ?></h2>
            <div class="ulo-import-export">
                <div class="ulo-export">
                    <h3><?php esc_html_e('Export Field Groups', 'ultra-light-options'); ?></h3>
                    <button type="button" id="ulo-export-btn" class="button">
                        <?php esc_html_e('Export All Groups', 'ultra-light-options'); ?>
                    </button>
                </div>
                <div class="ulo-import">
                    <h3><?php esc_html_e('Import Field Groups', 'ultra-light-options'); ?></h3>
                    <input type="file" id="ulo-import-file" accept=".json">
                    <button type="button" id="ulo-import-btn" class="button">
                        <?php esc_html_e('Import', 'ultra-light-options'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Color picker sync
            document.querySelectorAll('.ulo-color-picker').forEach(picker => {
                const textInput = picker.nextElementSibling;
                picker.addEventListener('input', () => {
                    textInput.value = picker.value;
                });
            });

            // Range slider value display
            document.querySelectorAll('.ulo-range-slider').forEach(slider => {
                const valueDisplay = slider.nextElementSibling;
                slider.addEventListener('input', () => {
                    valueDisplay.textContent = slider.value + 'px';
                });
            });

            // Card style selection
            document.querySelectorAll('.ulo-card-style-option input').forEach(radio => {
                radio.addEventListener('change', () => {
                    document.querySelectorAll('.ulo-card-style-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    radio.closest('.ulo-card-style-option').classList.add('selected');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get rules summary for display.
     *
     * @param array<string, mixed> $rules Rules configuration.
     * @return string Summary text.
     */
    private function get_rules_summary(array $rules): string
    {
        if (!empty($rules['all_products'])) {
            return esc_html__('All Products', 'ultra-light-options');
        }

        $parts = [];

        if (!empty($rules['product_ids'])) {
            $parts[] = sprintf(
                /* translators: %d: number of products */
                esc_html__('%d Products', 'ultra-light-options'),
                count($rules['product_ids'])
            );
        }

        if (!empty($rules['variation_ids'])) {
            $parts[] = sprintf(
                /* translators: %d: number of variations */
                esc_html__('%d Variations', 'ultra-light-options'),
                count($rules['variation_ids'])
            );
        }

        if (!empty($rules['category_ids'])) {
            $parts[] = sprintf(
                /* translators: %d: number of categories */
                esc_html__('%d Categories', 'ultra-light-options'),
                count($rules['category_ids'])
            );
        }

        return empty($parts) ? esc_html__('None', 'ultra-light-options') : implode(', ', $parts);
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Check if we're on a relevant page
        $is_product_page = ($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'product';
        $is_options_page = str_contains($hook, 'ulo-');

        if (!$is_product_page && !$is_options_page) {
            return;
        }

        wp_enqueue_style('ulo-admin');
        wp_enqueue_script('sortablejs');
        wp_enqueue_script('ulo-admin');
    }

    /**
     * Add plugin action links.
     *
     * @param array<int, string> $links Existing links.
     * @return array<int, string> Modified links.
     */
    public function add_plugin_action_links(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=ulo-settings'),
            esc_html__('Settings', 'ultra-light-options')
        );

        array_unshift($links, $settings_link);

        return $links;
    }
}
