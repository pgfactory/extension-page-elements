<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro
 */

use function PgFactory\PageFactoryElements\intlDate;
use function PgFactory\PageFactoryElements\intlDateFormat;
use function PgFactory\PageFactoryElements\resolveTimePlaceholders;

return function ($args = '') {
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'date' => ['[ISO-datetime] Defines the date/time to render. (default: now)', null],
            'format' => ['Defines how to render the date. (default: "Mittwoch, 19.März 2025")', null],
            'offset' => ['(string) Defines an offset that is applied to the date/time, e.g. "+2 months".', null],
            'default' => ['[ISO-datetime] Returned if format or date is missing.', false],
            'intlDateFormat' => ['If true, "IntlDateFormatter" format is used.', false],
        ],
        'summary' => <<<EOT

# date()

Accepts a date and/or time and converts it to another format, taking locale into account.

See table of supported symbols: https://www.php.net/manual/en/datetime.format.php

For option 'intlDateFormat', instead refer to https://www.unicode.org/reports/tr35/tr35-dates.html#Date_Field_Symbol_Table

## Format:

@@@ .pfy-2col33

### Day

'j'   2em>> => '1'
'd'   >> => '01'

### Month

'n'   2em>> => '1'
'm'   >> => '01'
'M'   >> => 'Jan'
'F'   >> => 'January'

### Year

'y'   2em>> => '25'
'Y'   >> => '2025'

@@@ .pfy-2col66

### Time

'g'   2em>> => '1'  4em>> \// Hour: 1 or 2 digits
'H'   >> => '01'	>> \// Hour: 2 digits
'i'   >> => '59'	>> \// Minutes: 2 digits
's'   >> => '59'    >> \// Seconds: 2 digits


### Day of week

'D'   2em>> => 'Mon'
'l'   >> => 'Monday'

### Others

'W'   2em>> => '12'  4em>> \// Week of year
'ISO'   2em>> => '...'  4em>> \// ISO Format
'\'   2em>> => '...'  4em>> \// Default format

@@@

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    $date = ($options['date']??false);
    $format = ($options['format']??false);

    // handle special case where format is first arg:
    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $tmp = $date;
        $date = $format;
        $format = $tmp;
    }
    // assemble output:
    if (!$date) {
        $t = time();
    } else {
        $t = resolveTimePlaceholders($date);
    }

    if ($offset = ($options['offset']??false)) {
        $t = strtotime($offset, $t);
    }

    if (!$format) {
        return intlDateFormat('LONG', $t);;
    }
    if ($options['intlDateFormat']??false) {
        $str .= intlDateFormat($format, $t);
    } else {
        $str .= intlDate($format, $t);
    }
    return $str;
};
