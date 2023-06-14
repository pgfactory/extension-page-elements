<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\DataSet;
use Usility\PageFactory\PfyForm;
use Usility\PageFactory\Utils;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactory\TransVars;
use function Usility\PageFactory\reloadAgent;
use function Usility\PageFactory\resolvePath;
use function Usility\PageFactory\translateToFilename;
use function Usility\PageFactory\mylog;

const ENLIST_INFO_ICON = 'ⓘ';
const ENLIST_MAIL_ICON = '✉';
const ENLIST_ADD_ICON = '+';
const ENLIST_DELETE_ICON = '−';


class Enlist
{
    private $options;
    private $inx;
    private $title;
    private $entries;
    private $nEntries = 0;
    private $nSlots = 0;
    private $nReserveSlots = 0;
    private $nTotalSlots = 0;
    private $db;
    private $datasets;
    private $datasetName;
    private $freezeTime;
    private $listFrozen = false;
    private $isEnlistAdmin = false;

    /**
     * @param $options
     * @throws \Exception
     */
    public function __construct($options)
    {
        if ($options['description']??false) {
            $options['info'] = $options['description'];
        }
        $this->parseOptions($options);

        $this->openDb();
        PageFactory::$pg->addAssets('FORMS');

        if ($permissionQuery = ($this->options['admin'] ?? false)) {
            if ($permissionQuery === true) {
                $permissionQuery = 'localhost|loggedin';
            }
            $this->isEnlistAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
            if ($this->isEnlistAdmin && ($this->inx === 1)) {
                PageFactory::$pg->addBodyTagClass('pfy-enlist-admin');
            }
        }
    } // __construct


    /**
     * @return string
     */
    public function render(): string
    {
        $html = '';
        $this->entries = $this->getDataset($this->datasetName);

        $id = $this->options['id']?: "pfy-enlist-wrapper-$this->inx";
        $class = rtrim("pfy-enlist-wrapper pfy-enlist-$this->inx " . $this->options['class']?: '');
        if ($this->isEnlistAdmin) {
            $class .= ' pfy-enlist-admin';

            if ($this->inx === 1) {
                if ($adminEmail = ($this->options['adminEmail'] ?? false)) {
                    PageFactory::$pg->addJs("const adminEmail = '$adminEmail';");
                } else {
                    $adminEmail = PageFactory::$webmasterEmail;
                    PageFactory::$pg->addJs("const adminEmail = '$adminEmail';");
                }
            }
        }
        if ($this->listFrozen) {
            $class .= ' pfy-enlist-frozen';
        }

        $this->nReserveSlots = $this->options['nReserveSlots'];
        $this->nSlots = $this->options['nSlots'];
        $nTotal = $this->nTotalSlots = $this->nSlots + $this->nReserveSlots;

        $this->renderInfoButton();

        $headButtons = $this->renderSendMailToAllButton();

        for ($i=0; $i<$nTotal; $i++) {
            $html .= $this->renderListEntry($i);
        }

        $html = <<<EOT
<div id='$id' class='$class' data-setInx="$this->inx">
<div class='pfy-enlist-title'><span>$this->title</span>$headButtons</div>
$html
</div>
EOT;
        return $html;
    } // render


