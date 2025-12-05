<?php

namespace PgFactory\PageFactoryElements;

use Kirby\Exception\Exception;
use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\PfyForm;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\fileTime;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\translateToClassName;
use function PgFactory\PageFactory\explodeTrimAssoc;

const ENLIST_INFO_ICON      = 'ⓘ';
const ENLIST_COLLAPSE_ICON  = '⇪';
const ENLIST_MAIL_ICON      = '✉';
const ENLIST_ADD_ICON       = '+';
const ENLIST_MODIFY_ICON    = '✎';
const ENLIST_DELETE_ICON    = '−';
const ENLIST_OBFUSCATED_VALUE = '#####';
const DEFAULT_DATA_PATH     = '~data/enlist/';
define ('DEFAULT_DATA_FILE', str_replace('/', '_', page()->id()) . '.json');
const PFY_FREEZETIMIE_UNIT  = 3600; // => hours
const PERSISTENT_OPTIONS = ['nSlots', 'nReserveSlots', 'title', 'freezeTime', 'deadline', 'class',
    'info', 'placeholder', 'ical', 'description', 'editable', 'directlyToReserve',
    'sendConfirmation', 'notifyOwner', 'notifyActivatedReserve', 'obfuscate', 'admin', 'adminEmail',
    'adminMail', 'schedule', 'rejectRobots'];
    // -> thus excluded: 'file', 'id'


class Enlist
{
    private static $initialized = false;
    private static int $enlistWidgetIndex = 0;
    private int|string $widgetInx;
    private array $options;
    private static array $persistentOptions = [];
    private static array $persistentCustomFields = [];
    private $title;
    private $nSlots = 0;
    private $nReserveSlots = 0;
    private $nTotalSlots = 0;

    public object|false $db = false;
    private array $widgetDescr = []; // data record containing widgetSlots ('slots') and options
    private string $widgetKey;
    private array $widgetSlots = []; // received slots data
    private bool $directlyReservePossible = false;
    private bool $deadlineExpired = false;
    private bool $isEnlistAdmin = false;
    private string $titleClass = '';
    private bool $hasVisibleCustomFields = false;
    private array $customFields = [];
    private array $customFormFields = [];
    private static $_file = null;
    private string|null $info0;
    private string|null $info;
    private string|null $placeholder;
    private float|bool|null $freezeTime = false;
    private string|bool|null $obfuscate;
    private mixed $admin = false;
    private bool $directlyToReserve;
    private string|null $class;
    private bool $editable = false;
    private array|false $events = false;
    private static bool $userPreset = false;
    private array $tableHeaders = [];
    private array $colClasses = [];
    private array $rowClasses = [];
    private array $rowIds = [];
    private static string $enlistFormHtml = '';
    private mixed $event;

    /**
     * @param $options
     * @param $customFields
     * @throws Exception
     */
    public function __construct($options, $customFields)
    {
        $this->parseOptions($options, $customFields);

        if (!self::$initialized) {
            self::$initialized = true;
            Assets::addAssets('FORMS');
            Assets::addAssets('ENLIST');
            Assets::addAssets('POPUPS');

            self::$enlistFormHtml = $this->renderEnlistForm();

            $adminEmail = $this->options['adminEmail'] ?: PageFactory::$webmasterEmail;
            Page::addJs("const adminEmail = '$adminEmail';");
            Page::applyRobotsAttrib();

            $this->checkCollapseRequest();
        } // initialize

    } // __construct


    /**
     * @return string
     */
    public function render(): string
    {
        $html = self::$enlistFormHtml;
        self::$enlistFormHtml = '';
        if ($this->events) {
            $html .= $this->renderByEvents();
            if (is_array($html)) {
                return $html[0];
            }
        } else {
            $html .= $this->renderEnlistWidget();
        }

        $this->propagateUsernameToBrowser(); // if user enlisted before, preset that name&email in further cases

        return $html;
    } // render


