<?php

namespace PgFactory\PageFactoryElements;

use IntlDateFormatter;
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
use function PgFactory\PageFactory\translateToClassName;
use function PgFactory\PageFactory\translateToFilename;
use function PgFactory\PageFactory\mylog;
use function PgFactory\PageFactory\explodeTrimAssoc;

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
    private bool $addFieldAdded = false;
    private $db;
    private $dataset;
    private $datasets;
    private $datasetName;
    private array $tableData = [];
    private $directlyReservePossible = false;
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
    private $directlyToReserve;
    private static $_directlyToReserve = null;
    private $class;
    private static $_class = null;
    private bool $editable = false;
    private static bool|null $_editable = null;
    private array|false $events = false;

    private static bool $userPreset = false;

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

        self::$session = kirby()->session();
        self::$obfuscateSessKey = "obfuscate:$this->pageId:elemKeys";

        if ($options['description'] ?? false) {
            $options['info'] = $options['description'];
        }

        $this->parseOptions($options);

        $this->events = $this->handleScheduleOption();

        $this->fieldNames = ['#', '&nbsp;', 'Name'];

        if ($customFields) {
            $this->customFields0 = $customFields;
            // Replace - with _ in all keys;
            $customFields1 = $customFields;
            $customFields = [];
            foreach ($customFields1 as $key => $rec) {
                $key = str_replace('-', '_', $key);
                $key = preg_replace('/\W/', '', $key);
                $customFields[$key] = $rec;
            }

            $nCustFields = sizeof($customFields);
            foreach ($customFields as $key => $customField) {
                if (($customField['hidden'] ?? false) && !$this->isEnlistAdmin) {
                    unset($customFields[$key]);
                    $nCustFields--;
                    continue;
                }
                // special case 'checkbox options':
                if ((($customField['type'] ?? 'text') === 'checkbox') ||
                    ($customField['options'] ?? false)) {
                    $customField['type'] = 'checkbox';
                    $customOptions = explodeTrimAssoc(',', $customField['options'] ?? '', splitOnLastMatch:true);
                    $customOptions = array_flip($customOptions);
                    $customFields[$key]['options'] = $customOptions;
                    if ($customField['splitOutput']??false) {
                        $this->fieldNames = array_merge($this->fieldNames, array_values($customOptions));
                        $nCustFields += sizeof($customOptions) - 1;
                    } else {
                        $key = rtrim($customField['label'] ?? $key, ':');
                        $this->fieldNames[] = $key;
                    }
                } else {
                    $key = rtrim($customField['label'] ?? $key, ':');
                    $this->fieldNames[] = $key;
                }
            }
            $this->customFields = $customFields;

            $this->customFieldsEmpty = array_fill(0, $nCustFields, '');
        }
        $this->fieldNames[] = '&nbsp; ';

        $this->openDb();
        PageFactory::$pg->addAssets('FORMS');

        $this->handleUserPreset();

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
                $this->datasetName = $event['start'];
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

        $id = ($this->options['id'] ?? false) ?: "pfy-enlist-wrapper-$this->inx";
        $class = rtrim("pfy-enlist-wrapper pfy-enlist-$this->inx " . $this->class);
        if ($this->isEnlistAdmin) {
            $class .= ' pfy-enlist-admin';

            if ($this->inx === 1) {
                if ($this->adminEmail === true) {
                    $adminEmail = PageFactory::$webmasterEmail;
                    PageFactory::$pg->addJs("const adminEmail = '$adminEmail';");
                } elseif ($this->adminEmail) {
                    PageFactory::$pg->addJs("const adminEmail = '$this->adminEmail';");
                }
            }
        }

        // add class to show that list is frozen (even if isEnlistAdmin):
        if ($this->deadlineExpired || ($this->deadlineExpired === null)) {
            $class .= ' pfy-enlist-expired';
        }

        if (!$this->title && !$this->isEnlistAdmin) {
            $this->titleClass = ' pfy-empty-title';
        }

        $this->renderInfoButton();

        $headButtons = $this->renderSendMailToAllButton();

        $html = $this->renderTable();
        $attrib = $this->directlyReservePossible ? ' data-directreserve="true"' : '';

        $html = <<<EOT