    /**
     * @param int $i
     * @return string
     */
    private function renderListEntry(int $i): string
    {
        $icon = $class = '';
        $text = '&nbsp;';
        // filled slots:
        if ($i < $this->nEntries) {
            $class = 'pfy-enlist-delete';
            $rec = $this->entries[$i];
            $icon = '<button type="button" title="{{ pfy-enlist-delete-title }}">'.ENLIST_DELETE_ICON.'</button>';
            $text = $rec['Name']??'## unknown ##';
            if ($obfuscate = ($this->options['obfuscate']??false)) {
                $text0 = $text;
                if ($obfuscate === true) {
                    $text = '*****';
                    $icon = '';
                } else {
                    $t = explode(' ', $text);
                    $text = '';
                    foreach ($t as $s) {
                        $text .= strtoupper($s[0]??'').' ';
                    }
                    $text = rtrim($text);
                }
                if ($this->isEnlistAdmin) { // in admin mode: show
                    $text .= " <span class='pfy-enlist-admin-preview'>($text0)</span>";
                }
            }
            if ($this->freezeTime) {
                $time = $rec['_time']??PHP_INT_MAX;
                if ($time < $this->freezeTime) {
                    $icon = '';
                }
            }

        // empty slots:
        } elseif ($i === $this->nEntries) {
            if (!$this->listFrozen) {
                $class = 'pfy-enlist-add';
                $text = '{{ pfy-enlist-add-text }}';
                $icon = '<button type="button" title="{{ pfy-enlist-add-title }}">' . ENLIST_ADD_ICON . '</button>';
            }
        } else {
            $class = 'pfy-enlist-empty';
            $text = '{{ pfy-enlist-empty-text }}';
        }
        if ($i >= $this->nSlots) {
            $class .= ' pfy-enlist-reserve';
        }
        if ($this->listFrozen) {
            $icon = '';
        }

        $text = "<span class='pfy-enlist-name'>$text</span>";
        $adminExt = '';
        if ($this->isEnlistAdmin && ($email = ($rec['Email']??false))) {
            $adminExt = "<span class='pfy-enlist-email'><a href='mailto:$email'>$email</a></span>\n";
        }

        $html = <<<EOT
    <div class='pfy-enlist-field $class' data-inx="$i">
        <span>$text$adminExt</span>$icon
    </div>

EOT;
        return $html;
    } // renderListEntry


    /**
     * @return void
     * @throws \Exception
     */
    private function openDb(): void
    {
        if (($filename = $this->options['file'])) {
            $filename = basename($filename);
        } else {
            $filename = translateToFilename(PageFactory::$slug, false);
        }
        $file = "~data/enlist/$filename.yaml";
        $file = resolvePath($file);
        $this->db = new DataSet($file, []);

        $this->datasets = $this->db->data();
    } // openDb


    /**
     * @param string $setName
     * @return array
     */
    private function getDataset(string $setName): array
    {
        $this->entries = $this->datasets[$setName]??[];
        if (is_array($this->entries)) {
            $this->nEntries = sizeof($this->entries);
        }
        return $this->entries;
    } // getDataset


    /**
     * @param int $setInx
     * @return string
     */
    private function getSetName(int $setInx): string
    {
        $sess = kirby()->session();
        $setnames = $sess->get('pfy.enlist-setnames')?: [];
        return $setnames[PageFactory::$slug][$setInx]??false;
    } // getSetName


    /**
     * @return string
     */
    private function renderSendMailToAllButton(): string
    {
        $headButtons = '';
        if ($this->isEnlistAdmin) {
            $mailIcon = ENLIST_MAIL_ICON;

            $headButtons = <<<EOT
    <span class='pfy-enlist-head-buttons'>
        <button class="pfy-enlist-sendmail-button pfy-button pfy-button-lean" type="button" title="{{ pfy-enlist-sendmail-button-title }}">$mailIcon</button>
    </span>
EOT;
        }
        return $headButtons;
    } // renderSendMailToAllButton


    /**
     * @return void
     */
    private function renderInfoButton(): void
    {
        if ($info = ($this->options['info'] ?? false)) {
            $this->title .= "<span tabindex='0' class='pfy-tooltip-anker'>" . ENLIST_INFO_ICON .
                "</span><span class='pfy-tooltip'>$info</span>";
        }
    } // renderInfoButton


