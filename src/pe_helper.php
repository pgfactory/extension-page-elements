<?php

namespace PgFactory\PageFactoryElements;

use IntlDateFormatter;
use Kirby\Exception\Exception;
use PgFactory\PageFactory\PageFactory;
use function PgFactory\PageFactory\isLocalhost;

const YEAR_THRESHOLD = 10;


/**
 * Twig-filter wrapper for  intlDate()
 * -> compiles "date()-style" (eg. Y-m-d) format and translates to local language.
 * @param string $arg
 * @param string $format
 * @return string
 */
function twigIntlDateFilter($arg, string $format): string
{
    if (!$arg) {
        if (isLocalhost()) {
            throw new Exception("Error: value missing (for twigIntlDateFilter($format)");
        } else {
            return '???';
        }
    }
    $time = strtotime($arg);
    return intlDate($format, $time);
} // twigIntlDateFilter


/**
 * Twig-filter wrapper for  intlDateFormat()
 * -> compiles "intlDate()-style" (eg. YYYY-MMM-dd) format and translates to local language.
 * @param string $arg
 * @param string $format
 * @return string
 */
function twigIntlDateFormatFilter(string $arg, string $format): string
{
    $time = strtotime($arg);
    return intlDateFormat($format, $time);
} // twigIntlDateFilter


/**
 * Compiles "date()-style" (eg. Y-m-d) format and translates to local language.
 * @param string $format
 * @param mixed $time
 * @return string
 */
function intlDate(string $format, mixed $time = false): string
{
    $time = $time ?: time();
    if (is_string($time)) {
        $time = strtotime($time);
    }

    // simple ISO format:
    if (!$format || $format === 'ISO') {
        return date('Y-m-d H:i', $time);
    } elseif ($format === 'ISOT') {
        return date('Y-m-d\TH:i', $time);
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
        list($dateFormat, $timeFormat) = explode(',', $format);
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

    $fmt = datefmt_create(
        PageFactory::$locale,
        $dateFormat,
        $timeFormat,
        PageFactory::$timezone,
        IntlDateFormatter::GREGORIAN,
        $format
    );
    return datefmt_format($fmt , $time);
} // intlDateFormat


/**
 * Entry point for handling UrlTokens, in particular for access-code-login:
 * @return bool
 */
function handleUrlToken(): bool
{
    $urlToken = PageFactory::$urlToken;
    if (!$urlToken) {
        return false;
    }

    // do something with $urlToken...
    $found = kirby()->users()->filter(function($p) {
        $data = $p->content()->data();
        $ac = $data['accesscode']??'';
        $urlToken = PageFactory::$urlToken;
        if ($ac === $urlToken) {
            return true;
        }
        return false;
    });
    $user = $found->first();
    if ($user) {
        $user->loginPasswordless();
    }

    // remove the urlToken:
    $target = PageFactory::$absPageUrl;
    \PgFactory\PageFactory\reloadAgent($target);
    return false;
} // handleUrlToken


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

