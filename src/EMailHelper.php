<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\PfyFormSplitSyntax;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\timestampStr;
use function PgFactory\PageFactory\unshieldStr;
use function PgFactory\PageFactory\writeFile;
use function PgFactory\PageFactory\fileTime;

const HTML_MAIL_TEMPLATE_HISTORY_FOLDER = '~data/.history/';
const HTML_MAIL_TEMPLATE_HISTORY_FILE = '_htmlmail-template-history.yaml';
class EMailHelper
{
    private static string $file = '';
    private static string $subject = '';
    private static string $markdown = '';
    private static string $css = '';
    private static string $plaintext = '';
    private static string $to = '';
    private static string|null $from = '';
    private static string $fromName = '';
    private static array $iCalOptions = [];
    private static string $icsFile = '';
    private static string $macroName = '';
    private static array $attachments = [];
    private static array|false $schedule = [];
    private static array $events = [];


    /**
     * @param array $options
     * @return string
     * @throws \Kirby\Exception\Exception
     */
    public static function render(array $options): string
    {
        // handle '?sent':
        if (isset($_GET['sent'])) {
            return '{{ pfy-htmlmail-sent-confirmation }}';
        }

        Assets::addAssets('HTML_MAIL');
        self::parseOptions($options);

        self::handleScheduleOption();


        if (!self::$markdown && $options['schedule']??false) {
            $html = <<<EOT
<h2>Availabe variables:</h2>
<pre>{{ _data_ }}
</pre>
EOT;
            return $html;
        }
        list($emailPreview, $sourceCode) = self::renderPreview();

        list($html, $parts) = self::renderEditForm();
        $subject = self::$subject;

        $wrapper = <<<EOT
$html

_pfy-htmlmail-open-form-head

<> {{ pfy-htmlmail-form-accordion-label }}

_pfy-htmlmail-open-form-fields

_pfy-htmlmail-open-form-submit
<>

{{ vgap }}

## {{ pfy-htmlmail-preview-heading }}

@@@@ .pfy-htmlmail-preview-subject

{{ pfy-htmlmail-preview-subject }}: <span class="pfy-htmlmail-subject">$subject</span>

@@@@ .pfy-htmlmail-preview

_pfy-htmlmail-preview

@@@@ .pfy-htmlmail-source-code
{{ vgap }}

<> {{ pfy-htmlmail-preview-sourcecode }}

@@@@@ .pfy-has-copy-btn
_pfy-htmlmail-source-code
@@@@@
<>
@@@@

{{ vgap }}

@@@@@ .send-mail
_pfy-htmlmail-open-form-send
@@@@@

_pfy-htmlmail-open-form-tail


EOT;
        $html = markdown($wrapper);
        $html = str_replace(['<p>','</p>'], ['', ''], $html);
        $html = str_replace('_pfy-htmlmail-open-form-head',   $parts[0], $html);
        $html = str_replace('_pfy-htmlmail-open-form-fields', $parts[1], $html);
        $html = str_replace('_pfy-htmlmail-open-form-submit', $parts[3], $html);
        $html = str_replace('_pfy-htmlmail-open-form-send',   $parts[2], $html);
        $html = str_replace('_pfy-htmlmail-open-form-tail',   $parts[4], $html);

        $html = str_replace('_pfy-htmlmail-preview',          $emailPreview, $html);
        $html = str_replace('_pfy-htmlmail-source-code',      $sourceCode, $html);

        return $html;
    } // render


