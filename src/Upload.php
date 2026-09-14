<?php

//namespace PgFactory\PageFactory;
namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Download;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactoryElements\HtmlMail;
use ZipArchive;
use function PgFactory\PageFactory\createHash;
use function PgFactory\PageFactory\fileExt;
use function PgFactory\PageFactory\fixPath;
use function PgFactory\PageFactory\getDirDeep;
use function PgFactory\PageFactory\mylog;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\writeFile;


class Upload
{
    private const WIDGET_OPTIONS = [
        'mode' => 'public upload',
        'path' => \PgFactory\PageFactory\PFY_UPLOAD_FOLDER,
        'allowMultipleFiles' => false,
        'permission' => null,
        'notificationMailTo' => false, // false|true|email
        'zipFileName' => 'files',
        'filetypes' => '', // one of `docs`, `pdf`, `text`, `images`, `sounds`, `videos`, `compressed`, `zip`
        'mimetypes' => '', // e.g. 'image/*'
        'maxMegaByte' => PFY_DEFAULT_MAX_UPLOAD_SIZE,
        'keepDays' => null, // 7 days in `file relay` mode, otherwise 365 days
    ];
    private static int $inx = 1;
    private array $options;
    private string $mode;
    private float $keepDays;
    private bool $permission;

    /**
     * @param $options
     */
    public function __construct($options)
    {
        $this->parseOptions($options);
        Download::purgeOldFiles($this->options['path']);
    } // __construct

    /**
     * @return string
     */
    public function renderWidget(): string
    {
        $inx = self::$inx++;

        if (!$this->permission) {
            return "{{ pfy-upload-no-permission }}";
        }

        if (!($uploadLabel =  TransVars::getVariable('pfy-upload-label'))) {
            Page::addCss('.pfy-upload-form .pfy-upload .pfy-label-wrapper {display: none;}');
        }
        $html = $this->renderForm($uploadLabel);

        $str = <<<EOT

<div id="pfy-upload-wrapper-$inx" class="pfy-upload-wrapper">
$html
</div>

EOT;
        return $str;
    } // renderWidget


    /**
     * @param string $uploadLabel
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function renderForm(string $uploadLabel): string
    {
        $formOptions = [
            'wrapperClass' => 'pfy-upload-form',
            'tableOptions' => false,
            'permission' => $this->options['permission'],
            'dataReceivedCallback' => function ($dataRec, $origDataRec) {
                return $this->formCallback($dataRec, $origDataRec);
            },
        ];
        $formFields = [];

        $formFields['Upload']   = [
                'type'          => $this->options['allowMultipleFiles'] ? 'multiupload' : 'upload',
                'label'         => $uploadLabel,
                'path'          => $this->options['path'],
                'maxMegaByte'   => $this->options['maxMegaByte'],
        ];
        if ($this->options['filetypes']??false) {
            $formFields['Upload']['filetypes'] = $this->options['filetypes'];
        }
        if ($this->options['mimetypes']??false) {
            $formFields['Upload']['mimetypes'] = $this->options['mimetypes'];
        }
        if ($this->options['maxMegaByte']??false) {
            $formFields['Upload']['maxMegaByte'] = $this->options['maxMegaByte'];
        }
        if ($this->mode === 'directed upload') {
            $formFields['Path']   = $this->renderTargetPathSelection();
        }
        $formFields['submit'] = [
                'type'          => 'submit',
                'label'         => TransVars::getVariable('pfy-upload-button-label', 'Upload now')
        ];
        $form = new PfyForm($formOptions);
        return $form->renderForm($formFields);
    } // renderForm


    /**
     * @return array
     * @throws \Exception
     */
    private function renderTargetPathSelection(): array
    {
        $paths = getDirDeep($this->options['path'], onlyDir: true);
        $l = strlen($this->options['path']);
        $pathOptions = [];
        foreach ($paths as $path) {
            $value = $label = substr($path, $l);
            if (!$label) {
                $label = basename($path);
            } else {
                $label = "↳ $label";
            }
            $pathOptions[$value]  = $label;
        }
        $formField = [
            'type'          => 'dropdown',
            'label'         => '{{ pfy-upload-path-selection-label }}',
            'options'       => $pathOptions,
        ];
        return $formField;
    } // renderTargetPathSelection




