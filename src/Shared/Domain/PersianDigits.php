<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * Normalizes Persian and Arabic-Indic digits to Latin, so a number typed on
 * a Persian keyboard reads the same as one typed on a Latin one
 * (principles §7, "ارقام فارسی نرمال شده‌اند").
 */
final class PersianDigits
{
    private const MAP = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function toLatin(string $value): string
    {
        return \strtr($value, self::MAP);
    }
}
