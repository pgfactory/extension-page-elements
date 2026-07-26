<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro
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
            'soundfile' => ['Specifies an mp3 file to be played instead of reading the text. '.
                'By default, the first mp3 file in the page folder is used, if found. '.
                'Set to "false" if you want to force TTS.', null],
            'autoplay' => ['If true, speech will start automatically when clicking the open button.', true],
            'speedset' => ['If true, .', true],
            'callback' => ['[functionName] Name of a js function which will be called upon activating the open button. '.
                'The function is expected to return a string to be read aloud. ', null],
        ],
        'summary' => <<<EOT

# $funcName()

Usage as Macro:
    \{{ say('#say-this') }}
    \@@@ #say-this
    Text to say...
    \@@@

Usage via js:
    \{{ button(
        Say something
        callback: "TextToSpeech.say('Say something...')"
    ) }}

**Note:**  
TTS only works after user interaction with the browser, e.g. clicking somewhere. 

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    $title = $options['title'] ? " title='{$options['title']}'" : ' title="{{ pfy-tts-open-title }}"';
    $textSelector = $options['textSelector'];
    $wrapperId = $options['wrapperId'].$options['id'];
    $wrapperId = $wrapperId? " id='{$wrapperId}'" : '';

    $wrapperClass = $options['wrapperClass'].$options['class'];
    $wrapperClass = $wrapperClass? " $wrapperClass": '';
    $wrapperClass .= $options['autoplay']? ' pfy-tts-autoplay' : '';

    $speedset = $options['speedset'] ?: '';
    if ($speedset) {
        $speedset = <<<EOT

<div class="pfy-tts-speed-wrapper">
<label class='pfy-invisible'>{{ pfy-tts-speed-label }}</label>
<span class="pfy-tts-speed-wrapper" title="{{ pfy-tts-speed-title }}">
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

    // handle soundfile option:
    if (($soundfile = $options['soundfile']) === true || $soundfile === null) {
        $files = getDir('~page/*.mp3', associative: true);
        $files = array_keys($files);
        $soundfile = $files[0]??false;
    }
    if ($soundfile) {
        if (!str_starts_with($soundfile, '~')) {
            $soundfile = "~page/$soundfile";
        }
        $soundfile = Utils::resolveUrl($soundfile, true);
        $soundfile = <<<EOT
<audio controls src="$soundfile" class="pfy-invisible"></audio>
EOT;
    }

    $soundfile = (string)$soundfile;

    $str .= <<<EOT

<div$wrapperId class="pfy-tts-widget$wrapperClass" data-say-target="$textSelector"$callback>
    <button id="pfy-button-open-$inx" class="pfy-button pfy-tts-open" aria-pressed="false"$title>{{ pfy-tts-open }}</button>
    <div class="pfy-tts-buttons">
        <button id="pfy-button-play-$inx" class="pfy-button pfy-tts-play" aria-pressed="false" title="{{ pfy-tts-play-title }}">{{ pfy-tts-play }}</button>
        <button id="pfy-button-pause-$inx" class="pfy-button pfy-tts-pause" aria-pressed="false" title="{{ pfy-tts-pause-title }}">{{ pfy-tts-pause }}</button>
        <button id="pfy-button-stop-$inx" class="pfy-button pfy-tts-stop" aria-pressed="false" title="{{ pfy-tts-stop-title }}">{{ pfy-tts-stop }}</button>
    </div><!--/.pfy-tts-buttons-->
$speedset$soundfile
</div><!--/.pfy-tts-widget-->

EOT;

    if ($inx === 1) {
        Assets::addAssets('SAYTTS');
        $html = <<<EOT
<svg width="48" height="48" viewBox="0 0 100 100" fill="none">
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
        Page::addBodyEndInjections($html);
    }

    $str = TransVars::translate($str);
    $str = shieldStr($str);
    return $str;
};