    // === Callback ==============================================================================
    /**
     * @param array $dataRec
     * @return array|string[]
     * @throws \Exception
     */
    public function formCallback(array $dataRec): array
    {
        $path = $this->options['path']??false;
        if (($p = (strpos($path, '$'))) !== false) {
            // case given path contains pattern '$xy', where xy is name of other data element:
            $k = substr($path, $p+1);
            $k = preg_replace('|\W.*|', '', $k); // remove trailing characters
            $path1 = $dataRec[$k] ?? '';
            $path = fixPath(substr($path, 0, $p).$path1);
        }

        $uploadObjs = $dataRec['Upload'];
        if (is_a($uploadObjs, 'Nette\Http\FileUpload')) {
            $uploadObjs = [$uploadObjs];
        }

        $showFeedbackInpage = true;
        $html = '';
        switch ($this->mode) {
            case 'simple upload':
                $this->doUpload($uploadObjs, $path);
                break;
            case 'directed upload':
                $subPath = $dataRec['Path'];
                $path = $this->options['path'] . $subPath;
                $this->doUpload($uploadObjs, $path);
                $showFeedbackInpage = false;
                break;
            case 'file relay':
                $html = $this->fileRelay($uploadObjs, $path);
                break;
        }
        $res = [
            'html' => $html,
            'continueEval' => false,
            'showFeedbackInpage' => $showFeedbackInpage,
            'dataRec' => $dataRec,
        ];
        return $res;
    } // formCallback


    /**
     * @param array $uploadObjs
     * @param string $path
     * @return void
     */
    private function doUpload(array $uploadObjs, string $path): void
    {
        if (!file_exists($path)) {
            mylog("Upload: possible tampering with path -> target path does not exist: '$path'");
            reloadAgent();
        }
        $targetFileNames = '';
        foreach ($uploadObjs as $uploadObj) {
            $targetFileName = $uploadObj->getSanitizedName();
            $targetFileName = basename($targetFileName);
            $targetFileNames .= ", $targetFileName";

            $targetFile = $path . $targetFileName;
            $uploadObj->move($targetFile);
            $this->writeMetaFile($targetFile);
        }

        if (sizeof($uploadObjs) > 1) {
            $response = TransVars::getVariable('pfy-upload-files-successful');
        } else {
            $response = TransVars::getVariable('pfy-upload-file-successful');
        }

        $this->sendNotification($targetFileNames, $path);

        reloadAgent(message: $response);
    } // doUpload


    /**
     * @param array $uploadObjs
     * @param string $path
     * @return string
     * @throws \Exception
     */
    private function fileRelay(array $uploadObjs, string $path): string
    {
        $pageId = page()->id();
        $hash = createHash(type: 'l');

        $path .= "$hash/";
        preparePath($path);

        $targetFileNames = '';

        if (sizeof($uploadObjs) > 1) {
            $zipFile = $this->options['zipFileName'] . '.zip';
            $targetId = "$hash/" . $zipFile;
            $lastTarg = kirby()->session()->get("pfy.$pageId.fileRelay");
            if ($lastTarg && (basename($lastTarg) === $zipFile)) {
                return $this->renderFileRelayResponse($lastTarg);
            }

            $targetFile = $path . $zipFile;
            $zip = new ZipArchive();
            $zip->open($targetFile, ZipArchive::CREATE);

            foreach ($uploadObjs as $uploadObj) {
                $filename = $uploadObj->getSanitizedName();
                $filename = basename($filename);

                $zip->addFile($uploadObj->getTemporaryFile(), $filename);
                $targetFileNames .= ", $filename";
            }
            $zip->close();

        } else {
            $uploadObj = $uploadObjs[0];
            $targetFileName = $uploadObj->getSanitizedName();
            $targetFileName = basename($targetFileName);

            $targetFile = $path . $targetFileName;
            $targetId = "$hash/" . $targetFileName;
            $lastTarg = kirby()->session()->get("pfy.$pageId.fileRelay");
            if ($lastTarg && (basename($lastTarg) === $targetFileName)) {
                return $this->renderFileRelayResponse($lastTarg);
            }

            $uploadObj->move($targetFile);
            $targetFileNames = $targetFileName;
        }
        $this->writeMetaFile($targetFile, $path); // note: multiple files saved in one zip-file, therefore only one meta file

        kirby()->session()->set("pfy.$pageId.fileRelay", $targetId);

        $this->sendNotification($targetFileNames, $path, variable: 'pfy-file-relays-notification-mail');

        return $this->renderFileRelayResponse($targetId);
    } // fileRelay


    /**
     * @param string $targetFile
     * @param string $path
     * @param string $expiration
     * @return void
     * @throws \Exception
     */
    private function writeMetaFile(string $targetFile, string $path = ''): void
    {
        $meta = [];
        if ($this->keepDays) {
            $expiration = date('Y-m-d H:i', time() + intval($this->keepDays * 86400));
            $meta['expiration'] = $expiration;
        }
        if ($path) {
            $meta['path'] = $path;
        }
        if ($meta) {
            writeFile("$targetFile.json", $meta);
        }
    } // writeMetaFile