    /**
     * @return mixed
     */
    private function renderByEvents(): mixed
    {
        $html = '';
        foreach ($this->events as $event) {
            if (str_starts_with($event['eventBanner'], '<h2>Template-Variables')) {
                return [$event['eventBanner']]; // special case: provide help about available variables
            }
            if ($this->options['title']) {
                $title = str_replace('%eventBanner%', $event['eventBanner'], $this->options['title']);
            } else {
                $title = $event['eventBanner'];
            }
            $this->title = trim(str_replace("\n", ' ', strip_tags($title)));
            $this->widgetKey = str_replace('T00:00', '', $event['start']);

            $this->initData();

            if ($event['info']??false) {
                $this->info = $event['info'];
            } else {
                $this->info = $this->info0;
            }
            $this->nSlots = intval($event['nSlots']?? ($this->nSlots ?: 1));
            $this->nReserveSlots = intval($event['nReserveSlots']?? ($this->nReserveSlots ?: 0));
            $this->nTotalSlots = $this->nSlots + $this->nReserveSlots;
            $this->directlyReservePossible = $this->directlyToReserve;
            $html .= $this->renderEnlistWidget();
        }
        return $html;
    } // renderByEvents


    /**
     * @return string
     */
    private function renderEnlistWidget(): string
    {
        $widgetInxCls = translateToClassName($this->widgetKey);
        $id = ($this->options['id'] ?? false) ?: "pfy-enlist-wrapper-$widgetInxCls";
        $class = rtrim("pfy-enlist-wrapper pfy-enlist-$widgetInxCls " . $this->class??'');
        if ($this->isEnlistAdmin) {
            $class .= ' pfy-enlist-admin';
        }

        // add class to show that list is frozen (even if isEnlistAdmin):
        if ($this->deadlineExpired || ($this->deadlineExpired === null)) {
            $class .= ' pfy-enlist-expired';
        }

        if (!$this->title && !$this->isEnlistAdmin) {
            $this->titleClass = ' pfy-empty-title';
        }

        $headButtons = $this->renderEnlistButtons();

        $html = $this->renderEnlistWidgetContent();
        
        $attrib = $this->directlyReservePossible ? ' data-directreserve="true"' : '';
        if ($this->placeholder) {
            $attrib .= " data-placeholder='$this->placeholder'";
        }

        if ($this->nTotalSlots === 1) {
            $class .= ' pfy-enlist-hide-num';
        }
        $title = $this->widgetDescr['title'];
        if ($this->deadlineExpired) {
            $title .= ' {{ pfy-enlist-dealine-past }}';
        }

        $html = <<<EOT
<div id='$id' class='$class' data-widget-key="$this->widgetKey"$attrib>
<div class='pfy-enlist-title$this->titleClass'><div class="pfy-enlist-title-inner">$title</div>$headButtons</div>
$html
</div>
EOT;
        return $html;
    } // renderEnlistWidget


    /**
     * @return string
     * @throws \Exception
     */
    private function renderEnlistWidgetContent()
    {
        $data = $this->prepareTableData();

        $tableClass = $this->customFields ? ' pfy-enlist-custom-fields' : '';

        $tableOptions = [
            'tableClass' => "pfy-enlist-table$tableClass",
            'headers' => $this->tableHeaders,
            'minRows' => $this->nTotalSlots,
            'announceEmptyTable' => false,
            'dataReference' => true,
            'colClasses' => $this->colClasses,
            'rowClasses' => $this->rowClasses,
            'rowIds' => $this->rowIds,
            'unknownValue' => '&nbsp;',
            'placeholderForUndefined' => '',
        ];
        if (($this->options['tableOptions']??false) && is_array($this->options['tableOptions'])) {
            foreach ($this->options['tableOptions'] as $key => $value) {
                $tableOptions[$key] = $value;
            }
        }
        $dt = new DataTable($data, $tableOptions);

        $html = $dt->render();

        return $html;
    } // renderEnlistWidgetContent


    /**
     * @return array
     */
    private function prepareTableData(): array
    {
        $this->tableHeaders = $this->prepareTableHeaders();

        $this->colClasses = $this->determineColClasses();

        $this->rowClasses = $this->determineRowClasses();

        return $this->doPrepareTableData();
    } // prepareTableData


