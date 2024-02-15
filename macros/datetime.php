<?php

namespace PgFactory\PageFactory;
use function PgFactory\PageFactoryElements\intlDate;

/*
 * PageFactory Macro (and Twig Function)
 */

function datetime($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'format' => ['[string] The format to be applied according to '.
                '<a href="https://www.php.net/manual/en/datetime.format.php" target="_blank">PHP Doc</a>.', 'Y-m-d H:i'],
            'datetime' => ['[int,string] The date and/or time either in ISO-notation or as a Unix timestamp. '.
                'Default is "now".', false],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a date and/or time converted to a string as defined by given format.

Textual output (e.g. name of month) is automatically translated to current language.

## Typical Codes:
|===
| j 	| Day of the month without leading zeros 	| 1 to 31
|---
| d 	| Day of the month, 2 digits with leading zeros 	| 01 to 31
|---
| D 	| A textual representation of a day, three letters | 	Mon through Sun
|---
| l (lowercase 'L') 	| A full textual representation of the day of the week | 	Monday through Sunday
|---
| M 	| A short textual representation of a month, three letters 	| Jan through Dec
|---
| F 	| A full textual representation of a month, such as January or March 	| January through December
|---
| m 	| Numeric representation of a month, with leading zeros 	| 01 through 12
|---
| n 	| Numeric representation of a month, without leading zeros 	| 1 through 12
|---
| Y 	| A full numeric representation of a year, at least 4 digits, with - for years BCE. | E.g. 1999, 2003
|---
| y 	| A two digit representation of a year 	| E.g. 99, 03
|---
| g 	| 12-hour format of an hour without leading zeros 	| 1 through 12
|---
| G 	| 24-hour format of an hour without leading zeros 	| 0 through 23
|---
| h 	| 12-hour format of an hour with leading zeros 	| 01 through 12
|---
| H 	| 24-hour format of an hour with leading zeros 	| 00 through 23
|---
| i 	| Minutes with leading zeros 	| 00 to 59
|---
| s 	| Seconds with leading zeros 	| 00 through 59
|===

Note: Names of weekdays and months are automatically translated, provided a translation table is available for the
currently active language.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    $format = $options['format'];
    $dateTime = $options['datetime'];

    if (!$dateTime) {
        $dateTime = time();
    } elseif (!is_numeric($dateTime)) {
        $dateTime = strtotime($dateTime);
    }

    // simple ISO formats:
    if ($format === 'ISO') {
        return date('Y-m-d H:i', $dateTime);
    } elseif ($format === 'ISOT') {
        return date('Y-m-d\TH:i', $dateTime);
    }

    $out = intlDate($format, $dateTime);
    $str .= $out;

    return $str; // return [$str]; if result needs to be shielded
}