    /**
     * @return string
     * @throws \Exception
     */
    public function renderForm(): string
    {
        if ($this->freezeTime) {
            $addHelp = TransVars::getVariable('pfy-enlist-popup-add-help');
        } else {
            $addHelp = TransVars::getVariable('pfy-enlist-popup-add-nofreeze-help');
        }
        $delHelp = TransVars::getVariable('pfy-enlist-popup-del-help');
        $popupHelp = "<div class='add'>$addHelp</div><div class='del'>$delHelp</div>";
        $formOptions = [
            'confirmationText' => '',
            'wrapperClass' => 'pfy-enlist-form-wrapper',
            'formBottom' => $popupHelp,
            'callback' => function($data) { return $this->callback($data); },
        ];
        $formFields = [
            'Name' => ['label' => 'Name:', 'required' => true],
            'Email' => ['label' => 'E-Mail:', 'required' => true],
            'cancel' => [],
            'submit' => [],
            'recid' => ['type' => 'hidden'],
            'setinx' => ['type' => 'hidden'],
        ];
        $form = new PfyForm($formOptions);
        $form->createForm(null, $formFields); // $auxOptions = form-elements
        $html = $form->renderForm();
        if (str_contains($html, 'class="error"')) {
            $jq = "Enlist.openPopup()";
            PageFactory::$pg->addJsReady($jq);
        }
        $html = "<div id='pfy-enlist-form'>\n$html</div>\n";
        return $html;
    } // renderForm


