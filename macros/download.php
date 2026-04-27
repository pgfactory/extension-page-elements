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
            'file' => ['If `file` is defined, a download link for just this file is rendered', null],
            'text' => ['If `file` is defined, this string is used as link text instead of the filename', null],
            'id' => ['Id to be applied to the enclosing li-tag (Default: pfy-dir-#)', null],
            'class' => ['Class to be applied to the enclosing li-tag (Default: pfy-dir)', 'pfy-dir'],
            'include' => ['[FILES,FOLDERS] Defines what to include in output', 'files'],
            'exclude' => ['Regex pattern by which to exclude specific elements.', null],
            'permission' => ['If set, defines whether visitor has permission to download.', 'anybody'],
            'asLinks' => ['Render elements as links.', false],
            'enableFolderDownload' => ['If true, folders can be downloaded as a zip file.', false],
            'template' => ['[text,file] The template based on which output is rendered.', null],
            'modifiers' => ['[REVERSE, REVERSE_FOLDERS, INCLUDE_PATH, DEEP, HIERARCHICAL, DOWNLOAD] '.
                'Activates miscellaneous modes.', null],
            'replaceOnElem' => ['(pattern,replace) If defined, regular expression is applied to each element. '.
                'Example: remove leading underscore:  "^_,&#39;&#39;"', null],
            'maxAge' => ['[integer] Maximum age of file (in number of days).', null],
            'markdown' => ['[bool] If true, output will be markdown compiled.', false],
        ],
        'summary' => <<<EOT
# download()

Renders the content of a directory for managed download.

This means that the actual download is only granted if the visitor has permission.

To secure the download folder, add a file `.htaccess`:

    # Deny all requests
    Require all denied


EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    if ($file = $options['file']) {
        $filename = basename($file);
        $text = $options['text'] ?: $filename;
        $str .= "<a href='~page/?download=$filename' class='pfy-download-link'>$text</a>";
        if ($_GET['download']??false) {
            $requestedFile = $_GET['download'];
            $requestedFilename = basename($requestedFile);
            if (basename($file) === $requestedFilename) {
                $path = dir_name($file);
                $files = array_keys(getDirDeep($path, assoc:true));
                if (in_array(basename($file), $files)) {
                    Download::initiateDownload($file, $options['permission']);
                }
                return '';
            }
        }

    } else {

        if (!$options['template'] ?? false) {
            $options['template'] = [
                'element' => "- (link: %download% text:%filename% type:%ext% target:_blank) %description%\n",
                'folderElement' => '<> <strong>%label%</strong>',
                'markdown' => true,
            ];
        }

        // assemble output:
        $obj = new Dir();
        $str .= $obj->render($options);
    }

    // set up click handler that opens the download in a new tab:
    if ($options['inx'] === 1) {
        $js = <<<EOT
pfyHandleEvent('a.pfy-download-link', ev => {
  ev.preventDefault();
  const downloadUrl = ev.target.href;
  const newTab = window.open(downloadUrl, '_blank');
  if (!newTab) {
    // Popup blocker fallback:
    window.location.href = downloadUrl;
  }
})
EOT;
        Page::addJsReady($js);
    }
    return $str;
};




