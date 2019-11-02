<?php
/**
 * Number helper class. Provides additional formatting methods that for working
 * with numbers.
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class Num
{
    public const ROUND_HALF_UP = 1;
    public const ROUND_HALF_DOWN = 2;
    public const ROUND_HALF_EVEN = 3;
    public const ROUND_HALF_ODD = 4;

    /**
     * Valid byte units => power of 2 that defines the unit's size
     * @var  array
     */
    public static array $byte_units = [
        'B'   => 0,
        'K'   => 10,
        'Ki'  => 10,
        'KB'  => 10,
        'KiB' => 10,
        'M'   => 20,
        'Mi'  => 20,
        'MB'  => 20,
        'MiB' => 20,
        'G'   => 30,
        'Gi'  => 30,
        'GB'  => 30,
        'GiB' => 30,
        'T'   => 40,
        'Ti'  => 40,
        'TB'  => 40,
        'TiB' => 40,
        'P'   => 50,
        'Pi'  => 50,
        'PB'  => 50,
        'PiB' => 50,
        'E'   => 60,
        'Ei'  => 60,
        'EB'  => 60,
        'EiB' => 60,
        'Z'   => 70,
        'Zi'  => 70,
        'ZB'  => 70,
        'ZiB' => 70,
        'Y'   => 80,
        'Yi'  => 80,
        'YB'  => 80,
        'YiB' => 80,
    ];

    /**
     * Returns the English ordinal suffix (th, st, nd, etc) of a number.
     *
     * @param integer $number
     * @return  string
     */
    public static function ordinal(int $number): string
    {
        if ($number % 100 > 10 || $number % 100 < 14) {
            return 'th';
        }

        switch ($number % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
            default:
                return 'th';
        }
    }

    /**
     * Locale-aware number and monetary formatting.
     *
     * @param float $number number to format
     * @param integer $places decimal places
     * @param boolean $monetary monetary formatting?
     * @return  string
     */
    public static function format(float $number, int $places, bool $monetary = FALSE): string
    {
        $info = localeconv();

        if ($monetary) {
            $decimal = $info['mon_decimal_point'];
            $thousands = $info['mon_thousands_sep'];
        } else {
            $decimal = $info['decimal_point'];
            $thousands = $info['thousands_sep'];
        }

        return number_format($number, $places, $decimal, $thousands);
    }

    /**
     * Round a number to a specified precision, using a specified tie breaking technique
     *
     * @param float $value Number to round
     * @param integer $precision Desired precision
     * @param integer $mode Tie breaking mode, accepts the PHP_ROUND_HALF_* constants
     * @param boolean $native Set to false to force use of the userland implementation
     * @return float Rounded number
     */
    public static function round(float $value, int $precision = 0, int $mode = self::ROUND_HALF_UP, bool $native = TRUE): ?float
    {
        if ($native) {
            return round($value, $precision, $mode);
        }

        if ($mode === self::ROUND_HALF_UP) {
            return round($value, $precision);
        }
        $factor = ($precision === 0) ? 1 : 10 ** $precision;

        switch ($mode) {
            case self::ROUND_HALF_DOWN:
            case self::ROUND_HALF_EVEN:
            case self::ROUND_HALF_ODD:
                // Check if we have a rounding tie, otherwise we can just call round()
                if (($value * $factor) - floor($value * $factor) === 0.5) {
                    if ($mode === self::ROUND_HALF_DOWN) {
                        // Round down operation, so we round down unless the value
                        // is -ve because up is down and down is up down there. ;)
                        $up = ($value < 0);
                    } else {
                        // Round up if the integer is odd and the round mode is set to even
                        // or the integer is even and the round mode is set to odd.
                        // Any other instance round down.
                        $up = (((bool)floor($value * $factor)) === ($mode === self::ROUND_HALF_EVEN));
                    }

                    if ($up) {
                        $value = ceil($value * $factor);
                    } else {
                        $value = floor($value * $factor);
                    }
                    return $value / $factor;
                }
                return round($value, $precision);
        }
        return null;
    }

    /**
     * Converts a file size number to a byte value. File sizes are defined in
     * the format: SB, where S is the size (1, 8.5, 300, etc.) and B is the
     * byte unit (K, MiB, GB, etc.). All valid byte units are defined in
     * Num::$byte_units
     *
     * @param string $size file size in SB format
     *
     * @return  float
     *
     * @throws Exception
     */
    public static function bytes(string $size): float
    {
        // Prepare the size
        $size = trim($size);

        // Construct an OR list of byte units for the regex
        $accepted = implode('|', array_keys(static::$byte_units));

        // Construct the regex pattern for verifying the size format
        $pattern = '/^([0-9]+(?:\.[0-9]+)?)(' . $accepted . ')?$/Di';

        // Verify the size format and store the matching parts
        if (!preg_match($pattern, $size, $matches)) {
            throw new Exception('The byte unit size, ":size", is improperly formatted.', [
                ':size' => $size,
            ]);
        }

        // Find the float value of the size
        $size = (float)$matches[1];

        // Find the actual unit, assume B if no unit specified
        $unit = Arr::get($matches, 2, 'B');

        // Convert the size into bytes
        return $size * (2 ** static::$byte_units[$unit]);
    }

}
