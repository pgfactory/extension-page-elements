<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\DataSet;
use Usility\PageFactory\PfyForm;
use Usility\PageFactory\Utils;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactory\TransVars;
use function Usility\PageFactory\explodeTrim;
use function Usility\PageFactory\parseArgumentStr;
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
    private $pagePath;
    private $pageId;
    private $fieldNames = [];
    private $customFields = [];
    private $customFieldsEmpty = [];

    /**
     * @param $options
     * @throws \Exception
     */
    public function __construct($options, $customFields)
    {
        $this->pagePath = substr(page()->url(), strlen(site()->url()) + 1) ?: 'home';
        $this->pageId = str_replace('/', '_', $this->pagePath);

        if ($options['description']??false) {
            $options['info'] = $options['description'];
        }
        $this->fieldNames = ['#', '&nbsp;', 'Name'];
        if ($customFields) {
            $nCustFiels = sizeof($customFields);
            foreach ($customFields as $key => $customField) {
                // special case 'checkbox options':
                if ((($customField['type']??'text') === 'checkbox') ||
                            ($customField['options']??false)) {
                    $customField['type'] = 'checkbox';
                    $customOptions = parseArgumentStr($customField['options']??'');
                    $customFields[$key]['options'] = $customOptions;
                    $this->fieldNames = array_merge($this->fieldNames, array_values($customOptions));
                    $nCustFiels += sizeof($customOptions) - 1;
                } else {
                    $this->fieldNames[] = $key;
                }
            }
            $this->customFields = $customFields;

            $this->customFieldsEmpty = array_fill(0, $nCustFiels, '');
            $this->fieldNames[] = '&nbsp;';
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

        // isEnlistAdmin overrides listFrozen:
        if ($this->isEnlistAdmin) {
            $this->listFrozen = null; // -> null signifies frozen, but class still added to wrapper
        }
    } // __construct


    /**
     * @return string
     */
    public function render(): string
    {
        $this->entries = $this->getDataset($this->datasetName);

        $id = ($this->options['id']??false)?: "pfy-enlist-wrapper-$this->inx";
        $class = rtrim("pfy-enlist-wrapper pfy-enlist-$this->inx " . ($this->options['class']??false)?: '');
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

        // add class to show that list is frozen (even if isEnlistAdmin):
        if ($this->listFrozen || ($this->listFrozen === null)) {
            $class .= ' pfy-enlist-frozen';
        }

        $this->nReserveSlots = $this->options['nReserveSlots'];
        $this->nSlots = $this->options['nSlots'];
        $nTotal = $this->nTotalSlots = $this->nSlots + $this->nReserveSlots;

        $this->renderInfoButton();

        $headButtons = $this->renderSendMailToAllButton();

        $html = $this->renderEntryTable();

        $html = <<<EOT
<div id='$id' class='$class' data-setInx="$this->inx">
<div class='pfy-enlist-title'><span>$this->title</span>$headButtons</div>
$html
</div>
EOT;
        return $html;
    } // render


    /**
     * @return string
     */
    private function renderEntryTable()
    {
        $thead = $this->renderEntryTableHeader();
        $rows = '';
        for ($i=0; $i<$this->nTotalSlots; $i++) {
            $rows .= $this->renderListEntry($i);
        }
        $tableClass = $this->customFields? ' pfy-enlist-custom-fields': '';

        $html = <<<EOT
<table class='pfy-enlist-table$tableClass'>
  <thead>
$thead
  </thead>

  <tbody>
$rows
  </tbody>
</table>
EOT;

        return $html;
    } // renderEntryTable


    /**
     * @return string
     */
    private function renderEntryTableHeader(): string
    {
        $thead = '';
        $n = sizeof($this->fieldNames);
        $hClasses = ['pfy-enlist-row-num','pfy-enlist-icon pfy-enlist-icon-1','pfy-enlist-name'];
        for ($i=3; $i<$n; $i++) {
            $hClasses[$i] = "pfy-enlist-custom-field pfy-enlist-custom-field-".($i+1);
        }
        $hClasses[] = 'pfy-enlist-icon pfy-enlist-icon-2';
        foreach ($this->fieldNames as $i => $headElem) {
            if ($i !== $n-1) {
                $thead .= "      <th class='{$hClasses[$i]}'>$headElem</th>\n";
            } else {
                $thead .= "      <th class='pfy-enlist-icon pfy-enlist-icon-2'>$headElem</th>\n";
            }
        }
        $header = <<<EOT
    <tr>
$thead    </tr>
EOT;

        return $header;
    } // renderEntryTableHeader


    /**
     * @param int $i
     * @return string
     */
    private function renderListEntry(int $i): string
    {
        $html = "      <td class='pfy-enlist-row-num'>".($i+1).":</td>\n";
        // filled element:
        if ($i < $this->nEntries) {
            list($icon, $class, $name, $customFields) = $this->renderFilledRow($i);

        // add element:
        } elseif ($i === $this->nEntries && !$this->listFrozen) {
            $icon = '<button type="button" title="{{ pfy-enlist-add-title }}">' . ENLIST_ADD_ICON . '</button>';
            list($class, $name, $customFields) = ['pfy-enlist-add', '{{ pfy-enlist-add-text }}', $this->customFieldsEmpty];

        } else {
            list($icon, $class, $name, $customFields) = ['', 'pfy-enlist-empty', '', $this->customFieldsEmpty];
        }
        if ($i >= $this->nSlots) {
            $class .= ' pfy-enlist-reserve';
        }
        $html .= "      <td class='pfy-enlist-icon-1'>$icon</td>\n";
        $html .= "      <td class='pfy-enlist-name'>$name</td>\n";
        if (is_array($customFields)) {
            $j = 1;
            foreach ($customFields as $name => $value) {
                if ($value['options']??false) {
                    foreach ($value['options'] as $elem) {
                        $html .= "      <td class='pfy-enlist-custom-field pfy-enlist-custom-field-$j'>$elem</td>\n";
                        $j++;
                    }
                    $j++;
                } else {
                    $html .= "      <td class='pfy-enlist-custom-field pfy-enlist-custom-field-$j'>$value</td>\n";
                }
            }
        }
        $html .= "      <td class='pfy-enlist-icon-2'>$icon</td>\n";

        $html = <<<EOT
    <tr class="$class" data-inx="$i">
$html    </tr>


EOT;
        return $html;
    } // renderListEntry


    /**
     * @param int $i
     * @return array
     */
    private function renderFilledRow(int $i): array
    {
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
        if ($this->listFrozen) {
            $icon = '';
        }

        if ($this->isEnlistAdmin && ($email = ($rec['Email']??false))) {
            $text .= " <span class='pfy-enlist-email'><a href='mailto:$email'>$email</a></span>\n";
        }

        $customFields = [];
        foreach ($this->customFields as $key => $value) {
            if ($value['options']??false) {
                foreach ($value['options'] as $k => $v) {
                    $customFields[] = $rec[$key][$k]??'?';
                }

            } else {
                $customFields[] = $rec[$key]??'?';
            }
        }
        return [$icon, $class, $text, $customFields];
    } // renderFilledRow


    /**
     * @return void
     * @throws \Exception
     */
    private function openDb(): void
    {
        if ($filename = ($this->options['file']??false)) {
            $filename = basename($filename);
        } else {
            $filename = $this->pageId;
        }
        $file = "~data/enlist/$filename.yaml";
        $file = resolvePath($file);
        $this->db = new DataSet($file, [
            'masterFileRecKeyType' => 'origKey',
            'masterFileRecKeySort' => true,
            'masterFileRecKeySortOnElement' => '_origRecKey',
            'recKeyType' => '_reckey',
        ]);

        $this->datasets = $this->db->data(recKeyType: 'origKey');
    } // openDb


    /**
     * @param string $setName
     * @return array
     */
    private function getDataset(string $setName): array
    {
        $this->entries = $this->datasets[$setName]??[];
        if (is_array($this->entries)) {
            $this->entries = array_values($this->entries);
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
        return $setnames[$this->pageId][$setInx]??false;
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
        // minimum required fields:
        $formFields = [
            'Name' => ['label' => 'Name:', 'required' => true],
            'Email' => ['label' => 'E-Mail:', 'required' => true],
        ];
        // optional custom fields:
        $i = 1;
        foreach ($this->customFields as $fieldName => $rec) {
            // special case 'checkbox options':
            if ($rec['options']??false) {
                $rec['type'] = 'checkbox';
            }
            $rec['class'] = 'pfy-enlist-custom pfy-enlist-custom-'.$i++;
            $formFields[$fieldName] = $rec;
        }
        // standard form end fields:
        $formFields['cancel'] = [];
        $formFields['submit'] = [];
        $formFields['recid'] = ['type' => 'hidden'];
        $formFields['setinx'] = ['type' => 'hidden'];

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
        $setInx = (int)$data['setinx'];
        $setName = $this->getSetName((int)$data['setinx']);
        $context = "[$setName: ".PageFactory::$hostUrl.$this->pagePath.']';
        $dataset = $this->getDataset($setName);

        $recId = $data['recid'];
        $data['_time'] = time();

        unset($data['_formInx']);
        unset($data['_cancel']);
        unset($data['recid']);
        unset($data['setinx']);

        $sess = kirby()->session();
        $listsFrozen = $sess->get('pfy.listsFrozen', []);
        if ($listsFrozen[$this->pageId][$setInx]??true) {
            mylog("EnList error deadline exeeded: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-error-deadline-exeeded }}');
        }

        if ($recId === '') {
            // new entry:
            $name = $data['Name']??'#####';
            $exists = array_filter($dataset, function ($e) use($name){
                return ($e['Name']??'') === $name;
            });
            if ($this->customFields) {
                foreach ($this->customFields as $key => $args) {
                    if (is_array($args['options']??false)) {
                        $o = [];
                        foreach ($args['options'] as $k => $v) {
                            $o[$k] = in_array($k, $data[$key]);
                        }
                        $data[$key] = $o;
                    }
                }
            }


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
            '%page' => $this->pagePath,
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
            '%page' => $this->pagePath,
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
        $setnames[$this->pageId][$this->inx] = $this->datasetName;
        $sess->set('pfy.enlist-setnames', $setnames);

        // determine whether list is past deadline:
        if ($deadline) {
            $this->listFrozen = ($deadline < time());
        }
        // record frozenList state in $sess for callback():
        $listsFrozen = $sess->get('pfy.listsFrozen', []);
        $listsFrozen[$this->pageId][$this->inx] = $this->listFrozen;
        $sess->set('pfy.listsFrozen', $listsFrozen);

        // prepare freezeTime threshold to apply to individual entries:
        $this->freezeTime = $this->options['freezeTime']??false;
        if ($this->freezeTime) {
            $this->freezeTime = time() - ($this->freezeTime * 3600); // freezeTime is in hours
        }
    } // parseOptions

} // Enlist