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
            'default' => ['Default value in case no url-arg is available.', null],
            'offset' => ['In case of numerical url-args, given offset is added to the value.', null],
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

        } else {
            TransVars::setVariable($urlArgName, $default);
            $value = $default;
        }
    }
    if ($options['offset'] && is_numeric($value)) {
        $value += $options['offset'];
    }
    $str .= $value;

    return $str;
};

