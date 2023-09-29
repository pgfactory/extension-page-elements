<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Login;

/*
 * PageFactory Macro (and Twig Function)
 */

function login($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'mode' => ['[login,passwordless] Defines the login mode, either with username&password or passwordless',
                'login'],
            'nextPage' => ['Defines the page which to open after login', './'],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a login form.
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
    Login::init($options);
    $str .= Login::render();

    return $str; // return [$str]; if result needs to be shielded
}

