<?php
declare(strict_types=1);

/**
 * Field Renderer - Factory pattern for rendering field types.
 *
 * Supports field types:
 * - text: Simple text input
 * - textarea: Multi-line text
 * - number: Numeric input
 * - radio: Radio button group
 * - radio_switch: Toggle switch (2 options)
 * - checkbox: Single checkbox
 * - checkbox_group: Multiple checkboxes
 * - select: Dropdown select
 * - date: Native date picker
 * - time: Native time picker
 * - file: File upload
 * - html: HTML content (instructions/separators)
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Classes;

use ULO\Traits\Sanitization;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Renderer class.
 */
final class Field_Renderer
{
    use Sanitization;

    /**
     * Available field types.
     */
    private const FIELD_TYPES = [
        'text' => 'Text Input',
        'textarea' => 'Textarea',
        'number' => 'Number',
        'radio' => 'Radio Buttons',
        'radio_switch' => 'Radio Switch/Toggle',
        'checkbox' => 'Checkbox',
        'checkbox_group' => 'Checkbox Group',
        'select' => 'Dropdown Select',
        'date' => 'Date Picker',
        'time' => 'Time Picker',
        'file' => 'File Upload',
        'html' => 'HTML Content',
    ];

    /**
     * Icons for field labels based on keywords.
     *
     * @var array<string, string>
     */
    private static array $icons = [];

    /**
     * Render a field based on its type.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param int $product_id Product ID.
     * @param int $variation_id Variation ID.
     * @return string Rendered HTML.
     */
    public static function render(array $field, int $product_id, int $variation_id = 0): string
    {
        // Require type and label, but 'id' can be derived from 'name'
        if (!isset($field['type'], $field['label'])) {
            return '';
        }

        // Use 'id' if available, otherwise generate from 'name'
        if (empty($field['id'])) {
            $field['id'] = 'ulo_field_' . sanitize_key($field['name'] ?? uniqid());
        }

        $field_type = $field['type'];

        // Use match expression (PHP 8.0+)
        $input_html = match ($field_type) {
            'text' => self::render_text($field),
            'textarea' => self::render_textarea($field),
            'number' => self::render_number($field),
            'radio' => self::render_radio($field),
            'radio_switch' => self::render_radio_switch($field),
            'checkbox' => self::render_checkbox($field),
            'checkbox_group' => self::render_checkbox_group($field),
            'select' => self::render_select($field),
            'date' => self::render_date($field),
            'time' => self::render_time($field),
            'file' => self::render_file($field),
            'html' => self::render_html_content($field),
            default => '',
        };

        if (empty($input_html)) {
            return '';
        }

        return self::wrap_field($field, $input_html);
    }

