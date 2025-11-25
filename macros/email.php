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
            'subject' => ['(string) String to be used for the mail\'s subject line.', null],
            'subjectVar' => ['(string) Name of a variable that contains the subject line.', null],
            'markdownVar' => ['(string) Name of a variable that contains the mail body in markdown format.', null],
            'markdownFile' => ['(string) Path to a markdown file that contains the mail body.', null],
            'cssVar' => ['(string) Name of a variable that contains CSS instructions to be applied to the mail.', null],
            'plainTextVar' => ['(string) Name of a variable that contains the mail\'s body as plain text.'.
                'If not defined plain text will be automatically generated', null],
            // 'edit' => ['(bool|permission)', true],
            'to' => ['(string) Destination mail address. Can be a comma-separated list of addresses.', null],
            'from' => ['(string) Mail address that appears as the sender address. Should always belong to the '.
                'same domain as the website.', null],
            'fromName' => ['(string) Freely definable string that appears as the sender\'s name.', null],
            'attachments' => ['(string) Path to a file that shall be sent as an attachment. E.g. "\~page/doc.pdf".', null],
            'schedule' => ['{options} If defined, the Events module is invoked to determine the next event and '.
                'based on that, make values defined in the event available as variables (%key%). '.
                '(For ref see macro *events()*).', false],
        ],
        'summary' => <<<EOT

# $funcName()

Takes a markdown-formatted string and renders it as an HTML email.

Then, presents a preview as well as a form to modify the mail content and destination address.

A collapsed section provides access to the resulting HTML code, which you can copy and paste into your mail.

Finally, a button allows to send the mail.

**Hint:**  
When using a schedule, you can omit the template. 
Then the macro will present available data elements as optained from scheduled events.

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
    $options['css']         = TransVars::getVariable($options['cssVar']??'');
    $options['plainText']   = TransVars::getVariable($options['plainTextVar']??'');

    $str .= EMailHelper::render($options);
    return $str;
};

