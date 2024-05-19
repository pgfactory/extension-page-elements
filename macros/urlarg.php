<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function urlarg($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'name' => ['Name of the url-argument as in "?myarg=xy"', false],
            'default' => ['Name of the url-argument as in "?myarg=xy"', null],
        ],
        'summary' => <<<EOT

# $funcName()

Returns url argument with given name.

Example:
    URL: domain.net?myarg=myvalue

URL-Arg: "\{{ urlarg(myarg) }}" => URL-Arg: "myvalue"
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
    if ($urlArgName = ($options['name']??false)) {
        if (isset($_GET[$urlArgName])) {
            $value = $_GET[$urlArgName];
            TransVars::setVariable($urlArgName, $value);
            $str .= $value;
        }
    }

    return $str;
}

