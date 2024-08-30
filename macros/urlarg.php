<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($args = '')
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
    $default = $options['default']??'';

    if ($urlArgName = ($options['name']??false)) {
        if (isset($_GET[$urlArgName])) {
            $value = $_GET[$urlArgName];
            if ($default && !$value) {
                $value = $default;
            }
            TransVars::setVariable($urlArgName, $value);
            $str .= $value;

        } else {
            TransVars::setVariable($urlArgName, $default);
            $str .= $default;
        }
    }

    return $str;
};

