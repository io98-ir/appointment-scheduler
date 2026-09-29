<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * The owner's own look (white-label): the name that replaces the product
 * name on screens, a logo and an accent colour. Each part may be empty, which
 * means "the default".
 */
final class Brand
{
    public const NAME_MAX = 60;
    public const LOGO_MAX = 500;

    private function __construct(
        public readonly string $name,
        public readonly string $logoUrl,
        public readonly string $color,
    ) {
    }

    /**
     * @throws InvalidValue invalid_brand_name, invalid_brand_logo or invalid_brand_color.
     */
    public static function of(string $name, string $logoUrl, string $color): self
    {
        $name = \trim($name);
        $logoUrl = \trim($logoUrl);
        $color = \strtolower(\trim($color));

        if (\mb_strlen($name) > self::NAME_MAX || 1 === \preg_match('/[\x00-\x1F<>]/', $name)) {
            throw new InvalidValue('invalid_brand_name', 'The brand name is too long or has control characters.');
        }
        if (
            '' !== $logoUrl
            && (\strlen($logoUrl) > self::LOGO_MAX || 1 !== \preg_match('#^https?://[^\s<>"\']+$#i', $logoUrl))
        ) {
            throw new InvalidValue('invalid_brand_logo', 'The logo must be an http or https address.');
        }
        if ('' !== $color && 1 !== \preg_match('/^#[0-9a-f]{6}$/D', $color)) {
            throw new InvalidValue('invalid_brand_color', 'The colour must be written as #rrggbb.');
        }

        return new self($name, $logoUrl, $color);
    }

    /**
     * Reads what is stored; a part that no longer passes falls back to the
     * default, so a hand-edited option cannot break the site.
     *
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): self
    {
        $part = static fn (string $key): string => \is_string($stored[$key] ?? null) ? $stored[$key] : '';
        $name = $logo = $color = '';
        try {
            $name = self::of($part('name'), '', '')->name;
        } catch (InvalidValue) {
            // Left empty.
        }
        try {
            $logo = self::of('', $part('logo_url'), '')->logoUrl;
        } catch (InvalidValue) {
            // Left empty.
        }
        try {
            $color = self::of('', '', $part('color'))->color;
        } catch (InvalidValue) {
            // Left empty.
        }

        return new self($name, $logo, $color);
    }

    public static function none(): self
    {
        return new self('', '', '');
    }

    /**
     * @param string $default The product name.
     */
    public function displayName(string $default): string
    {
        return '' === $this->name ? $default : $this->name;
    }
}
