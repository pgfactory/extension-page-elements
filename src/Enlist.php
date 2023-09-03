<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\DataSet;
use Usility\PageFactory\PfyForm;
use Usility\PageFactory\Utils;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactory\TransVars;
use function Usility\PageFactory\createHash;
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
    private $dataset;
    private $nEntries = 0;
    private $nSlots = 0;
    private $nReserveSlots = 0;
    private $nTotalSlots = 0;
    private $db;
    private $datasets;
    private $datasetName;
    private $deadlineExpired = false;
    private $isEnlistAdmin = false;
    private $pagePath;
    private $pageId;
    private $fieldNames = [];
    private $customFields = [];
    private $customFieldsEmpty = [];
    protected static $session;

    private $freezeTime = false;
    private static $_freezeTime = null;

    private $deadline;
    private static $_deadline = null;
    private $sendConfirmation;
    private static $_sendConfirmation = null;
    private $notifyOwner;
    private static $_notifyOwner = null;
    private $obfuscate;
    private static $_obfuscate = null;
    private $admin;
    private static $_admin = null;
    private $adminEmail;
    private static $_adminEmail = null;
    private $class;
    private static $_class = null;

    /**
     * @var string
     */
    protected static $obfuscateSessKey;

    /**
     * @param $options
     * @throws \Exception
     */
    public function __construct($options, $customFields)
    {
        $this->pagePath = substr(page()->url(), strlen(site()->url()) + 1) ?: 'home';
        $this->pageId = str_replace('/', '_', $this->pagePath);

        $pageId = page()->id();
        self::$session = kirby()->session();
        self::$obfuscateSessKey = "obfuscate:$pageId:elemKeys";

        if ($options['description']??false) {
            $options['info'] = $options['description'];
        }
        $this->fieldNames = ['#', '&nbsp;', 'Name'];
//???
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
                    $key = rtrim($customField['label']??$key, ':');
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

        if ($permissionQuery = $this->admin) {
            if ($permissionQuery === true) {
                $permissionQuery = 'localhost|loggedin';
            }
            $this->isEnlistAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
            if ($this->isEnlistAdmin && ($this->inx === 1)) {
                PageFactory::$pg->addBodyTagClass('pfy-enlist-admin');
            }
        }

        // isEnlistAdmin overrides deadlineExpired:
        if ($this->deadlineExpired && $this->isEnlistAdmin) {
            $this->deadlineExpired = null; // -> null signifies frozen, but class still added to wrapper
        }
    } // __construct


    /**
     * @return string
     */
    public function render(): string
    {
        // get access to the current dataset:
        $this->dataset = $this->getDataset();

        // obtain current count of entries:
        $this->nEntries = $this->countEntries();

        $id = ($this->options['id']??false)?: "pfy-enlist-wrapper-$this->inx";
        $class = rtrim("pfy-enlist-wrapper pfy-enlist-$this->inx " . $this->class);
        if ($this->isEnlistAdmin) {
            $class .= ' pfy-enlist-admin';

            if ($this->inx === 1) {
                if ($this->adminEmail === true) {
                    $adminEmail = PageFactory::$webmasterEmail;
                    PageFactory::$pg->addJs("const adminEmail = '$adminEmail';");
                } elseif($this->adminEmail) {
                    PageFactory::$pg->addJs("const adminEmail = '$this->adminEmail';");
                }
            }
        }

        // add class to show that list is frozen (even if isEnlistAdmin):
        if ($this->deadlineExpired || ($this->deadlineExpired === null)) {
            $class .= ' pfy-enlist-frozen';
        }

        $this->renderInfoButton();

        $headButtons = $this->renderSendMailToAllButton();

        $html = $this->renderEntryTable();

        $html = <<<EOT
<div id='$id' class='$class' data-setname="$this->datasetName">
<div class='pfy-enlist-title'><span>$this->title</span>$headButtons</div>
$html
</div>
EOT;
        return $html;
    } // render


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

        $this->prepStaticOption('freezeTime');
        $this->prepStaticOption('sendConfirmation');
        $this->prepStaticOption('notifyOwner');
        $this->prepStaticOption('obfuscate');
        $this->prepStaticOption('admin');
        $this->prepStaticOption('adminEmail');
        $this->prepStaticOption('class');
        $deadlineStr = $this->prepStaticOption('deadline');
        $deadline = false;
        if ($deadlineStr) {
            $deadline = strtotime($deadlineStr);
            $deadlineStr = date('l, d.F Y', $deadline);
            $title0 = str_replace('%deadline%', $deadlineStr, $title);
            $title = translateDateTimes($title0);
        }

        $this->title = $title;

        if (!($this->datasetName = ($this->options['listName']??false))) {
            if ($title0) {
                $this->datasetName = translateToFilename(strip_tags($title0), false);
            } else {
                $this->datasetName = "List-$this->inx";
            }
        }

        // determine whether list is past deadline:
        if ($deadline) {
            $this->deadlineExpired = ($deadline < time());
        }
        $this->nReserveSlots = $this->options['nReserveSlots']??0;
        $this->nSlots        = $this->options['nSlots']??1;
        $this->nTotalSlots   = $this->nSlots + $this->nReserveSlots;

        if ($this->freezeTime) {
            $this->freezeTime = $this->freezeTime * 3600; // freezeTime is in hours
        }
    } // parseOptions


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
        $elemKey = '';
        // filled element:
        if ($i < $this->nEntries) {
            list($icon, $class, $name, $customFields, $elemKey) = $this->renderFilledRow($i);

        // add element:
        } elseif ($i === $this->nEntries && !$this->deadlineExpired) {
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
                if (($value['options']??false) && (is_array($value['options']))) {
                    foreach ($value['options'] as $elem) {
                        $html .= "      <td class='pfy-enlist-custom-field pfy-enlist-custom-field-$j'>$elem</td>\n";
                        $j++;
                    }
                    $j++;
                } else {
                    $value = str_replace("\n", '<br>', $value);
                    $html .= "      <td class='pfy-enlist-custom-field pfy-enlist-custom-field-$j'>$value</td>\n";
                }
            }
        }
        $html .= "      <td class='pfy-enlist-icon-2'>$icon</td>\n";

        $html = <<<EOT
    <tr class="$class" data-elemkey="$elemKey">
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
        $rec = $this->dataset[$i];
        $icon = '<button type="button" title="{{ pfy-enlist-delete-title }}">'.ENLIST_DELETE_ICON.'</button>';
        $text = $rec['EnlistName']??'## unknown ##';

        // handle obfuscate name:
        list($text, $icon) = $this->obfuscateNameInRow($text, $icon);

        // handle freezeTime:
        if ($this->freezeTime) {
            $time = $rec['_time']??PHP_INT_MAX;
            if (is_string($time)) {
                $time = strtotime($time);
            }
            if ($time < (time() - $this->freezeTime)) {
                if (!$this->isEnlistAdmin) {
                    $icon = '';
                    $class = 'pfy-enlist-elem-frozen';
                } else {
                    $class = 'pfy-enlist-delete pfy-enlist-elem-pseudo-frozen';
                }
            }
        }
        if ($this->deadlineExpired) {
            $icon = '';
        }

        // append email if in admin mode:
        if ($this->isEnlistAdmin && ($email = ($rec['EnlistEmail']??false))) {
            $text .= " <span class='pfy-enlist-email'><a href='mailto:$email'>$email</a></span>\n";
        }

        // handle custom fields:
        $customFields = [];
        foreach ($this->customFields as $key => $value) {
            // element containing options:
            if ($value['options']??false) {
                foreach ($value['options'] as $k => $v) {
                    $customFields[] = $rec[$key][$k]??'?';
                }

            // regular element:
            } else {
                $customFields[] = $rec[$key]??'?';
            }
        }
        $elemKey = $rec['_elemKey']??'';
        $elemKey = $this->obfuscateKey($elemKey);
        return [$icon, $class, $text, $customFields, $elemKey];
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
    private function getDataset(string|false $setName = false): array
    {
        $nTotalSlots = false;
        if (!$setName) {
            $setName = $this->datasetName;
            $nTotalSlots = $this->nTotalSlots;
        }

        if (!isset($this->datasets[$setName])) {
            // create new dataset:
            $dataset = [
                'title' => $this->title,
                'nSlots' => $nTotalSlots,
                ];
            if ($this->freezeTime) {
                $dataset['freezeTime'] = $this->freezeTime;
            }
            if ($this->deadlineExpired) {
                $dataset['deadlineExpired'] = true;
            }
            $this->db->addRec($dataset, recKeyToUse:$setName);
            $data = $this->db->data();
            $this->dataset = $data[$setName];

        } else {
            // access existing dataset:
            $this->dataset = $dataset = $this->datasets[$setName];

            // if called during initial run, check whether 'nSlots' still up-to-date:
            $needUpdate = false;
            if ($nTotalSlots && ($dataset['nSlots'] !== $nTotalSlots)) {
                // 'nSlots' not up-to-date, so update it:
                $dataset['nSlots'] = $nTotalSlots;
                $needUpdate = true;
            }
            if (($dataset['freezeTime']??false) !== $this->freezeTime) {
                // 'freezeTime' not up-to-date, so update it:
                $dataset['freezeTime'] = $this->freezeTime;
                $needUpdate = true;
            }
            if ($this->deadlineExpired) {
                if (!isset($dataset['deadlineExpired']) || !$dataset['deadlineExpired']) {
                    $dataset['deadlineExpired'] = true;
                    $needUpdate = true;
                }
            } else {
                if (isset($dataset['deadlineExpired']) && $dataset['deadlineExpired']) {
                    $dataset['deadlineExpired'] = false;
                    $needUpdate = true;
                }
            }

            if ($needUpdate) {
                $this->db->addRec($dataset, recKeyToUse:$setName);
                $data = $this->db->data();
                $this->dataset = $data[$setName];
            }
        }

        return $this->dataset;
    } // getDataset


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
            'EnlistName' => ['label' => 'Name:', 'required' => true],
            'EnlistEmail' => ['label' => 'E-Mail:', 'required' => true],
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
        $formFields['elemId'] = ['type' => 'hidden'];
        $formFields['setname'] = ['type' => 'hidden'];

        $form = new PfyForm($formOptions);
        $form->createForm($formFields); // $auxOptions = form-elements
        $html = $form->renderForm();
        if (str_contains($html, 'class="error"')) {
            $jq = "Enlist.openPopup()";
            PageFactory::$pg->addJsReady($jq);
        }
        $html = "<div id='pfy-enlist-form'>\n$html</div>\n";
        return $html;
    } // renderForm


    /**
     * @param mixed $text
     * @param string $icon
     * @return array
     */
    private function obfuscateNameInRow(mixed $text, string $icon): array
    {
        if ($this->obfuscate) {
            $text0 = $text;
            if ($this->obfuscate === true) {
                $text = '*****';
                if (!$this->isEnlistAdmin) {
                    $icon = '';
                }
            } else {
                $t = explode(' ', $text);
                $text = '';
                foreach ($t as $s) {
                    $text .= strtoupper($s[0] ?? '') . ' ';
                }
                $text = rtrim($text);
            }
            if ($this->isEnlistAdmin) { // in admin mode: show
                $text .= " <span class='pfy-enlist-admin-preview'>($text0)</span>";
            }
        }
        return array($text, $icon);
    } // obfuscateNameInRow


    /**
     * @param string $key
     * @return string
     * @throws \Exception
     */
    private function obfuscateKey(string $key): string
    {
        $tableRecKeyTab = self::$session->get(self::$obfuscateSessKey);
        if (!$tableRecKeyTab || !($obfuscatedKey = array_search($key, $tableRecKeyTab))) {
            $obfuscatedKey = \Usility\PageFactory\createHash();
        }
        $tableRecKeyTab[$obfuscatedKey] = $key;
        self::$session->set(self::$obfuscateSessKey, $tableRecKeyTab);
        return $obfuscatedKey;
    } // deObfuscateKey


    /**
     * @param string $key
     * @return string
     */
    private function deObfuscateKey(string $key): string
    {
        $tableRecKeyTab = self::$session->get(self::$obfuscateSessKey);
        if ($tableRecKeyTab && (isset($tableRecKeyTab[$key]))) {
            $key = $tableRecKeyTab[$key];
        }
        return $key;
    } // deObfuscateKey


    private function countEntries(array|false $dataset = false): int
    {
        if (!$dataset) {
            $dataset = $this->dataset;
        }
        $nEntries = 0;
        array_walk($dataset, function ($value, $key) use(&$nEntries) {
            if (is_numeric($key)) {
                $nEntries++;
            }
        });
        return $nEntries;
    } // countEntries



    // === Callback: handle user response ==========================
    /**
     * @param array $data
     * @return string
     * @throws \Exception
     */
    private function callback(array $data): string
    {
        $setName = $data['setname'];
        $context = "[$setName: ".PageFactory::$hostUrl.$this->pagePath.']';
        if ($this->isEnlistAdmin) {
            $context = rtrim($context, ']').' (as admin)]';
        }
        $dataset = $this->getDataset($setName);

        $message = '';
        $elemId = $data['elemId'];
        $elemId = $this->deObfuscateKey($elemId);
        $data['_time'] = date('Y-m-d\TH:i');

        unset($data['_formInx']);
        unset($data['_cancel']);
        unset($data['_reckey']);
        unset($data['elemId']);
        unset($data['setname']);

        if ($dataset['deadlineExpired']??false) {
            if ($this->isEnlistAdmin) {
                $message = '{{ pfy-enlist-error-deadline-was-expired }}';
            } else {
                mylog("EnList error deadline exeeded: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-deadline-expired }}');
            }
        }

        if ($elemId === '') {
            // new entry:
            $this->handleNewEntry($data, $dataset, $context, $setName, $message);

        } else {
            // delete entry:
            $this->handleExistingEntry($dataset, $elemId, $message, $data, $context, $setName);
        }
        return ''; // don't continue with default processing
    } // callback


    private function handleNewEntry(array $data, array $dataset, string $context, mixed $setName, string $message): void
    {
        $name = $data['EnlistName'] ?? '#####';
        $exists = array_filter($dataset, function ($e) use ($name) {
            return ($e['EnlistName'] ?? '') === $name;
        });
        if ($this->customFields) {
            foreach ($this->customFields as $key => $args) {
                if (is_array($args['options'] ?? false)) {
                    $o = [];
                    foreach ($args['options'] as $k => $v) {
                        $o[$k] = in_array($k, $data[$key]);
                    }
                    $data[$key] = $o;
                }
            }
        }

        if ($exists) {
            $prevElemId = array_keys($exists)[0];
            $email = $data['EnlistEmail'] ?? '#####';
            $email0 = $dataset[$prevElemId]['EnlistEmail'];
            if ($email0 !== $email) {
                mylog("EnList error Rec exists: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-rec-exists }}');
            }
            $dataset[$prevElemId] = $data;
        } else {
            $nSlots = $dataset['nSlots'];
            unset($dataset['nSlots']);
            if ($freezeTime = ($dataset['freezeTime']??false)) {
                unset($dataset['freezeTime']);
            }
            $title = $dataset['title'];
            unset($dataset['title']);
            if ($deadlineExpired = ($dataset['deadlineExpired'] ?? false)) {
                unset($dataset['deadlineExpired']);
            }

            $data['_elemKey'] = createHash();
            $dataset[] = $data;
            $dataset = array_values($dataset);

            $dataset['title'] = $title;
            $dataset['nSlots'] = $nSlots;
            if ($freezeTime) {
                $dataset['freezeTime'] = $freezeTime;
            }
            if ($deadlineExpired) {
                $dataset['deadlineExpired'] = true;
            }
        }

        // double-check against max number of entries:
        $nEntries = $this->countEntries($dataset);
        if ($nEntries > $dataset['nSlots']) {
            mylog("EnList: fishy data entry: max slots exeeded. $context", 'enlist-log.txt');
            reloadAgent();
        }

        // add new entry:
        $this->db->addRec($dataset, recKeyToUse: $setName);

        $this->handleNotifyOwner($data, 'add', $dataset['title']);
        if ($this->handleSendConfirmation($data, $dataset['title'])) {
            mylog("EnList new entry & confirmation sent: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-confirmation-sent }}');
        }
        mylog("EnList new entry: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
        reloadAgent(message: $message);
    } // handleNewEntry


    private function handleExistingEntry(array $dataset, string $elemId, string $message, array $data, string $context, mixed $setName): array
    {
        $found = array_filter($dataset, function ($e) use ($elemId) {
            return ($e['_elemKey'] ?? '') === $elemId;
        });
        if (!$found) {
            mylog('Delete request ignored, element not found - probably outdated');
            reloadAgent(message: '{{ pfy-enlist-data-outdated }}');
        }

        $elemKey = array_keys($found)[0];
        $rec = array_shift($found);
        $time = strtotime($rec['_time'] ?? 0);
        if ($time < (time() - ($dataset['freezeTime']??PHP_INT_MAX))) {
            if ($this->isEnlistAdmin) {
                $message = '{{ pfy-enlist-del-freeze-time-expired }}';
            } else {
                mylog("EnList freezeTime expired: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-del-freeze-time-expired }}');
            }
        }

        $email = $data['EnlistEmail'] ?? '#####';
        $email0 = $rec['EnlistEmail'] ?? '@@@@@@@';
        if ($email0 !== $email) {
            mylog("EnList wrong email for delete: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-del-error-wrong-email }}');
        } else {
            $nSlots = $dataset['nSlots'];
            unset($dataset['nSlots']);
            if ($freezeTime = ($dataset['freezeTime']??false)) {
                unset($dataset['freezeTime']);
            }
            $title = $dataset['title'];
            unset($dataset['title']);
            if ($deadlineExpired = ($dataset['deadlineExpired'] ?? false)) {
                unset($dataset['deadlineExpired']);
            }

            unset($dataset[$elemKey]);
            $dataset = array_values($dataset);
            $dataset['title'] = $title;
            $dataset['nSlots'] = $nSlots;
            if ($freezeTime) {
                $dataset['freezeTime'] = $freezeTime;
            }
            if ($deadlineExpired) {
                $dataset['deadlineExpired'] = true;
            }
            $this->db->addRec($dataset, recKeyToUse: $setName);
            $this->handleNotifyOwner($rec, 'del', $setName);
            mylog("EnList entry deleted: {$data['EnlistName']} {$data['EnlistEmail']} $context", 'enlist-log.txt');
            $message = $message ? "<br>$message" : '';
            reloadAgent(message: '{{ pfy-enlist-deleted }}' . $message);
        }
    } // handleExistingEntry


    /**
     * @param array $data
     * @param string $mode
     * @param string $setName
     * @return void
     */
    private function handleNotifyOwner(array $data, string $mode, string $setName): void
    {
        if (!($to = $this->notifyOwner)) {
            return;
        }
        if ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }

        if ($mode === 'add') {
            $subject = '{{ pfy-enlist-add-notification-subject }}';
            $body = '{{ pfy-enlist-add-notification-body }}';
        } else {
            $subject = '{{ pfy-enlist-del-notification-body }}';
            $body = '{{ pfy-enlist-del-notification-body }}';
        }
        $replace = [
            '%name' => $data['EnlistName'],
            '%email' => $data['EnlistEmail'],
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
    } // handleNotifyOwner


    /**
     * @param array $data
     * @param string $title
     * @return bool
     */
    private function handleSendConfirmation(array $data, string $title): bool
    {
        if (!$this->sendConfirmation) {
            return false;
        }

        $subject = TransVars::resolveVariables('{{ pfy-enlist-visitor-confirmation-subject }}');
        $body = TransVars::resolveVariables('{{ pfy-enlist-visitor-confirmation-body }}');
        $replace = [
            '%name%' => $data['EnlistName'],
            '%email%' => $data['EnlistEmail'],
            '%topic%' => $title,
            '%host%' => PageFactory::$hostUrl,
            '%page%' => $this->pagePath,
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            $subject);
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            $body);

        Utils::sendMail($data['EnlistEmail'], $subject, $body );
        return true;
    } // handleSendConfirmation


    private function prepStaticOption(string $key): mixed
    {
        $val = ($this->options[$key] ?? false) ?: self::${"_$key"};
        if ($val && self::${"_$key"} === null) {
            // set global default first time a value is defined:
            self::${"_$key"} = $val;
        }
        if ($val) {
            $this->$key = $val;
        }
        return $this->$key;
    } // prepStaticOption


} // Enlist