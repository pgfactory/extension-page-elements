<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function user($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'which' => ['[name,email,role, true] Defines the output. "true" \=> "username &lt;email&gt; (role)" ', true],
        ],
        'summary' => <<<EOT

# $funcName()

Checks whether the visitor is currently logged in. Renders the output depending on argument 'which'.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    $which = $options['which'];

    $user = kirby()->user();
    if ($user) {
        if ($which === true) {
            $name = $user->credentials()['name'] ?? '';
            $email = $user->credentials()['email'] ?? '';
            $role = $user->credentials()['role'] ?? '';
            $str .= "$name &lt;$email&gt; ($role)";
        } else {
            $str .= strtolower($user->credentials()[$which] ?? '');
        }
    } else {
        $str .= 'not logged in';
    }

    return $str;
}

