<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\timestampStr;
use function PgFactory\PageFactory\writeFile;
use function PgFactory\PageFactory\fileTime;

const HTML_MAIL_TEMPLATE_HISTORY_FOLDER = '~data/.history/';
const HTML_MAIL_TEMPLATE_HISTORY_FILE = '_htmlmail-template-history.yaml';
class EMailHelper
{
    private string $file = '';
    private string $subject = '';
    private string $markdown = '';
    private string $css = '';
    private string $plaintext = '';
    private string $to = '';
    private string|null $from = null;
    private string $fromName = '';
    private array $iCalOptions = [];
    private string $icsFile = '';
    private string $macroName = '';
    private string $output = 'all';
    private array $attachments = [];
    private array|false $schedule = [];
    private array $events = [];

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->parseOptions($options);
    } // __construct


    /**
     * @return string
     * @throws \Kirby\Exception\Exception
     */
    public function render(): string
    {
        // handle '?sent':
        if (isset($_GET['sent'])) {
            return '{{ pfy-htmlmail-sent-confirmation }}';
        }

        Assets::addAssets('HTML_MAIL');
        $this->handleScheduleOption();

        if (!$this->markdown && $this->schedule) {
            $html = <<<EOT
<h2>Available variables:</h2>
<pre>{{ _data_ }}
</pre>
EOT;
            return $html;
        }
        list($emailPreview, $sourceCode) = $this->renderPreview();

        list($html, $parts) = $this->renderEditForm();
        $subject = $this->subject;

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
    private function renderEditForm(): array
    {
        $formOptions = [
            'file'                  => $this->file,
            'class'                 => 'pfy-form-colored',
            'wrapperClass'          => 'pfy-htmlmail-wrapper',
            'permission'            => true,
            'retainData'            => true,
            'tableOptions'          => false,
            'dataReceivedCallback'  => fn($dataRec, $origDataRec) => $this->formCallback($dataRec, $origDataRec),
        ];

        $plaintext = HtmlMail::cleanupPlaintext($this->plaintext);
        $formFields = [
            'Subject'   => ['preset' => $this->subject],
            'Markdown'  => ['type' => 'textarea', 'preset' => $this->markdown],
            'Css'       => ['type' => 'textarea', 'preset' => $this->css],
//            'Text'      => ['type' => 'textarea', 'preset' => $plaintext],

            'To'        => ['type' => 'text', 'label'=> '{{ pfy-htmlmail-to }}','class' => 'halve-width', 'preset' => $this->to],

            'Send'      => [
                'type' => 'button',
                'label' => '{{ pfy-htmlmail-send-button }}',
                'class' => 'pfy-htmlmail-send-button',
                'callback' => 'sendMailCallback',
            ],

            'cancel'    => ['label' => '{{ pfy-htmlmail-cancel-button }}'],
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
            $parts[] = $frm->renderFormPieces(uptoWhich: 'Css');
//            $parts[] = $frm->renderFormPieces(uptoWhich: 'Text');
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
     * @param array $dataRec
     * @param array $origDataRec
     * @return string
     */
    public function formCallback(array $dataRec, array $origDataRec): string
    {
        // check whether submitted data has been changed, save it if so:
        $subject = str_replace("\r\n", "\n", $this->subject);
        $markdown = str_replace("\r\n", "\n", $this->markdown);
        $css = str_replace("\r\n", "\n", $this->css);
//        $plaintext = str_replace("\r\n", "\n", $this->plaintext);
        if ($subject !== ($dataRec['Subject']??'') ||
            $markdown !== ($dataRec['Markdown']??'') ||
            $css !== ($dataRec['Css']??'')
//            $css !== ($dataRec['Css']??'') ||
//            $plaintext !== ($dataRec['Text']??'')
        ) {
            // anything changed, then save it:
            $tmp = $dataRec;
            foreach ($tmp as $key => $value) {
                if ($key[0] === '_') {
                    unset($tmp[$key]);
                }
            }
            $this->subject = $subject;
            $this->markdown = $markdown;
            $this->css = $css;
//            $this->plaintext = $plaintext;

            $file = HTML_MAIL_TEMPLATE_HISTORY_FOLDER . timestampStr() . HTML_MAIL_TEMPLATE_HISTORY_FILE;
            writeFile($file, $tmp);
        }

        // send data if requested:
        if ($origDataRec['_sendmail']??false) {
            $this->sendMail($dataRec);
            reloadAgent(PFY_PAGE_URL.'?sent');
        }
        return ''; // don't continue saving submitted data by PfyForms
    } // formCallback
    

    /**
     * @return array
     */
    private function renderPreview(): array
    {
        $html       = $this->compileForPreview();

        if ($this->output === 'all') {
            $sourceCode = $this->compileForMail();
        } else {
            $sourceCode = $this->compileForMail(prettyWrapper: false);
        }
        $sourceCode = htmlentities($sourceCode);
        $sourceCode = <<<EOT

<pre><code>$sourceCode
</code></pre>

EOT;
        $html .= $this->showAttachments();
        return [$html, $sourceCode];
    } // renderPreview


    /**
     * @param bool $prettyWrapper
     * @return string
     * @throws \Exception
     */
    private function compileForMail(bool $prettyWrapper = true): string
    {
        $data = reset($this->events) ?: [];
        list($html, $plaintext) = HtmlMail::compileForMail($this->markdown, $this->css, data:$data, prettyWrapper: $prettyWrapper);
        return $html;
    } // compileForMail


    /**
     * @param bool $prettyWrapper
     * @return string
     * @throws \Exception
     */
    private function compileForPreview(bool $prettyWrapper = true): string
    {
        $data = reset($this->events) ?: [];
        list($html, $plaintext) = HtmlMail::compileForMail($this->markdown, $this->css, data:$data, forPreview: true, prettyWrapper: $prettyWrapper);
        if ($this->plaintext === '-auto-') {
            $this->plaintext = $plaintext;
        }
        return $html;
    } // compileForPreview


    /**
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function handleScheduleOption(): void
    {
        if (!($eventOptions = $this->schedule)) {
            return;
        }

        if (!($src = ($eventOptions['src']??false))) {
            $src = $eventOptions['file']??false;
        }
        $count = $eventOptions['ical']['count'] ?? 1;
        $eventOptions['file'] = $src;
        $eventOptions['macroName'] = $this->macroName;

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
        }
        $fileTime = fileTime($src);
        foreach ($nextEvents as $dataRec) {
            $this->attachments[] = $this->prepareIcsFile($dataRec, $fileTime);
        }
        $this->events = $nextEvents;
    } // handleScheduleOption


    /**
     * @param array $dataRec
     * @param int $filetime
     * @return string
     * @throws \Exception
     */
    private function prepareIcsFile(array $dataRec, int $filetime): string
    {
        $icalOptions = $this->iCalOptions;
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


    /**
     * @return string
     */
    private function showAttachments(): string
    {
        $html = '';
        if ($this->attachments && is_array($this->attachments)) {
            foreach ($this->attachments as $file) {
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
    private function sendMail(array $dataRec): void
    {
        $eventData = reset($this->events) ?: [];
        $to = $dataRec['To'] ?? PageFactory::$webmasterEmail;
        $markdown   = $this->markdown;

        $plaintext  = TransVars::translate($this->plaintext, $eventData);

        $css        = $this->css;

        list($html, $plaintext, $images) = HtmlMail::compileForMail($markdown, $css, data:$eventData, plaintext: $plaintext);

        $subject = $dataRec['Subject'] ?? '';
        $plaintext  = TransVars::translate($plaintext, $eventData);

        $props = [
            'to' => $to,
            'from' => $this->from,
            'fromName' => $this->fromName,
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
        if ($this->attachments) {
            $props['attachments'] = $this->attachments;
        }
        if ($this->icsFile) {
            $props['attachments'][] = $this->icsFile;
        }

        if ($images) {
            foreach ($images as $cid => $image) {
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
     * @param array $options
     * @return void
     */
    private function parseOptions(array $options): void
    {
        $this->subject          = $options['subject']??'';
        $this->markdown         = $options['markdown']??'';
        $this->output           = $options['output']??'';
        $this->css              = $options['css']??'';
        $this->plaintext        = ($options['plainText']??false) ?: '-auto-'; // -auto- means: derive plaintext from markdown
        $this->schedule         = $options['schedule']??false;
        $this->macroName        = $options['macroName']??'';
        $this->to               = ($options['to']??false) ?: PageFactory::$webmasterEmail;
        $this->from             = ($options['from']??false) ?: PageFactory::$webmasterEmail;
        $this->fromName         = ($options['fromName']??false) ?: 'Webmaster';
        $attachments            = $options['attachments']??false;
        if (is_string($attachments)) {
            $attachments = explodeTrim(',', $attachments);
        } elseif (!is_array($attachments)) {
            $attachments = [];
        }
        if ($attachments) {
            foreach ($attachments as $key => $attachment) {
                $attachments[$key] = Utils::resolvePath($attachment);
            }
        }
        $this->attachments = $attachments;

        $this->file = '~data/email.json';

        if (preg_match('/\n==== [A-Z]+\n/s', $this->markdown)) {
            list($plaintext1, $this->markdown, $css1) = HtmlMail::parseSections($this->markdown);
            $this->plaintext = $plaintext1 ?: $this->plaintext;
            $this->css = $css1 ?: $this->css;
        }

        if ($this->schedule) {
            $this->iCalOptions = $this->schedule['ical']??[];
        }

        // update values if submitted by form:
        if (isset($_POST['Markdown'])) {
            $this->subject      = $_POST['Subject']??'';
            $this->markdown     = $_POST['Markdown']??'';
            $this->css          = $_POST['Css']??'';
            $this->plaintext    = $_POST['Text']??'';
            $this->to           = $_POST['To']??'';
        }
    } // parseOptions

} // EMailHelper