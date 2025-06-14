<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Dir;


return function($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'path' => ['Selects the folder to be read. May include an optional '.
                'selection pattern  (-> \'glob style\', e.g. "*.pdf" or "{&#92;&#42;.js,&#92;&#42;.css}")', null],
            'id' => ['Id to be applied to the enclosing li-tag (Default: pfy-dir-#)', null],
            'class' => ['Class to be applied to the enclosing li-tag (Default: pfy-dir)', 'pfy-dir'],
            'include' => ['[FILES,FOLDERS] Defines what to include in output', 'files'],
            'exclude' => ['Regex pattern by which to exclude specific elements.', null],
            'permission' => ['If set, defines whether visitor has permission to download.', 'loggedin|localhost'],
            'asLinks' => ['Render elements as links.', false],
            'enableFolderDownload' => ['If true, folders can be downloaded as a zip file.', true],
            'template' => ['[text,file] The template based on which output is rendered.', null],
            'modifiers' => ['[REVERSE, REVERSE_FOLDERS, INCLUDE_PATH, DEEP, HIERARCHICAL, DOWNLOAD] '.
                'Activates miscellaneous modes.', 'DEEP,HIERARCHICAL'],
            'replaceOnElem' => ['(pattern,replace) If defined, regular expression is applied to each element. '.
                'Example: remove leading underscore:  "^_,&#39;&#39;"', null],
            'maxAge' => ['[integer] Maximum age of file (in number of days).', null],
        ],
        'summary' => <<<EOT
# dir()

Renders the content of a directory.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    // assemble output:
    $obj = new Dir();
    $str .= $obj->render($options);

    return $str;
};