    /**
     * @return array
     */
    private function doPrepareTableData(): array
    {
        $emptyRow = $this->prepareEmptyRow();
        list($deleteIcon, $addIcon) = $this->prepareIcons();

        $slots = $this->db->getEnlistSlots($this->widgetKey);

        // check and fix number of slots:
        $n = sizeof($slots);
        if ($n > $this->nTotalSlots) {
            $slots = array_slice($slots, 0, $this->nTotalSlots);
            $this->db->updateWidgetSlots($this->widgetKey, $slots);
        } elseif ($n < $this->nTotalSlots) {
            for ($i=$n; $i < $this->nTotalSlots; $i++) {
                $slots[] = [];
            }
            $this->db->updateWidgetSlots($this->widgetKey, $slots);
        }

        // create new array just containing data to be rendered:
        $out = [];
        foreach ($slots as $i => $row) {
            $rec = $emptyRow;
            $rowClass = $this->rowClasses[$i];
            if (($row['Name'] ?? false)) {
                if ($this->obfuscate) {
                    $rec['Name'] = $this->obfuscateSlot($row);
                } else {
                    $rec['Name'] = "<span class='pfy-enlist-name'>{$row['Name']}</span>";
                    if ($this->isEnlistAdmin) {
                        $rec['Name'] .= " <span class='pfy-enlist-email'><a href='mailto:{$row['Email']}'>{$row['Email']}</a></span>";
                    }
                }
            }
            $rec['num'] = $i + 1;
            if (str_contains($rowClass, 'delete') ||
                str_contains($rowClass, 'modify')) {
                $rec['icon-1'] = $deleteIcon;
                $rec['icon-2'] = $deleteIcon;
            } elseif (str_contains($rowClass, 'add')) {
                $rec['icon-1'] = $addIcon;
                $rec['icon-2'] = $addIcon;
            }

            // fill custom fields:
            if ($this->hasVisibleCustomFields) {
                foreach ($row as $k => $v) {
                    if (str_contains('Name,Email,_time', $k)) {
                        continue;
                    }
                    if (($this->customFields[$k] ?? false) && ($this->customFields[$k]['hidden'] ?? false)) {
                        continue;
                    }
                    if (is_array($v)) {
                        foreach ($v as $v2) {
                            $k2 = "$k.$v2";
                            $rec[$k2] = "X";
                        }

                    } else {
                        $rec[$k] = $v;
                    }
                }
            }
            $out[$i] = $rec;
        }
        return $out;
    } // doPrepareTableData


    /**
     * @param string $value
     * @return string
     */
    private function obfuscateSlot(array $row): string
    {
        $value = $row['Name'];
        if ($this->obfuscate === true) {
            $value = ENLIST_OBFUSCATED_VALUE;

        } elseif ($this->obfuscate === 'initials') {
            $ar = array_map(function ($e) {
                return $e[0]??'';
                }, explode(' ', $value));
            $value = implode(' ', $ar);

        } else {
            $value = $this->obfuscate;
        }
        if ($this->isEnlistAdmin) {
            $mail = "<span class='pfy-enlist-email'><a href='mailto:{$row['Email']}'>{$row['Email']}</a></span>";
            $value .= " <span class='pfy-enlist-admin-view'>[<span class='pfy-enlist-name'>{$row['Name']}</span> $mail]</span>";
        }
        return $value;
    } // obfuscateSlot



    /**
     * @return string
     * @throws \Exception
     */
    private function renderEnlistButtons(): string
    {
        $headButtons = '';
        $headButtons .= $this->renderInfoButton();
        $headButtons .= $this->renderICal();
        if ($this->isEnlistAdmin) {
            $headButtons .= $this->renderCollapseEmptySlotsButton();
            $headButtons .= $this->renderSendMailToAllButton();
        }
        $headButtons = <<<EOT

    <div class='pfy-enlist-head-buttons-wrapper'>
$headButtons
    </div>
EOT;
        return $headButtons;
    } // renderEnlistButtons


    /**
     * @return string
     */
    private function renderSendMailToAllButton(): string
    {
        $mailIcon = ENLIST_MAIL_ICON;
        $headButtons = <<<EOT
        <button class="pfy-enlist-sendmail-button pfy-button pfy-button-lean" type="button" title="{{ pfy-enlist-sendmail-button-title }}"><span>$mailIcon</span></button>
EOT;
        return $headButtons;
    } // renderSendMailToAllButton


