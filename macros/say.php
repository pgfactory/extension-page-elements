<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\MarkdownPlus\MarkdownPlus;

function say($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'textSelector' => ['[selector] A selector that identifies the text to be read.', false],
            'wrapperId' => ['Id to apply to the widget.', null],
            'wrapperClass' => ['Class to apply to the widget.', null],
            'id' => ['Synonyme for "wrapperClass"', null],
            'class' => ['Synonyme for "wrapperId".', null],
            'title' => ['Title attribute to apply to the widget.', null],
            'autoplay' => ['If true, speech will start automatically when clicken the open button.', true],
            'callback' => ['[functionName] Name of a js function which will be called upon activating the open button. '.
                'The function is expected to return a string to be read aloud. ', null],
        ],
        'summary' => <<<EOT

# $funcName()

ToDo: describe purpose of function
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    $title = $options['title'] ? " title='{$options['title']}'" : '';
    $textSelector = $options['textSelector'];
    $wrapperId = $options['wrapperId'].$options['id'];
    $wrapperId = $wrapperId? " id='{$wrapperId}'" : '';

    $wrapperClass = $options['wrapperClass'].$options['class'];
    $wrapperClass = $wrapperClass? " $wrapperClass": '';
    $wrapperClass .= $options['autoplay']? ' pfy-say-autoplay' : '';

    $callback = $options['callback'];
    if ($callback) {
        $callback = " data-callback='$callback'";
    }
    // assemble output:

    $str .= <<<EOT

<div$wrapperId class="pfy-say-widget$wrapperClass" data-say-target="$textSelector"$callback>
    <button id="pfy-button-open-$inx" class="pfy-button pfy-say-open" aria-pressed="false"$title>{{ pfy-say-open }}</button>
    <div class="pfy-say-buttons">
        <button id="pfy-button-play-$inx" class="pfy-button pfy-say-play" aria-pressed="false">{{ pfy-say-play }}</button>
        <button id="pfy-button-pause-$inx" class="pfy-button pfy-say-pause" aria-pressed="false">{{ pfy-say-pause }}</button>
        <button id="pfy-button-stop-$inx" class="pfy-button pfy-say-stop" aria-pressed="false">{{ pfy-say-stop }}</button>
    </div><!--/.pfy-say-buttons-->
</div><!--/.pfy-say-widget-->

EOT;

    if ($inx === 1) {
        PageFactory::$pg->addAssets('SAYTTS');
    }

    $str = TransVars::translate($str);
    $str = shieldStr($str);
    return $str;
}

