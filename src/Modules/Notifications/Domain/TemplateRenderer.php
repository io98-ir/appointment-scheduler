<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * Fills {placeholders} of a template. A name that is not in $values becomes
 * empty, so a typo never shows braces to a customer. Values are single-line:
 * they come from customers, and a subject with a line break is a mail header
 * injection.
 */
final class TemplateRenderer
{
    /**
     * @param array<string, string> $values
     */
    public function render(string $text, array $values): string
    {
        return (string) \preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static fn (array $match): string => \preg_replace('/\s*[\r\n]+\s*/', ' ', $values[$match[1]] ?? '') ?? '',
            $text
        );
    }
}
