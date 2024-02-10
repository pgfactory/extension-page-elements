<?php

/*
 * Calendar
 *
 */


namespace PgFactory\PageFactoryElements;
use DateTimeZone;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\DataSet;
use PgFactory\PageFactory\Maintenance;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\PfyForm;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\getFile;
use function PgFactory\PageFactory\isAdmin;
use function \PgFactory\PageFactory\resolvePath;
use function \PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\createHash;
use function PgFactory\PageFactory\fileExt;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\base_name;
use function PgFactory\PageFactory\mylog;
use function PgFactory\PageFactory\translateToIdentifier;


class Calendar
{
    private int $inx;
    private array $options;
    private string $fullCalendarOptions;
    private string $initialDate;
    private string $calOptions;
    private string $source;
    private string $class;
    private string $id;
    private array  $fields;
    private string $defaultView;
    private mixed $defaultEventDuration;
    private string $sessDbKey;
    private string $sessCalRecKey;
    private array  $sessCalRec;
    private mixed $categories;
    private string $edPermStr;
    private string $adminPermStr;
    /**
     * @var false|mixed
     */
    private mixed $modifyPermission;
    /**
     * @var false|mixed
     */
    private bool $userCategories;
    /**
     * @var mixed|string
     */
    private mixed $headerLeftButtons;
    /**
     * @var mixed|string
     */
    private mixed $headerRightButtons;
    /**
     * @var false|mixed
     */
    private mixed $freezePast;
    private $businessHours;
    private $visibleHours;

    /**
     * @param array $args
     * @param array $fields
     * @throws \Exception
     */
    public function __construct(array $args, array $fields)
    {
        $this->inx =     $args['inx'];
        $this->fields =  $this->fixCategories($fields);
        $this->options = $args;
        $pageId =        PageFactory::$pageId;

        // get persistent data stored in session rec:
        $this->sessCalRecKey = "pfy.cal.$pageId:$this->inx"; // corresponds to key defined in class Calendar
        $this->sessCalRec    = kirby()->session()->get($this->sessCalRecKey, []);

        $this->parseOptions($args);
        PageFactory::$pg->addAssets('CALENDAR');
        $locale = str_replace('_', '-', PageFactory::$locale);
        PageFactory::$pg->addJs("const locale = '$locale';");
        $timezone = PageFactory::$timezone;
        PageFactory::$pg->addJs("const timezone = '$timezone';");

        $this->checkAndFixDB();
    } // __construct


    /**
     * @return string
     */
    public function render()
    {
        $str = $this->renderForm();

        $lang = PageFactory::$lang;
        $timezone = PageFactory::$timezone;

        $calOptions = <<<EOT
    inx: $this->inx,
    initialView:            '$this->defaultView',
    admin:                  $this->adminPermStr,
    edit:                   $this->edPermStr,
    freezePast:             $this->freezePast,
    modifyPermission:       $this->modifyPermission,
    headerLeftButtons:      '$this->headerLeftButtons',
    headerRightButtons:     '$this->headerRightButtons',
    businessHours:          '$this->businessHours',
    visibleHours:           '$this->visibleHours',
    fullCalendarOptions: {
        locale:             '$lang',
        timeZone:           '$timezone',
        initialView:        '$this->defaultView',
        initialDate:        '$this->initialDate',
$this->fullCalendarOptions
    },
EOT;

        // append the call to invole lzyCalendar:
        $jq = <<<EOT
const calElem = document.querySelector('#pfy-calendar-$this->inx');
if (calElem) {
    let pfyCalendar = new PfyCalendar();
    pfyCalendar.init(calElem, {
$calOptions
    });
}
EOT;
        PageFactory::$pg->addJsReady( $jq );

        $str .= "<div id='$this->id' class='pfy-calendar pfy-calendar-$this->inx $this->class' data-calInx='$this->inx' data-datasrc='DATA-REF'>CAL PLACEHOLDER</div>\n";

        // save sessCalRec in session for use in AjaxHandler:
        kirby()->session()->set($this->sessCalRecKey, $this->sessCalRec);
        kirby()->session()->set($this->sessDbKey, $this->source);

        return $str;
    } // render


