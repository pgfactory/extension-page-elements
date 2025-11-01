<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\PfyFormSplitSyntax;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\resolvePath;

class EMailHelper
{
    private static string $file = '';
    private static string $subject = '';
    private static string $markdown = '';
    private static string $css = '';
    private static string $plaintext = '';
    private static string $to = '';
    private static string $macroName = '';
    private static array $attachments = [];
    private static array|false $schedule = [];


    /**
     * @param array $options
     * @return string
     * @throws \Kirby\Exception\Exception
     */
    public static function render(array $options): string
    {
        if (isset($_GET['sent'])) {
            return '{{ pfy-htmlmail-sent-confirmation }}';
        }

        Assets::addAssets('HTML_MAIL');
        self::parseOptions($options);

        self::handleScheduleOption();

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

{{ pfy-htmlmail-preview-subject }}: $subject

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
        $formFields = [
            'Subject'   => ['preset' => self::$subject],
            'Markdown'  => ['type' => 'textarea', 'preset' => $markdown],
            'Css'       => ['type' => 'textarea', 'preset' => self::$css],
            'Text'      => ['type' => 'textarea', 'preset' => self::$plaintext],

            'To'        => ['type' => 'email', 'label'=> '{{ pfy-htmlmail-to }}','class' => 'halve-width', 'preset' => self::$to],

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
        if ($dataRec['_sendmail']) {
            self::sendMail($dataRec);
            reloadAgent('./?sent');
//            reloadAgent(message: '{{ pfy-htmlmail-sent-confirmation }}');
        }
        return false; // don't continue saving submitted data
    } // formCallback


    /**
     * @return array
     */
    private static function renderPreview(): array
    {
        $html       = self::compileForPreview();

        $sourceCode = self::compile();
        $sourceCode = htmlentities($sourceCode);
        $sourceCode = <<<EOT

<pre><code>$sourceCode
</code></pre>

EOT;
        return [$html, $sourceCode];
    } // renderPreview


    /**
     * @return string
     * @throws \Exception
     */
    private static function compile(): string
    {
        list($html, $plaintext) = HtmlMail::compileForMail(self::$markdown, self::$css);
        return $html;
    } // compile


    /**
     * @return string
     * @throws \Exception
     */
    private static function compileForPreview(): string
    {
        list($html, $plaintext) = HtmlMail::compileForMail(self::$markdown, self::$css, forPreview: true);
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
        $eventOptions['file'] = $src;
        $eventOptions['macroName'] = self::$macroName;

        $sched = new Events($eventOptions);
        $nextEvents = $sched->getNextEvents(count: 1);
        $dataRec = $nextEvents[0] ?? [];
        if ($dataRec) {
            foreach ($dataRec as $key => $value) {
                if (is_array($value)) {
                    $value = $value[0] ?? json_encode($value);
                }
                TransVars::setTempVariable($key, $value);
            }
        }
    } // handleScheduleOption


    /**
     * @param array $dataRec
     * @return void
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private static function sendMail(array $dataRec): void
    {
        $to = $dataRec['To'] ?? PageFactory::$webmasterEmail;
        $markdown   = $dataRec['Markdown'] ?? '';
        $css        = $dataRec['Css'] ?? '';
        list($html, $plaintext, $images) = HtmlMail::compileForMail($markdown, $css);
        $plaintext  = ($dataRec['Text']??false) ?: $plaintext;

        $props = [
            'to' => $to,
            'from' => TransVars::getVariable('webmaster_email'),
            'fromName' => 'Webmaster',
            'subject' => $dataRec['Subject'] ?? '',
            'body' => $plaintext,
        ];
        if ($html) {
            $props['body'] = [
                'html' => $html,
                'text' => $plaintext,
            ];
        }

        if (self::$attachments) {
            $props['attachments'] = self::$attachments;
        } elseif ($images || self::$attachments) {
            $props['attachments'] = [];
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

        self::$file = $options['file']??'~data/email.json';

        if (preg_match('/\n==== [A-Z]+\n/s', self::$markdown)) {
            list($plaintext1, self::$markdown, $css1) = HtmlMail::parseSections(self::$markdown);
            self::$plaintext = $plaintext1 ?: self::$plaintext;
            self::$css = $css1 ?: self::$css;
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