<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\CountVisits;


if (!defined('VISITS_FILE')) {
    define('VISITS_FILE', 'site/logs/visits/visits.yaml');
}
if (!defined('VISITS_SINCE_FILE')) {
    define('VISITS_SINCE_FILE', 'site/logs/visits/visits-since.yaml');
}
if (!defined('VISITS_BOTS_FILE')) {
    define('VISITS_BOTS_FILE', 'site/logs/visits/visits_bots.yaml');
}
require_once dirname(__DIR__).'/src/CountVisits.php';


return function ($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'show'      => ['[false|loggedin] Whether to show result or record it silently.', true],
            'prefix'    => ['What to put in front of result.', null],
            'postfix'   => ['What to put behind result.', null],
        ],
        'summary' => <<<EOT
# countvisits()

Counts visits per page and returns the count.

Excludes visits from bots and IP-addresses defined in `site/config/config.php:

    'pgfactory.pagefactory.options' \=> [
        'visitCounterIgnoreIPs' \=> '001.002.003.005,::1', \// define list of IP addresses to exclude from visit counts
    ],

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $sourceCode, $inx, $funcName) = $str;
        $str = $sourceCode;
    }

    // assemble output:
    $obj = new CountVisits();
    $str .= $obj->render($options);

    return $str;
};

