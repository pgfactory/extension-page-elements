<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\PageFactoryElements\EMailHelper;
use PgFactory\PageFactoryElements\HtmlMail;

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'subject' => ['(string)', null],
            'subjectVar' => ['(string)', null],
            'markdownVar' => ['(string)', null],
            'markdownFile' => ['(string)', null],
            'cssVar' => ['(string)', null],
            'plainTextVar' => ['(string)', null],
            'edit' => ['(bool|permission)', true],
            'to' => ['(string)', null],
            'from' => ['(string)', null],
            'fromName' => ['(string)', null],
            'file' => ['(string)', null],
            'attachments' => ['(string)', null],
            'schedule' => ['{options} If defined, the Events module is invoked to determine the next event and '.
                'based on that, make values defined in the event available as variables (%key%). '.
                '(For ref see macro *events()*).', false],
        ],
        'summary' => <<<EOT

# $funcName()

TBD

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }
    if ($options['markdownFile']??false) {
        $file = $options['markdownFile'];
        if ($file[0] !== '~') {
            $file = "~page/$file";
        }
        $options['markdown'] = loadFile($file);
    } elseif ($options['markdownVar']??false) {
        $options['markdown'] = TransVars::getVariable($options['markdownVar'], varNameIfNotFound: true);
    }

    $options['subject']     = ($options['subject']??false) ?: TransVars::getVariable((string)$options['subjectVar'], varNameIfNotFound: true);
    $options['css']         = TransVars::getVariable($options['cssVar'], varNameIfNotFound: true);
    $options['plainText']   = TransVars::getVariable($options['plainTextVar'], varNameIfNotFound: false);

    $str .= EMailHelper::render($options);
    return $str;
};