    /**
     * @return string
     */
    private function renderInfoButton(): string
    {
        $info = $this->info??'';
        if ($info) {
            if ((str_contains($info, '%')) && ($event = ($this->event??false))) {
                foreach ($event as $key => $value) {
                    $info = str_replace("%$key%", $value, $info);
                }
            }
            $info = "<div tabindex='0' class='pfy-enlist-tooltip-anker'>" . ENLIST_INFO_ICON .
                "</div><div class='pfy-enlist-tooltip-content'>$info</div>";
        }
        return $info;
    } // renderInfoButton


    /**
     * @return string
     */
    private function renderCollapseEmptySlotsButton(): string
    {
        if (!$this->hasCollapsableSlots()) {
            return '';
        }
        $icon = ENLIST_COLLAPSE_ICON;
        $html = <<<EOT
        <button class="pfy-enlist-collapse-button pfy-button pfy-button-lean" type="button" title="{{ pfy-enlist-collapse-button-title }}"><span>$icon</span></button>
EOT;
        return $html;
    } // renderCollapseEmptySlotsButton


    /**
     * @return bool
     */
    private function hasCollapsableSlots(): bool
    {
        if ($this->nReserveSlots === 0) {
            return false;
        }

        // 1) first reserve slot must be filled:
        $isCollapsable = !!($this->widgetSlots[$this->nSlots]['Name'] ?? false);
        // 2) last regular slot must be empty:
        $isCollapsable = $isCollapsable && !($this->widgetSlots[$this->nSlots - 1]['Name'] ?? false);

        return $isCollapsable;
    } // hasCollapsableSlots


    /**
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function renderEnlistForm(): string
    {
        if ($this->freezeTime) {
            $addHelp = TransVars::getVariable('pfy-enlist-popup-add-help');
            // translate freezeTime:
            $s = intlDateFormat('RELATIVE_MEDIUM,SHORT', time() + $this->freezeTime);
            $addHelp = str_replace('%freezetime%', $s, $addHelp);
        } else {
            $addHelp = TransVars::getVariable('pfy-enlist-popup-add-nofreeze-help');
        }
        $delHelp = TransVars::getVariable('pfy-enlist-popup-del-help');
        $modifyHelp = TransVars::getVariable('pfy-enlist-popup-modify-help');
        $popupHelp = "<div class='pfy-add'>$addHelp</div><div class='pfy-del'>$delHelp</div><div class='pfy-modify'>$modifyHelp</div>";
        $wrapperClass = 'pfy-enlist-form-wrapper';
        if ($this->hasVisibleCustomFields) {
            $wrapperClass .= ' pfy-enlist-has-custom-fields';
        }
        if ($this->obfuscate) {
            $wrapperClass .= ' pfy-enlist-obfuscated';
        }

        $formOptions = [
            'file' => true,
            'class' => 'pfy-form-colored',
            'confirmationText' => '',
            'wrapperClass' => $wrapperClass,
            'formBottom' => $popupHelp,
            'dataReceivedCallback' => function($data) {
                return (new EnlistCallbackHandler)->callback($this, $data);
            },
        ];
        // minimum required fields:
        $formFields = [
            'Name' => ['label' => '{{ pfy-enlist-name }}:', 'required' => true],
            'Email' => ['label' => '{{ pfy-enlist-email }}:', 'required' => true, 'info' => '{{ pfy-enlist-email-info }}'],
        ];

        // optional custom fields:
        $i = 1;
        foreach ($this->customFormFields as $fieldName => $rec) {
            // if type missing but options present -> set to checkbox as default:
            if (!($rec['type']??false) && ($rec['options']??false)) {
                $rec['type'] = 'checkbox';
            }
            $rec['class'] = 'pfy-enlist-custom pfy-enlist-custom-'.$i++;
            $rec['class'] .= ' pfy-elem_'.translateToClassName($fieldName);
            $formFields[$fieldName] = $rec;
        }

        // option directlyToReserve:
        $formFields['directlyToReserve'] = [
            'label' => '{{ pfy-enlist-directly-to-reserve }}',
            'type' => 'checkbox',
            'class' => 'pfy-enlist-directly',
        ];

        // option editable:
        if ($this->editable) {
            $formFields['delete_entry'] = [
                'label' => '{{ pfy-enlist-delete-label }}',
                'type'  => 'checkbox',
                'class' => 'pfy-enlist-delete-checkbox',
                'id'    => 'pfy-enlist-delete',
            ];
        }

        // standard form end fields:
        $formFields['cancel']           = [];
        $formFields['submit']           = [];
        $formFields['mode']             = ['type' => 'hidden'];
        $formFields['widgetKey']        = ['type' => 'hidden'];
        $formFields['widgetTitle']      = ['type' => 'hidden'];

        $form = new PfyForm($formOptions);
        $html = $form->renderForm($formFields);
        if (str_contains($html, 'class="error"')) {
            $jq = "Enlist.openPopup()";
            Page::addJsReady($jq);
        }
        $html = "\n\n<div id='pfy-enlist-form'>\n$html</div>\n<!-- /pfy-enlist-form -->\n\n";
        return $html;
    } // renderEnlistForm


    /**
     * @return void
     */
    private function propagateUsernameToBrowser(): void
    {
        if (self::$userPreset) {
            return;
        }
        self::$userPreset = true;

        $namePreset = Utils::getSessionVar('pfy.enlist.name', false);
        $emailPreset = Utils::getSessionVar('pfy.enlist.email', false);

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
        Page::addJs($js);
    } // propagateUsernameToBrowser