<div id='$id' class='$class' data-setname="$this->datasetName"$attrib>
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
        $this->presetOptionsMode = !($options['output'] ?? true);
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
        $this->prepStaticOption('directlyToReserve');
        $this->prepStaticOption('editable');
        $deadlineStr = $this->prepStaticOption('deadline');
        $deadline = false;
        if ($deadlineStr) {
            $deadlineStr = intlDateTime($deadlineStr, IntlDateFormatter::RELATIVE_LONG, false);
            $title0 = str_replace('%deadline%', $deadlineStr, $title);
            $title = translateDateTimes($title0);
        }

        $this->title = $title;

        if (!($this->datasetName = ($this->options['listName'] ?? false))) {
            if ($title0 && (self::$_title === null)) {
                $this->datasetName = $this->inx . '_' . translateToFilename(strip_tags($title0), false);
            } else {
                $this->datasetName = $this->inx . "_List";
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

        // determine whether list is past deadline:
        if ($deadline) {
            $this->deadlineExpired = ($deadline < time());
        }
        $this->nTotalSlots = $this->nSlots + $this->nReserveSlots;

        if ($this->freezeTime) {
            $this->freezeTime = $this->freezeTime * 3600; // freezeTime is in hours
        }
    } // parseOptions


    /**
     * @return string
     * @throws \Exception
     */
    private function renderTable()
    {
        list($data, $tableHeaders, $colClasses, $rowClasses, $rowIds) = $this->prepareTableData();

        $tableClass = $this->customFields ? ' pfy-enlist-custom-fields' : '';
        $tableOptions = [
            'tableClass' => "pfy-enlist-table$tableClass",
            'tableHeaders' => $tableHeaders,
            'minRows' => $this->nTotalSlots,
            'announceEmptyTable' => false,
            'dataReference' => true,
            'colClasses' => $colClasses,
            'rowClasses' => $rowClasses,
            'rowIds' => $rowIds,
            'unknownValue' => '&nbsp;',
            'placeholderForUndefined' => '',
        ];
        $dt = new DataTable($data, $tableOptions);

        $html = $dt->render();

        return $html;
    } // renderTable


    /**
     * @return array
     * @throws \Exception
     */
    private function prepareTableData(): array
    {
        $rowIds = [];
        $this->tableData = $this->dataset;
        // obtain current count of entries:
        $data = &$this->tableData;
        $this->nEntries = $this->countEntries();

        $tableHeaders = [];
        $tableHeaders['#'] = '#';
        $tableHeaders['&nbsp;'] = '&nbsp;';
        $tableHeaders['Name'] = 'Name';

        // custom fields:
        foreach ($this->customFields as $key => $value) {
            if ($value['options']??false) {
                if ($value['splitOutput']??false) {
                    foreach ($value['options'] as $val => $label) {
                        if ($val || $label) {
                            $tableHeaders["$key.$val"] = $val;
                        }
                    }
                } else {
                    $tableHeaders[$key] = $key;
                }

            } else {
                $tableHeaders[$key] = $key;
            }

        }
        $tableHeaders['&nbsp; '] = '&nbsp; ';

        // fix data: remove email, _time, directlyToReserve; obfuscate elemKey, add email to name if admin:
        foreach ($data as $k => $rec) {
            if (!is_int($k)) {
                unset($data[$k]);
            } elseif ($this->isEnlistAdmin && ($email = ($data[$k]['Email']??false))) {
                $data[$k]['Name'] .= " <span class='pfy-enlist-email'><a href='mailto:$email'>$email</a></span>\n";
            }
            if ($data[$k]['_elemKey']??false) {
                $rowIds[$k] = $this->obfuscateKey($data[$k]['_elemKey']);
            }
            if ($rec['Email']??false) {
                unset($data[$k]['Email']);
            }
            if ($rec['_time']??false) {
                unset($data[$k]['_time']);
            }
            if ($rec['directlyToReserve']??false) {
                unset($data[$k]['directlyToReserve']);
            }
        }

        // determine colClasses:
        $colClasses = ['pfy-enlist-row-num','pfy-enlist-icon pfy-enlist-icon-1','pfy-enlist-name'];
        $i = 0;
        foreach ($this->customFields as $key => $customField) {
            if (isset($customField['options'])) {
                if ($customField['splitOutput']??false) {
                    foreach ($customField['options'] as $val => $label) {
                        if ($val || $label) {
                            $i++;
                            $colClasses[] = "pfy-enlist-custom pfy-enlist-custom-$i pfy-elem_" . translateToClassName("$key-$val");
                        }
                    }
                } else {
                    $i++;
                    $colClasses[] = "pfy-enlist-custom pfy-enlist-custom-$i pfy-elem_".translateToClassName($key);
                }
            } else {
                $i++;
                $colClasses[] = "pfy-enlist-custom pfy-enlist-custom-$i pfy-elem_".translateToClassName($key);
            }
        }
        $colClasses[] = 'pfy-enlist-icon pfy-enlist-icon-2';

        // determine rowClasses:
        $rowClasses = [];
        $addFieldDone = false;
        $freezeTime = $this->freezeTime? time() - intval($this->freezeTime): false;
        for ($i=0; $i<$this->nTotalSlots; $i++) {
            $rec = ($data[$i]??false) ? $data[$i] : [];
            $rowClasses[$i] = '';

            // check whether freezeTime defined and expired:
            if ($freezeTime) {
                $time = strtotime($this->dataset[$i]['_time'] ?? '');
                if ($time && ($time < $freezeTime)) {
                    $rowClasses[$i] = 'pfy-enlist-elem-frozen ';
                    if (!$this->isEnlistAdmin) {
                        // stop here if not admin:
                        $rowClasses[$i] .= ($i >= $this->nSlots)? ' pfy-enlist-reserve': '';
                        continue;
                    }
                }
            }

            // check whether entire list reached deadline:
            if ($this->deadlineExpired) {
                $rowClasses[$i] .= 'pfy-enlist-expired';
                if (!$this->isEnlistAdmin) {
                    // stop here if not admin:
                    $rowClasses[$i] .= ($i >= $this->nSlots) ? ' pfy-enlist-reserve' : '';
                    continue;
                }
            }
            if ($rec['Name'] ?? false) {
                $rowClasses[$i] .= ($this->obfuscate !== true) ? 'pfy-enlist-delete' : 'pfy-enlist-obfuscated';

            } else {
                if (!$addFieldDone) {
                    $addFieldDone = true;
                    $rowClasses[$i] .= 'pfy-enlist-add';
                } else {
                    $rowClasses[$i] .= 'pfy-enlist-empty';
                }
            }
            $rowClasses[$i] .= ($i >= $this->nSlots)? ' pfy-enlist-reserve': '';
        }

        $emptyRow = [];
        foreach ($this->fieldNames as $v) {
            $emptyRow[$v] = '';
        }

        // fill data:
        foreach ($data as $i => $rec) {
            if (!($rec['Name']??false)) {
                $data[$i] = $emptyRow;
            }
        }

        if ($this->obfuscate !== true) {
            $deleteIcon = '<button type="button" title="{{ pfy-enlist-delete-title }}">' . ENLIST_DELETE_ICON . '</button>';
        } else {
            $deleteIcon = '';
        }
        $addIcon    = '<button type="button" title="{{ pfy-enlist-add-title }}">' . ENLIST_ADD_ICON . '</button>';
        for ($i = 0; $i < $this->nTotalSlots; $i++) {
            $rowClass = $rowClasses[$i];
            $row = &$data[$i];
            $row['#'] = $i + 1;
            if (str_contains($rowClass, 'delete')) {
                $row['&nbsp;'] = $deleteIcon;
                $row['&nbsp; '] = $deleteIcon;
            } elseif (str_contains($rowClass, 'add')) {
                $row['&nbsp;'] = $addIcon;
                $row['&nbsp; '] = $addIcon;
            }
        }

        return [$data, $tableHeaders, $colClasses, $rowClasses, $rowIds];
    } // prepareTableData



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
        $nReserveSlots = false;
        $initialRun = false;
        if (!$setName) {
            $setName = $this->datasetName;
            $nTotalSlots = $this->nTotalSlots;
            $nReserveSlots = $this->nReserveSlots;
            $initialRun = true;
        }

        if (!isset($this->datasets[$setName])) {
            // create new dataset:
            $dataset = array_fill(0, $nTotalSlots, []);
            $dataset['title'] = $this->title;
            $dataset['nSlots'] = $nTotalSlots;
            $dataset['nReserveSlots'] = $nReserveSlots;
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
            $this->dataset = $dataset = $this->prepareDataSet($setName);

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

                $normalSlotsFull = true;
                for ($i=0; $i<$this->nSlots; $i++) {
                    if (!isset($dataset[$i]['Name'])) {
                        $normalSlotsFull = false;
                        break;
                    }
                }
                if ($normalSlotsFull) {
                    for ($i=$this->nSlots; $i<$this->nTotalSlots; $i++) {
                        if (isset($dataset[$i]['directlyToReserve'])) {
                            unset($dataset[$i]['directlyToReserve']);
                            $needUpdate = true;
                        }
                    }
                }

                if ($needUpdate) {
                    $this->db->addRec($dataset, recKeyToUse:$setName);
                    $data = $this->db->data();
                    $this->dataset = $data[$setName];
                }

                // check whether there are free reserve slots:
                if ($this->directlyToReserve) {
                    $directlyToReserve = false;
                    for ($i = 0; $i < $this->nSlots; $i++) {
                        if (!($dataset[$i]['Name'] ?? false)) {
                            $directlyToReserve = true;
                            break;
                        }
                    }
                    if ($directlyToReserve) {
                        $directlyToReserve = false;
                        for ($i = $this->nSlots; $i < $nTotalSlots; $i++) {
                            if (!($dataset[$i]['Name'] ?? false)) {
                                $directlyToReserve = true;
                                break;
                            }
                        }
                    }
                    if (!$directlyToReserve) {
                        $this->directlyToReserve = false;
                    }
                }

                if ($this->obfuscate) {
                    for ($i = 0; $i < $this->nSlots; $i++) {
                        if (($dataset[$i]['Name'] ?? false)) {
                            $this->dataset[$i]['Name'] = $this->obfuscateNameInRow($dataset[$i]['Name']);
                        }
                    }
                }
            }
        }
        return $this->dataset;
    } // getDataset


    /**
     * @param string $setName
     * @return array
     */
    private function prepareDataSet(string $setName): array
    {
        $dataset = $this->datasets[$setName];
        return $dataset;
    } // prepareDataSet


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
            // translate freezeTime:
            $s = intlDateTime(time() + $this->freezeTime, IntlDateFormatter::MEDIUM);
            $addHelp = str_replace('%freezetime%', $s, $addHelp);
        } else {
            $addHelp = TransVars::getVariable('pfy-enlist-popup-add-nofreeze-help');
        }
        $delHelp = TransVars::getVariable('pfy-enlist-popup-del-help');
        $modifyHelp = TransVars::getVariable('pfy-enlist-popup-modify-help');
        $popupHelp = "<div class='add'>$addHelp</div><div class='del'>$delHelp</div><div class='modify'>$modifyHelp</div>";
        $formOptions = [
            'class' => 'pfy-form-colored',
            'confirmationText' => '',
            'wrapperClass' => 'pfy-enlist-form-wrapper',
            'formBottom' => $popupHelp,
            'callback' => function($data) { return $this->callback($data); },
        ];
        // minimum required fields:
        $formFields = [
            'Name' => ['label' => '{{ pfy-enlist-name }}:', 'required' => true],
            'Email' => ['label' => '{{ pfy-enlist-email }}:', 'required' => true],
        ];

        // optional custom fields:
        $i = 1;
        foreach ($this->customFields as $fieldName => $rec) {
            // if type missing but options present -> set to checkbox as default:
            if (!($rec['type']??false) && ($rec['options']??false)) {
                $rec['type'] = 'checkbox';
            }
            $rec['class'] = 'pfy-enlist-custom pfy-enlist-custom-'.$i++;
            $rec['class'] .= ' pfy-elem_'.translateToClassName($fieldName);
            $formFields[$fieldName] = $rec;
        }

        // option directlyToReserve:
        if ($this->directlyToReserve) {
            $formFields['directlyToReserve'] = [
                'label' => '{{ pfy-enlist-directly-to-reserve }}',
                'type' => 'checkbox',
                'class' => 'pfy-enlist-directly',
            ];
        }

        // option editable:
        if ($this->editable) {
            $formFields['delete_entry'] = [
                'label' => '{{ pfy-enlist-delete-label }}',
                'type' => 'checkbox',
                'class' => 'pfy-enlist-delete-checkbox',
                'id'    => 'pfy-enlist-delete',
            ];
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
     * @return string
     */
    private function obfuscateNameInRow(mixed $text): string
    {
            $text0 = $text;
            if ($this->obfuscate === true) {
                $text = '*****';
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
        return $text;
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
            if (isset($value['Name'])) {
                $nEntries++;
            }
        });
        return $nEntries;
    } // countEntries


    private function handleUserPreset(): void
    {
        if (self::$userPreset) {
            return;
        }
        self::$userPreset = true;

        $namePreset = self::$session->get('pfy.enlist.name', false);
        $emailPreset = self::$session->get('pfy.enlist.email', false);

        if (!$namePreset && ($user = PageFactory::$user)) {
            $namePreset = $user->firstName() . ' ' . $user->lastName();
            $emailPreset = $user->email();
        }

        if (!$namePreset) {
            return;
        }

        $js = <<<EOT
const userPreset = {
    name: '$namePreset',
    email: '$emailPreset',
};
EOT;
        PageFactory::$pg->addJs($js);
    } // handleUserPreset


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

        if (isset($data['directlyToReserve']) && !$data['directlyToReserve']) {
            unset($data['directlyToReserve']);
        }

        if ($dataset['deadlineExpired']??false) {
            if ($this->isEnlistAdmin) {
                $message = '{{ pfy-enlist-error-deadline-was-expired }}';
            } else {
                mylog("EnList error deadline exeeded: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-deadline-expired }}');
            }
        }

        if ($elemId === '' || $elemId === 'undefined') {
            // new entry:
            self::$session->set('pfy.enlist.name', $data['Name']);
            self::$session->set('pfy.enlist.email', $data['Email']);
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
        if ($exists) {
            $prevElemId = array_keys($exists)[0];
            $email = $data['Email'] ?? '#####';
            $email0 = $dataset[$prevElemId]['Email'];
            if ($email0 === $email) {
                mylog("EnList error Rec exists: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-rec-exists }}');
            }
            if ($elemKey = ($dataset[$prevElemId]['_elemKey']??false)) {
                $data['_elemKey'] = $elemKey;
            }
            $dataset[$prevElemId] = $data;
        } else {
            if (isset($data['delete_entry'])) {
                unset($data['delete_entry']);
            }
            $data['_time'] = date('Y-m-d\TH:i');
            $dataset = $this->modifyDataSet($dataset, 'add', data: $data);
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
     * @param string $alertMsg
     * @param array $data
     * @param string $context
     * @param mixed $setName
     * @return array
     * @throws \Exception
     */
    private function handleExistingEntry(array $dataset, string $elemId, string $alertMsg, array $data, string $context, mixed $setName): array
    {
        $found = array_filter($dataset, function ($e) use ($elemId, $data) {
            if (!is_array($e)) {
                return false;
            } elseif (isset($e['_elemKey'])) {
                return ($e['_elemKey'] ?? '') === $elemId;
            } elseif ($e) {
                return (($e['Name']??1) === ($data['Name']??2)) && (($e['Email']??3) === ($data['Email']??4));
            } else {
                return false;
            }
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
                $alertMsg = '{{ pfy-enlist-del-freeze-time-expired }}';
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
            // catch irregular case: _elemKey missing:
            if ($rec['_elemKey']??false) {
                $data['_elemKey'] = $rec['_elemKey'];
            } else {
                $data['_elemKey'] = $elemId;
            }
            if (isset($data['delete_entry'])) {
                $del = ($data['delete_entry']??false);
                $op = $del ?'delete' : 'modify';
                unset($data['delete_entry']);
            } else {
                $op = 'delete';
            }
            $dataset = $this->modifyDataSet($dataset, $op, $data, elemKey: $elemKey);
            $this->db->addRec($dataset, recKeyToUse: $setName);
            $this->handleNotifyOwner($data, 'del', $setName);
            if ($elemKey !== false) {
                $this->handleNotifyReserve($dataset);
            }
            if ($op === 'modify') {
                $mainMsg = '{{ pfy-enlist-modified }}';
                $logMsg = 'modified';
            } else {
                $mainMsg = '{{ pfy-enlist-deleted }}';
                $logMsg = 'deleted';
            }
            mylog("EnList entry $logMsg: {$data['Name']} {$data['Email']} $context", 'enlist-log.txt');
            if ($alertMsg) {
                $mainMsg = "$alertMsg<br>Admin: $mainMsg";
            }
            reloadAgent(message: $mainMsg);
        }
        return []; // actually always reloads before getting here
    } // handleExistingEntry


    /**
     * @param array $dataset
     * @param array|false $data
     * @param string|false $elemKey
     * @return array
     * @throws \Exception
     */
    private function modifyDataSet(array $dataset, string $op, array|false $data = false, string|false $elemKey = false): array
    {
        $data = $this->fixCustomFields($data);

        $nTotalSlots = $dataset['nSlots'];
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

        if ( $elemKey === false) {
            $op = 'add';
        }
        switch ($op) {
            case 'add':
                $data['_elemKey'] = createHash();
                // find next empty slot (depending on directlyToReserve):
                $i = ($data['directlyToReserve']??false) ? ($nTotalSlots - $nReserveSlots) : 0;
                for (;$i < $nTotalSlots; $i++) {
                    if (!isset($dataset[$i]['Name'])) {
                        $dataset[$i] = $data;
                        break;
                    }
                }
                break;
            case 'modify':
                $elemKey = $data['_elemKey'];
                foreach ($dataset as $key => $rec) {
                    if (($rec['_elemKey']??false) === $elemKey) {
                        $dataset[$key] = $data;
                        break;
                    }
                }
                break;

            default: // case 'delete':
                for ($i=$elemKey; $i<$nTotalSlots-1; $i++) {
                    if (!isset($dataset[$i+1])) {
                        continue;
                    }
                    if ($dataset[$i+1]['directlyToReserve']??false) {
                        break;
                    }
                    $dataset[$i] = $dataset[$i+1];
                }
                $dataset[$i] = [];
        }
        $dataset = array_values($dataset);

        $dataset['title'] = $title;
        $dataset['nSlots'] = $nTotalSlots;
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
        $rec = isset($dataset[$nActiveSlots - 1]['Name']) ? $dataset[$nActiveSlots - 1]: false;
        if ($rec && !($rec['directlyToReserve']??false)) {
            $title = strip_tags($dataset['title']);
            $this->notifyActivatedReserve($rec, $title);
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
        mylog("Newly activated reserve slot notified: {$rec['Name']} {$rec['Email']}", 'enlist-log.txt');
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
        if (($this->options[$key]??null) !== null) {
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


    /**
     * @param array $data
     * @return array
     */
    private function fixCustomFields(array $data): array
    {
        if ($this->customFields) {
            foreach ($this->customFields as $key => $customField) {
                if (is_array($customField['options'] ?? false)) {
                    $o = [];
                    $o['_'] = '';
                    foreach ($customField['options'] as $value => $label) {
                        $receivedVal = $data[$key];
                        if (is_array($receivedVal)) { // -> heckbox and multiselect
                            $recV = in_array($value, $receivedVal);
                            $o[$value] = $recV;
                            if ($recV) {
                                $o['_'] .= $value.',';
                            }
                        } else { // -> radio
                            $recV = ($value === $receivedVal);
                            $o[$value] = $recV;
                            if ($recV) {
                                $o['_'] .= "$value,";
                            }
                        }
                    }
                    $o['_'] = rtrim($o['_'], ',');
                    $data[$key] = $o;
                }
            }
        }
        return $data;
    } // fixCustomFields


} // Enlist