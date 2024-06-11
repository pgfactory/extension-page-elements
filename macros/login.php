<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Login;

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'mode' => ['[login,passwordless,username-password-only] Defines the login mode. '.
                'Default is a form supporting both username&password and passwordless.'.
                'Use "username-password-only" if you don\'t want passwordless option.',
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
};