    /**
     * @return string
     * @throws \Exception
     */
    private function renderICal(): string
    {
        if (!($this->event??false)) {
            return '';
        }
        $rec    = $this->event;
        $icalOptions = $this->options['ical'] ?? false;
        if (!$icalOptions) {
            return '';
        } elseif (!is_array($icalOptions)) {
            $title = $icalOptions;
            $icalOptions = [
                'title' => $title,
                'shortName' => $title,
            ];
        }
        $icalOptions['linkText'] = '%icon%';
        $icalOptions['tooltip'] = '{{ pfy-enlist-ical-tooltip }}';
        $icalOptions['prefix'] = $icalOptions['shortName']??'';
        $icalOptions['asButton'] = true;
        $iCal = new Ical([$rec], $icalOptions);

        $tTargetFile = $iCal->getTargetFileTime();
        $dataFile = $this->db->getFile();
        $tDataFile = fileTime($dataFile);
        if ($tDataFile > $tTargetFile) {
            $iCal->saveToFile();
        }

        return $iCal->renderIcsLink();
    } // renderICal


    /**
     * @return string[]
     */
    private function prepareTableHeaders(): array
    {
        $tableHeaders = [
            'num' => '#',
            'icon-1' => '',
            'Name' => 'Name'
        ];

        // custom fields:
        foreach ($this->customFields as $key => $value) {
            if ($value['hidden'] ?? false) {
                continue;
            } elseif ($value['options'] ?? false) {
                if ($value['splitOutput'] ?? false) {
                    foreach ($value['options'] as $val => $label) {
                        if ($val || $label) {
                            $tableHeaders["$key.$val"] = $val;
                        }
                    }
                } else {
                    $tableHeaders[$key] = $key;
                }

            } elseif ($value['label'] ?? false) {
                $tableHeaders[$key] = $value['label'];
            } else {
                $tableHeaders[$key] = $key;
            }

        }
        $tableHeaders['icon-2'] = '';
        return $tableHeaders;
    } // prepareTableHeaders


    /**
     * @return string[]
     */
    private function determineColClasses(): array
    {
        $colClasses = ['pfy-enlist-row-num', 'pfy-enlist-icon pfy-enlist-icon-1', 'pfy-enlist-name'];
        $i = 0;
        foreach ($this->customFields as $key => $customField) {
            if (isset($customField['options'])) {
                if ($customField['splitOutput'] ?? false) {
                    foreach ($customField['options'] as $val => $label) {
                        if ($val || $label) {
                            $i++;
                            $colClasses[] = "pfy-enlist-custom pfy-enlist-custom-$i pfy-elem_" . translateToClassName("$key-$val");
                        }
                    }
                } else {
                    $i++;
                    $colClasses[] = "pfy-enlist-custom pfy-enlist-custom-$i pfy-elem_" . translateToClassName($key);
                }
            } else {
                $i++;
                $colClasses[] = "pfy-enlist-custom pfy-enlist-custom-$i pfy-elem_" . translateToClassName($key);
            }
        }
        $colClasses[] = 'pfy-enlist-icon pfy-enlist-icon-2';
        return $colClasses;
    } // determineColClasses


