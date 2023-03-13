<?php
namespace Usility\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function auto_headings_numbering($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'before' => ['If true, the current page number is used. Otherwise given text', false],
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

    $before = $options['before'];
    // assemble output:
    PageFactory::$pg->addAssets('site/plugins/pagefactory-pageelements/assets/css/-head-numbering.css');
    PageFactory::$pg->addBodyTagClass('pfy-auto-heading-numbers');
    $pg = page();
    if (!$before) {
        $branchNr = '';

    } elseif ($before === true || $before[0] === 'n' || $before[0] === 'p') { // page number
        $branchNr = $pg->pageNr()->value();

    } elseif ($before[0] === 'h') { // hierarchical number
        $branchNr = $pg->pageIndex()->value();

    } elseif ($before[0] === 'b') { // branch
        if (preg_match_all('|/(\d+)_|', $pg->root(), $m)) {
            $branchNr = $m[1][0];
        }

    } elseif ($before) {
        $branchNr = $before;
    }
    $branchNr = $branchNr ? "$branchNr.": '';
    PageFactory::$pg->addCss("body {--heading-number-before: \"$branchNr\";}");

    return $str;
}

