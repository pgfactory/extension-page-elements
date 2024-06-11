<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\MarkdownPlus\MarkdownPlus;

return function ($args = '')
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
            'speedset' => ['If true, .', true],
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

    $title = $options['title'] ? " title='{$options['title']}'" : ' title="{{ pfy-say-open-title }}"';
    $textSelector = $options['textSelector'];
    $wrapperId = $options['wrapperId'].$options['id'];
    $wrapperId = $wrapperId? " id='{$wrapperId}'" : '';

    $wrapperClass = $options['wrapperClass'].$options['class'];
    $wrapperClass = $wrapperClass? " $wrapperClass": '';
    $wrapperClass .= $options['autoplay']? ' pfy-say-autoplay' : '';

    $speedset = $options['speedset'] ?: '';
    if ($speedset) {
        $speedset = <<<EOT

<div class="pfy-say-speed-wrapper">
<label class='pfy-invisible'>{{ pfy-say-speed-label }}</label>
<span class="pfy-say-speed-wrapper" title="{{ pfy-say-speed-title }}">
<label><input type="radio" name="speed" value="0.8x"><span>0.8x</span></label>
<label><input type="radio" name="speed" value="1x"><span>1x</span></label>
<label><input type="radio" name="speed" value="1.15x"><span>1.15x</span></label>
<label><input type="radio" name="speed" value="1.3x"><span>1.3x</span></label>
</span>
</div>

EOT;

    }

    $callback = $options['callback'];
    if ($callback) {
        $callback = " data-callback='$callback'";
    }
    // assemble output:

    $str .= <<<EOT

<div$wrapperId class="pfy-say-widget$wrapperClass" data-say-target="$textSelector"$callback>
    <button id="pfy-button-open-$inx" class="pfy-button pfy-say-open" aria-pressed="false"$title>{{ pfy-say-open }}</button>
    <div class="pfy-say-buttons">
        <button id="pfy-button-play-$inx" class="pfy-button pfy-say-play" aria-pressed="false" title="{{ pfy-say-play-title }}">{{ pfy-say-play }}</button>
        <button id="pfy-button-pause-$inx" class="pfy-button pfy-say-pause" aria-pressed="false" title="{{ pfy-say-pause-title }}">{{ pfy-say-pause }}</button>
        <button id="pfy-button-stop-$inx" class="pfy-button pfy-say-stop" aria-pressed="false" title="{{ pfy-say-stop-title }}">{{ pfy-say-stop }}</button>
    </div><!--/.pfy-say-buttons-->
$speedset
</div><!--/.pfy-say-widget-->

EOT;

    if ($inx === 1) {
        PageFactory::$pg->addAssets('SAYTTS');
        $html = <<<EOT
<svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
<g>
<symbol id='pfy-iconset-play'>
<path d="M82 50L14.5 93.3013V6.69873L82 50Z" fill="currentColor"/>
</symbol>
<symbol id='pfy-iconset-stop'>
<rect x="20" y="20" width="60" height="60" fill="currentColor"/>
</symbol>
<symbol id='pfy-iconset-pause'>
<rect x="22.5" y="20" width="20" height="60" fill="currentColor"/>
<rect x="57.5" y="20" width="20" height="60" fill="currentColor"/>
</symbol>
</g>
</svg>

EOT;
        PageFactory::$pg->addBodyEndInjections($html);
    }



    $str = TransVars::translate($str);
    $str = shieldStr($str);
    return $str;
};