    /**
     * Wrap field input with container, label, and condition attributes.
     *
     * @param array<string, mixed> $field Field configuration.
     * @param string $input_html Input HTML.
     * @return string Wrapped HTML.
     */
    private static function wrap_field(array $field, string $input_html): string
    {
        $field_id = esc_attr($field['id']);
        $field_type = esc_attr($field['type']);
        $field_label = esc_html($field['label']);
        $required = isset($field['required']) && $field['required'];
        $description = $field['description'] ?? '';

        // Get condition data attributes
        $condition_attr = '';
        if (isset($field['condition'])) {
            $condition_attr = Condition_Engine::get_data_attributes($field['condition']);
        }

        // Build CSS classes
        $wrapper_classes = ['ulo-field', "ulo-field-{$field_type}"];
        if (!empty($field['condition'])) {
            $wrapper_classes[] = 'ulo-conditional-field';
        }
        if ($required) {
            $wrapper_classes[] = 'ulo-field-required';
        }

        // Get icon if available
        $icon_html = self::get_icon_for_label($field_label);
        if (!empty($icon_html)) {
            $wrapper_classes[] = 'ulo-has-icon';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
             data-field-id="<?php echo esc_attr((string) $field_id); ?>"
             data-field-type="<?php echo esc_attr((string) $field_type); ?>"
             <?php echo $condition_attr; // PHPCS: XSS ok. Condition attr is already escaped in build_conditions_attribute() ?>>

            <?php if ($field_type !== 'html' && $field_type !== 'checkbox'): ?>
                <div class="ulo-field-header">
                    <?php if (!empty($icon_html)): ?>
                        <div class="ulo-field-icon"><?php echo $icon_html; ?></div>
                    <?php endif; ?>
                    <label for="ulo_<?php echo esc_attr((string) $field_id); ?>" class="ulo-field-label">
                        <?php echo esc_html($field_label); ?>
                        <?php if ($required): ?>
                            <span class="ulo-required" aria-label="<?php esc_attr_e('Required', 'ultra-light-options'); ?>">*</span>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endif; ?>

            <div class="ulo-field-input">
                <?php echo $input_html; // PHPCS: XSS ok. input_html consists of escaped components from render methods. ?>
            </div>

            <?php if (!empty($description)): ?>
                <div class="ulo-field-description">
                    <?php echo wp_kses_post($description); ?>
                </div>
            <?php endif; ?>

            <?php 
            if (!empty($field['badge']) && $field_type !== 'checkbox') {
                echo self::render_badge($field['badge'], $field['badge_color'] ?? '#ef4444', !empty($field['badge_pulse']));
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render text input.
     */
    private static function render_text(array $field): string
    {
        $field_id = esc_attr($field['id']);
        $placeholder = esc_attr($field['placeholder'] ?? '');
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $price_type = $field['price_type'] ?? '';
        $multiplier = (float) ($field['multiplier'] ?? 0);

        $data_attrs = '';
        $field_id = $field['id'];
        $placeholder = $field['placeholder'] ?? '';
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $price_type = $field['price_type'] ?? '';
        $multiplier = (float) ($field['multiplier'] ?? 0);

        $input_type = $field['input_type'] ?? 'text'; // Allow custom input types like 'email', 'url', 'tel'
        $input_classes = ['ulo-text-input'];

        if ($price_type === 'field_value' && $multiplier > 0) {
            $data_attrs = sprintf(
                ' data-ulo-price-type="field_value" data-ulo-multiplier="%s"',
                esc_attr((string) $multiplier)
            );
        } else {
            $data_attrs = '';
        }

        ob_start();
        ?>
        <input type="<?php echo esc_attr($input_type); ?>"
               class="<?php echo esc_attr(implode(' ', $input_classes)); ?>"
               name="ulo[<?php echo esc_attr($field_id); ?>]"
               id="ulo_<?php echo esc_attr($field_id); ?>"
               value=""
               placeholder="<?php echo esc_attr($placeholder); ?>"
               <?php echo $required; // PHPCS: XSS ok. ?>
               <?php echo $data_attrs; // PHPCS: XSS ok. ?>>
        <?php
        return ob_get_clean();
    }

    /**
     * Render textarea.
     */
    private static function render_textarea(array $field): string
    {
        $field_id = $field['id'];
        $placeholder = $field['placeholder'] ?? '';
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $rows = (int) ($field['rows'] ?? 4);

        $input_classes = ['ulo-textarea'];

        ob_start();
        ?>
        <textarea name="ulo[<?php echo esc_attr($field_id); ?>]"
                  id="ulo_<?php echo esc_attr($field_id); ?>"
                  class="<?php echo esc_attr(implode(' ', $input_classes)); ?>"
                  placeholder="<?php echo esc_attr($placeholder); ?>"
                  rows="<?php echo esc_attr((string) $rows); ?>"
                  <?php echo $required; // PHPCS: XSS ok. ?>></textarea>
        <?php
        return ob_get_clean();
    }

    /**
     * Render number input.
     */
    private static function render_number(array $field): string
    {
        $field_id = esc_attr($field['id']);
        $placeholder = esc_attr($field['placeholder'] ?? '');
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $min = $field['min'] ?? '';
        $max = $field['max'] ?? '';
        $step = $field['step'] ?? 'any';
        $price_type = $field['price_type'] ?? '';
        $multiplier = (float) ($field['multiplier'] ?? 0);

        $data_attrs = '';
        if ($price_type === 'field_value' && $multiplier > 0) {
            $data_attrs = sprintf(
                ' data-ulo-price-type="field_value" data-ulo-multiplier="%s"',
                esc_attr((string) $multiplier)
            );
        }

        ob_start();
        ?>
        <input type="number"
               name="ulo[<?php echo $field_id; ?>]"
               id="ulo_<?php echo $field_id; ?>"
               class="ulo-number-input"
               placeholder="<?php echo $placeholder; ?>"
               <?php if ($min !== ''): ?>min="<?php echo esc_attr((string) $min); ?>"<?php endif; ?>
               <?php if ($max !== ''): ?>max="<?php echo esc_attr((string) $max); ?>"<?php endif; ?>
               step="<?php echo esc_attr((string) $step); ?>"
               <?php echo $required; ?>
               <?php echo $data_attrs; ?>>
        <?php
        return ob_get_clean();
    }

    /**
     * Render radio buttons.
     */
    private static function render_radio(array $field): string
    {
        if (!isset($field['options']) || !is_array($field['options'])) {
            return '';
        }

        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'];
        $name = "ulo[{$field_id}]";

        ob_start();
        ?>
        <div class="ulo-radio-group" role="radiogroup" aria-label="<?php echo esc_attr($field['label']); ?>">
            <?php foreach ($field['options'] as $index => $option): ?>
                <?php
                $option_id = $field_id . '_' . $index;
                $value = esc_attr($option['value'] ?? '');
                $label = esc_html($option['label'] ?? '');
                $price = (float) ($option['price'] ?? 0);
                $price_type = $option['price_type'] ?? 'flat';
                $image = $option['image'] ?? '';
                $price_display = $price > 0 ? ' <span class="ulo-price-suffix">(+' . wc_price($price) . ')</span>' : '';

                $data_attrs = sprintf(
                    'data-ulo-price="%s" data-ulo-price-type="%s"',
                    esc_attr((string) $price),
                    esc_attr($price_type)
                );
                ?>
                <label class="ulo-radio-option <?php echo $image ? 'ulo-has-swatch' : ''; ?>"
                       for="ulo_<?php echo esc_attr($option_id); ?>">
                    <input type="radio"
                           name="<?php echo esc_attr($name); ?>"
                           id="ulo_<?php echo esc_attr($option_id); ?>"
                           value="<?php echo esc_attr($value); ?>"
                           <?php echo $data_attrs; // PHPCS: XSS ok. ?>
                           <?php echo $required && $index === 0 ? 'required' : ''; ?>>
                    <?php if ($image): ?>
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo $label; ?>" class="ulo-swatch-image">
                    <?php endif; ?>
                    <span class="ulo-radio-label"><?php echo $label; ?></span>
                    <?php echo $price_display; ?>
                    <?php 
                    if (!empty($option['badge'])) {
                        echo self::render_badge($option['badge'], $option['badge_color'] ?? '#ef4444', !empty($option['badge_pulse']));
                    }
                    ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render radio switch (toggle).
     */
    private static function render_radio_switch(array $field): string
    {
        if (!isset($field['options']) || !is_array($field['options']) || count($field['options']) < 2) {
            return '<p class="ulo-error">' . esc_html__('Switch requires at least 2 options.', 'ultra-light-options') . '</p>';
        }

        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'];
        $name = "ulo[{$field_id}]";
        $options = array_slice($field['options'], 0, 2); // Only first 2 options

        ob_start();
        ?>
        <div class="ulo-switch-wrapper">
            <div class="ulo-switch-inner" role="radiogroup" aria-label="<?php echo esc_attr($field['label']); ?>">
                <?php foreach ($options as $index => $option): ?>
                    <?php
                    $option_id = $field_id . '_' . $index;
                    $value = esc_attr($option['value'] ?? '');
                    $label = esc_html($option['label'] ?? '');
                    $price = (float) ($option['price'] ?? 0);
                    $price_type = $option['price_type'] ?? 'flat';

                    $data_attrs = sprintf(
                        'data-ulo-price="%s" data-ulo-price-type="%s"',
                        esc_attr((string) $price),
                        esc_attr($price_type)
                    );
                    ?>
                    <label class="ulo-switch-option" for="ulo_<?php echo esc_attr($option_id); ?>">
                        <input type="radio"
                               name="<?php echo esc_attr($name); ?>"
                               id="ulo_<?php echo esc_attr($option_id); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               <?php echo $data_attrs; // PHPCS: XSS ok. ?>
                               <?php echo $required && $index === 0 ? 'required' : ''; ?>>
                        <span class="ulo-switch-label"><?php echo esc_html($label); ?></span>
                        <?php 
                        if (!empty($option['badge'])) {
                            echo self::render_badge($option['badge'], $option['badge_color'] ?? '#ef4444', !empty($option['badge_pulse']));
                        }
                        ?>
                    </label>
                <?php endforeach; ?>
                <span class="ulo-switch-slider" aria-hidden="true"></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render checkbox.
     */
    private static function render_checkbox(array $field): string
    {
        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $price = (float) ($field['price'] ?? 0);
        $price_type = $field['price_type'] ?? 'flat';
        $price_display = $price > 0 ? ' <span class="ulo-price-suffix">(+' . wc_price($price) . ')</span>' : '';

        $data_attrs = sprintf(
            'data-ulo-price="%s" data-ulo-price-type="%s"',
            esc_attr((string) $price),
            esc_attr($price_type)
        );

        ob_start();
        ?>
        <label class="ulo-checkbox-option" for="ulo_<?php echo esc_attr($field_id); ?>">
            <input type="checkbox"
                   name="ulo[<?php echo esc_attr($field_id); ?>]"
                   id="ulo_<?php echo esc_attr($field_id); ?>"
                   value="1"
                   <?php echo $data_attrs; // PHPCS: XSS ok. ?>
                   <?php echo $required ? 'required' : ''; ?>>
            <span class="ulo-checkbox-label"><?php echo esc_html($field['label']); ?></span>
            <?php 
            if (!empty($field['badge'])) {
                echo self::render_badge($field['badge'], $field['badge_color'] ?? '#ef4444', !empty($field['badge_pulse']));
            }
            ?>
            <?php echo $price_display; ?>
        </label>
        <?php
        return ob_get_clean();
    }

    /**
     * Render checkbox group (multiple checkboxes).
     */
    private static function render_checkbox_group(array $field): string
    {
        if (!isset($field['options']) || !is_array($field['options'])) {
            return '';
        }

        $field_id = esc_attr($field['id']);

        ob_start();
        ?>
        <div class="ulo-checkbox-group">
            <?php foreach ($field['options'] as $index => $option): ?>
                <?php
                $option_id = $field_id . '_' . $index;
                $value = esc_attr($option['value'] ?? '');
                $label = esc_html($option['label'] ?? '');
                $price = (float) ($option['price'] ?? 0);
                $price_type = $option['price_type'] ?? 'flat';
                $price_display = $price > 0 ? ' <span class="ulo-price-suffix">(+' . wc_price($price) . ')</span>' : '';

                $data_attrs = sprintf(
                    'data-ulo-price="%s" data-ulo-price-type="%s"',
                    esc_attr((string) $price),
                    esc_attr($price_type)
                );
                ?>
                <label class="ulo-checkbox-option" for="ulo_<?php echo esc_attr($option_id); ?>">
                    <input type="checkbox"
                           name="ulo[<?php echo esc_attr($field_id); ?>][]"
                           id="ulo_<?php echo esc_attr($option_id); ?>"
                           value="<?php echo esc_attr($value); ?>"
                           <?php echo $data_attrs; // PHPCS: XSS ok. ?>>
                    <span class="ulo-checkbox-label"><?php echo $label; ?></span>
                    <?php echo $price_display; ?>
                    <?php 
                    if (!empty($option['badge'])) {
                        echo self::render_badge($option['badge'], $option['badge_color'] ?? '#ef4444', !empty($option['badge_pulse']));
                    }
                    ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render select dropdown.
     */
    private static function render_select(array $field): string
    {
        if (!isset($field['options']) || !is_array($field['options'])) {
            return '';
        }

        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'] ? 'required' : '';

        ob_start();
        ?>
        <div class="ulo-select-wrapper">
            <select name="ulo[<?php echo $field_id; ?>]"
                    id="ulo_<?php echo $field_id; ?>"
                    class="ulo-select"
                    <?php echo $required; ?>>
                <option value=""><?php esc_html_e('Select an option...', 'ultra-light-options'); ?></option>
                <?php foreach ($field['options'] as $option): ?>
                    <?php
                    $value = esc_attr($option['value'] ?? '');
                    $label = esc_html($option['label'] ?? '');
                    $price = (float) ($option['price'] ?? 0);
                    $price_type = $option['price_type'] ?? 'flat';
                    $price_display = $price > 0 ? ' (+' . strip_tags(wc_price($price)) . ')' : '';

                    $data_attrs = sprintf(
                        'data-ulo-price="%s" data-ulo-price-type="%s"',
                        esc_attr((string) $price),
                        esc_attr($price_type)
                    );
                    ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php echo $data_attrs; // PHPCS: XSS ok. ?>>
                        <?php echo esc_html($label . $price_display); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render native date picker.
     */
    private static function render_date(array $field): string
    {
        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $min = $field['min_date'] ?? '';
        $max = $field['max_date'] ?? '';

        ob_start();
        ?>
        <div class="ulo-date-wrapper">
            <input type="date"
                   name="ulo[<?php echo $field_id; ?>]"
                   id="ulo_<?php echo $field_id; ?>"
                   class="ulo-date-input"
                   <?php if ($min): ?>min="<?php echo esc_attr($min); ?>"<?php endif; ?>
                   <?php if ($max): ?>max="<?php echo esc_attr($max); ?>"<?php endif; ?>
                   <?php echo $required; ?>>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render native time picker.
     */
    private static function render_time(array $field): string
    {
        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $min = $field['min_time'] ?? '';
        $max = $field['max_time'] ?? '';

        ob_start();
        ?>
        <div class="ulo-time-wrapper">
            <input type="time"
                   name="ulo[<?php echo $field_id; ?>]"
                   id="ulo_<?php echo $field_id; ?>"
                   class="ulo-time-input"
                   <?php if ($min): ?>min="<?php echo esc_attr($min); ?>"<?php endif; ?>
                   <?php if ($max): ?>max="<?php echo esc_attr($max); ?>"<?php endif; ?>
                   <?php echo $required; ?>>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render file upload.
     */
    private static function render_file(array $field): string
    {
        $field_id = esc_attr($field['id']);
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $allowed_types = $field['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'pdf'];
        $max_size = $field['max_size'] ?? 5242880; // 5MB default
        $accept = '.' . implode(',.', $allowed_types);

        ob_start();
        ?>
        <div class="ulo-file-wrapper" data-field-id="<?php echo esc_attr($field_id); ?>">
            <input type="file"
                   name="ulo_file_<?php echo esc_attr($field_id); ?>"
                   id="ulo_<?php echo esc_attr($field_id); ?>"
                   class="ulo-file-input"
                   accept="<?php echo esc_attr($accept); ?>"
                   data-max-size="<?php echo esc_attr((string) $max_size); ?>"
                   <?php echo $required ? 'required' : ''; ?>>
            <input type="hidden"
                   name="ulo[<?php echo esc_attr($field_id); ?>]"
                   id="ulo_<?php echo esc_attr($field_id); ?>_value"
                   class="ulo-file-value">
            <div class="ulo-file-dropzone">
                <div class="ulo-file-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 16 12 12 8 16"></polyline>
                        <line x1="12" y1="12" x2="12" y2="21"></line>
                        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                        <polyline points="16 16 12 12 8 16"></polyline>
                    </svg>
                </div>
                <p class="ulo-file-text">
                    <?php esc_html_e('Click to upload or drag and drop', 'ultra-light-options'); ?>
                </p>
                <p class="ulo-file-types">
                    <?php printf(
                        esc_html__('Max size: %s. Allowed: %s', 'ultra-light-options'),
                        size_format($max_size),
                        strtoupper(implode(', ', $allowed_types))
                    ); ?>
                </p>
            </div>
            <div class="ulo-file-progress" style="display: none;">
                <div class="ulo-file-progress-bar"></div>
            </div>
            <div class="ulo-file-preview" style="display: none;">
                <span class="ulo-file-name"></span>
                <button type="button" class="ulo-file-remove" aria-label="<?php esc_attr_e('Remove file', 'ultra-light-options'); ?>">×</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render HTML content (instructions/separators).
     */
    private static function render_html_content(array $field): string
    {
        $content = $field['content'] ?? $field['description'] ?? '';

        ob_start();
        ?>
        <div class="ulo-html-content">
            <?php echo wp_kses_post($content); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get icon SVG based on label keywords.
     */
    private static function get_icon_for_label(string $label): string
    {
        $label_lower = strtolower($label);

        $icons = [
            'color' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>',
            'size' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>',
            'date' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
            'delivery' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
            'gift' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>',
            'install' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
            'upload' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path></svg>',
            'length' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h20M12 2l4 4-4 4M12 22l-4-4 4-4"/></svg>',
            'quantity' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
        ];

        foreach ($icons as $keyword => $svg) {
            if (str_contains($label_lower, $keyword)) {
                return $svg;
            }
        }

        return '';
    }

    /**
     * Render a badge for a field or option.
     *
     * @param string $text Badge text.
     * @param string $color Badge background color.
     * @param bool $pulse Whether to add pulse animation.
     * @return string Rendered badge HTML.
     */
    private static function render_badge(string $text, string $color = '#ef4444', bool $pulse = false): string
    {
        if (empty($text)) {
            return '';
        }

        $classes = ['ulo-badge'];
        if ($pulse) {
            $classes[] = 'ulo-badge-pulse';
        }

        return sprintf(
            '<span class="%s" style="background-color: %s;">%s</span>',
            esc_attr(implode(' ', $classes)),
            esc_attr($color),
            esc_html($text)
        );
    }

    /**
     * Get available field types.
     *
     * @return array<string, string> Field type => label.
     */
    public static function get_field_types(): array
    {
        return array_map(
            static fn(string $label): string => __($label, 'ultra-light-options'),
            self::FIELD_TYPES
        );
    }
}
