<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use function PgFactory\PageFactoryElements\intlDate;
use function PgFactory\PageFactoryElements\intlDateFormat;

function _date($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'format' => ['Defines how to render the date.', false],
            'date' => ['[ISO-datetime] Defines the date/time to render.', false],
            'intlDateFormat' => ['If true, "IntlDateFormatter" format is used.', false],
        ],
        'summary' => <<<EOT

# date()

Accepts a date and/or time and converts it to another format, taking locale into account.

See table of supported symbols: https://www.php.net/manual/en/datetime.format.php

For option 'intlDateFormat', instead refer to https://www.unicode.org/reports/tr35/tr35-dates.html#Date_Field_Symbol_Table

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    // assemble output:
    $format = $options['format']??false;
    if (!$format) {
        return $str;
    }
    if (!$date = ($options['date']??false)) {
        $t = time();
    } else {
        $t = strtotime($date);
    }
    if ($options['intlDateFormat']??false) {
        $str .= intlDateFormat($format, $t);
    } else {
        $str .= intlDate($format, $t);
    }

    return $str;
}
