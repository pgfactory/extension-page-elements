<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

use PgFactory\MarkdownPlus\MarkdownPlus;

function message($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'text' => ['Text to be displayed.', false],
            'msg' => ['Synonyme for "text".', false],
            'mdCompile' => ['If true, text will be md-compiled before being displayed.', false],
        ],
        'summary' => <<<EOT
# message()

Briefly displays a notification message in the upper right corner.
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
    $msg = $options['text'] ?: $options['msg'];

    if ($msg) {
        if (strpos($msg, '{{') !== false) {
            $msg = TransVars::translate($msg);
        }
        if ($options['mdCompile']) {
            $mdp = new MarkdownPlus();
            $msg = $mdp->compile($msg);
        }

        PageFactory::$pg->addAssets('MESSAGES');
        $html = "\t\t<div class='pfy-msgbox'>$msg</div>\n";
        PageFactory::$pg->addBodyEndInjections($html);
    }
    return $sourceCode;
}

