<?php

namespace App\Services;

use InvalidArgumentException;

class DocumentVariableRenderer
{
    public function render(string $content, array $variables): string
    {
        preg_match_all('/\{\{\s*([a-z][a-z0-9_.]*)\s*\}\}/i', $content, $matches);

        foreach (array_unique($matches[1]) as $variable) {
            if (! array_key_exists($variable, $variables)) {
                throw new InvalidArgumentException("Unsupported document variable: {$variable}");
            }

            $value = $variables[$variable];
            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Document variable must be scalar: {$variable}");
            }

            $content = preg_replace(
                '/\{\{\s*'.preg_quote($variable, '/').'\s*\}\}/',
                (string) $value,
                $content,
            );
        }

        return $content;
    }
}