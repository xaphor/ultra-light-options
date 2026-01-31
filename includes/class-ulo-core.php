<?php
declare(strict_types=1);

/**
 * Core class - Main plugin initialization.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Core;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core initialization class.
 */
final class ULO_Core
{
    /**
     * Instance of this class.
     */
    private static ?ULO_Core $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): ULO_Core
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
        $this->init();
    }

    /**
     * Initialize core functionality.
     */
    private function init(): void
    {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);

        // Initialize file upload handler
        if (class_exists('\ULO\Classes\File_Upload_Handler')) {
            \ULO\Classes\File_Upload_Handler::get_instance();
        }
    }

    /**
     * Register frontend assets.
     */
    public function register_frontend_assets(): void
    {
        // Only on product pages
        if (!is_product()) {
            return;
        }

        // Register CSS
        wp_register_style(
            'ulo-frontend',
            ULO_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            ULO_VERSION
        );

        // Register main JS with defer strategy (WordPress 6.3+)
        wp_register_script(
            'ulo-frontend',
            ULO_PLUGIN_URL . 'assets/js/frontend/main.js',
            [], // No dependencies - vanilla JS
            ULO_VERSION,
            [
                'strategy' => 'defer',
                'in_footer' => true,
            ]
        );

        // Localize script data
        wp_localize_script('ulo-frontend', 'uloFrontend', $this->get_frontend_localize_data());
    }

    /**
     * Get frontend localization data.
     *
     * @return array<string, mixed> Localization data.
     */
    private function get_frontend_localize_data(): array
    {
        global $post;
        $product_id = $post ? $post->ID : 0;
        $product = $product_id ? wc_get_product($product_id) : null;
        $base_price = $product ? (float) $product->get_price() : 0;

        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ulo-ajax-nonce'),
            'productId' => $product_id,
            'basePrice' => $base_price,
            'currency' => get_woocommerce_currency(),
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'currencyPosition' => get_option('woocommerce_currency_pos', 'left'),
            'thousandSep' => wc_get_price_thousand_separator(),
            'decimalSep' => wc_get_price_decimal_separator(),
            'decimals' => wc_get_price_decimals(),
            'i18n' => [
                'basePrice' => __('Base Price', 'ultra-light-options'),
                'optionsTotal' => __('Options Total', 'ultra-light-options'),
                'finalTotal' => __('Final Total', 'ultra-light-options'),
                'required' => __('This field is required.', 'ultra-light-options'),
                'uploadError' => __('Error uploading file.', 'ultra-light-options'),
                'fileTooLarge' => __('File is too large.', 'ultra-light-options'),
                'invalidFileType' => __('Invalid file type.', 'ultra-light-options'),
                'uploading' => __('Uploading...', 'ultra-light-options'),
                'removeFile' => __('Remove file', 'ultra-light-options'),
            ],
        ];
    }

    /**
     * Register admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function register_admin_assets(string $hook): void
    {
        // Only on specific admin pages
        $allowed_hooks = [
            'post.php',
            'post-new.php',
            'woocommerce_page_ulo-settings',
            'woocommerce_page_ulo-global-options',
        ];

        if (!in_array($hook, $allowed_hooks, true)) {
            // Check if it's a product edit page
            global $post_type;
            if ($post_type !== 'product' && !str_contains($hook, 'ulo')) {
                return;
            }
        }

        // Register admin CSS
        wp_register_style(
            'ulo-admin',
            ULO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ULO_VERSION
        );

        // Register admin JS
        wp_register_script(
            'ulo-admin',
            ULO_PLUGIN_URL . 'assets/js/admin/builder.js',
            [], // No dependencies
            ULO_VERSION,
            true
        );

        // Register SortableJS for drag-and-drop
        wp_register_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            [],
            '1.15.0',
            true
        );

        // Localize admin script
        wp_localize_script('ulo-admin', 'uloAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ulo-admin-nonce'),
            'i18n' => [
                // General
                'cancel' => __('Cancel', 'ultra-light-options'),
                'save' => __('Save', 'ultra-light-options'),
                'edit' => __('Edit', 'ultra-light-options'),
                'delete' => __('Delete', 'ultra-light-options'),
                'duplicate' => __('Duplicate', 'ultra-light-options'),
                'active' => __('Active', 'ultra-light-options'),
                'inactive' => __('Inactive', 'ultra-light-options'),
                'field' => __('Field', 'ultra-light-options'),
                'fields' => __('Fields', 'ultra-light-options'),
                'label' => __('Label', 'ultra-light-options'),
                'value' => __('Value', 'ultra-light-options'),
                'price' => __('Price', 'ultra-light-options'),
                'required' => __('Required', 'ultra-light-options'),

                // Groups
                'newFieldGroup' => __('New Field Group', 'ultra-light-options'),
                'editFieldGroup' => __('Edit Field Group', 'ultra-light-options'),
                'groupTitle' => __('Group Title', 'ultra-light-options'),
                'saveGroup' => __('Save Group', 'ultra-light-options'),
                'noGroups' => __('No field groups found', 'ultra-light-options'),
                'noGroupsDesc' => __('Create your first field group to get started.', 'ultra-light-options'),
                'createFirstGroup' => __('Create First Group', 'ultra-light-options'),
                'groupSaved' => __('Field group saved successfully.', 'ultra-light-options'),
                'groupDeleted' => __('Field group deleted.', 'ultra-light-options'),
                'groupDuplicated' => __('Field group duplicated.', 'ultra-light-options'),
                'confirmDelete' => __('Are you sure you want to delete this group?', 'ultra-light-options'),
                'confirmDeleteField' => __('Are you sure you want to delete this field?', 'ultra-light-options'),
                'titleRequired' => __('Group title is required.', 'ultra-light-options'),
                'saveFailed' => __('Failed to save field group.', 'ultra-light-options'),
                'deleteFailed' => __('Failed to delete field group.', 'ultra-light-options'),
                'unsavedChanges' => __('You have unsaved changes. Are you sure you want to close?', 'ultra-light-options'),
                'groupActive' => __('Group is active', 'ultra-light-options'),
                'priority' => __('Priority', 'ultra-light-options'),
                'priorityDesc' => __('Higher numbers = higher priority when multiple groups apply to the same product.', 'ultra-light-options'),

                // Tabs
                'assignmentRules' => __('Assignment Rules', 'ultra-light-options'),
                'settings' => __('Settings', 'ultra-light-options'),

                // Fields
                'addField' => __('Add Field', 'ultra-light-options'),
                'editField' => __('Edit Field', 'ultra-light-options'),
                'saveField' => __('Save Field', 'ultra-light-options'),
                'noFields' => __('No fields added yet. Click "Add Field" to create one.', 'ultra-light-options'),
                'fieldType' => __('Field Type', 'ultra-light-options'),
                'fieldLabel' => __('Field Label', 'ultra-light-options'),
                'fieldName' => __('Field Name', 'ultra-light-options'),
                'autoGenerated' => __('Auto-generated from label', 'ultra-light-options'),
                'fieldNameDesc' => __('Internal field name (no spaces, lowercase).', 'ultra-light-options'),
                'placeholder' => __('Placeholder', 'ultra-light-options'),
                'description' => __('Description', 'ultra-light-options'),
                'selectFieldType' => __('Please select a field type.', 'ultra-light-options'),
                'labelRequired' => __('Field label is required.', 'ultra-light-options'),
                'badgeText' => __('Badge Text', 'ultra-light-options'),
                'badgeColor' => __('Badge Color', 'ultra-light-options'),
                'pulseAnimation' => __('Pulse Animation', 'ultra-light-options'),

                // Field types
                'text' => __('Text', 'ultra-light-options'),
                'textarea' => __('Textarea', 'ultra-light-options'),
                'number' => __('Number', 'ultra-light-options'),
                'radio' => __('Radio', 'ultra-light-options'),
                'checkbox' => __('Checkbox', 'ultra-light-options'),
                'select' => __('Select', 'ultra-light-options'),
                'date' => __('Date', 'ultra-light-options'),
                'time' => __('Time', 'ultra-light-options'),
                'file' => __('File Upload', 'ultra-light-options'),
                'html' => __('HTML', 'ultra-light-options'),

                // Options
                'options' => __('Options', 'ultra-light-options'),
                'addOption' => __('Add Option', 'ultra-light-options'),
                'removeOption' => __('Remove Option', 'ultra-light-options'),

                // Pricing
                'pricing' => __('Pricing', 'ultra-light-options'),
                'noPricing' => __('No Pricing', 'ultra-light-options'),
                'flatFee' => __('Flat Fee', 'ultra-light-options'),
                'quantityFlat' => __('Quantity × Fee', 'ultra-light-options'),
                'formula' => __('Formula', 'ultra-light-options'),
                'fieldValue' => __('Field Value', 'ultra-light-options'),
                'pricePerUnit' => __('Price Per Unit', 'ultra-light-options'),
                'multiplier' => __('Multiplier', 'ultra-light-options'),
                'multiplierDesc' => __('User-entered value will be multiplied by this amount.', 'ultra-light-options'),
                'availableVariables' => __('Available Variables', 'ultra-light-options'),
                'examples' => __('Examples', 'ultra-light-options'),
                'testFormula' => __('Test Formula', 'ultra-light-options'),
                'enterFormula' => __('Please enter a formula to test.', 'ultra-light-options'),
                'invalidFormula' => __('Invalid formula', 'ultra-light-options'),

                // Conditional Logic
                'conditionalLogic' => __('Conditional Logic', 'ultra-light-options'),
                'enableConditions' => __('Enable conditional logic', 'ultra-light-options'),
                'showField' => __('Show this field', 'ultra-light-options'),
                'hideField' => __('Hide this field', 'ultra-light-options'),
                'ifAllMatch' => __('if ALL of the following match:', 'ultra-light-options'),
                'addCondition' => __('Add Condition', 'ultra-light-options'),
                'removeCondition' => __('Remove Condition', 'ultra-light-options'),
                'selectField' => __('Select field...', 'ultra-light-options'),

                // Operators
                'equals' => __('equals', 'ultra-light-options'),
                'notEquals' => __('not equals', 'ultra-light-options'),
                'contains' => __('contains', 'ultra-light-options'),
                'notContains' => __('does not contain', 'ultra-light-options'),
                'isEmpty' => __('is empty', 'ultra-light-options'),
                'isNotEmpty' => __('is not empty', 'ultra-light-options'),
                'greaterThan' => __('greater than', 'ultra-light-options'),
                'lessThan' => __('less than', 'ultra-light-options'),

                // Assignment Rules
                'allProducts' => __('All Products', 'ultra-light-options'),
                'allProductsDesc' => __('Apply to all products in your store.', 'ultra-light-options'),
                'specificProducts' => __('Specific Products', 'ultra-light-options'),
                'specificProductsDesc' => __('Choose which products this group applies to.', 'ultra-light-options'),
                'specificVariations' => __('Specific Variations', 'ultra-light-options'),
                'specificVariationsDesc' => __('Choose specific product variations.', 'ultra-light-options'),
                'searchProducts' => __('Search products...', 'ultra-light-options'),
            ],
        ]);
    }

    /**
     * Get plugin settings.
     *
     * @return array<string, mixed> Settings.
     */
    public static function get_settings(): array
    {
        $defaults = [
            // File upload settings
            'max_file_size' => 5242880,
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],

            // Formula settings
            'formula_max_length' => 500,
            'formula_timeout_ms' => 100,

            // Debug settings
            'enable_debug_log' => false,

            // Style settings - Modern UI customization
            'accent_color' => '#2271b1',
            'accent_bg_color' => '#f0f7ff',
            'success_color' => '#00a32a',
            'border_color' => '#c3c4c7',
            'border_radius' => 8,
            'card_style' => 'outlined', // minimal, outlined, filled, elegant
            'enable_animations' => true,
            'option_layout' => 'cards', // cards, list, grid
            'show_price_summary' => true,
        ];

        $settings = get_option('ulo_settings', []);

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Update plugin settings.
     *
     * @param array<string, mixed> $settings Settings to update.
     * @return bool True on success.
     */
    public static function update_settings(array $settings): bool
    {
        $current = self::get_settings();
        $updated = wp_parse_args($settings, $current);

        return update_option('ulo_settings', $updated);
    }
}
