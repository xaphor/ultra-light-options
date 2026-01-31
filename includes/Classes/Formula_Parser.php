<?php
declare(strict_types=1);

/**
 * Formula Parser - Safe mathematical expression evaluator.
 *
 * SECURITY: This class NEVER uses eval() or similar dangerous functions.
 * It uses a custom tokenizer and recursive descent parser for safe evaluation.
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
 * Safe formula parser class.
 */
final class Formula_Parser
{
    use Logger;

    /**
     * Allowed operators.
     *
     * @var array<int, string>
     */
    private const ALLOWED_OPERATORS = ['+', '-', '*', '/', '(', ')'];

    /**
     * Maximum formula length.
     */
    private const MAX_FORMULA_LENGTH = 500;

    /**
     * Maximum number of tokens.
     */
    private const MAX_TOKENS = 100;

    /**
     * Current position in token array.
     */
    private int $position = 0;

    /**
     * Tokens array.
     *
     * @var array<int, array{type: string, value: string|float}>
     */
    private array $tokens = [];

    /**
     * Evaluate a formula with given variables.
     *
     * @param string $formula Formula string with variable placeholders.
     * @param array<string, float|int|string> $variables Variable values.
     * @return float Calculated result.
     * @throws \InvalidArgumentException If formula is invalid.
     */
    public static function evaluate(string $formula, array $variables = []): float
    {
        $parser = new self();
        return $parser->parse($formula, $variables);
    }

