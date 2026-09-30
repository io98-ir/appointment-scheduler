<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Admin\Presentation\MenuIcon;

final class MenuIconTest extends TestCase
{
    public function testTheIconIsAValidSvgWithTheMenuFillOnItsRoot(): void
    {
        $svg = new \SimpleXMLElement(MenuIcon::svg());

        self::assertSame('svg', $svg->getName());
        self::assertSame('0 0 20 20', (string) $svg['viewBox']);
        // WordPress repaints a menu icon by the fill on the root element.
        self::assertSame('#a7aaad', (string) $svg['fill']);
    }

    public function testTheMakersNameIsPunchedOutOfTheCalendar(): void
    {
        $svg = new \SimpleXMLElement(MenuIcon::svg());
        $svg->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $holes = $svg->xpath('//s:mask/s:g[@fill="#000"]/s:rect');

        // The line under the month, then the cells of i (4), o (8), 9 (12) and 8 (13) in a 3 x 5 grid.
        self::assertIsArray($holes);
        self::assertCount(1 + 4 + 8 + 12 + 13, $holes);
    }

    public function testItIsAnSvgDataUriWordPressCanRecolour(): void
    {
        $uri = MenuIcon::dataUri();

        self::assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        self::assertSame(MenuIcon::svg(), \base64_decode(\substr($uri, \strlen('data:image/svg+xml;base64,')), true));
    }
}
