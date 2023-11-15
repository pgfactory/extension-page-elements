<?php

namespace PgFactory\PageFactoryElements;

use Kirby\Exception\InvalidArgumentException;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\DataSet;
use PgFactory\PageFactory\PfyForm;
use PgFactory\PageFactory\Utils;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\createHash;
use function PgFactory\PageFactory\parseArgumentStr;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\translateToFilename;
use function PgFactory\PageFactory\mylog;
use function PgFactory\PageFactory\fileExt;

const ENLIST_INFO_ICON = 'ⓘ';
const ENLIST_MAIL_ICON = '✉';
const ENLIST_ADD_ICON = '+';
const ENLIST_DELETE_ICON = '−';


class Enlist
{
    private $options;
    private $inx;
    private $presetOptionsMode = false;
    private $title;
    private $nEntries = 0;
    private $nSlots = 0;
    private $nReserveSlots = 0;
    private $nTotalSlots = 0;
    private $db;
    private $dataset;
    private $datasets;
    private $datasetName;
    private $deadlineExpired = false;
    private $isEnlistAdmin = false;
    private $titleClass = '';
    private $pagePath;
    private $pageId;
    private $fieldNames = [];
    private $customFields = [];
    private $customFields0 = [];
    private $customFieldsEmpty = [];
    protected static $session;

    private static $_title = null;
    private static $_nSlots = null;
    private static $_nReserveSlots = null;
    private $file;
    private static $_file = null;
    private $info;
    private static $_info = null;
    private $freezeTime = false;
    private static $_freezeTime = null;

    private $deadline;
    private static $_deadline = null;
    private $sendConfirmation;
    private static $_sendConfirmation = null;
    private $notifyOwner;
    private static $_notifyOwner = null;
    private $notifyActivatedReserve;
    private static $_notifyActivatedReserve = null;
    private $obfuscate;
    private static $_obfuscate = null;
    private $admin;
    private static $_admin = null;
    private $adminEmail;
    private static $_adminEmail = null;
    private $class;
    private static $_class = null;
    private array|false $events = false;

    /**
     * @var string
     */
    protected static $obfuscateSessKey;

    /**
     * @param $options
     * @param $customFields
     * @throws InvalidArgumentException
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

        $this->parseOptions($options);

        $this->events = $this->handleScheduleOption();

        $this->fieldNames = ['#', '&nbsp;', 'Name'];

        if ($customFields) {
            $this->customFields0 = $customFields;
            $nCustFields = sizeof($customFields);
            foreach ($customFields as $key => $customField) {
                if (($customField['hidden']??false) && !$this->isEnlistAdmin) {
                    unset($customFields[$key]);
                    $nCustFields--;
                    continue;
                }
                // special case 'checkbox options':
                if ((($customField['type']??'text') === 'checkbox') ||
                            ($customField['options']??false)) {
                    $customField['type'] = 'checkbox';
                    $customOptions = parseArgumentStr($customField['options']??'');
                    $customFields[$key]['options'] = $customOptions;
                    $this->fieldNames = array_merge($this->fieldNames, array_values($customOptions));
                    $nCustFields += sizeof($customOptions) - 1;
                } else {
                    $key = rtrim($customField['label']??$key, ':');
                    $this->fieldNames[] = $key;
                }
            }
            $this->customFields = $customFields;

            $this->customFieldsEmpty = array_fill(0, $nCustFields, '');
            $this->fieldNames[] = '&nbsp;';
        }

        $this->openDb();
        PageFactory::$pg->addAssets('FORMS');
    } // __construct


    /**
     * @return string
     */
    public function render(): string
    {
        if ($this->events) {
            $html = '';
            foreach ($this->events as $event) {
                $this->title = $event['eventBanner'];
                $this->datasetName =  $event['start'];
                $html .= $this->renderElement();
            }

        } else {
            $html = $this->renderElement();
        }

        return $html;
    } // render


