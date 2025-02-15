<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

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

    $attributes = '';

    // variant youtube video:
    $youtube = $options['youtube'];
    if ($youtube) {
        $attributes = '';
        $title = $options['title'] ? " title='{$options['title']}'" : '';
        $class = $options['class'] ?: $options['wrapperClass'];
        $class = $class ? " class='$class'" : '';
        if ($width = ($options['width']??false)) {
            $attributes .= " width='$width'";
        }
        if ($height = ($options['height']??false)) {
            $attributes .= " height='$height'";
        }
        $startAt = $options['startAt'] ? "&amp;start={$options['startAt']}" : '';
        $html = <<<EOT
<iframe $attributes$class src="https://www.youtube.com/embed/$youtube$startAt"$title allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

EOT;
        return $html;
    }

    // variant locally stored video:
    $title = $options['title'] ? " title='{$options['title']}'" : '';
    $class = $options['class'] ?: $options['wrapperClass'];
    $class = $class ? " $class" : '';
    $startAt = $options['startAt'] ? "#t={$options['startAt']}" : '';

    // determine source files:
    $file = $options['file'] ?: $options['src'];
    $src = '';
    // case 1: no comma-separated list, no extension or last car is '*':
    if (!str_contains($file, ',') && !(pathinfo($file, PATHINFO_EXTENSION)) || (substr($file, -1) === '*')) {
        $path = dirname($file).'/';
        $file = resolvePath(rtrim($file, '*'));
        $files = [];
        foreach (PFY_SUPPORTED_VIDEO_FORMATS as $ext) {
            $f = "$file.$ext";
            if (file_exists($f)) {
                $files[] = $path.basename($f);
            } else {
                $dir = getDir("$file*.$ext");
                foreach ($dir as $f) {
                    $files[] = $path.basename($f);
                }
            }
        }
    // case 2: comma-separated list:
    } else {
        $files = explodeTrim(',', $file);
    }
    // run through all source files and render <source> tags:
    foreach ($files as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (in_array($ext, PFY_SUPPORTED_VIDEO_FORMATS)) {
            $src .= "    <source src='$file$startAt' type='video/$ext'>\n";
        }
    }

    $style = '';
    if ($width = ($options['width']??false)) {
        $style .= "width:$width;";
    }
    if ($height = ($options['height']??false)) {
        $style .= "height:$height;";
        if (!$width) {
            $style .= "width:auto;";
        }
    }
    $style = $style ? ' style="'.$style.'"' : '';

    // misc $attributes:
    if ($poster = ($options['poster']??false)) {
        $attributes .= " poster=\"$poster\"";
    }
    if ($options['controls']??false) {
        $attributes .= ' controls';
    }
    if ($options['autoplay']??false) {
        $attributes .= ' autoplay muted';
    } elseif ($options['muted']??false) {
        $attributes .= ' muted';
    }
    if ($options['loop']??false) {
        $attributes .= ' loop';
    }

    if ($caption = ($options['caption']??false)) {
        $caption = "<div id='pfy-video-caption-$inx' class='pfy-video-caption'>$caption</div>";
        $attributes .= " aria-describedby='pfy-video-caption-$inx'";
    }

    // assemble output:
    $str .= <<<EOT

<div id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$style>
  <video$attributes$title>
$src
    Your browser does not support the video tag.
  </video>
  $caption
</div><!-- /pfy-video-wrapper -->

EOT;

    if ($inx === 1) {
        Assets::addAssets([
            'media/plugins/pgfactory/pagefactory-pageelements/css/-video.css',
        ]);
    }

    return $str; // return [$str]; if result needs to be shielded
};