    /**
     * Validate a formula without evaluating.
     *
     * @param string $formula Formula string.
     * @return bool True if valid.
     */
    public static function validate(string $formula): bool
    {
        try {
            // Test with dummy values
            $test_vars = [
                'width' => 100.0,
                'height' => 100.0,
                'length' => 100.0,
                'quantity' => 1.0,
                'base_price' => 50.0,
                'product.width' => 100.0,
                'product.height' => 100.0,
                'product.length' => 100.0,
                'product.weight' => 5.0,
            ];
            $result = self::evaluate($formula, $test_vars);
            return is_numeric($result) && $result >= 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse and evaluate formula.
     *
     * @param string $formula Formula string.
     * @param array<string, float|int|string> $variables Variable values.
     * @return float Result.
     * @throws \InvalidArgumentException If formula is invalid.
     */
    private function parse(string $formula, array $variables): float
    {
        // Security: Check formula length
        if (strlen($formula) > self::MAX_FORMULA_LENGTH) {
            throw new \InvalidArgumentException('Formula exceeds maximum length');
        }

        // Security: Check for dangerous patterns
        $this->checkDangerousPatterns($formula);

        // Substitute variables
        $expression = $this->substituteVariables($formula, $variables);

        // Tokenize
        $this->tokens = $this->tokenize($expression);

        // Security: Check token count
        if (count($this->tokens) > self::MAX_TOKENS) {
            throw new \InvalidArgumentException('Formula too complex');
        }

        // Validate tokens
        $this->validateTokens();

        // Parse expression
        $this->position = 0;
        $result = $this->parseExpression();

        // Ensure we consumed all tokens
        if ($this->position < count($this->tokens)) {
            throw new \InvalidArgumentException('Unexpected token at end of formula');
        }

        // Ensure result is valid
        if (!is_finite($result)) {
            throw new \InvalidArgumentException('Formula resulted in invalid number');
        }

        self::log_formula_evaluation($formula, $variables, $result);

        return max(0.0, $result); // Never return negative prices
    }

    /**
     * Check for dangerous patterns in formula.
     *
     * @param string $formula Formula string.
     * @throws \InvalidArgumentException If dangerous pattern found.
     */
    private function checkDangerousPatterns(string $formula): void
    {
        $dangerous_patterns = [
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/popen\s*\(/i',
            '/proc_open\s*\(/i',
            '/\$\{/i',
            '/\$\[/i',
            '/`/',
            '/\\\\x[0-9a-f]{2}/i',
            '/file_/i',
            '/include/i',
            '/require/i',
            '/function\s*\(/i',
            '/\=\>/i',
            '/\-\>/i',
            '/\:\:/i',
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $formula)) {
                self::log_warning('Dangerous pattern detected in formula', ['formula' => $formula]);
                throw new \InvalidArgumentException('Formula contains dangerous pattern');
            }
        }
    }

    /**
     * Substitute variables with their values.
     *
     * @param string $formula Formula string.
     * @param array<string, float|int|string> $variables Variable values.
     * @return string Expression with substituted values.
     */
    private function substituteVariables(string $formula, array $variables): string
    {
        $expression = $formula;

        // Find all variable placeholders {variable_name}
        preg_match_all('/\{([^}]+)\}/', $formula, $matches);

        foreach ($matches[0] as $index => $placeholder) {
            $var_name = $matches[1][$index];

            // Get value from variables, default to 0
            $value = 0.0;
            if (isset($variables[$var_name])) {
                $value = (float) $variables[$var_name];
            }

            // Security: Ensure value is numeric
            if (!is_numeric($value)) {
                $value = 0.0;
            }

            $expression = str_replace($placeholder, (string) $value, $expression);
        }

        return $expression;
    }

    /**
     * Tokenize expression into operators and numbers.
     *
     * @param string $expression Expression string.
     * @return array<int, array{type: string, value: string|float}> Tokens.
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            // Skip whitespace
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Check for operators
            if (in_array($char, self::ALLOWED_OPERATORS, true)) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $i++;
                continue;
            }

            // Check for numbers (including decimals and negative)
            if (ctype_digit($char) || $char === '.') {
                $num = '';
                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $num .= $expression[$i];
                    $i++;
                }
                // Validate it's a proper number
                if (!is_numeric($num)) {
                    throw new \InvalidArgumentException("Invalid number: {$num}");
                }
                $tokens[] = ['type' => 'number', 'value' => (float) $num];
                continue;
            }

            // Unknown character
            throw new \InvalidArgumentException("Invalid character in formula: {$char}");
        }

        return $tokens;
    }

    /**
     * Validate all tokens are safe.
     *
     * @throws \InvalidArgumentException If invalid token found.
     */
    private function validateTokens(): void
    {
        foreach ($this->tokens as $token) {
            if ($token['type'] === 'operator') {
                if (!in_array($token['value'], self::ALLOWED_OPERATORS, true)) {
                    throw new \InvalidArgumentException("Invalid operator: {$token['value']}");
                }
            } elseif ($token['type'] === 'number') {
                if (!is_numeric($token['value'])) {
                    throw new \InvalidArgumentException("Invalid number: {$token['value']}");
                }
            } else {
                throw new \InvalidArgumentException("Unknown token type: {$token['type']}");
            }
        }
    }

    /**
     * Parse expression (handles + and -).
     *
     * @return float Result.
     */
    private function parseExpression(): float
    {
        $result = $this->parseTerm();

        while ($this->position < count($this->tokens)) {
            $token = $this->tokens[$this->position];

            if ($token['type'] === 'operator' && ($token['value'] === '+' || $token['value'] === '-')) {
                $this->position++;
                $right = $this->parseTerm();

                if ($token['value'] === '+') {
                    $result += $right;
                } else {
                    $result -= $right;
                }
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Parse term (handles * and /).
     *
     * @return float Result.
     */
    private function parseTerm(): float
    {
        $result = $this->parseFactor();

        while ($this->position < count($this->tokens)) {
            $token = $this->tokens[$this->position];

            if ($token['type'] === 'operator' && ($token['value'] === '*' || $token['value'] === '/')) {
                $this->position++;
                $right = $this->parseFactor();

                if ($token['value'] === '*') {
                    $result *= $right;
                } else {
                    // Prevent division by zero
                    if ($right == 0) {
                        throw new \InvalidArgumentException('Division by zero');
                    }
                    $result /= $right;
                }
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Parse factor (handles numbers and parentheses).
     *
     * @return float Result.
     * @throws \InvalidArgumentException If unexpected token.
     */
    private function parseFactor(): float
    {
        if ($this->position >= count($this->tokens)) {
            throw new \InvalidArgumentException('Unexpected end of formula');
        }

        $token = $this->tokens[$this->position];

        // Handle unary minus
        if ($token['type'] === 'operator' && $token['value'] === '-') {
            $this->position++;
            return -$this->parseFactor();
        }

        // Handle unary plus
        if ($token['type'] === 'operator' && $token['value'] === '+') {
            $this->position++;
            return $this->parseFactor();
        }

        // Handle parentheses
        if ($token['type'] === 'operator' && $token['value'] === '(') {
            $this->position++;
            $result = $this->parseExpression();

            if (
                $this->position >= count($this->tokens) ||
                $this->tokens[$this->position]['type'] !== 'operator' ||
                $this->tokens[$this->position]['value'] !== ')'
            ) {
                throw new \InvalidArgumentException('Missing closing parenthesis');
            }

            $this->position++;
            return $result;
        }

        // Handle number
        if ($token['type'] === 'number') {
            $this->position++;
            return (float) $token['value'];
        }

        throw new \InvalidArgumentException("Unexpected token: {$token['value']}");
    }

    /**
     * Get available variables for admin UI.
     *
     * @return array<string, string> Variable name => description.
     */
    public static function get_available_variables(): array
    {
        $variables = [
            'quantity' => __('Product quantity', 'ultra-light-options'),
            'base_price' => __('Product base price', 'ultra-light-options'),
            'product.width' => __('Product width (cm)', 'ultra-light-options'),
            'product.height' => __('Product height (cm)', 'ultra-light-options'),
            'product.length' => __('Product length (cm)', 'ultra-light-options'),
            'product.weight' => __('Product weight (kg)', 'ultra-light-options'),
        ];

        /**
         * Filter available formula variables.
         *
         * @param array<string, string> $variables Variable name => description.
         */
        return apply_filters('ulo_formula_variables', $variables);
    }

    /**
     * Get example formulas for admin UI.
     *
     * @return array<string, array{formula: string, description: string}> Example formulas.
     */
    public static function get_example_formulas(): array
    {
        return [
            'area' => [
                'formula' => '({product.width} * {product.height}) / 10000 * 50',
                'description' => __('Area-based pricing: width × height / 10000 × price per sqm', 'ultra-light-options'),
            ],
            'volume' => [
                'formula' => '({product.width} * {product.height} * {product.length}) / 1000000 * 25',
                'description' => __('Volume-based pricing: width × height × length / 1000000 × price per cubic meter', 'ultra-light-options'),
            ],
            'weight_shipping' => [
                'formula' => '{product.weight} * {quantity} * 2.5',
                'description' => __('Weight-based shipping: weight × quantity × rate per kg', 'ultra-light-options'),
            ],
            'quantity_tier' => [
                'formula' => '{quantity} * 10 + 5',
                'description' => __('Tiered quantity pricing: quantity × per-unit price + base fee', 'ultra-light-options'),
            ],
        ];
    }
}
