<?php

declare(strict_types=1);

namespace Flow\Job;

use Closure;
use Flow\JobInterface;
use Symfony\Component\String\UnicodeString;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements JobInterface<TArgs,TReturn>
 */
class LambdaJob implements JobInterface
{
    /**
     * @param string|UnicodeString $expression
     */
    public function __construct(private string|UnicodeString $expression) {}

    public function __invoke($data): mixed
    {
        $expr = $this->expression instanceof UnicodeString 
            ? $this->expression 
            : new UnicodeString($this->expression);
            
        $tokens = $this->tokenize($expr);
        $ast = $this->parse($tokens);
        $lambda = $this->evaluate($ast);
        dd($lambda);

        return null;
    }

    private function tokenize(UnicodeString $expression): array {
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

            // Match identifiers
            if (preg_match('/^[a-z]+/', $expression->slice($position)->toString(), $matches)) {
                $tokens[] = ['type' => 'identifier', 'value' => $matches[0]];
                $position += strlen($matches[0]);
                continue;
            }

            // Match dot
            if ($expression->slice($position, 1)->toString() === '.') {
                $tokens[] = ['type' => 'dot', 'value' => '.'];
                $position++;
                continue;
            }

            // Match parentheses
            if ($expression->slice($position, 1)->toString() === '(') {
                $tokens[] = ['type' => 'lparen', 'value' => '('];
                $position++;
                continue;
            }

            if ($expression->slice($position, 1)->toString() === ')') {
                $tokens[] = ['type' => 'rparen', 'value' => ')'];
                $position++;
                continue;
            }

            // Invalid character
            throw new \RuntimeException(sprintf(
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

        $parseExpr = function () use (&$position, $tokens, &$parseExpr, $length) {
            if ($position >= $length) {
                throw new \RuntimeException('Unexpected end of input');
            }

            // Try lambda
            if ($position + 3 < $length &&
                $tokens[$position]['type'] === 'lambda' &&
                $tokens[$position + 1]['type'] === 'identifier' &&
                $tokens[$position + 2]['type'] === 'dot') {
                
                $position++; // skip lambda
                $var = $tokens[$position]['value'];
                $position += 2; // skip var and dot
                
                $expr = $parseExpr();
                return ['λ', $var, $expr];
            }

            // Try application
            $expr = null;
            if ($tokens[$position]['type'] === 'lparen') {
                $position++; // skip lparen
                $expr = $parseExpr();
                
                if ($position >= $length || $tokens[$position]['type'] !== 'rparen') {
                    throw new \RuntimeException('Expected closing parenthesis');
                }
                $position++; // skip rparen
            } else if ($tokens[$position]['type'] === 'identifier') {
                $expr = $tokens[$position]['value'];
                $position++;
            } else {
                throw new \RuntimeException(sprintf(
                    'Unexpected token type "%s"',
                    $tokens[$position]['type']
                ));
            }

            // Look for application
            while ($position < $length && 
                   $position + 1 < $length &&
                   preg_match('/\s/', $tokens[$position]['value'])) {
                $position++; // skip space
                $right = $parseExpr();
                $expr = [$expr, $right];
            }

            return $expr;
        };

        $ast = $parseExpr();

        if ($position < $length) {
            throw new \RuntimeException('Unexpected tokens after expression');
        }

        return $ast;
    }

    private function evaluate($exp, array $env = []): mixed
    {
        // Handle primitives and callables
        if (is_int($exp) || is_float($exp) || is_bool($exp) || (is_object($exp) && is_callable($exp))) {
            return $exp;
        }

        // Handle variable lookup
        if (is_string($exp)) {
            return $env[$exp];
        }

        // Handle lambda abstraction
        if ($exp[0] === 'λ') {
            [$_, $arg, $body] = $exp;
            return ['closure', $arg, $body, $env];
        }

        // Handle function application
        $f = $this->evaluate($exp[0], $env);
        $arg = $this->evaluate($exp[1], $env);
        
        // Apply function
        if (is_callable($f)) {
            return $f($arg);
        }
        
        if (is_array($f) && $f[0] === 'closure') {
            [, $param, $body, $closure_env] = $f;
            return $this->evaluate($body, array_merge($closure_env, [$param => $arg]));
        }
        
        throw new \RuntimeException('Invalid function application');
    }
}