    /**
     * @param array $data
     * @return string
     * @throws \Exception
     */
    private function callback(array $data): string
    {
        $setName = $this->getSetName((int)$data['setinx']);
        $context = "[$setName: ".PageFactory::$hostUrl.PageFactory::$slug.']';
        $dataset = $this->getDataset($setName);

        $recId = $data['recid'];
        $data['_time'] = time();

        unset($data['_formInx']);
        unset($data['_cancel']);
        unset($data['recid']);
        unset($data['setinx']);

        if ($this->listFrozen) {
            mylog("EnList error deadline exeeded: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-error-deadline-exeeded }}');
        }

        if ($recId === '') {
            // new entry:
            $name = $data['Name']??'#####';
            $exists = array_filter($dataset, function ($e) use($name){
                return ($e['Name']??'') === $name;
            });
            if ($exists) {
                $prevRecId = array_keys($exists)[0];
                $email = $data['Email']??'#####';
                $email0 =  $dataset[$prevRecId]['Email'];
                if ($email0 !== $email) {
                    mylog("EnList error Rec exists: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                    reloadAgent(message: '{{ pfy-enlist-error-rec-exists }}');
                }
                $dataset[$prevRecId] = $data;
            } else {
                $dataset[] = $data;
            }
            $dataset = array_values($dataset);
            if (sizeof($dataset) > $this->nTotalSlots) {
                mylog("EnList: fishy data entry: max slots exeeded. $context", 'enlist-log.txt');
                reloadAgent();
            }
            $this->db->addRec($dataset, recKeyToUse:$setName);
            $this->notifyOwner($data, 'add', $setName);
            if ($this->sendConfirmation($data, $setName)) {
                mylog("EnList new entry & confirmation sent: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-confirmation-sent }}');
            }
            mylog("EnList new entry: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            reloadAgent();

        // delete entry:
        } else {
            $rec = $dataset[$recId]??false;
            if ($rec) {
                $time = $rec['_time']??0;
                if ($time < $this->freezeTime) {
                    mylog("EnList freezeTime expired: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                    reloadAgent(message: '{{ pfy-enlist-del-freeze-time-expired }}');
                }

                $email = $data['Email']??'#####';
                $email0 =  $rec['Email']??'@@@@@@@';
                if ($email0 !== $email) {
                    mylog("EnList wrong email for delete: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                    reloadAgent(message: '{{ pfy-enlist-del-error-wrong-email }}');
                } else {
                    unset($dataset[$recId]);
                    $dataset = array_values($dataset);
                    $this->db->addRec($dataset, recKeyToUse:$setName);
                    $this->notifyOwner($rec, 'del', $setName);
                    mylog("EnList entry deleted: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                    reloadAgent(message: '{{ pfy-enlist-deleted }}');
                }
            }
        }
        return '';
    } // callback


    /**
     * @param array $data
     * @param string $mode
     * @param string $setName
     * @return void
     */
    private function notifyOwner(array $data, string $mode, string $setName): void
    {
        if (!($to = ($this->options['notifyOwner']??false))) {
            return;
        }

        if ($mode === 'add') {
            $subject = '{{ pfy-enlist-add-notification-subject }}';
            $body = '{{ pfy-enlist-add-notification-body }}';
        } else {
            $subject = '{{ pfy-enlist-del-notification-body }}';
            $body = '{{ pfy-enlist-del-notification-body }}';
        }
        $replace = [
            '%name' => $data['Name'],
            '%email' => $data['Email'],
            '%topic' => $setName,
            '%host' => PageFactory::$hostUrl,
            '%page' => PageFactory::$slug,
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($subject, PageFactory::$defaultLanguage));
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($body, PageFactory::$defaultLanguage));

        Utils::sendMail($to, $subject, $body );
    } // notifyOwner


    /**
     * @param array $data
     * @param string $setName
     * @return bool
     */
    private function sendConfirmation(array $data, string $setName): bool
    {
        if (!($this->options['sendConfirmation']??false)) {
            return false;
        }

        $subject = '{{ pfy-enlist-visitor-confirmation-subject }}';
        $body = '{{ pfy-enlist-visitor-confirmation-body }}';
        $replace = [
            '%name' => $data['Name'],
            '%email' => $data['Email'],
            '%topic' => $setName,
            '%host' => PageFactory::$hostUrl,
            '%page' => PageFactory::$slug,
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($subject, PageFactory::$defaultLanguage));
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($body, PageFactory::$defaultLanguage));

        Utils::sendMail($data['Email'], $subject, $body );
        return true;
    } // sendConfirmation


    /**
     * @param array $options
     * @return void
     */
    private function parseOptions(array $options): void
    {
        $this->options = $options;
        if (($this->inx = $options['inx'] ?? false) === false) {
            if (isset($GLOBALS['pfyEnlistInx'])) {
                $this->inx = $GLOBALS['pfyEnlistInx']++;
            } else {
                $this->inx = $GLOBALS['pfyEnlistInx'] = 1;
            }
        }

        $title = $title0 = $this->options['title'] ?? '';
        $dateTime = strtotime($this->options['datetime']??'');
        $deadline = false;
        if ($dateTime) {
            if ($deadlineStr = ($this->options['deadline'] ?? '')) {
                if (preg_match('/-\d+\s*\w+/', $deadlineStr, $m)) {
                    $deadline = strtotime($deadlineStr, $dateTime);
                } else {
                    $deadline = strtotime($deadlineStr);
                }
                $deadline = intval($deadline / 86400) * 86400 + 86400; // round up to end of day
                $deadlineStr = date('l, d.F Y', $deadline - 86400);
            }
            if ($dateTime % 86400) {
                $dateTimeStr = date('l, d.F Y, H:i %%', $dateTime);
            } else {
                $dateTimeStr = date('l, d.F Y', $dateTime);
            }
            $pattern = ['%datetime' => $dateTimeStr, '%deadline' => $deadlineStr?:''];
            $title0 = str_replace(array_keys($pattern), array_values($pattern), $title);
            $title = translateDateTimes($title0);
            $title0 = trim(str_replace('%%', '', $title0));
        }
        if ($deadline) {
            $this->listFrozen = ($deadline < time());
        }

        $this->title = $title;

        if (!($this->datasetName = ($this->options['listName']??false))) {
            if ($title0) {
                $this->datasetName = translateToFilename(strip_tags($title0), false);
            } else {
                $this->datasetName = "List-$this->inx";
            }
        }

        $sess = kirby()->session();
        $setnames = $sess->get('pfy.enlist-setnames')?: [];
        $setnames[PageFactory::$slug][$this->inx] = $this->datasetName;
        $sess->set('pfy.enlist-setnames', $setnames);

        $this->freezeTime = $this->options['freezeTime']??false;
        if ($this->freezeTime) {
            $this->freezeTime = time() - ($this->freezeTime * 3600); // freezeTime is in hours
        }

    } // parseOptions

} // Enlist