    /**
     * @return array
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private static function renderEditForm(): array
    {
        $formOptions = [
            'file'                  => self::$file,
            'class'                 => 'pfy-form-colored',
            'wrapperClass'          => 'pfy-htmlmail-wrapper',
            'permission'            => true,
            'tableOptions'          => false,
            'dataReceivedCallback'  => function($dataRec) {
                return self::formCallback($dataRec);
            },
        ];
        $markdown = str_replace('~', '∽', self::$markdown);
        $markdown = str_replace("{", "&#123;", $markdown);

        $plaintext = HtmlMail::cleanupPlaintext(self::$plaintext);
        $plaintext = str_replace("{", "&#123;", $plaintext);
        $formFields = [
            'Subject'   => ['preset' => self::$subject],
            'Markdown'  => ['type' => 'textarea', 'preset' => $markdown],
            'Css'       => ['type' => 'textarea', 'preset' => self::$css],
            'Text'      => ['type' => 'textarea', 'preset' => $plaintext],

            'To'        => ['type' => 'text', 'label'=> '{{ pfy-htmlmail-to }}','class' => 'halve-width', 'preset' => self::$to],

            'Send'      => [
                'type' => 'button',
                'label' => '{{ pfy-htmlmail-send-button }}',
                'class' => 'pfy-htmlmail-send-button',
                'callback' => 'sendMailCallback',
            ],

            'submit'    => ['label' => '{{ pfy-htmlmail-submit-button }}'],
            '_sendmail' => ['type' => 'hidden', 'value' => false],
        ];

        $frm = new PfyFormSplitSyntax($formOptions);
        list($continue, $html) = $frm->initRenderForm($formFields);
        $parts = [];
        if ($continue) {
            $head1 = $frm->renderFormWrapperHead();
            $head2 = $frm->renderFormPieces(uptoWhich: 'head');
            $parts[] = "$head1$head2";
            $parts[] = $frm->renderFormPieces(uptoWhich: 'Text');
            $parts[] = $frm->renderFormPieces(uptoWhich: 'Send');
            $parts[] = $frm->renderFormPieces(uptoWhich: 'rest');
            $parts[] = $frm->renderFormPieces(uptoWhich: 'tail');
        }
        $js = <<<EOT
console.log('sendMailCallback()');
function sendMailCallback(ev) {
    console.log(ev);
    const el = ev.target;
    const form = el.closest('form');
    domForOne(form, '[name=_sendmail]', el => {
        el.value = true;
    });
    form.submit();
}
EOT;
        Page::addJs($js);

        return [$html, $parts];
    } // renderEditForm


    /**
     * @param $dataRec
     * @return string
     */
    public static function formCallback($dataRec): string
    {
        // check whether submitted data has been changed, save it if so:
        $subject = str_replace("\r\n", "\n", self::$subject);
        $markdown = str_replace("\r\n", "\n", self::$markdown);
        $css = str_replace("\r\n", "\n", self::$css);
        $plaintext = str_replace("\r\n", "\n", self::$plaintext);
        if ($subject !== ($dataRec['Subject']??'') ||
            $markdown !== ($dataRec['Markdown']??'') ||
            $css !== ($dataRec['Css']??'') ||
            $plaintext !== ($dataRec['Text']??'')
        ) {
            // anything changed, then save it:
            $tmp = $dataRec;
            foreach ($tmp as $key => $value) {
                if ($key[0] === '_') {
                    unset($tmp[$key]);
                }
            }
            self::$subject = $subject;
            self::$markdown = $markdown;
            self::$css = $css;
            self::$plaintext = $plaintext;

            $file = HTML_MAIL_TEMPLATE_HISTORY_FOLDER . timestampStr() . HTML_MAIL_TEMPLATE_HISTORY_FILE;
            writeFile($file, $tmp);
        }

        // send data if requested:
        if ($dataRec['_sendmail']??false) {
            self::sendMail($dataRec);
            reloadAgent(PFY_PAGE_URL.'?sent');
        }
        return false; // don't continue saving submitted data by PfyForms
    } // formCallback


    /**
     * @return array
     */
    private static function renderPreview(): array
    {
        $html       = self::compileForPreview();

        $sourceCode = self::compileForMail();
        $sourceCode = htmlentities($sourceCode);
        $sourceCode = <<<EOT

<pre><code>$sourceCode
</code></pre>

EOT;
        $html .= self::showAttachments();
        return [$html, $sourceCode];
    } // renderPreview


    /**
     * @return string
     * @throws \Exception
     */
    private static function compileForMail(): string
    {
        $data = reset(self::$events) ?: [];
        list($html, $plaintext) = HtmlMail::compileForMail(self::$markdown, self::$css, data:$data);
        return $html;
    } // compileForMail


    /**
     * @return string
     * @throws \Exception
     */
    private static function compileForPreview(): string
    {
        $data = reset(self::$events) ?: [];
        list($html, $plaintext) = HtmlMail::compileForMail(self::$markdown, self::$css, data:$data, forPreview: true);
        if (self::$plaintext === '-auto-') {
            self::$plaintext = $plaintext;
        }
        return $html;
    } // compileForPreview


    private static function handleScheduleOption(): void
    {
        if (!($eventOptions = self::$schedule)) {
            return;
        }

        if (!($src = ($eventOptions['src']??false))) {
            $src = $eventOptions['file']??false;
        }
        $count = ($eventOptions['ical']['count']??false) ?: 1;
        $eventOptions['file'] = $src;
        $eventOptions['macroName'] = self::$macroName;

        $sched = new Events($eventOptions);
        $nextEvents = $sched->getNextEvents(count: $count);
        $dataRec = $nextEvents[0] ?? [];
        $_data_ = '';
        if ($dataRec) {
            foreach ($dataRec as $key => $value) {
                if (is_array($value)) {
                    $value = $value[0] ?? json_encode($value);
                }
                TransVars::setTempVariable($key, $value);
                if ($key[0] !== '_') {
                    $_data_ .= "%$key%\n";
                }
            }
            TransVars::setTempVariable('_data_', $_data_);

            $fileTime = fileTime($src);
        }
        foreach ($nextEvents as $dataRec) {
            self::$attachments[] = self::prepareIcsFile($dataRec, $fileTime);
        }
        self::$events = $nextEvents;
    } // handleScheduleOption


