<?php
declare(strict_types=1);

/**
 * File Upload Handler - Manages file uploads with AJAX and security.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Classes;

use ULO\Traits\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * File Upload Handler class.
 */
final class File_Upload_Handler
{
    use Logger;

    /**
     * Temp directory name.
     */
    private const TEMP_DIR = 'ulo-temp';

    /**
     * Secure directory name.
     */
    private const SECURE_DIR = 'ulo-secure';

    /**
     * Default max file size (5MB).
     */
    private const DEFAULT_MAX_SIZE = 5242880;

    /**
     * Default allowed file types.
     *
     * @var array<int, string>
     */
    private const DEFAULT_ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

    /**
     * Instance of this class.
     */
    private static ?File_Upload_Handler $instance = null;

    /**
     * Get instance.
     */
    public static function get_instance(): File_Upload_Handler
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
        // AJAX handlers
        add_action('wp_ajax_ulo_upload_file', [$this, 'ajax_upload_file']);
        add_action('wp_ajax_nopriv_ulo_upload_file', [$this, 'ajax_upload_file']);

        add_action('wp_ajax_ulo_remove_file', [$this, 'ajax_remove_file']);
        add_action('wp_ajax_nopriv_ulo_remove_file', [$this, 'ajax_remove_file']);

        // Move files to secure location on cart add
        add_action('woocommerce_add_to_cart', [$this, 'move_files_on_add_to_cart'], 10, 6);

        // Cleanup temp files on checkout complete
        add_action('woocommerce_checkout_order_processed', [$this, 'move_files_on_order'], 10, 3);