    /**
     * @return array
     */
    private function determineRowClasses(): array
    {
        $slots = $this->widgetSlots;
        $rowClasses = [];
        $addFieldDone = false;
        $currFreezeTime = $this->freezeTime ? time() - ($this->freezeTime * PFY_FREEZETIMIE_UNIT) : false;
        for ($i = 0; $i < $this->nTotalSlots; $i++) {
            $slot = ($slots[$i] ?? false) ? $slots[$i] : [];
            $rowClasses[$i] = '';

            // mark reserve slots:
            $rowClasses[$i] .= ($i >= $this->nSlots) ? ' pfy-enlist-reserve' : '';

            // check whether freezeTime defined and expired:
            if ($this->checkSlotFreezeTime($currFreezeTime, $slot)) {
                if ($this->isEnlistAdmin) {
                    $rowClasses[$i] .= ' pfy-enlist-frozen-while-admin';
                } else {
                    continue;
                }
            }

            // check whether entire list reached deadline:
            if ($this->deadlineExpired) {
                $rowClasses[$i] .= ' pfy-enlist-expired';
                if (!$this->isEnlistAdmin) {
                    // stop here if not admin:
                    $rowClasses[$i] .= ($i >= $this->nSlots) ? ' pfy-enlist-reserve' : '';
                    continue;
                }
            }
            if ($slot['Name'] ?? false) {
                if ($this->editable && $this->hasVisibleCustomFields) {
                    $rowClasses[$i] .= ' pfy-enlist-modify';
                } else {
                    $rowClasses[$i] .= ($this->obfuscate !== true) ? ' pfy-enlist-delete' : ' pfy-enlist-delete pfy-enlist-obfuscated';
                }

            } else {
                if (!$addFieldDone) {
                    $addFieldDone = true;
                    $rowClasses[$i] .= ' pfy-enlist-add';
                } else {
                    $rowClasses[$i] .= ' pfy-enlist-empty';
                }
            }
            $rowClasses[$i] .= ($i >= $this->nSlots) ? ' pfy-enlist-reserve' : '';
        }
        return $rowClasses;
    } // determineRowClasses


    /**
     * @return array
     */
    private function prepareEmptyRow(): array
    {
        $emptyRow = [];
        foreach ($this->tableHeaders as $k => $v) {
            $emptyRow[$k] = '';
        }
        return $emptyRow;
    } // prepareEmptyRow


    /**
     * @return string[]
     */
    private function prepareIcons(): array
    {
        if ($this->obfuscate && !$this->isEnlistAdmin) {
            $deleteIcon = '';

        } elseif ($this->editable && $this->hasVisibleCustomFields && !$this->obfuscate) {
            $deleteIcon = '<button type="button" title="{{ pfy-enlist-modify-title }}">' . ENLIST_MODIFY_ICON . '</button>';

        } else {
            $deleteIcon = '<button type="button" title="{{ pfy-enlist-delete-title }}">' . ENLIST_DELETE_ICON . '</button>';
        }
        $addIcon = '<button type="button" title="{{ pfy-enlist-add-title }}">' . ENLIST_ADD_ICON . '</button>';
        return array($deleteIcon, $addIcon);
    } // prepareIcons


    /**
     * @param int $currFreezeTime
     * @param array $slot
     * @return bool
     */
    private function checkSlotFreezeTime(int $currFreezeTime, array $slot): bool
    {
        if (!$currFreezeTime) {
            return false;
        }
        $storeTime = strtotime($slot['_time'] ?? '');
        return ($storeTime && ($storeTime < $currFreezeTime));
    } // checkSlotFreezeTime


    /**
     * @return void
     */
    private function openDb(): void
    {
        if (!$this->db) {
            $this->db = new EnlistData($this->options);
        }
        EnlistComm::setDb($this->db);
    } // openDb


