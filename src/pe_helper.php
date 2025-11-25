<?php

namespace PgFactory\PageFactoryElements;

use IntlDateFormatter;
use Kirby\Exception\Exception;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars as TransVars;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\isLocalhost;
use function \PgFactory\PageFactory\writeFile;
use function \PgFactory\PageFactory\translateToIdentifier;
use function \PgFactory\PageFactory\getDir;
use function \PgFactory\PageFactory\fileTime;
use function \PgFactory\PageFactory\getFile;
use function \PgFactory\PageFactory\mylog;

 // Europe centric (and incomplete) presets:
if (PageFactory::$langCode === 'en') {
    define('FULL_DATE_FORMAT', 'EEEE, d MMMM yyyy, h:mm a');
} elseif (PageFactory::$langCode === 'de') {
    define('FULL_DATE_FORMAT', "EEEE, d. MMMM yyyy, HH.mm 'Uhr'");
} elseif (PageFactory::$langCode === 'fr') {
    define('FULL_DATE_FORMAT', "EEEE, d. MMMM yyyy, HH'h'mm");
} else {
    define('FULL_DATE_FORMAT', "EEEE, d. MMMM yyyy, HH:mm");
}
if (PageFactory::$langCode === 'en') {
    define('LONG_DATE_FORMAT',  'EEEE, d MMMM yyyy');
    define('MEDIUM_DATE_FORMAT',  'd MMM yyyy');
} else {
    define('LONG_DATE_FORMAT',  'EEEE, d.MMMM yyyy');
    define('MEDIUM_DATE_FORMAT',  'EEEE, d.MMM yyyy');
}


/**
 * Compiles format string to localized date string.
 * Supports syntax 'Ynnn', where nnn is number of days to early switch to next year.
 * @param string $format
 * @param mixed $time
 * @return string
 */
function intlDate(string $format, mixed $time = false): string
{
    $format = resolveYearPlaceholder($format);
    $time = $time ?: time();
    if (!is_numeric($time)) {
        $time = strtotime($time);
    } elseif (is_string($time)) {
        $time = intval($time);
    }

    if (str_contains(',FULL,LONG,MEDIUM,SHORT,RELATIVE_LONG,RELATIVE_MEDIUM,RELATIVE_SHORT,NONE,', ",$format,")) {
        return intlDateFormat($format, $time);
    // simple ISO format:
    } elseif (!$format || $format === 'ISO') {
        return date('Y-m-d H:i', $time);
    } elseif ($format === 'ISOT') {
        return date('Y-m-d\TH:i', $time);
    } elseif ($format === 'r') {
        return intlDateFormat('LONG,SHORT', $time);
    } elseif ($format === '') {
        return intlDateFormat('LONG,NONE', $time);
    }

    $replacements = [
        'j' => 'd', // 1
        'd' => 'dd', // 01

        'n' => 'M',
        'm' => 'MM',
        'M' => 'MMM',
        'F' => 'MMMM',

        'y' => 'yy',
        'Y' => 'yyyy',

        'g' => 'H',
        'G' => 'H',
        'H' => 'HH',
        'i' => 'mm',
        's' => 'ss',

        'N' => 'e', // day in week
        'D' => 'E', // Mon
        'l' => 'EEEE', // Monday

        'W' => 'w', // week in year
        'e' => 'z', // timezone
        'T' => 'z',
        'c' => 'YYYY-MM-dd\'T\'HH:mm', // ISO 8601 date, e.g. 2004-02-12T15:19:21+00:00
    ];
    $format1 = '';
    for ($i=0; $i<strlen($format); $i++) {
        $char = $format[$i];
        if (isset($replacements[$char])) {
            $format1 .= $replacements[$char];
        } else {
            $format1 .= $char;
        }
    }
    return intlDateFormat($format1, $time);
} // intlDate


/**
 * Compiles "intlDate()-style" (eg. YYYY-MMM-dd) format and translates to local language.
 *   Alternative format: "XXX,YYY", where XXX resp. YYY are one of FULL|LONG|MEDIUM|SHORT|NONE
 * @param string $format
 * @param mixed $time
 * @return string
 */
