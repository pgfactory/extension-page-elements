<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\CountVisits;


if (!defined('VISITS_FILE')) {
    define('VISITS_FILE', PFY_KIRBY_BASE_PATH . 'site/logs/visits/visits.yaml');
}
if (!defined('VISITS_SINCE_FILE')) {
    define('VISITS_SINCE_FILE', PFY_KIRBY_BASE_PATH . 'site/logs/visits/visits-since.yaml');
}
if (!defined('VISITS_BOTS_FILE')) {
    define('VISITS_BOTS_FILE', PFY_KIRBY_BASE_PATH . 'site/logs/visits/visits_bots.yaml');
}
require_once dirname(__DIR__).'/src/CountVisits.php';


return function ($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'show'      => ['[false|permission-expr] Whether to show result (otherwise counting silently).', true],
            'prefix'    => ['What to put in front of result.<br>Use "%since%" to inject date since when visits were recorded', null],
            'suffix'    => ['What to put behind result.<br>Use "%since%" to inject date since when visits were recorded.', null],
            'pageId'    => ['If defined, visit count of that page is rendered instead of the current page.', null],
            'dontCount' => ['[true|permission-expr] If true (or permission like "loggedin"), this macro call is not counted.', false],
            'wrapperTag'=> ['Tag in which to wrap the output.', 'div'],
        ],
        'summary' => <<<EOT
# countvisits()

Counts visits per page and returns the count.

Excludes visits from bots and IP-addresses defined in `site/config/config.php:

    'pgfactory.pagefactory' \=> [
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
    $tag = $options["wrapperTag"];
    $obj = new CountVisits();
    $str .= $obj->render($options);
    if ($str) {
        $str = "\n\t<$tag class='pfy-countvisits'>$str</$tag>\n";
    }

    return $str;
};

