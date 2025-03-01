<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\PageFactoryElements\Video;

if (!defined('PFY_SUPPORTED_VIDEO_FORMATS')) {
    define('PFY_SUPPORTED_VIDEO_FORMATS', ['webm','ogg','mp4']);
}

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'src' => ['Video source file. May be "path/to/*" or a list like "~page/movie.webm,~page/movie.mp4"', null],
            'file' => ['Synonym for "src".', null],
            'youtube' => ['[youtube code] Let\'s you embed a video from youtube.<br>Obtain the youtube code as described '.
                'in the help video (-> use [Share], [Copy] then extract code from URL).', null],
            'startAt' => ['[seconds] If defined, video starts at given time.', null],
            'wrapperClass' => ['Class applied to the wrapper tag.', null],
            'class' => ['Synonym for "wrapperClass".', null],
            'title' => ['Title attribute applied to player.', null],
            'controls' => ['If true, the browser will display its video control widgets.', true],
            'autoplay' => ['If true, the browser will automatically start playing the video. '.
                'In this case option "mute" is automatically set to true as autoplay would not work otherwise.', false],
            'muted' => ['If true, video will be in muted state.', false],
            'loop' => ['If true, video will be run as endless loop.', false],
            'poster' => ['[path/to/image] Specifies an image that will initially be shown in place of the video.'.
                'The image\'s aspect ration should match the video\'s.', false],
            'caption' => ['[string] Some text that will be displayed below the video.', false],
            'width' => ['[css value and unit] Specifies width of video frame, e.g. "25vw".', null],
            'height' => ['[css value and unit] Specifies height of video frame, e.g. "auto".', null],
        ],
        'summary' => <<<EOT

# $funcName()
Youtube video explaining how to obtain the youtube code:

<iframe width="560" height="315" src="https://www.youtube.com/embed/ly36kn0ug4k?si=bIN1wDX0t4GgqFmN&amp;start=36" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    return $str . Video::render($options);
};