    /**
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function renderForm(): string
    {
        $formOptions = [
            'file' => $this->source,
            'class' => 'pfy-form-colored',
            'confirmationText' => '{{ pfy-cal-stored-confirmation }}',
            'formBottom' => '',
            'editMode' => 'popup',
            'permission' => true,
            'showDirectFeedback' => false,
            'init' => false,
            'callback' => function ($dataRec) {
                return $this->formCallback($dataRec);
            },
        ];

        if (!($formFields = $this->fields)) {
            // if none supplied, use default fields:
            $formFields = [
                'allday'        => ['type' => 'checkbox', 'label' => '{{ pfy-cal-allday-event }}:', 'class' => 'reversed pfy-cal-allday'],
                'category'      => $this->categories? ['type' => 'dropdown', 'options' => $this->categories] : false,
                'Title'         => ['type' => 'text'],
                'Event'         => ['type' => 'event', 'defaultEventDuration' => $this->defaultEventDuration],
                'Description'   => ['type' => 'textarea'],
            ];
        }

        // 'defaultDuration' is synonym for 'defaultEventDuration':
        if (($formFields['Event']['defaultDuration']??false) !== false) {
            $formFields['Event']['defaultEventDuration'] = $formFields['Event']['defaultDuration'];
        }
        // if event field has no defaultEventDuration defined, use the one from general options:
        if (($formFields['Event']['defaultEventDuration']??false) === false) {
            $formFields['Event']['defaultEventDuration'] = $this->defaultEventDuration;
        }

        // add generic fields, if not defined yet:
        if (!isset($formFields['_delete'])) {
            $formFields['_delete'] = [
                'type' => 'checkbox',
                'label' => '{{ pfy-cal-delete-entry }}',
                'class' => 'pfy-cal-delete-entry',
            ];
        }
           if (!isset($formFields['cancel'])) {
            $formFields['cancel'] = [];
        }
        if (!isset($formFields['submit'])) {
            $formFields['submit'] = [];
        }
        $formFields['creator'] = ['type' => 'hidden', 'value' => PageFactory::$userName];
        $formFields['_creator'] = ['type' => 'bypassed', 'value' => PageFactory::$userName];

        $form = new PfyForm($formOptions);
        $html = $form->renderForm($formFields);

        $html = <<<EOT

<div id="pfy-cal-form-wrapper-$this->inx" class="pfy-cal-form-wrapper pfy-dispno">
$html
</div>

EOT;
        return $html;
    } // renderForm


    /**
     * @param $dataRec
     * @return mixed
     */
    private function formCallback(&$dataRec): mixed
    {
        if ($this->adminPermStr !== 'false') {
            $res = true;
        } elseif ($this->edPermStr !== 'false') {
            $start = strtotime($dataRec['start']??0);
            $end = strtotime($dataRec['end']??0);
            if ($end < time()) {
                $res = [
                    'html' => '{{ pfy-cal-event-in-the-past }}',
                    'continueEval' => false,
                    'showDirectFeedback' => false
                ];

            } elseif ($start < time()) {
                if ($dataRec['_delete']??false) {
                    unset($dataRec['_delete']);
                    $dataRec['end'] = date('Y-m-d H:i');
                    $res = [
                        'html' => '{{ pfy-cal-event-start-in-the-past }}',
                        'continueEval' => true,
                        'showDirectFeedback' => false,
                        'dataRec' => $dataRec,
                    ];
                } else {
                    $res = [
                        'html' => '{{ pfy-cal-event-start-in-the-past }}',
                        'continueEval' => false,
                        'showDirectFeedback' => false,
                    ];
                }

            } else {
                // fix allday event -> add 1 day to end to conform with user logic:
                if ($dataRec['allday']??false) {
                    $dataRec['end'] = date('Y-m-d', $end + 86400);
                }
                $res = true;
            }
        } else {
            $res = [
                'html' => '{{ pfy-cal-insufficient-permission }}',
                'continueEval' => false,
                'showDirectFeedback' => false
            ];
        }

        if ($dataRec['allday']??false) {
            if (strlen($dataRec['start']) > 10) {
                $dataRec['start'] = substr($dataRec['start'], 0, 10);
                $dataRec['end'] = substr($dataRec['end'], 0, 10);
            }
        } else {
            if (strlen($dataRec['start']) < 16) {
                $dataRec['start'] = substr($dataRec['start'], 0, 10).'T09:00';
                $dataRec['end'] = substr($dataRec['end'], 0, 10).'T10:00';
            }
        }

        // check end before start:
        if ($dataRec['start'] > $dataRec['end']) {
            $res = [
                'html' => '{{ pfy-cal-end-before-start }}',
                'continueEval' => false,
                'showDirectFeedback' => false
            ];
        }

        // prevent creator tampering:
        if (($res === true || $res['continueEval']) && !$dataRec['_reckey']) {
            $dataRec['creator'] = $dataRec['_creator'];
            $res = [
                'html' => '',
                'continueEval' => true,
                'showDirectFeedback' => false,
                'dataRec' => $dataRec,
            ];
        }
        return $res;
    } // formCallback


    /**
     * @param array $fields
     * @return array
     */
    private function fixCategories(array $fields):array
    {
        if ($options = ($fields['category']['options']??false)) {
            $opts = explodeTrim(',', $options);
            foreach ($opts as $i => $opt) {
                $name = translateToIdentifier($opt);
                $opts[$i] = "$name:$opt";
            }
            $fields['category']['options'] = implode(',', $opts);
        }
        return $fields;
    } // fixCategories


