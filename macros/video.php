<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function video($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['', false],
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

    $file = resolvePath($options['file']);

    // assemble output:
    $str .= <<<EOT

<div class="pfy-video-wrapper">
<video controls>
  <source src="$file" type="video/mp4">
Your browser does not support the video tag.
</video>
</div><!-- /pfy-video-wrapper -->

EOT;

    if ($inx === 1) {
        PageFactory::$pg->addAssets([
            'media/plugins/pgfactory/pagefactory-pageelements/css/-video.css',
        ]);
    }

    return $str; // return [$str]; if result needs to be shielded
}