    /**
     * @return void
     */
    private function initData(): void
    {
        $widgetOptions = [
            'widgetKey' => $this->widgetKey,
            'title' => $this->title,
            'nSlots' => $this->nSlots,
            'nReserveSlots' => $this->nReserveSlots,
            'nTotalSlots' => $this->nTotalSlots,
            'freezeTime' => $this->freezeTime,
            'directlyToReserve' => $this->directlyToReserve,
//ToDo: custom fields
        ];
        $this->widgetDescr = $this->db->prepareWidgetDescr($this->widgetKey, $widgetOptions);
        $this->widgetSlots = $this->widgetDescr['slots'];
    } // initData


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    } // getOptions


    /**
     * @return string
     * @throws Exception
     */
    private function determineDataFile(): string
    {
        if ($this->options['file']??false) {
            $file = $this->options['file'];
            $file .= (!preg_match('/\.\w{1,6}$/', $file)) ? '.json' : '';
            if (!str_contains($file, '~')) {
                $file = "~data/$file";
            }
            $file = resolvePath($file);
            if (self::$_file) {
                if (self::$_file !== $file) {
                    throw new Exception("Error: all enlist widgets in a page must use same data-file.");
                }
            } else {
                self::$_file = $file;
            }
        } elseif (!self::$_file) {
            self::$_file = resolvePath(DEFAULT_DATA_PATH . DEFAULT_DATA_FILE);
        } else {
            return resolvePath(DEFAULT_DATA_PATH . DEFAULT_DATA_FILE);
        }
        return self::$_file;
    } // determineDataFile


    /**
     * @param array $options
     * @param array $customFields
     * @return void
     */
    private function handlePersistentOptions(array &$options, array &$customFields)
    {
        if ($options['setDefaults']) {
            foreach (PERSISTENT_OPTIONS as $key) {
                if (($options[$key]??null) !== null) {
                    self::$persistentOptions[$key] = $options[$key];
                }
            }

            foreach ($customFields as $key => $customField) {
                self::$persistentCustomFields[$key] = $customField;
            }

        } else {
            if (self::$persistentOptions) {
                foreach (self::$persistentOptions as $key => $value) {
                    if (($options[$key]??null) === null) {
                        $options[$key] = $value;
                    }
                }
            }
            if (self::$persistentCustomFields) {
                foreach (self::$persistentCustomFields as $key => $customField) {
                    if (!isset($customFields[$key])) {
                        $customFields[$key] = $customField;
                    }
                }
            }
        }
        foreach (PERSISTENT_OPTIONS as $key) {
            if (!isset($options[$key])) {
                $options[$key] = null;
            }
        };
    } // handlePersistentOptions()


    /**
     * @return array|false
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getScheduleEvents(): array|false
    {
        if (!($eventOptions = $this->options['schedule']??false)) {
            return false;
        }

        if (!($src = ($eventOptions['src']??false))) {
            $src = $eventOptions['file']??false;
        }
        $eventOptions['file'] = $src;
        $eventOptions['macroName'] = $this->options['macroName'];

        $sched = new Events($eventOptions);
        $count = $eventOptions['count']??false;
        $nextEvents = $sched->getNextEvents(count: $count);
        return $nextEvents;
    } // getScheduleEvents


    /**
     * @return void
     */
    private function checkCollapseRequest(): void
    {
        // handle ?collapse-enlist
        if (isset($_GET['collapse-enlist'])) {
            $widgetInx = $_GET['collapse-enlist'];
            $widgetTitle = urldecode($_GET['widgetTitle']??'');
            $this->db->collapseEmptySlots($widgetInx, $widgetTitle);
            reloadAgent();
        }
    } // checkCollapseRequest


    /**
     * @param array $options
     * @param array $customFields
     * @return void
     * @throws Exception
     */
    private function parseOptions(array $options, array $customFields): void
    {
        if ($options['description'] ?? false) {
            $options['info'] = $options['description'];
        }

        $this->handlePersistentOptions($options, $customFields);

        $options['tooltip'] = '{{ pfy-enlist-ical-tooltip }}';
        $this->options = $options;
        $options = &$this->options;

        $options['file'] = $this->determineDataFile();

        $this->nSlots = $options['nSlots'];
        $this->nReserveSlots = $options['nReserveSlots'];
        $this->info = $options['info'];
        $this->placeholder = $options['placeholder'];
        $this->info0 = $this->info;
        $this->freezeTime = $options['freezeTime'];
        $this->obfuscate = $options['obfuscate'];
        $this->admin = $options['admin'];
        $this->class = $options['class'];
        $this->directlyToReserve = $options['directlyToReserve'];
        $this->editable = $options['editable'];

        $this->nTotalSlots = $this->nSlots + $this->nReserveSlots;

        // --- process schedule options:
        $this->events = $this->getScheduleEvents();

        // --- process custom fields:
        $hasVisibleCustomFields = false;
        if ($customFields) {
            // Replace - with _ in all keys;
            $customFields1 = $customFields;
            $customFields = [];
            foreach ($customFields1 as $key => $rec) {
                $key = str_replace('-', '_', $key);
                $key = preg_replace('/\W/', '', $key);
                if (!($rec['hidden']??false)) {
                    $customFields[$key] = $rec;
                    $hasVisibleCustomFields = true;
                }
            }

            $nCustFields = sizeof($customFields);
            foreach ($customFields1 as $key => $customField) {
                // special case 'checkbox options':
                if ((($customField['type'] ?? 'text') === 'checkbox') ||
                    ($customField['options'] ?? false)) {
                    $customField['type'] = 'checkbox';
                    $customOptions = explodeTrimAssoc(',', $customField['options'] ?? '');
                    $customFields[$key]['options'] = $customOptions;
                    if ($customField['splitOutput']??false) {
                        $nCustFields += sizeof($customOptions) - 1;
                    }
                }
            }
            $this->hasVisibleCustomFields = $hasVisibleCustomFields;
            $this->customFields = $customFields;
            $this->customFormFields = $customFields1;
        }


        $options = [
                'nSlots' => $this->nSlots,
                'nReserveSlots' => $this->nReserveSlots,
                'nTotalSlots' => $this->nTotalSlots,
                'isEnlistAdmin' => $this->isEnlistAdmin,
            ] + $options;

        if ($options['emailFromName']??false) {
            EnlistComm::setEmailFromName($options['emailFromName']);
        }

        // prepare database:
        $this->openDb();
        if ($this->events) {
            foreach ($this->events as $event) {
                self::$enlistWidgetIndex++;
                $this->widgetInx = self::$enlistWidgetIndex;
                $this->event = $event;

                $this->parseWidgetOptions();
            }
        } else {
            // Note: if list is based on scheduled events, widgetInx and title are defined by the event itself
            self::$enlistWidgetIndex++;
            $this->widgetInx = self::$enlistWidgetIndex;
            $options['title'] = ($options['title']??false) ?: '';

            $this->parseWidgetOptions();
            $this->widgetKey = $this->title ?: "Enlist-$this->widgetInx";
            $this->initData();
            $this->directlyReservePossible = $this->directlyToReserve;
        }

    } // parseOptions


    /**
     * @return void
     */
    private function parseWidgetOptions(): void
    {
        $options = &$this->options;
        $title = $options['title'];
        $deadline = $options['deadline'];
        if ($deadline) {
            $deadlineStr = $deadline;
            $deadline = strtotime($deadline);
            $title = str_replace('%deadline%', $deadlineStr, $title);
            if (preg_match('/\d{4}-\d\d-\d\d$/', $deadlineStr)) {
                $deadline += 86400;
            }
            // determine whether list is past deadline:
            $this->deadlineExpired = ($deadline < time());
        }

        $this->title = $options['title'] = $title;
        $this->widgetKey = $title ?: 'Enlist-' > ($this->widgetInx + 1);
        if ($permissionQuery = $this->admin) {
            if ($permissionQuery === true) {
                $permissionQuery = 'localhost|loggedin';
            }
            $this->isEnlistAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$dev);
            if ($this->isEnlistAdmin && ($this->widgetInx === 1)) {
                Page::addBodyTagClass('pfy-enlist-admin');
            }
        }
    } // parseWidgetOptions

} // Enlist