    /**
     * @param array $args
     * @return void
     */
    private function parseOptions(array $args): void
    {
        $this->source =                 $args['file'];
        $this->id =                     $args['id']?? "pfy-calendar-$this->inx";
        $this->class =                  $args['class']??'';
        $this->defaultEventDuration =   $args['defaultEventDuration']??false;
        $this->categories =             $args['categories']??false;
        $this->headerLeftButtons =      $args['headerLeftButtons']??'prev,today,next';
        $this->headerRightButtons =     $args['headerRightButtons']??'timeGridWeek,dayGridMonth,listYear';
        $this->freezePast =             ($args['freezePast']??true)?'true':'false';
        $this->businessHours =          $args['businessHours']??'08:00-17:00';
        $this->visibleHours =           $args['visibleHours']??'07:00-21:00';
        $this->userCategories =         $args['userCategories']??false;
        $this->fullCalendarOptions =    $args['fullCalendarOptions'];
        $pageId =                       PageFactory::$pageId;
        $this->sessDbKey =              "db:$pageId:$this->inx:file";

        $this->headerRightButtons = str_replace(
            [',week,',',month,',',year,',',list,'],
            [',timeGridWeek,',',dayGridMonth,',',listYear,',',listYear,'], ','.$this->headerRightButtons.',');
        $this->headerRightButtons = trim($this->headerRightButtons, ',');

        if ($eventTemplateFile = ($args['eventTemplate']??false)) {
            $eventTemplateFile = resolvePath($eventTemplateFile);
            $this->sessCalRec['template'] = $eventTemplateFile;
        }

        // Default View:
        if (!($this->defaultView = $this->sessCalRec['mode']??false)) {
            $defaultView =  $args['defaultView'] ?? ' ';
            $this->defaultView = match ($defaultView[0]??'') {
                'y' => 'listYear',
                'm' => 'dayGridMonth',
                default => 'timeGridWeek',
            };
            $this->sessCalRec['mode'] = $this->defaultView;
        }

        $modifyPermission =   $args['modifyPermission']??false;
        if ($modifyPermission === 'self') {
            $user = (PageFactory::$userName?:'anon');
            $this->modifyPermission = "'$user'";
            if ($this->userCategories && str_contains($this->fields['category']['options'], $user)) {
                $user = translateToIdentifier($user) . ':' . $user;
                $this->fields['category']['options'] = $user;
            }

        } elseif ($modifyPermission) {
            $this->modifyPermission = "'$modifyPermission'";

        } else {
            $this->modifyPermission = 'false';
        }
        $this->categories =   $args['categories']??false;
        $this->sessCalRec['categories'] = $this->categories;

        if (isAdmin()) {
            $this->adminPermStr = 'true';
            $this->sessCalRec['admin'] = true;
            $edPerm = true;
        } else {
            $this->adminPermStr = 'false';
            $this->sessCalRec['admin'] = false;
            $edPerm = Permission::evaluate($this->options['edit']??false);
        }
        $this->edPermStr = $edPerm? 'true': 'false';
        $this->sessCalRec['edit'] = $edPerm;

        // initial date:
        $this->initialDate = $this->sessCalRec['date'] ?? date('Y-m-d');
        $this->sessCalRec['date'] = $this->initialDate;
    } // parseOptions


    /**
     * @return void
     * @throws \Exception
     */
    private function checkAndFixDB(): mixed
    {
        $db = new DataSet($this->source,[
            'masterFileRecKeyType' => 'index',
            'obfuscateRecKeys' => true,
            'keepDataDuration' => $this->options['keepDataDuration']??false,
        ]);
        $data = $db->data(true);
        $modified = false;
        foreach ($data as $key => $rec) {
            if ($rec['allday']??false) {
                if (strlen($rec['start']) > 10) {
                    $data[$key]['start'] = substr($rec['start'], 0, 10);
                    $data[$key]['end'] = substr($rec['end'], 0, 10);
                    $modified = true;
                }
            } else {
                if (strlen($rec['start']) < 16) {
                    $data[$key]['start'] = substr($rec['start'], 0, 10).'T09:00';
                    $data[$key]['end'] = substr($rec['end'], 0, 10).'T10:00';
                    $modified = true;
                } else {
                    if ($rec['start'][10] === ' ') {
                        $data[$key]['start'] = substr($rec['start'], 0, 10).'T'.substr($rec['start'], 11);
                        $modified = true;
                    }
                    if ($rec['end'][10] === ' ') {
                        $data[$key]['end'] = substr($rec['end'], 0, 10).'T'.substr($rec['end'], 11);
                        $modified = true;
                    }
                }
            }
        }
        if ($modified) {
            $db->write($data);
        }
        return true;
    } // checkAndFixDB

} // class Calendar

