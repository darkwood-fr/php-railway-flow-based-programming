<?php

declare(strict_types=1);

namespace Flow\Job;

use Closure;
use Flow\JobInterface;
use RuntimeException;
use Symfony\Component\String\UnicodeString;

use function count;
use function is_callable;
use function is_string;
use function sprintf;
use function strlen;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements JobInterface<TArgs,TReturn>
 */
class LambdaJob implements JobInterface
{
    public function __construct(private string|UnicodeString $expression) {}

    public function __invoke($data): mixed
    {
        $expression = $this->expression instanceof UnicodeString
            ? $this->expression
            : new UnicodeString($this->expression);

        $tokens = $this->tokenize($expression);
        $ast = $this->parse($tokens);
        $lambda = $this->evaluate($ast);

        return $lambda($data);
    }

    private function tokenize(UnicodeString $expression): array
    {
        $tokens = [];
        $position = 0;
        $length = $expression->length();

        while ($position < $length) {
            // Skip whitespace
            if (preg_match('/\s/', $expression->slice($position, 1)->toString())) {
                $position++;

                continue;
            }

            // Match lambda symbol
            if ($expression->slice($position, 1)->toString() === 'λ') {
                $tokens[] = ['type' => 'lambda', 'value' => 'λ'];
                $position++;

                continue;
            }

            // Match variables (multiple letters with optional subscript numbers)
            if (preg_match('/^([a-zA-Z0-9]+)/i', $expression->slice($position)->toString(), $matches)) {
                $tokens[] = ['type' => 'var', 'value' => $matches[0]];
                $position += strlen($matches[0]);

                continue;
            }

            // Match dot and parentheses
            $symbols = ['.', '(', ')'];
            $types = ['dot', 'lparen', 'rparen'];
            $char = $expression->slice($position, 1)->toString();
            $index = array_search($char, $symbols, true);
            if ($index !== false) {
                $tokens[] = ['type' => $types[$index], 'value' => $char];
                $position++;

                continue;
            }

            throw new RuntimeException(sprintf(
                'Unexpected character "%s" at position %d',
                $expression->slice($position, 1)->toString(),
                $position
            ));
        }

        return $tokens;
    }

    private function parse(array $tokens): array
    {
        $position = 0;
        $length = count($tokens);

        $parseExpr = static function () use (&$position, &$tokens, &$parseExpr, $length) {
            if ($position >= $length) {
                throw new RuntimeException('Unexpected end of input');
            }

            // Handle lambda abstraction 'λx.expr'
            if ($tokens[$position]['type'] === 'lambda') {
                // dump('lambda');
                $position++; // Skip 'λ'

                if ($position >= $length || $tokens[$position]['type'] !== 'var') {
                    throw new RuntimeException('Expected variable after lambda');
                }
                $param = $tokens[$position++]['value'];

                if ($position >= $length || $tokens[$position]['type'] !== 'dot') {
                    throw new RuntimeException('Expected dot after parameter');
                }
                $position++; // Skip '.'

                $expr = ['λ', $param, $parseExpr()];
            }

            // Handle variables
            else if ($tokens[$position]['type'] === 'var') {
                // dump('var');
                $expr = $tokens[$position++]['value'];
            }

            // Handle parens '(expr)'
            else if ($tokens[$position]['type'] === 'lparen') {
                // dump('(expr)');
                $position++; // Skip '('

                $expr = $parseExpr();

                if ($position >= $length || $tokens[$position]['type'] !== 'rparen') {
                    throw new RuntimeException('Expected closing parenthesis');
                }
                $position++; // Skip ')'
            }

            if(empty($expr)) {
                throw new RuntimeException(sprintf(
                    'Unexpected token type "%s" at position %d',
                    $tokens[$position]['type'],
                    $position
                ));
            }

            if ($position >= $length || $tokens[$position]['type'] === 'rparen') {
                return $expr;
            }

            // Handle application 'expr expr'
            return ['app', $expr, $parseExpr()];
        };

        $ast = $parseExpr();

        if ($position < $length) {
            throw new RuntimeException('Unexpected tokens after expression');
        }

        return $ast;
    }

    private function evaluate($exp, array $env = []): mixed
    {
        // Add debug output to trace execution
        $depth = count(debug_backtrace());
        $indent = str_repeat('  ', $depth - 1);
        echo $indent . "Evaluating: " . json_encode($exp) . "\n";
        echo $indent . "Environment: " . json_encode($env) . "\n";

        // Variable reference
        if (is_string($exp)) {
            if (!isset($env[$exp])) {
                echo $indent . "ERROR: Unbound variable: {$exp}\n";
                throw new RuntimeException("Unbound variable: {$exp}");
            }
            echo $indent . "Variable lookup: {$exp} = " . json_encode($env[$exp]) . "\n";
            return $env[$exp];
        }

        // Lambda abstraction
        if ($exp[0] === 'λ') {
            [, $param, $body] = $exp;
            echo $indent . "Creating lambda with param: {$param}\n";

            // Return a closure that captures the current environment
            return function ($arg) use ($param, $body, $env) {
                echo str_repeat('  ', count(debug_backtrace()) - 1) . "Applying lambda {$param} with arg: " . json_encode($arg) . "\n";
                return $this->evaluate($body, array_merge($env, [$param => $arg]));
            };
        }

        // Application
        if ($exp[0] === 'app') {
            [, $e1, $e2] = $exp;
            echo $indent . "Evaluating function expression...\n";
            $fn = $this->evaluate($e1, $env);
            echo $indent . "Evaluating argument expression...\n";
            $arg = $this->evaluate($e2, $env);

            if (!is_callable($fn)) {
                echo $indent . "ERROR: Cannot apply non-function value\n";
                throw new RuntimeException('Cannot apply non-function value');
            }

            echo $indent . "Applying function to argument...\n";
            return $fn($arg);
        }

        echo $indent . "ERROR: Invalid expression type\n";
        throw new RuntimeException('Invalid expression type');
    }
}