    /**
     * @param string $targetId
     * @return string
     */
    private function renderFileRelayResponse(string $targetId): string
    {
        $explanation2 = TransVars::getVariable('pfy-upload-file-relay-explanation2');
        if ($this->keepDays) {
            $explanation2 .= TransVars::getVariable('pfy-upload-file-relay-expiration-text');
            $explanation2 = str_replace('%days%', (string)$this->keepDays, $explanation2);
        }
        $url = PFY_APP_BASE_URL . "?download=$targetId";
        $continueLink = PFY_PAGE_URL;
        $html = <<<EOT

<div class="pfy-upload-response-wrapper">
    <div class="pfy-upload-response">{{ pfy-upload-file-relay-explanation }}</div>
    <div class="pfy-upload-link-wrapper pfy-has-copy-btn">
        <code>$url</code>
    </div>
    <div class="pfy-upload-response2">$explanation2</div>
</div> <!-- /pfy-upload-response-wrapper -->
<div class="pfy-upload-link"><a href="$continueLink">{{ pfy-upload-link-continue-label }}</a></div>

EOT;
        return $html;
    } // renderFileRelayResponse


    /**
     * @param string $targetFileName
     * @param string $variable
     * @return void
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function sendNotification(string $targetFileName, string $targetPath,  string $variable = 'pfy-upload-file-notification-mail'): void
    {
        $to = $this->options['notificationMailTo'];
        if ($to === false) {
            return;
        } elseif ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }

        $targetFileName = ltrim($targetFileName, ', ');

        $message = TransVars::getVariable($variable);
        $message = str_replace('%files%', $targetFileName, $message);
        if (str_starts_with($targetPath, '/')) {
            $targetPath = substr($targetPath, strlen(PFY_APP_BASE_PATH));
        }
        $message = str_replace('%targetPath%', $targetPath, $message);

        $user = PageFactory::$userName ?: TransVars::getVariable('pfy-unknown');
        $sender = TransVars::getVariable('pfy-upload-file-sender');
        $sender = str_replace('%user%', $user, $sender);
        $message = str_replace('%sender%', $sender, $message);

        $subject = TransVars::getVariable('pfy-upload-file-notification-mail-subject');
        $subject = str_replace('%host%', PFY_APP_BASE_URL, $subject);
        HtmlMail::sendMail([
            'to'        => $to,
            'from'      => TransVars::getVariable('webmaster_email'),
            'subject'   => $subject,
            'body'      => $message,
        ]);
    } // sendNotification


    /**
     * @param array $options
     * @return array
     * @throws \Exception
     */
    private function parseOptions(array $options): array
    {
        $options = array_merge(self::WIDGET_OPTIONS, array_filter($options, function ($value) {
            return $value !== null;
        }));

        $mode = $options['mode'];
        if (str_contains($mode, 'rela')) {
            $this->mode = 'file relay';
            $options['permission'] = ($options['permission'] !== null) ? $options['permission'] : 'anybody';
            $options['keepDays'] = ($options['keepDays'] !== null) ? $options['keepDays'] : 14;

        } elseif (str_contains($mode, 'dir')) {
            $this->mode = 'directed upload';
            $options['permission'] = ($options['permission'] !== null) ? $options['permission'] : 'loggedin|localhost';
            $options['keepDays'] = ($options['keepDays'] !== null) ? $options['keepDays'] : false;

        } else {
            $this->mode = 'simple upload';
            $options['permission'] = ($options['permission'] !== null) ? $options['permission'] : 'anybody';
            $options['keepDays'] = ($options['keepDays'] !== null) ? $options['keepDays'] : false;
        }

        $this->permission = Permission::evaluate($options['permission']);

        if (!($path = $options['path']??false)) {
            // case nothing specified -> use ~/uploads/:
            $path = PFY_UPLOAD_FOLDER;
        }
        $path = Utils::resolvePath($path);
        $options['path'] = $path;
        if (!is_file($path)) {
            preparePath($path);
            $htfile = "$path.htaccess";
            if (!file_exists($htfile)) {
                    file_put_contents($htfile, 'Deny from all');
            }
        }

        $this->keepDays = $options['keepDays'] ?: 0;
        $options['zipFileName'] = fileExt($options['zipFileName'], true);

        $this->options = $options;
        return $options;
    } // parseOptions

} // Upload
