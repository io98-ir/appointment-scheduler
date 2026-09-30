<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Presentation;

/**
 * The plugin's icon in the wp-admin menu: a calendar with "io98" punched out
 * of it in pixel letters, so the maker's name is what the eye finds first.
 * WordPress recolours an SVG data URI by the fill on its root element, and
 * the mask leaves the letters see-through in whatever colour the menu has.
 */
final class MenuIcon
{
    /** 3 x 5 cells per letter, "#" is a see-through cell. */
    private const GLYPHS = [
        'i' => ['.#.', '...', '.#.', '.#.', '.#.'],
        // Low, like a lowercase letter: a tall one reads as a zero.
        'o' => ['...', '...', '###', '#.#', '###'],
        '9' => ['###', '#.#', '###', '..#', '###'],
        '8' => ['###', '#.#', '###', '#.#', '###'],
    ];

    /** WordPress's own menu icon colour; it repaints it on hover and when current. */
    private const FILL = '#a7aaad';

    private const TEXT = 'io98';
    private const LETTER_WIDTH = 3;
    private const GAP = 1;
    private const TEXT_X = 2;
    private const TEXT_Y = 11;

    public static function svg(): string
    {
        $cells = '';
        $x = self::TEXT_X;
        foreach (\str_split(self::TEXT) as $letter) {
            foreach (self::GLYPHS[$letter] as $row => $line) {
                foreach (\str_split($line) as $column => $cell) {
                    if ('#' === $cell) {
                        $cells .= \sprintf(
                            '<rect x="%d" y="%d" width="1" height="1"/>',
                            $x + $column,
                            self::TEXT_Y + $row
                        );
                    }
                }
            }
            $x += self::LETTER_WIDTH + self::GAP;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="' . self::FILL . '">'
            . '<mask id="m" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20">'
            . '<rect x="0" y="3" width="19" height="16" rx="3" fill="#fff"/>'
            // The line under the month and the letters.
            . '<g fill="#000" shape-rendering="crispEdges"><rect x="2" y="6" width="15" height="1"/>' . $cells . '</g>'
            . '</mask>'
            . '<rect x="0" y="3" width="19" height="16" rx="3" mask="url(#m)"/>'
            . '<rect x="4" y="0" width="2" height="5" rx="1"/><rect x="13" y="0" width="2" height="5" rx="1"/>'
            . '</svg>';
    }

    /**
     * For the icon argument of add_menu_page().
     */
    public static function dataUri(): string
    {
        // A menu icon has to be base64 for WordPress to recolour it, which is all this encoding is for.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return 'data:image/svg+xml;base64,' . \base64_encode(self::svg());
    }
}
