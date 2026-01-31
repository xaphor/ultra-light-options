<?php
declare(strict_types=1);

/**
 * Logger Trait - Shared logging methods.
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

namespace ULO\Traits;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait for shared logging methods.
 */
trait Logger
{
    /**
     * Log a debug message.
     *
     * @param string $message Log message.
     * @param array<string, mixed> $context Additional context.
     */
    public static function log_debug(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        self::write_log('DEBUG', $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message.
     * @param array<string, mixed> $context Additional context.
     */
    public static function log_info(string $message, array $context = []): void
    {
        self::write_log('INFO', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message.
     * @param array<string, mixed> $context Additional context.
     */
    public static function log_warning(string $message, array $context = []): void
    {
        self::write_log('WARNING', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message.
     * @param array<string, mixed> $context Additional context.
     */
    public static function log_error(string $message, array $context = []): void
    {
        self::write_log('ERROR', $message, $context);
    }

    /**
     * Write log entry.
     *
     * @param string $level Log level.
     * @param string $message Log message.
     * @param array<string, mixed> $context Additional context.
     */
    private static function write_log(string $level, string $message, array $context = []): void
    {
        $log_entry = sprintf(
            '[ULO %s] %s: %s',
            $level,
            current_time('mysql'),
            $message
        );

        if (!empty($context)) {
            $log_entry .= ' | Context: ' . wp_json_encode($context);
        }

        // Use WooCommerce logger if available
        if (function_exists('wc_get_logger') && class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $wc_level = match ($level) {
                'DEBUG' => 'debug',
                'INFO' => 'info',
                'WARNING' => 'warning',
                'ERROR' => 'error',
                default => 'info',
            };
            $logger->log($wc_level, $message, array_merge(['source' => 'ultra-light-options'], $context));
        } else {
            // Fallback to error_log
            error_log($log_entry);
        }
    }

    /**
     * Log formula evaluation for debugging.
     *
     * @param string $formula Original formula.
     * @param array<string, mixed> $variables Variables used.
     * @param float|string $result Result or error.
     */
    public static function log_formula_evaluation(string $formula, array $variables, float|string $result): void
    {
        self::log_debug('Formula evaluation', [
            'formula' => $formula,
            'variables' => $variables,
            'result' => $result,
        ]);
    }

    /**
     * Log price calculation for debugging.
     *
     * @param string $field_id Field ID.
     * @param string $price_type Pricing type.
     * @param float $calculated_price Calculated price.
     * @param array<string, mixed> $details Additional details.
     */
    public static function log_price_calculation(
        string $field_id,
        string $price_type,
        float $calculated_price,
        array $details = []
    ): void {
        self::log_debug('Price calculation', [
            'field_id' => $field_id,
            'price_type' => $price_type,
            'calculated_price' => $calculated_price,
            'details' => $details,
        ]);
    }
}
