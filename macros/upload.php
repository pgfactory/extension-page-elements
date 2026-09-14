<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Upload;

/*
 * PageFactory Macro
 */

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'mode' => ['(simple upload|directed upload|file relay) Selects the mode in which to operate.', 'simple upload'],
            'path' => ['`path` defines the location where uploaded files are stored. (default: &#126;/uploads/)', null],
            'allowMultipleFiles' => ['If true, the user can select and upload multiple files at the time. (default: false)', null],
            'permission' => ['Defines who may upload files. (default: ``loggedin|localhost`` in `directed upload` mode, otherwise ``anybody``)', null],
            'zipFileName' => ['In "file relay" mode with multiple files, this is the name used, ', 'files'],
            'notificationMailTo' =>	['[false|e-mail] Sends a notification mail to the user. ', null],
            'keepDays' => ['(number|false) Number of days during which uploaded files are available. '.
                'After that, they will be automatically deleted. (default: 14 days in `file relay` mode, otherwise indefinite)', null],
            'filetypes' => ['(file type) Short-cuts for a selection of useful file types.<br>'.
                'Available file types: `docs`, `pdf`, `text`, `images`, `sounds`, `videos`, `compressed`, `zip`', null],
            'mimetypes' => ['(comma-sep list of mimetypes) E.g. "``video/*``".<br>'.
                'Basic mimetypes: `text/`, `image/`, `video/`, `audio/`, `font/`, `application/`', null],
            'maxMegaByte' => ['(mega bytes) Size limit for uploaded files.<br>'.
                '(default: '.PFY_DEFAULT_MAX_UPLOAD_SIZE.'MB)', null],
        ],
        'summary' => <<<EOT

# $funcName()

Lets the user upload one or multiple files to the webhost.

### Modes
`simple upload`     10em>> Just let's the user upload one or multiple files
`directed upload`    >> The user selects a target folder where the uploaded files are to be saved.
`file relay`        >> After uploading files, the user is given a link with which to download those files.<br>In case of multiple files, they are compressed and offered for download as one .zip file.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    // assemble output:
    $upl = new Upload($options);
    $str .= $upl->renderWidget();

    return $str;
};
