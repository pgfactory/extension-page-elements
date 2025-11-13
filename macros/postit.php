<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'content'       => ['(string) The content of the post-it note.', null],
            'text'          => ['Synonyme for "content"', null],
            'class'         => ['(string) Class to be applied (in addition to "pfy-post-it")', null],
            'draggable'     => ['(bool) If true, the post-it note can be dragged around.', true],
            'removable'     => ['(bool) If true, the post-it note can be removed.', true],
            'width'         => ['Width of the post-it note.', '13em'],
            'height'        => ['Height of the post-it note.', null],
            'angle'         => ['Angle.', '10deg'],
            'top'           => ['Displacement from top edge of container.', '1em'],
            'left'          => ['Displacement from left edge of container.', null],
            'right'         => ['Displacement from right edge of container.', '1em'],
            'bgcolor'       => ['Background color of the post-it note. (default: #FAF9C7FF)', null],
            'style'         => ['Style to be applied to the post-it note.', null],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a post-it note.

## CSS Variables

    -\-pfy-post-it-bg-color
    -\-pfy-post-it-border-color
    -\-pfy-post-it-font
    -\-pfy-post-it-angle

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }
    Page::addAssets('POST_IT');

    // assemble output:
    $content = $options['content'] . $options['text'];

    $class = "pfy-post-it pfy-post-it-$inx " . $options['class']??'';
    if ($options['draggable']) {
        $class .= ' pfy-draggable';
        Page::addAssets('DRAGGABLE');
    }
    $css = '';
    if ($width = $options['width']??false) {
        $css .=  "width:$width;";
    }
    if ($width = $options['height']??false) {
        $css .=  "height:$width;";
    }
    if ($options['bgcolor']??false) {
        $css .= " --pfy-post-it-bg-color: {$options['bgcolor']};";
    }
    if ($options['style']??false) {
        $css .= " {$options['style']};";
    }
    if ($options['angle']??false) {
        $css .= " --pfy-post-it-angle:{$options['angle']};";
    }
    if ($left = ($options['left']??false)) {
        $css .= " left:$left;";
    }
    if ($right = ($options['right']??false)) {
        $css .= " right:$right;";
    }
    if ($options['top']??false) {
        $css .= " top:{$options['top']};";
    }
    if ($css) {
        $css = ".pfy-post-it.pfy-post-it-$inx { $css }";
        Page::addCss($css);
    }

    if ($options['removable']??false) {
        $class .= ' pfy-post-it-removable';
    }

    $str .= <<<EOT
<div class="$class">
$content
</div><!-- /pfy-postit -->

EOT;

    return $str;
};