function intlDateFormat(string $format, mixed $time = false): string
{
    $time = $time ?: time();
    if (is_string($time)) {
        $time = strtotime($time);
    }

    $dateFormat = false;
    $timeFormat = false;
    if (preg_match('/(FULL|LONG|MEDIUM|SHORT|NONE)/', $format)) {
        list($dateFormat, $timeFormat) = explode(',', "$format,NONE");
        $format = '';
    }

    switch ($dateFormat) {
        case 'FULL':   $dateFormat = IntlDateFormatter::FULL; break;
        case 'LONG':   $dateFormat = IntlDateFormatter::LONG; break;
        case 'MEDIUM': $dateFormat = IntlDateFormatter::MEDIUM; break;
        case 'SHORT':  $dateFormat = IntlDateFormatter::SHORT; break;
        case 'RELATIVE_LONG':   $dateFormat = IntlDateFormatter::RELATIVE_LONG; break;
        case 'RELATIVE_MEDIUM': $dateFormat = IntlDateFormatter::RELATIVE_MEDIUM; break;
        case 'RELATIVE_SHORT':  $dateFormat = IntlDateFormatter::RELATIVE_SHORT; break;
        case 'NONE':   $dateFormat = IntlDateFormatter::NONE; break;
    }
    switch ($timeFormat) {
        case 'FULL':   $timeFormat = IntlDateFormatter::FULL; break;
        case 'LONG':   $timeFormat = IntlDateFormatter::LONG; break;
        case 'MEDIUM': $timeFormat = IntlDateFormatter::MEDIUM; break;
        case 'SHORT':  $timeFormat = IntlDateFormatter::SHORT; break;
        case 'RELATIVE_LONG':   $timeFormat = IntlDateFormatter::RELATIVE_LONG; break;
        case 'RELATIVE_MEDIUM': $timeFormat = IntlDateFormatter::RELATIVE_MEDIUM; break;
        case 'RELATIVE_SHORT':  $timeFormat = IntlDateFormatter::RELATIVE_SHORT; break;
        case 'NONE':   $timeFormat = IntlDateFormatter::NONE; break;
    }

    if (isset(PageFactory::$timezone)) {
        $systemTimeZone = PageFactory::$timezone;
    } else {
        $systemTimeZone = date_default_timezone_get();
    }
    if (isset(PageFactory::$locale)) {
        $currentLocale = PageFactory::$locale;
    } else {
        $currentLocale = setlocale(LC_ALL, 0);
    }

    $fmt = datefmt_create(
        $currentLocale,
        $dateFormat,
        $timeFormat,
        $systemTimeZone,
        IntlDateFormatter::GREGORIAN,
        $format
    );
    return datefmt_format($fmt , $time);
} // intlDateFormat


/**
 * Handles format 'Ynnn', where nnn is number of days to early switch to next year
 * @param string $str
 * @return string
 */
function resolveYearPlaceholder(string $str,): string
{
    if (preg_match('/Y(\d+)/', $str, $m)) {
        $y = date('Y');
        $d = intval($m[1]);
        if ($d) {
            $dayOfYear = intval(date('z'));
            if ($dayOfYear > (365 - $d)) {
                $y += 1;
            }
        }
        $str = str_replace($m[0], (string)$y, $str);
    }
    return $str;
} // resolveYearPlaceholder


/**
 * @param string $url
 * @param string $arg
 * @return string
 */
function urlAppendArg(string $url, string $arg): string
{
    if (str_contains($url, '?')) {
        $url .= '&'.$arg;
    } else {
        $url .= '?'.$arg;
    }
    return $url;
} // urlAppendArg


/**
 * array_splice_assoc
 * Splice an associative array
 * Removes the elements designated by offset & length and replaces them
 * with the elements of replacement array
 * https://nimblewebdeveloper.com/blog/php-splice-associative-keyed-array
 * @param $input array
 * @param $key string
 * @param $length int
 * @param $replacement array
 */
function array_splice_associative($input, $key, $length, $replacement=array()) {
    $index = array_search($key, array_keys($input));

    if($index === false) {
        return $input;
    }

    $before_slice = array_slice($input, 0, $index);
    $after_slice = array_slice($input, $index+$length);

    return array_merge($before_slice, $replacement, $after_slice);
} // array_splice_associative


function sizetostr(int|string $arg, int $precision = 1): string
{
    if (is_string($arg)) {
        $size = filesize($arg);
    } else {
        $size = $arg;
    }

    if ($size < 1024) {
        return $size.' B';

    } elseif ($size < 1048576) {
        return round($size/1024, $precision).' kB';

    } elseif ($size < 1073741824) {
        return round($size/1048576, $precision).' MB';

    } else {
        return round($size/1073741824, $precision).' GB';
    }
} // sizetostr



/**
 * Resolves a format string to current values.
 *   Supports date() type arguments (e.g. 'Y-m-d' or 'Y2-m-d').
 *   Special case: 'Yn' (where n=number) -> flips year to next year when month is greater than 12-n.
 *   Example: Y2 returns next year when called in November or December, otherwise the current year.
 *   Values M (=Jan), F (=January), D (=Mon), l (=Monday) are translated to local language
 */
function resolveTimePlaceholders(string $str, $returnUnixTime = true): string|int
{
    if (preg_match('/\( (.*?) \)/xu', $str, $m)) {
        $str = str_replace($m[0], '', $str); // remove modifier

        // determine reference time:
        $offsetExpr = $m[1];
        if (preg_match('/^\s*([-+]?)(\d+)(\w+)/', $offsetExpr, $mm)) {
            if (!($sign = $mm[1])) {
                $sign = '+';
            }
            switch ($unit = strtolower($mm[3])) {
                case 'y':
                    $unit = 'years';
                    break;
                case 'm':
                    $unit = 'months';
                    break;
                case 'd':
                    $unit = 'days';
                    break;
            }
            $offsetExpr = "$sign{$mm[2]}$unit";
            $tRef = strtotime($offsetExpr);
        } else {
            $tRef = time();
        }

    // legacy format "Yn-m-d":
    } elseif (preg_match('/Y(\d+)/', $str, $m)) {
        $str = str_replace($m[0], 'Y', $str);
        $offsetExpr = "+{$m[1]}days";
        $tRef = strtotime($offsetExpr);
    } else {
        $tRef = time();
    }
    $str = str_replace(['Y', 'm', 'd'], [date('Y', $tRef), date('m', $tRef), date('d', $tRef)], $str);

    if ($returnUnixTime) {
        $t = strtotime($str);
        return $t;
    } else {
        return $str;
    }
} // resolveTimePlaceholders