    /**
     * @return string
     */
    private function renderElement(): string
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
            $class .= ' pfy-enlist-expired';
        }

        $this->renderInfoButton();

        $headButtons = $this->renderSendMailToAllButton();

        $html = $this->renderEntryTable();

        $html = <<<EOT
<div id='$id' class='$class' data-setname="$this->datasetName">
<div class='pfy-enlist-title$this->titleClass'><span>$this->title</span>$headButtons</div>
$html
</div>
EOT;
        return $html;
    } // renderElement


    /**
     * @param array $options
     * @return void
     */
    private function parseOptions(array $options): void
    {
        $this->presetOptionsMode = !($options['output']??true);
        if (($this->inx = $options['inx'] ?? false) === false) {
            if (isset($GLOBALS['pfyEnlistInx'])) {
                $GLOBALS['pfyEnlistInx']++;
                $this->inx = $GLOBALS['pfyEnlistInx'];
            } elseif ($options['output']) {
                $this->inx = $GLOBALS['pfyEnlistInx'] = 1;
            } else {
                $this->inx = 0;
            }
        }
        $this->options = $options;

        $title = $title0 = $this->prepStaticOption('title', '');

        $this->prepStaticOption('nSlots', 1);
        $this->prepStaticOption('nReserveSlots', 0);
        $this->prepStaticOption('info');
        $this->prepStaticOption('file');
        $this->prepStaticOption('freezeTime');
        $this->prepStaticOption('sendConfirmation');
        $this->prepStaticOption('notifyOwner');
        $this->prepStaticOption('notifyActivatedReserve');
        $this->prepStaticOption('obfuscate');
        $this->prepStaticOption('admin', true);
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
            if ($title0 && (self::$_title === null)) {
                $this->datasetName = translateToFilename(strip_tags($title0), false);
            } else {
                $this->datasetName = "List-$this->inx";
            }
        }

        if ($permissionQuery = $this->admin) {
            if ($permissionQuery === true) {
                $permissionQuery = 'localhost|loggedin';
            }
            $this->isEnlistAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
            if ($this->isEnlistAdmin && ($this->inx === 1)) {
                PageFactory::$pg->addBodyTagClass('pfy-enlist-admin');
            }
        }

        if (!$this->title && !$this->isEnlistAdmin) {
            $this->titleClass = ' pfy-empty-title';
        }

        // determine whether list is past deadline:
        if ($deadline) {
            $this->deadlineExpired = ($deadline < time());
        }
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
            $hidden = $this->customFields[$headElem]['hidden']??false;
            if ($hidden && !$this->isEnlistAdmin) {
                continue;
            }
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
        } elseif ($i === $this->nEntries && !($this->deadlineExpired && !$this->isEnlistAdmin)) {
            $class = ($this->deadlineExpired && $this->isEnlistAdmin) ? ' pfy-enlist-elem-frozen' : '';
            $icon = '<button type="button" title="{{ pfy-enlist-add-title }}">' . ENLIST_ADD_ICON . '</button>';
            list($class, $name, $customFields) = ['pfy-enlist-add'.$class, '{{ pfy-enlist-add-text }}', $this->customFieldsEmpty];

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
        $class .= ($this->deadlineExpired && $this->isEnlistAdmin) ? ' pfy-enlist-elem-frozen' : '';
        $rec = $this->dataset[$i];
        $icon = '<button type="button" title="{{ pfy-enlist-delete-title }}">'.ENLIST_DELETE_ICON.'</button>';
        $text = $rec['Name']??'## unknown ##';

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
        if ($this->deadlineExpired && !$this->isEnlistAdmin) {
            $icon = '';
        }

        // append email if in admin mode:
        if ($this->isEnlistAdmin && ($email = ($rec['Email']??false))) {
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
        if ($this->file) {
            $filename = basename($this->file);
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
     * @param string|false $setName
     * @return array
     */
    private function getDataset(string|false $setName = false): array
    {
        $nTotalSlots = false;
        $initialRun = false;
        if (!$setName) {
            $setName = $this->datasetName;
            $nTotalSlots = $this->nTotalSlots;
            $nReserveSlots = $this->nReserveSlots;
            $initialRun = true;
        }

        if (!isset($this->datasets[$setName])) {
            // create new dataset:
            $dataset = [
                'title' => $this->title,
                'nSlots' => $nTotalSlots,
                'nReserveSlots' => $nReserveSlots,
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
            if ($initialRun) {
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
        if ($this->info) {
            $this->title .= "<span tabindex='0' class='pfy-tooltip-anker'>" . ENLIST_INFO_ICON .
                "</span><span class='pfy-tooltip'>$this->info</span>";
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
        foreach ($this->customFields0 as $fieldName => $rec) {
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
        $html = $form->renderForm($formFields);
        if (str_contains($html, 'class="error"')) {
            $jq = "Enlist.openPopup()";
            PageFactory::$pg->addJsReady($jq);
        }
        $html = "\n\n<div id='pfy-enlist-form'>\n$html</div>\n<!-- /pfy-enlist-form -->\n\n";
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
                    $text .= strtoupper($s[0] ?? '');
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
            $obfuscatedKey = \PgFactory\PageFactory\createHash();
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


    /**
     * @param array|false $dataset
     * @return int
     */
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
        unset($data['_csrf']);
        unset($data['elemId']);
        unset($data['setname']);

        if ($dataset['deadlineExpired']??false) {
            if ($this->isEnlistAdmin) {
                $message = '{{ pfy-enlist-error-deadline-was-expired }}';
            } else {
                mylog("EnList error deadline exeeded: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
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


    /**
     * @param array $data
     * @param array $dataset
     * @param string $context
     * @param mixed $setName
     * @param string $message
     * @return void
     * @throws \Exception
     */
    private function handleNewEntry(array $data, array $dataset, string $context, mixed $setName, string $message): void
    {
        $name = $data['Name'] ?? '#####';
        $exists = array_filter($dataset, function ($e) use ($name) {
            return ($e['Name'] ?? '') === $name;
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
            $email = $data['Email'] ?? '#####';
            $email0 = $dataset[$prevElemId]['Email'];
            if ($email0 !== $email) {
                mylog("EnList error Rec exists: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-rec-exists }}');
            }
            $dataset[$prevElemId] = $data;
        } else {
            $dataset = $this->modifyDataSet($dataset, data: $data);
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
            mylog("EnList new entry & confirmation sent: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-confirmation-sent }}');
        }
        mylog("EnList new entry: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
        reloadAgent(message: $message);
    } // handleNewEntry


    /**
     * @param array $dataset
     * @param string $elemId
     * @param string $message
     * @param array $data
     * @param string $context
     * @param mixed $setName
     * @return array
     * @throws \Exception
     */
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
                mylog("EnList freezeTime expired: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-del-freeze-time-expired }}');
            }
        }

        $email = $data['Email'] ?? '#####';
        $email0 = $rec['Email'] ?? '@@@@@@@';
        if ($email0 !== $email) {
            mylog("EnList wrong email for delete: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-del-error-wrong-email }}');
        } else {
            $dataset = $this->modifyDataSet($dataset, elemKey: $elemKey);
            $this->db->addRec($dataset, recKeyToUse: $setName);
            $this->handleNotifyOwner($rec, 'del', $setName);
            if ($elemKey !== false) {
                $this->handleNotifyReserve($dataset);
            }
            mylog("EnList entry deleted: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            $message = $message ? "<br>$message" : '';
            reloadAgent(message: '{{ pfy-enlist-deleted }}' . $message);
        }
    } // handleExistingEntry


    /**
     * @param array $dataset
     * @param array|false $data
     * @param string|false $elemKey
     * @return array
     * @throws \Exception
     */
    private function modifyDataSet(array $dataset, array|false $data = false, string|false $elemKey = false): array
    {
        $nSlots = $dataset['nSlots'];
        unset($dataset['nSlots']);

        $nReserveSlots = $dataset['nReserveSlots'];
        unset($dataset['nReserveSlots']);

        if (isset($dataset['freezeTime'])) {
            $freezeTime = $dataset['freezeTime'];
            unset($dataset['freezeTime']);
        }
        $title = $dataset['title'];
        unset($dataset['title']);

        if (isset($dataset['deadlineExpired'])) {
            $deadlineExpired = $dataset['deadlineExpired'];
            unset($dataset['deadlineExpired']);
        }

        if ($elemKey === false) {
            $data['_elemKey'] = createHash();
            $dataset[] = $data;
            $dataset = array_values($dataset);
        } else {
            unset($dataset[$elemKey]);
            $dataset = array_values($dataset);
        }

        $dataset['title'] = $title;
        $dataset['nSlots'] = $nSlots;
        $dataset['nReserveSlots'] = $nReserveSlots;
        if (isset($freezeTime)) {
            $dataset['freezeTime'] = $freezeTime;
        }
        if (isset($deadlineExpired)) {
            $dataset['deadlineExpired'] = $deadlineExpired;
        }
        return $dataset;
    } // modifyDataSet


    /**
     * @param array $dataset
     * @return void
     */
    private function handleNotifyReserve(array $dataset): void
    {
        if (!$this->notifyActivatedReserve) {
            return;
        }
        $nSlots = $dataset['nSlots'];
        $nReserveSlots = $dataset['nReserveSlots'];
        $nActiveSlots = $nSlots - $nReserveSlots;
        if (isset($dataset[$nActiveSlots - 1])) {
            $rec = $dataset[$nActiveSlots - 1];
            $this->notifyActivatedReserve($rec, $dataset['title']);
        }
    } // handleNotifyReserve


    /**
     * @param array $rec
     * @param string $setName
     * @return void
     */
    private function notifyActivatedReserve(array $rec, string $setName): void
    {
        $subject = TransVars::resolveVariables('{{ pfy-enlist-notify-activated-reserve-subject }}');
        $body = TransVars::resolveVariables('{{ pfy-enlist-notify-activated-reserve-body }}');
        $replace = [
            '%name%' => $rec['Name'],
            '%email%' => $rec['Email'],
            '%title%' => $setName,
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

        Utils::sendMail($rec['Email'], $subject, $body );
    } // notifyActivatedReserve


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
            '%name%' => $data['Name'],
            '%email%' => $data['Email'],
            '%title%' => $setName,
            '%host%' => PageFactory::$hostUrl,
            '%page%' => $this->pagePath,
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($subject));
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($body));

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
            '%name%' => $data['Name'],
            '%email%' => $data['Email'],
            '%title%' => $title,
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

        Utils::sendMail($data['Email'], $subject, $body );
        return true;
    } // handleSendConfirmation


    /**
     * @param string $key
     * @return mixed
     */
    private function prepStaticOption(string $key, mixed $default = false): mixed
    {
        if ($this->options[$key] !== null) {
            // explicitly provided value:
            $val = $this->options[$key];
            if ($this->presetOptionsMode) {
                self::${"_$key"} = $val;
            }
        } else {
            // no explicit value -> try preset one:
            $val = (self::${"_$key"} !== null) ? self::${"_$key"} : $default;
        }
        return $this->$key = $val;
    } // prepStaticOption


    /**
     * @return array|false
     * @throws \Exception
     */
    private function handleScheduleOption(): array|false
    {
        if (!($eventOptions = $this->options['schedule']??false)) {
            return false;
        }
        if (!($src = $eventOptions['src']??false)) {
            if (!($src = $eventOptions['file']??false)) { // allow 'file' as synonyme for 'src'
                throw new \Exception("Form: option 'schedule' without option 'src'.");
            }
        }

        unset($eventOptions['file']);
        $sched = new Events($src, $eventOptions);
        $count = $eventOptions['count']??false;
        $nextEvents = $sched->getNextEvents(count: $count);
        return $nextEvents;
    } // handleScheduleOption


} // Enlist