    private static function prepareIcsFile(array $dataRec, int $filetime): string
    {
        $icalOptions = self::$iCalOptions;
        if (!$icalOptions) {
            return '';
        } elseif (!is_array($icalOptions)) {
            $title = $icalOptions;
            $icalOptions = [
                'title' => $title,
                'shortName' => $title,
            ];
        }
        $icalOptions['prefix'] = $icalOptions['shortName']??'';
        $iCal = new Ical([$dataRec], $icalOptions);

        $tTargetFile = $iCal->getTargetFileTime();
        if ($filetime > $tTargetFile) {
            $iCal->saveToFile();
        }
        return $iCal->getTargetFile();
    } // prepareIcsFile


    private static function showAttachments(): string
    {
        $html = '';
        if (self::$attachments && is_array(self::$attachments)) {
            foreach (self::$attachments as $file) {
                $file = substr($file, strlen(PFY_KIRBY_BASE_PATH));
                $html .= "<li><code>$file</code></li>\n";
            }
        }

        if ($html) {
            $html = <<<EOT
<div class="pfy-htmlmail-attachments">
<p>{{ pfy-htmlmail-preview-attachments }}:</p>
<ol>
$html
</ol>
</div>
EOT;
        }
        return $html;
    } // showAttachments


    /**
     * @param array $dataRec
     * @return void
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private static function sendMail(array $dataRec): void
    {
        $eventData = reset(self::$events) ?: [];
        $to = $dataRec['To'] ?? PageFactory::$webmasterEmail;
        $markdown   = self::$markdown;

        $plaintext  = TransVars::translate(self::$plaintext, $eventData);

        $css        = self::$css;

        list($html, $plaintext, $images) = HtmlMail::compileForMail($markdown, $css, data:$eventData, plaintext: $plaintext);

        $subject = $dataRec['Subject'] ?? '';

        $props = [
            'to' => $to,
            'from' => self::$from,
            'fromName' => self::$fromName,
            'subject' => $subject,
            'body' => $plaintext,
        ];
        if ($html) {
            $props['body'] = [
                'html' => $html,
                'text' => $plaintext,
            ];
        }

        $props['attachments'] = [];
        if (self::$attachments) {
            $props['attachments'] = self::$attachments;
        }
        if (self::$icsFile) {
            $props['attachments'][] = self::$icsFile;
        }

        if ($images) {
            foreach ($images as $cid =>  $image) {
                $file = $image['path'];
                $props['attachments'][] = [
                  'file' => $file,
                  'cid' => $cid,
                ];
            }
        }
        HtmlMail::sendMail($props);
    } // sendMail


    /**
     * @param $options
     * @return void
     */
    private static function parseOptions($options)
    {
        self::$subject          = $options['subject']??false;
        self::$markdown         = $options['markdown']??false;
        self::$css              = $options['css']??false;
        self::$plaintext        = ($options['plainText']??false) ?: '-auto-'; // -auto- means: derive plaintext from markdown
        self::$schedule         = $options['schedule']??false;
        self::$macroName        = $options['macroName']??false;
        self::$to               = ($options['to']??false) ?: PageFactory::$webmasterEmail;
        self::$from             = ($options['from']??false) ?: PageFactory::$webmasterEmail;
        self::$fromName         = ($options['fromName']??false) ?: 'Webmaster';
        $attachments            = $options['attachments']??false;
        if (is_string($attachments)) {
            $attachments = explodeTrim(',', $attachments);
        } elseif (!is_array($attachments)) {
            $attachments = [];
        }
        if ($attachments) {
            foreach ($attachments as $key => $attachment) {
                $attachments[$key] = resolvePath($attachment);
            }
        }
        self::$attachments = $attachments;

        self::$file = '~data/email.json';

        if (preg_match('/\n==== [A-Z]+\n/s', self::$markdown)) {
            list($plaintext1, self::$markdown, $css1) = HtmlMail::parseSections(self::$markdown);
            self::$plaintext = $plaintext1 ?: self::$plaintext;
            self::$css = $css1 ?: self::$css;
        }

        if (self::$schedule) {
            self::$iCalOptions = self::$schedule['ical']??[];
        }

        // update values if submitted by form:
        if (isset($_POST['Markdown'])) {
            self::$subject      = $_POST['Subject']??'';
            self::$markdown     = $_POST['Markdown']??'';
            self::$css          = $_POST['Css']??'';
            self::$plaintext    = $_POST['Text']??'';
            self::$to           = $_POST['To']??'';
        }
    } // parseOptions

} // EMailHelper