        // Schedule cleanup cron
        add_action('ulo_cleanup_temp_files', [$this, 'cleanup_temp_files']);
    }

    /**
     * Handle AJAX file upload.
     */
    public function ajax_upload_file(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulo-ajax-nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultra-light-options')]);
        }

        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'ultra-light-options')]);
        }

        $file = $_FILES['file'];
        $field_id = isset($_POST['field_id']) ? sanitize_key($_POST['field_id']) : '';

        // Get field configuration for validation rules
        $allowed_types = self::DEFAULT_ALLOWED_TYPES;
        $max_size = self::DEFAULT_MAX_SIZE;

        if (isset($_POST['allowed_types'])) {
            $allowed_types = array_map('sanitize_key', explode(',', $_POST['allowed_types']));
        }
        if (isset($_POST['max_size'])) {
            $max_size = absint($_POST['max_size']);
        }

        // Validate file
        $validation = $this->validate_file($file, $allowed_types, $max_size);
        if (is_wp_error($validation)) {
            wp_send_json_error(['message' => $validation->get_error_message()]);
        }

        // Upload to temp directory
        $result = $this->upload_to_temp($file, $field_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        self::log_info('File uploaded successfully', ['file' => $result['filename'], 'field_id' => $field_id]);

        wp_send_json_success([
            'filename' => $result['filename'],
            'url' => $result['url'],
            'temp_path' => $result['temp_path'],
            'size' => size_format($file['size']),
        ]);
    }

    /**
     * Handle AJAX file removal.
     */
    public function ajax_remove_file(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulo-ajax-nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultra-light-options')]);
        }

        $temp_path = isset($_POST['temp_path']) ? sanitize_text_field($_POST['temp_path']) : '';

        if (empty($temp_path)) {
            wp_send_json_error(['message' => __('Invalid file path.', 'ultra-light-options')]);
        }

        // Validate path is within temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/' . self::TEMP_DIR;

        $real_path = realpath($temp_path);
        if (!$real_path || strpos($real_path, realpath($temp_dir)) !== 0) {
            wp_send_json_error(['message' => __('Invalid file path.', 'ultra-light-options')]);
        }

        // Delete file
        if (file_exists($real_path)) {
            unlink($real_path);
            self::log_info('File removed', ['path' => $real_path]);
        }

        wp_send_json_success(['message' => __('File removed.', 'ultra-light-options')]);
    }

    /**
     * Validate uploaded file.
     *
     * @param array<string, mixed> $file File data from $_FILES.
     * @param array<int, string> $allowed_types Allowed file extensions.
     * @param int $max_size Maximum file size in bytes.
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public function validate_file(array $file, array $allowed_types, int $max_size): true|\WP_Error
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: maximum file size */
                    __('File size exceeds maximum allowed size of %s.', 'ultra-light-options'),
                    size_format($max_size)
                )
            );
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_types, true)) {
            return new \WP_Error(
                'invalid_file_type',
                sprintf(
                    /* translators: %s: allowed file types */
                    __('File type not allowed. Allowed types: %s.', 'ultra-light-options'),
                    strtoupper(implode(', ', $allowed_types))
                )
            );
        }

        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = $this->get_allowed_mimes($allowed_types);
        if (!in_array($mime_type, $allowed_mimes, true)) {
            return new \WP_Error('invalid_mime_type', __('File MIME type is not allowed.', 'ultra-light-options'));
        }

        return true;
    }

    /**
     * Upload file to temp directory.
     *
     * @param array<string, mixed> $file File data from $_FILES.
     * @param string $field_id Field ID.
     * @return array{filename: string, url: string, temp_path: string}|\WP_Error Upload result.
     */
    public function upload_to_temp(array $file, string $field_id): array|\WP_Error
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/' . self::TEMP_DIR;

        // Ensure directory exists
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden');
        }

        // Generate unique filename
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $original_name = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
        $unique_id = uniqid($field_id . '_', true);
        $filename = $original_name . '_' . $unique_id . '.' . $ext;
        $target_path = $temp_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new \WP_Error('upload_failed', __('Failed to upload file.', 'ultra-light-options'));
        }

        return [
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . self::TEMP_DIR . '/' . $filename,
            'temp_path' => $target_path,
        ];
    }

    /**
     * Move files to secure location when adding to cart.
     *
     * @param string $cart_item_key Cart item key.
     * @param int $product_id Product ID.
     * @param int $quantity Quantity.
     * @param int $variation_id Variation ID.
     * @param array<string, mixed> $variation Variation data.
     * @param array<string, mixed> $cart_item_data Cart item data.
     */
    public function move_files_on_add_to_cart(
        string $cart_item_key,
        int $product_id,
        int $quantity,
        int $variation_id,
        array $variation,
        array $cart_item_data
    ): void {
        if (!isset($cart_item_data['ulo_options'])) {
            return;
        }

        // Move any uploaded files to secure location
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_item_key);

        if (!$cart_item) {
            return;
        }

        // Get fields to identify file fields
        $fields = Data_Handler::get_all_fields($product_id, $variation_id);
        $updated_options = $cart_item_data['ulo_options'];
        $has_changes = false;

        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'file') {
                continue;
            }

            $field_id = $field['id'] ?? '';
            if (!isset($updated_options[$field_id]) || empty($updated_options[$field_id])) {
                continue;
            }

            $temp_path = $updated_options[$field_id];
            $secure_path = $this->move_to_secure_location($temp_path, $cart_item_key);

            if ($secure_path) {
                $updated_options[$field_id] = $secure_path;
                $has_changes = true;
            }
        }

        if ($has_changes) {
            $cart->cart_contents[$cart_item_key]['ulo_options'] = $updated_options;
        }
    }

    /**
     * Move files to secure location when order is processed.
     *
     * @param int $order_id Order ID.
     * @param array<string, mixed> $posted_data Posted data.
     * @param \WC_Order $order Order object.
     */
    public function move_files_on_order(int $order_id, array $posted_data, \WC_Order $order): void
    {
        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $raw_options = $item->get_meta('_ulo_options_raw');

            if (empty($raw_options) || !is_array($raw_options)) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $fields = Data_Handler::get_all_fields($product_id, $variation_id);

            foreach ($fields as $field) {
                if (($field['type'] ?? '') !== 'file') {
                    continue;
                }

                $field_id = $field['id'] ?? '';
                if (!isset($raw_options[$field_id])) {
                    continue;
                }

                // Move to order-specific directory
                $current_path = $raw_options[$field_id];
                $order_path = $this->move_to_order_location($current_path, $order_id);

                if ($order_path) {
                    $raw_options[$field_id] = $order_path;
                }
            }

            // Update meta with new paths
            $item->update_meta_data('_ulo_options_raw', $raw_options);
            $item->save();
        }
    }

    /**
     * Move file to secure location.
     *
     * @param string $temp_path Temp file path.
     * @param string $identifier Unique identifier for subdirectory.
     * @return string|null New path or null on failure.
     */
    public function move_to_secure_location(string $temp_path, string $identifier): ?string
    {
        if (!file_exists($temp_path)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/' . self::SECURE_DIR . '/' . sanitize_file_name($identifier);

        // Ensure directory exists with protection
        if (!file_exists($secure_dir)) {
            wp_mkdir_p($secure_dir);
            file_put_contents($secure_dir . '/.htaccess', "Order deny,allow\nDeny from all");
            file_put_contents($secure_dir . '/index.php', '<?php // Silence is golden');
        }

        $filename = basename($temp_path);
        $new_path = $secure_dir . '/' . $filename;

        if (rename($temp_path, $new_path)) {
            self::log_info('File moved to secure location', [
                'from' => $temp_path,
                'to' => $new_path,
            ]);
            return $new_path;
        }

        return null;
    }

    /**
     * Move file to order-specific location.
     *
     * @param string $current_path Current file path.
     * @param int $order_id Order ID.
     * @return string|null New path or null on failure.
     */
    public function move_to_order_location(string $current_path, int $order_id): ?string
    {
        if (!file_exists($current_path)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $order_dir = $upload_dir['basedir'] . '/' . self::SECURE_DIR . '/orders/' . $order_id;

        // Ensure directory exists with protection
        if (!file_exists($order_dir)) {
            wp_mkdir_p($order_dir);
            file_put_contents($order_dir . '/.htaccess', "Order deny,allow\nDeny from all");
            file_put_contents($order_dir . '/index.php', '<?php // Silence is golden');
        }

        $filename = basename($current_path);
        $new_path = $order_dir . '/' . $filename;

        if (rename($current_path, $new_path)) {
            self::log_info('File moved to order location', [
                'order_id' => $order_id,
                'from' => $current_path,
                'to' => $new_path,
            ]);
            return $new_path;
        }

        return null;
    }

    /**
     * Cleanup old temp files (called by cron).
     */
    public function cleanup_temp_files(): void
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/' . self::TEMP_DIR;

        if (!is_dir($temp_dir)) {
            return;
        }

        $max_age = 24 * 60 * 60; // 24 hours
        $now = time();
        $deleted = 0;

        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $max_age) {
                unlink($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            self::log_info('Cleaned up temp files', ['deleted_count' => $deleted]);
        }
    }

    /**
     * Get upload error message.
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    private function get_upload_error_message(int $error_code): string
    {
        return match ($error_code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('File is too large.', 'ultra-light-options'),
            UPLOAD_ERR_PARTIAL => __('File was only partially uploaded.', 'ultra-light-options'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'ultra-light-options'),
            UPLOAD_ERR_NO_TMP_DIR => __('Server configuration error.', 'ultra-light-options'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'ultra-light-options'),
            UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'ultra-light-options'),
            default => __('Unknown upload error.', 'ultra-light-options'),
        };
    }

    /**
     * Get allowed MIME types for file extensions.
     *
     * @param array<int, string> $extensions File extensions.
     * @return array<int, string> MIME types.
     */
    private function get_allowed_mimes(array $extensions): array
    {
        $mime_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            if (isset($mime_map[$ext])) {
                $mimes[] = $mime_map[$ext];
            }
        }

        return $mimes;
    }

    /**
     * Get secure file URL for admin viewing.
     *
     * @param string $file_path File path.
     * @param int $order_id Order ID.
     * @return string|null Secure URL or null.
     */
    public static function get_secure_file_url(string $file_path, int $order_id): ?string
    {
        if (!file_exists($file_path)) {
            return null;
        }

        // Generate temporary access token
        $token = wp_hash($file_path . $order_id . time());

        // Store token temporarily
        set_transient('ulo_file_token_' . $token, $file_path, 3600); // 1 hour

        return add_query_arg([
            'ulo_download' => $token,
            'order_id' => $order_id,
        ], admin_url('admin-ajax.php'));
    }
}
