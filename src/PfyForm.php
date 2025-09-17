<?php

/*
 * PfyForm is based on Nette Forms
 * https://doc.nette.org/en/forms/controls
 */

namespace PgFactory\PageFactory;

use Kirby\Exception\InvalidArgumentException;
use Nette\Forms\Form;
use Nette\Utils\Html;
use Kirby\Email\PHPMailer;
use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactoryElements\Events as Events;
use PgFactory\PageFactoryElements\PageElements;
use PgFactory\PageFactoryElements\TemplateCompiler;
use RRule\RRule;
use PgFactory\PageFactoryElements\DataTable as DataTable;
use function PgFactory\PageFactory\var_r as var_r;
use function PgFactory\PageFactoryElements\array_splice_associative as array_splice_associative;
use function PgFactory\PageFactoryElements\intlDateFormat as intlDateFormat;

define('ARRAY_SUMMARY_NAME', '_');
const PFY_FORMS_SUPPORTED_TYPES =
    ',text,password,email,textarea,hidden,readonly,'.
    'url,date,datetime-local,time,datetime,month,integer,number,float,range,tel,'.
    'radio,checkbox,dropdown,select,multiselect,upload,multiupload,bypassed,'.
    'event,address,'.
    'button,reset,submit,cancel,@import,literal,';
    // future: toggle,hash,fieldset,fieldset-end,reveal,literal,file,

const INFO_ICON = 'ⓘ';
const MEGABYTE = 1048576;
const DEFAULT_KEEP_OLD_DATA_DURATION = 3; // month
const PFY_FORM_OPTIONS = [
    'file' => false,
    'confirmationText' => false,
    'mailTo' => false,
    'maxCount' => false,
    'maxCountOn' => false,
    'labelWidth' => false,
    'formTop' => false,
    'formHint' => false,
    'formBottom' => false,
    'confirmationEmail' => '',
    'emailFieldName' => '',
    'mailFrom' => false,
    'mailFromName' => false,
    'deadline' => false,
    'id' => false,
    'class' => false,
    'wrapperClass' => false,
    'outerWrapperClass' => '',
    'action' => '~page/',
    'next' => '~page/',
    'dataReceivedCallback' => false,
    'presetCallbackJs' => false,
    'scriptInjectionFilter' => true,
    'tableOptions' => [
        'tableButtons' => '',
        'serviceColumns' => '',
        'editMode' => 'inpage', // inpage, popup, save
        'minRows' => false,
        'announceEmptyTable' => true,
        'permission' => 'loggedin|localhost',
        'showAllFields' => false,
        'headers' => '',
        'tableTitle' => false,
        'masterFileRecKeyType' => 'index',
        'scrollHints' => false,
        'markLocked' => false,
        'obfuscateRecKeys' => true,
        'rowCallback' => true,
        'obfuscateCols' => ['passwor*'],
    ],
    'dbOptions' => [
        'keepDataDuration' => DEFAULT_KEEP_OLD_DATA_DURATION,
        'keepDataOnField' => false,
        'masterFileRecKeyType' => 'index',
        'includeMeta' => true,
    ],
    'feedback' => 'inpage',
    'showFeedbackInpage' => true,
    'retainData' => false,
    'formDataId' => false,
    'recLocking' => false,
    'sideBySide' => null,
    'readonly' => false,
    'recId' => '',
    'init' => true,
    'showData' => null,
    'editData' => null,
    'beforeunloadWarning' => false,
    'keepSubmittedDataInForm' => false,
];

const PFY_ELEMENT_OPTIONS = [
    'type' => false,
    'label' => null,
    'name' => '',
    'required' => '',
    'info' => '',
    'class' => null,
    'antiSpam' => null,
    'autocomplete' => null,
    'disabled' => false,
    'options' => false, // choice options
    'optionWidth' => '',
    'false' => '',
    'autoGrow' => true,
    'origName' => '',
];
mb_internal_encoding("utf-8");


class PfyForm extends Form
{
    private array $formOptions;
    private string|bool $file;
    private array $origReceivedData;
    private array|false $tableOptions = [];
    private array $fieldNames = [];
    protected array $formElements = [];
    private array $choiceOptions = [];
    private array $bypassedElements = [];
    protected $db = false;
    private $dataTable = false;
    protected static $formCounter = 0; // internal form count
    protected int $formIndex = 0; // form-index used for rendering (can be overridden by arg)
    private int $elemInx = 0;
    private int $revealInx = 0;
    protected bool $inhibitAntiSpam = false;
    private string $formWrapperClass = '';
    private array $auxBannerValues = [];
    private $matchingEventAvailable = null;
    private $requiredInputFound = [];
    private $addFormTableWrapper = false;
    private $eventFieldFound = false;
    private $tableTitle;
    private string $formButtons = '';
    private bool $noShowOpened = false;

    protected mixed $deadlinePassed = false;
    protected mixed $maxCountExceeded = false;

    // Output controlling states:
    protected bool $showFeedbackInpage;
    protected string $formResponse = '';
    protected bool $formErrorState = false;
    protected  bool $isFormAdmin = false;
    protected  bool $showTable = false;
    protected bool $showForm = true;
    protected bool $popupMode = false;
    private static bool $initialized = false;
    private static array $scheduleRecs = [];
    private bool $readonly = false;
    private bool $recLocking = false;
    private bool|null $sideBySide = false;
    protected bool|null $keepSubmittedDataInForm = false;
    private array $presetDataRec = [];
    private string $lastCreatedRecKey = '';
    protected string $requestedRecKey = '';
    protected array $formDataRec = [];
    protected string|false $formDataId = false;
    protected string|false $tableId = false;

    /**
     * @param $formOptions
     * @throws \Exception
     */
    public function __construct($formOptions = [])
    {
        self::$formCounter++;
        if (self::$formCounter > 1) {
            // if page contains multiple forms, we need to apply check when receiving data:
            $sessKey = "form:" . PFY_PAGE_ID . ":formCount";
            kirby()->session()->set($sessKey, self::$formCounter);
        }

        $formOptions = $this->parseOptions($formOptions);

        if ($this->recLocking) {
            Page::addJs('const pfyFormRecLocking = true;');
        }
        if ($this->showTable) {
            $this->addFormTableWrapper = true;
            $permissionQuery = $this->tableOptions['permission'];
            $this->isFormAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$dev);
        } else {
            $this->isFormAdmin = PageFactory::$dev;
        }

        $this->handleScheduleOption();
        $this->checkDeadline(); // -> sets $this->deadlinePassed and $this->formInpageResponse
        $this->checkMaxCount();

        // open database:
        if ($formOptions['file']) {
            $this->openDB();
        }

        // prevent announceEmptyTable by default in case minRows is active:
        if ($this->tableOptions && $this->tableOptions['minRows']) {
            $this->tableOptions['announceEmptyTable'] = false;
        }
        parent::__construct($this->formIndex); // -> adds hidden field _form_

        if (!self::$initialized) {
            self::$initialized = true;

            Assets::addAssets('POPUPS');
            Assets::addAssets('REVEAL');
            Assets::addAssets('FORMS');

            if ($formOptions['init']) {
                $setFocus = ($formOptions['tableOptions']['mode']??false) ? 'null, false' : '';
                Page::addJsReady("pfyFormsHelper.init($setFocus);");
            }
            $this->activateWindowFreeze();
            $this->activatebeforeunloadWarning();
        }
        if ($this->keepSubmittedDataInForm && (($_GET['clearform']??false) == $this->formIndex)) {
            Utils::pullSessionVar("form-$this->formIndex", overrideKey:$this->formDataId);
            reloadAgent();

        } elseif ($_GET['presetForm']??false) { // ?presetForm
            $this->requestedRecKey = $_GET['presetForm'];
        }
    } // __construct


    /**
     * All-in-one convenience method: renders a form in one call.
     * @param array $formElements
     * @return string
     * @throws InvalidArgumentException
     */
    public function renderForm(array $formElements): string
    {
        $this->createForm($formElements);
        $html = "\n\n<!-- === pfy form widget === -->\n";

        $this->__processReceivedData();

        $formResponse = $this->deadlinePassed . $this->maxCountExceeded . $this->formResponse;
        if (!$this->showFeedbackInpage && $formResponse) {
            reloadAgent(message: strip_tags($formResponse));
        }

        // add 'continue...' if direct feedback is active:
        if ($this->showFeedbackInpage && $formResponse) {
            $next = $this->formOptions['next'];
            $class = 'pfy-form-continue';
            if ($next === '~page/') {
                $class .= ' pfy-form-continue-same';
            }
            $formResponse .= "<div class='$class'><a href='$next'>{{ pfy-form-success-continue }}</a></div>\n";
        }

        if ($formResponse) {
            $this->injectNoShowCssRule();
            $formTopBanner = $this->injectScrollToFormJs();
            $formTopBanner .= $this->renderFormTopBanner();

            if (!$this->isFormAdmin) {
                return "$formTopBanner\n$formResponse";
            }
            if ($this->formResponse) {
                $formResponse .= $this->renderDataTable();              //    pfy-table-data-output-wrapper/
                $formResponse .= "<!-- === /pfy form widget === -->\n";
                return "$formTopBanner\n$formResponse";
            }
            $html .= "$formTopBanner\n$formResponse";;
        }

        if (!$this->showForm && $this->showFeedbackInpage) {
            // normal case after data received -> show response, hide form:
            $html .= $this->injectNoShowCssRule();
        }

        $table = $this->renderDataTable();              //    pfy-table-data-output-wrapper/


        // assemble form:
        $html .= $this->renderFormWrapperHead();        // pfy-form-and-table-wrapper
                                                        //    pfy-form-wrapper
        $html .= $this->renderFormHead();               //      form
                                                        //        pfy-elems-wrapper
        $html .= $this->renderFormFields();             //          pfy-elem-wrapper ...

        $html .= $this->renderFormTail();               //        /pfy-elems-wrapper
                                                        //      /form
                                                        //    /pfy-form-wrapper
        $html .= $table;                                //

        $html .= $this->renderFormTableWrapperTail();   // /pfy-form-and-table-wrapper
        $html .= $this->renderProblemWithFormBanner();  // pfy-problem-with-form-hint/

        $html .= $this->injectNoShowEnd();
        $html .= "<!-- === /pfy form widget === -->\n\n";
        return $html;
    } // renderForm



    // === Create Form ===================================================================
    /**
     * For each element in $this->formElements invokes addElement() which adds elements to NetteForm.
     * Handles composed elements, such as 'event'.
     * Aditionally adds hidden bookkeeping elements.
     * @return void
     * @throws InvalidArgumentException
     */
    public function createForm($formElements): void
    {
        // build $this->formElements from submitted $formElements:
        foreach ($formElements as $name => $rec) {
            if ($rec === false) { // if unknow argument is false, just silently drop it
                unset($formElements[$name]);
                continue;
            } elseif (!is_array($rec)) {
                if (is_bool($rec)) {
                    $rec = $rec ? 'true' : 'false';
                }
                throw new \Exception("Error in form declaration: unkown argument '$name: $rec'.");
            }

            $rec['origName'] = trim($name);
            if ($rec['name']??false) {
                $name = $rec['name'];
            }
            if (!($this->formElements[$name]??false)) {
                $name = translateToIdentifier($name);
                $this->formElements[$name] = $rec;
            }
        }

        $inx = $this->formIndex;
        // handle option labelWidth:
        if ($lWidth = $this->formOptions['labelWidth']) {
            Page::addCss(".pfy-form-$inx { --pfy-form-label-width: $lWidth}\n");
        }


        // handle '@import' => import form element defs from file:
        $this->handleFieldsImport();
        $this->handleComposedFields();

        // add fields:
        foreach ($this->formElements as $name => $elemOptions) {
            if (!is_array($elemOptions)) {
                throw new \Exception("Syntax error in Forms option '$name'");
            }
            $this->addElement($name);
        }

        if ($this->file) {
            // standard hidden fields for internal bookkeeping:
            $this->addElement('', [
                'type' => 'hidden',
                'name' => '_reckey',
                'class' => 'pfy-reckey',
                'value' => $this->formOptions['recId']
            ]);

            $this->addElement('', ['type' => 'hidden', 'name' => '_dataSrcInx', 'value' => $this->formIndex]);
            $this->addElement('', ['type' => 'hidden', 'name' => '_csrf', 'value' => csrf()]);
        }
        $this->fireRenderEvents();

    } // createForm


    /**
     * Creates an element in NetteForms.
     * This is the place where field types are interpreted.
     * @param array $elemOptions
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    protected function addElement(string $elemName, array|null $elemOptions = null): void
    {
        $this->elemInx++;
        // if $elemOptions not supplied, use $this->formElements
        if ($elemOptions === null) {
            $elemOptions = &$this->formElements[$elemName];
            if (!($elemOptions['name']??false)) {
                $elemOptions['name'] = $elemName;
            }
        }

        $elemOptions['elemInx'] = $this->elemInx;

        // determine $label, $name, $type and $subType:
        list($label, $name, $type) = $this->parseElementOptions($elemOptions);
        if ($name === null) { // this is the case if antiSpam is suppressed by edit-rec option
            return;
        }

        $subType = '';
        switch ($type) {
            case 'hidden':
                $elem = $this->addHidden($name, $elemOptions['value']??'');
                break;
            case 'bypassed':
                $this->bypassedElements[$name] = ($elemOptions['value']??'') ?: $elemOptions['preset']??'';
                $elem = $this->addHidden($name, '');
                break;
            case 'readonly':
                $elem = $this->addText($name, $label);
                $elem->setHtmlAttribute('readonly', '');
                break;
            case 'search':
            case 'tel':
            case 'url':
            case 'range':
            case 'date':
            case 'datetime':
            case 'datetime-local':
            case 'month':
            case 'time':
            case 'week':
                $subType = $type;
            case 'text':
                $elem = $this->addText($name, $label);
                if ($subType) {
                    $elem->setHtmlType($subType);
                }
                break;
            case 'textarea':
                $elem = $this->addTextareaElem($name, $label);
                break;
            case 'integer':
                $elem = $this->addInteger($name, $label);
                break;
            case 'number':
            case 'float':
                $elem = $this->addFloat($name, $label);
                break;
            case 'email':
                $elem = $this->addEmail($name, $label);
                break;
            case 'password':
                $elem = $this->addPassword($name, $label);
                break;
            case 'dropdown':
            case 'multiselect':
            case 'select':
                $elem = $this->addSelectElem($name, $label);
                break;
            case 'radio':
                $elem = $this->addRadioElem($name, $label);
                break;
            case 'checkbox':
                $elem = $this->addCheckboxElem($name, $label);
                break;
            case 'upload':
                $elem = $this->addUploadElem($name, $label);
                break;
            case 'multiupload':
                $elem = $this->addUploadElem($name, $label, multiUpload: true);
                break;
            case 'newrec':
            case 'button':
                $elem = $this->addButton('_'.$name, $label);
                $elem->setHtmlAttribute('class', "pfy-$type");
                break;
            case 'literal':
                return; // nothing to do
            case 'cancel':
            case 'reset':
                $elem = $this->addButton('_cancel', $label);
                if ($next = ($this->formOptions['next'])) {
                    $elem->setHtmlAttribute('data-next', $next);
                }
                break;
            case 'submit':
                $elem = $this->addSubmit($name, $label);
                break;
            default:
                throw new \Exception("PfyForm: field type '$type' not supported");
        }

        // make sure each elem get unique id:
        if ($id = $elemOptions['id']??false) {
            $elem->setHtmlId($id);
        } elseif ($this->formIndex > 1) {
            $id = "frm-$type-$this->formIndex-$this->elemInx";
            $elem->setHtmlId($id);
        }

        $class = "pfy-$type";

        // handle 'required' option:
        if ($required =($elemOptions['required']??false)) {
            if ($required === true) {
                $this->requiredInputFound['_'] = '{{ pfy-form-required-info }}';
                $elem->setRequired();
            } else {
                $this->requiredInputFound[$required] = $required;
            }
        }

        // handle 'disabled' option:
        if (($elemOptions['disabled']??false) !== false) {
            $elem->setDisabled();
        }

        // handle 'enableSubmit' option:
        if (($elemOptions['enableSubmit']??false) !== false) {
            $elem->setHtmlAttribute('data-enablesubmit', true);
        }

        // handle 'readonly' option:
        if (($elemOptions['readonly']??false) !== false) {
            $elem->setHtmlAttribute('readonly', '');
        }

        // handle 'class' option:
        if (($class1 = ($elemOptions['class']??''))) {
            $class .= " $class1";
        }
        $elem->setHtmlAttribute('class', trim($class));

        // handle placeholders:
        if ($placeholder = ($elemOptions['placeholder']??false)) {
            $elem->setHtmlAttribute('placeholder', $placeholder);
        }

        // handle compute-saveAs:
        if ($saveAs = ($elemOptions['saveAs']??false)) {
            $this->formElements[$name]['saveAs'] = $saveAs;
        }

        // handle defaultEventDuration:
        if (isset($elemOptions['defaultEventDuration'])) {
            $defaultEventDuration = ($elemOptions['defaultEventDuration']??0);
            $elem->setHtmlAttribute('data-related-field', $elemOptions['relatedField']??'');
            $elem->setHtmlAttribute('data-event-duration', $defaultEventDuration);
        }

        // handle step:
        if ($step = ($elemOptions['step']??false)) {
            $elem->setHtmlAttribute('step', $step);
        }

        // handle min:
        $min = $elemOptions['min']??false;
        if ($min !== false) {
            $elem->addRule(self::Min, 'Min value: %d', $min);
        }

        // handle max -> take into account case maxCount:
        $max = $elemOptions['max']??false;
        if ($max !== false) {
            if ($name === $this->formOptions['maxCountOn']) {
                // if sign-up limitation is active, limit max input if necessary, unless privileged:
                list($available, $maxCount) = $this->getAvailableAndMaxCount();
                if ($maxCount && !$this->isFormAdmin) {
                    $max = min($max, $available);
                }
            }
            $elem->addRule(self::Max, 'Max value: %d', $max);
        }

        // handle 'wrapperId' option:
        if ($wrapperId = ($elemOptions['wrapperId']??false)) {
            $elem->setHtmlAttribute('data-wrapper-id', $wrapperId);
        }

        // handle 'category' option:
        if ($showForCategory = ($elemOptions['category']??false)) {
            $elem->setHtmlAttribute('data-category', $showForCategory);
        }

        // handle 'antiSpam' option:
        if ($antiSpam = ($elemOptions['antiSpam']??false)) {
            $elem->setHtmlAttribute('data-check', $antiSpam);
            $elem->setHtmlAttribute('aria-hidden', 'true');
            $elem->setHtmlAttribute('tabindex', '-1');
            unset($this->fieldNames[$name]);
        }

        // note: 'info' option handled in parseElementOptions()
    } // addElement


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\TextArea
     * @throws \Exception
     */
    private function addTextareaElem(string $name, string $label): object
    {
        $elemOptions = &$this->formElements[$name];

        // textarea option 'reveal':
        $elem = $this->addTextarea($name, $label);
        if ($elemOptions['autoGrow']) {
            $elemOptions['class'] .= ' pfy-auto-grow';
        }
        return $elem;
    } // addTextareaElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\RadioList
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addRadioElem(string $name, string $label): object
    {
        $elemOptions = &$this->formElements[$name];
        $radioElems = $elemOptions['options'];
        $elem = $this->addRadioList($name, $label, $radioElems);
        if ($elemOptions['preset']??false) {
            // check preset -> if value given, replace with key:
            if (($p = array_search($elemOptions['preset'], $radioElems)) !== false) {
                $elemOptions['preset'] = $p;
            }
        }
        $this->formElements[$name]['isArray'] = true;
        $this->formElements[$name]['subKeys'] = array_keys($radioElems);
        // handle option 'horizontal':
        $elemOptions['class'] .= (($layout = ($elemOptions['layout']??false)) && ($layout[0] !== 'h')) ? '' : ' pfy-horizontal';
        $elemOptions['class'] = 'pfy-choice '.$elemOptions['class'];

        if ($elemOptions['splitOutput']??false) {
            $this->addFieldNames($name, $radioElems);
        }

        if ($elemOptions['revealTarget']??false) {
            Assets::addAssets('REVEAL');
            $elem->setHtmlAttribute('data-reveal-target', $elemOptions['revealTarget']);
        }

        return $elem;
    } // addRadioElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\Checkbox|\Nette\Forms\Controls\CheckboxList
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addCheckboxElem(string $name, string $label): object
    {
        $elemOptions = &$this->formElements[$name];
        if ($elemOptions['options']??false) {
            $checkboxes = $elemOptions['options'];
            $elem = $this->addCheckboxList($name, $label, $checkboxes);
            if ($elemOptions['preset']??false) {
                // check presets -> if value(s) given, replace with key(s):
                $presets = explodeTrim(',', $elemOptions['preset']);
                $presetStr = '';
                foreach ($presets as $preset) {
                    if (($p = array_search($preset, $checkboxes)) !== false) {
                        $presetStr .= "$p,";
                    } else {
                        $presetStr .= "$preset,";
                    }
                }
                $elemOptions['preset'] = rtrim($presetStr, ',');
            }
            $this->choiceOptions[$name] = $checkboxes;
            if ($elemOptions['splitOutput']??false) {
                $this->addFieldNames($name, $checkboxes);
            }
            $this->formElements[$name]['isArray'] = true;
            $this->formElements[$name]['subKeys'] = array_keys($checkboxes);

            // handle option 'horizontal':
            $elemOptions['class'] .= (($layout = ($elemOptions['layout']??false)) && ($layout[0] !== 'h')) ? '' : ' pfy-horizontal';

        } else {
            $elemOptions['class'] .= ' pfy-single-checkbox';
            if (!str_contains($elemOptions['class'], 'reversed')) {
                $label = rtrim($label, ':');
            }
            $elem = $this->addCheckbox($name, $label);
        }
        $elemOptions['class'] = 'pfy-choice '.$elemOptions['class'];

        $elem->setHtmlAttribute('class', "pfy-form-checkbox");

        if ($elemOptions['revealTarget']??false) {
            Assets::addAssets('REVEAL');
            $elem->setHtmlAttribute('data-reveal-target', $elemOptions['revealTarget']);
        }

        return $elem;
    } // addCheckboxElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @param string $type
     * @return object|\Nette\Forms\Controls\MultiSelectBox|\Nette\Forms\Controls\SelectBox
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addSelectElem(string $name, string $label): object
    {
        $elemOptions = &$this->formElements[$name];
        $type = $elemOptions['type'];
        if ($type === 'dropdown') { // make 'dropdown' synonym for 'select'
            $type = $elemOptions['type'] = 'select';
        }
        $selectionElems = $elemOptions['options'];

        // handle special case of one select option -> render as readonly:
        if (sizeof($selectionElems) === 1) {
            $elem = $this->addText($name, $label);
            $elem->setHtmlAttribute('readonly', '');
            $elemOptions['value'] = reset($selectionElems);
            return $elem;
        }

        foreach ($selectionElems as $key => $value) {
            if (!$value) {
                $selectionElems[$key] = '{{ pfy-form-select-empty-option }}';
            }
        }
        if ($type === 'multiselect') {
            $elem = $this->addMultiSelect($name, $label, $selectionElems);
            $elem->setHtmlAttribute('size', min(5,sizeof($selectionElems)));
            $this->formElements[$name]['isArray'] = true;
            $this->formElements[$name]['subKeys'] = array_keys($selectionElems);
            if ($elemOptions['preset']??false) {
                // check presets -> if value(s) given, replace with key(s):
                $presets = explodeTrim(',', $elemOptions['preset']);
                $presetStr = '';
                foreach ($presets as $preset) {
                    if (($p = array_search($preset, $selectionElems)) !== false) {
                        $presetStr .= "$p,";
                    } else {
                        $presetStr .= "$preset,";
                    }
                }
                $elemOptions['preset'] = rtrim($presetStr, ',');
            }

        } else {
            $elem = $this->addSelect($name, $label, $selectionElems);
            if ($elemOptions['preset']??false) {
                // check presets -> if value(s) given, replace with key(s):
                if (($p = array_search($elemOptions['preset'], $selectionElems)) !== false) {
                    $elemOptions['preset'] = $p;
                }
            }
        }
        if ($elemOptions['prompt']??false) {
            $elem->setPrompt($elemOptions['prompt']);
        }
        $this->choiceOptions[$name] = $selectionElems;

        if ($elemOptions['splitOutput']??false) {
            $this->addFieldNames($name, $selectionElems);
        }
        return $elem;
    } // addSelectElem


    /**
     * @param string $name
     * @param string $label
     * @param bool $multiUpload
     * @return object|\Nette\Forms\Controls\UploadControl
     */
    private function addUploadElem(string $name, string $label, bool $multiUpload = false): object
    {
        if ($multiUpload) {
            $elem = $this->addMultiUpload($name, $label);
        } else {
            $elem = $this->addUpload($name, $label);
        }
        $filter = $this->formElements[$name]['filter']??false;
        if ('images' === $filter) {
            $elem->addRule(self::Image, 'File must be JPEG, PNG, GIF or WebP');
        } elseif ($filter) {
            if (str_contains($filter, ',')) {
                $pattern = str_replace([',', ' '], ['|', ''], $filter);
                $pattern = "($pattern)$";
            } else {
                $pattern = "$filter$";
            }
            $pattern = '.*\\.'.$pattern;
            $elem->addRule(self::PatternInsensitive, "File must have extension '$filter'", $pattern);
        }
        if ($mb = ($elemOptions['maxMegaByte']??false)) {
            $elem->addRule(self::MaxFileSize, "Maximum size is $mb MB", MEGABYTE * $mb);
        }
        return $elem;
    } // addUploadElem


    /**
     * @param string $name
     * @param array $array
     * @return void
     */
    private function addFieldNames(string $name, array $array): void
    {
        array_pop($this->fieldNames); // remove mother elem and replace it with children
        foreach ($array as $k => $v) {
            $this->fieldNames["$name.$k"] = $k;
        }
    } // addFieldNames


    /**
     * @return void
     */
    private function handleComposedFields(): void
    {
        foreach ($this->formElements as $name => $rec) {
            $type = ($rec['type']??false);
            if ($type === 'event') {
                $this->composeEventElement($name, $rec);
            } elseif ($type === 'address') {
                $this->composeAddressElement($name, $rec);
            }
        }
    } // handleComposedFields


    /**
     * @param int|string $name
     * @return void
     */
    private function composeEventElement(int|string $name, array $rec): void
    {
        if (!$this->eventFieldFound) {
            $this->eventFieldFound = true;
            $startName = 'start';
            $endName   = 'end';
            $startLabel = TransVars::getVariable('pfy-form-event-start-label');
            $endLabel = TransVars::getVariable('pfy-form-event-end-label');

        } elseif ($this->formElements[$name]['suffix']??false) {
            $suffix = $this->formElements[$name]['suffix'];
            $startName = 'start' . $suffix;
            $endName = 'end' . $suffix;

            if (!($startLabel = TransVars::getVariable("pfy-form-event-$startName-label"))) {
                $startLabel = TransVars::getVariable('pfy-form-event-start-label');
                if (preg_match('/(.*)(\W+)$/', $startLabel, $m)) {
                    $startLabel = $m[1] . $suffix . $m[2];

                } else {
                    $startLabel = $startLabel . $suffix;
                }
            }

            // endLabel:
            if (!($endLabel = TransVars::getVariable("pfy-form-event-$endName-label"))) {
                $endLabel = TransVars::getVariable('pfy-form-event-end-label');
                if (preg_match('/(.*)(\W+)$/', $endLabel, $m)) {
                    $endLabel = $m[1] . $suffix . $m[2];

                } else {
                    $endLabel = $endLabel . $suffix;
                }
            }

        } else {
            $startName   = 'start_'.$name;
            $endName   = 'end_'.$name;
            $startLabel = TransVars::getVariable('pfy-form-event-start-label');
            $endLabel = TransVars::getVariable('pfy-form-event-end-label');
        }

        $eventElements = [];

        // preset: true = today, hour = today plus given time
        $preset = $this->formElements[$name]['preset']??'';
        if ($preset === true) {
            $preset = date('Y-m-d').' 12:00';
        } elseif (preg_match('/^\d\d[.:]\d\d$/', $preset)) {
            $preset = date('Y-m-d ').$preset;
        }
        $eventElements[$startName] = [
            'type' => 'datetime-local',
            'label' => $startLabel,
            'class' => 'pfy-event-elem pfy-event-elem-from',
            'preset' => $preset,
        ];

        $defaultEventDuration = ($this->formElements[$name]['defaultEventDuration'] ?? ($this->formElements[$name]['defaultDuration']??0));
        $eventElements[$endName] = [
            'type' => 'datetime-local',
            'label' => $endLabel,
            'class' => 'pfy-event-elem pfy-event-elem-till',
            'relatedField' => $startName,
            'defaultEventDuration' => $defaultEventDuration,
        ];

        $this->formElements = array_splice_associative($this->formElements, $name, 1, $eventElements);

        if ($rec['repeatable']??false) {
            $this->composeRruleElement($name, $rec);
        }
    } // composeEventElement


    /**
     * @param int|string $name
     * @param array $rec
     * @return void
     * @throws InvalidArgumentException
     */
    private function composeAddressElement(int|string $name, array $rec): void
    {
        if ($labels = ($this->formElements[$name]['label']??'')) {
            $labels = parseArgumentStr($labels);
        }
        if ($infos = ($this->formElements[$name]['info']??'')) {
            $infos = parseArgumentStr($infos);
        }
        if ($description = ($this->formElements[$name]['description']??'')) {
            $description = parseArgumentStr($description);
        }
        if ($presets = ($this->formElements[$name]['preset']??'')) {
            $presets = parseArgumentStr($presets);
        }
        if ($names = ($this->formElements[$name]['name']??'')) {
            $names = parseArgumentStr($names);
            if (array_keys($names)[0] === '_anonInx0') {
                $names = array_combine(['street', 'zip', 'city'], $names);
            }
        }

        $addressElements = [];

        $elName = ($names['street']??false) ?: 'street';
        $addressElements[$elName] = [
            'type' => 'text',
            'label' => '{{ pfy-form-address-street-label }}',
            'class' => 'pfy-address-elem pfy-address-street',
            'autocomplete' => 'street-address',
        ];
        if ($labels['street']??false) {
            $addressElements[$elName]['label'] = $labels['street'];
        }
        if ($infos['street']??false) {
            $addressElements[$elName]['info'] = $infos['street'];
        }
        if ($description['street']??false) {
            $str = $description['street'];
            if (preg_match('/\\\:(\w{2,20})\\\:/', $str, $m)) {
                $icon = MdPlusHelper::renderIcon(":{$m[1]}:");
                $str = str_replace($m[0], $icon, $str);
            }
            $addressElements[$elName]['description'] = $str;
        }
        if ($presets['street']??false) {
            $addressElements[$elName]['preset'] = $presets['street'];
        }

        $elName = ($names['zip']??false) ?: 'zip';
        $addressElements[$elName] = [
            'type' => 'text',
            'label' => '{{ pfy-form-address-zip-label }}',
            'class' => 'pfy-address-elem pfy-address-zip',
            'autocomplete' => 'postal-code',
            'description' => '{{ pfy-form-address-combined-label }}',
        ];
        if ($labels['zip']??false) {
            $addressElements[$elName]['label'] = $labels['zip'];
        }
        if ($infos['zip']??false) {
            $addressElements[$elName]['info'] = $infos['zip'];
        }
        if ($presets['zip']??false) {
            $addressElements[$elName]['preset'] = $presets['zip'];
        }
        if ($names['zip']??false) {
            $addressElements[$elName]['name'] = $names['zip'];
        }

        $elName = ($names['city']??false) ?: 'city';
        $addressElements[$elName] = [
            'type' => 'text',
            'label' => '{{ pfy-form-address-city-label }}',
            'class' => 'pfy-address-elem pfy-address-city',
            'autocomplete' => 'address-level2',
        ];
        if ($labels['city']??false) {
            $addressElements[$elName]['lebel'] = $labels['city'];
        }
        if ($infos['city']??false) {
            $addressElements[$elName]['info'] = $infos['city'];
        }
        if ($presets['city']??false) {
            $addressElements[$elName]['preset'] = $presets['city'];
        }
        if ($names['city']??false) {
            $addressElements[$elName]['name'] = $names['city'];
        }


        $this->formElements = array_splice_associative($this->formElements, $name, 1, $addressElements);

    } // composeAddressElement


    /**
     * @param int|string $name
     * @param array $rec
     * @return void
     */
    private function composeRruleElement(int|string $name, array $rec): void
    {
        $wkst = $rec['wkst']?? 'MO';
        $eventElements = [];

        $eventElements['rrule'] = [
            'type'  => 'hidden',
            'saveAs'  => '"RRULE:FREQ=$_freq;COUNT=$_count;INTERVAL=$_interval;WKST='.$wkst.';BYDAY=$_byweekday;BYMONTH=$_bymonth;"',
        ];

        $eventElements['_repeatEvent'] = [
            'type' => 'literal',
            'html' => "<!-- pfy-rrule-wrapper -->\n<details class='pfy-form-rrule-wrapper'>\n<summary>\n",
        ];

        $eventElements['_freq'] = [
            'type' => 'dropdown',
            'label' => '{{ pfy-form-rrule-freq-label }}',
            'class' => 'pfy-rrule-elem pfy-rrule-elem-freq',
            'info' => '{{ pfy-form-rrule-freq-info }}',
            'options' =>
                'NONE:"{{ pfy-form-rrule-none-option }}",'.
                'DAILY:"{{ pfy-form-rrule-daily-option }}",'.
                'WEEKLY:"{{ pfy-form-rrule-weekly-option }}",'.
                'MONTHLY:"{{ pfy-form-rrule-monthly-option }}",'.
                'YEARLY:"{{ pfy-form-rrule-yearly-option }}"',
        ];

        $eventElements['_repeatEventBody'] = [
            'type'      => 'literal',
            'html'      => "</summary>\n<div class='pfy-form-rrule-body-wrapper'>",
        ];

        $eventElements['_until'] = [
            'type'      => 'datetime-local',
            'label'     => '{{ pfy-form-rrule-until-label }}',
            'class'     => 'pfy-rrule-elem pfy-rrule-elem-until medium',
            'info'      => '{{ pfy-form-rrule-until-info }}',
        ];

        $eventElements['_count'] = [
            'type'      => 'integer',
            'label'     => '{{ pfy-form-rrule-count-label }}',
            'class'     => 'pfy-rrule-elem pfy-rrule-elem-count short',
            'preset'    => 1,
            'min'       => 1,
            'max'       => 100,
            'info'      => '{{ pfy-form-rrule-count-info }}',
        ];

        $eventElements['_interval'] = [
            'type'      => 'integer',
            'label'     => '{{ pfy-form-rrule-interval-label }}',
            'class'     => 'pfy-rrule-elem pfy-rrule-elem-interval short',
            'info'      => '{{ pfy-form-rrule-interval-info }}',
            'preset'    => 1,
            'min'       => 1,
            'max'       => 366,
        ];

        $options = '';
        foreach (['MO','TU','WE','TH','FR','SA','SU'] as $i => $wday) {
            $d = ($i+5) > 9 ? $i+5 : '0'.$i+5;
            $options .= $wday .':'. intlDateFormat('E', strtotime("1970-01-$d")) .',';
        }
        $eventElements['_byweekday'] = [
            'type'      => 'checkbox',
            'options'   => rtrim($options, ','),
            'label'     => '{{ pfy-form-rrule-byweekday-label }}',
            'class'     => 'pfy-rrule-elem pfy-rrule-elem-byweekday pfy-short-options',
            'info'      => '{{ pfy-form-rrule-byweekday-info }}',
        ];

        $options = '';
        for ($month = 1; $month <= 12; $month++) {
            $options .= $month .':'. intlDateFormat('MMM', strtotime("1970-$month-01")) .',';
        }
        $eventElements['_bymonth'] = [
            'type'      => 'checkbox',
            'options'   => rtrim($options, ','),
            'label'     => '{{ pfy-form-rrule-bymonth-label }}',
            'class'     => 'pfy-rrule-elem pfy-rrule-elem-bymonth pfy-short-options',
            'info'      => '{{ pfy-form-rrule-bymonth-info }}',
        ];

        $eventElements['_repeatEventEnd'] = [
            'type'      => 'literal',
            'html'      => "</div><!-- /pfy-form-rrule-body-wrapper -->\n</details>\n<!-- /pfy-rrule-wrapper -->\n",
        ];

        $names = array_keys($this->formElements);
        $n = array_search('end', $names);
        $name = $names[$n+1]??'';
        $this->formElements = array_splice_associative($this->formElements, $name, 0, $eventElements);
    } // composeRruleElement


    /**
     * @return string
     */
    protected function injectNoShowCssRule(): string
    {
        $css = ".pfy-form-{$this->formIndex},\n" .
            ".pfy-show-unless-form-data-received,\n" .
            ".pfy-show-unless-form-data-received-$this->formIndex {display:none;}";
        Page::addCss($css);
        Page::addBodyTagClass('pfy-form-data-received');
        $this->noShowOpened = true;
        return "<div class='pfy-show-unless-form-data-received-$this->formIndex'>\n";
    } // injectNoShowCssRule






    // === Render Form Helpers ========================================================

    /**
     * Renders element identified by $name.
     * @param string $name
     * @return string
     */
    protected function renderFormElement(string $name): string
    {
        if (isset($this->formElements[$name])) {
            $rec = $this->formElements[$name];
        } else {
            // find rec with this $name:
            foreach ($this->formElements as $rec) {
                if ($rec['name'] === $name) {
                    $found = true;
                    break;
                }
            }
            // PHP 8.4+ alternative:
            //            $rec = array_find($this->formElements, function ($elem) use ($name) {
            //                return $elem['name'] === $name;
            //            });
        }

        // special case: type literal -> just output literal
        if (($rec['type']??false) === 'literal') {
            return $rec['html']??'';
        }

        $_name = strtolower($name);
        try {
            $netteFormElemName = $rec['name']??$name;
            $elem = $this[$netteFormElemName];
        } catch (\Exception $e) {
            try {
                // check for name with leading '_':
                $elem = $this['_'.$netteFormElemName];
            } catch (\Exception $e) {
                throw new \Exception("Error: form element '{$netteFormElemName}' unknown to Nette Forms.");
            }
        }

        if ($rec['autocomplete']??false) {
            $elem->setHtmlAttribute('autocomplete', $rec['autocomplete']);
        }
        $label = (string)$elem->getLabel();
        $label = str_replace(['&lt;','&gt;'], ['<','>'], $label);

        $label = "<span class='pfy-label-wrapper'>$label</span>";
        $input = (string)$elem->getControl();
        $input = str_replace(['&lt;','&gt;'], ['<','>'], $input);

        // fix for NetteForm's quirk: input outside of label in choice fields -> move it outside:
        if (str_contains($input, 'type="radio"') || str_contains($input, 'type="checkbox"')) {
            $input = $this->fixInputInsideLabelQuirk($input, $name);
        }
        $type = $this->determineType($_name, $rec['type'] ?? false);
        $attr = '';

        // if nette forms applied a value, turn it into a data-value (same for checked and selected):
        $input = $this->applyFormFieldValues($input, $type, $name);

        // for password field prepare required icons:
        if ($type === 'password') {
            PageElements::loadIcons();
            $icon = "<svg viewBox='0 0 512 512' class='pfy-icon-show'><use href='#pfy-iconset-show' /></svg>".
                "<svg viewBox='0 0 512 512' class='pfy-icon-hide'><use href='#pfy-iconset-hide' /></svg>";
            $input .= "<button type='button' class='pfy-form-show-pw' aria-pressed='false'>$icon</button>";
        }

        $description = $rec['description'] ?? '';
        if (preg_match('/(:\w{2,20}:)/', $description, $m)) {
            $icon = MdPlusHelper::renderIcon($m[1]);
            $description = str_replace($m[0], $icon, $description);
        }
        if ($type !== 'hidden') {
            $input .= "<span class='pfy-form-field-description'>$description</span>";
        }

        $class = $rec['class'];
        if ($rec['required'] ?? false) {
            if (($rec['required'] === true)) {
                $class .= ' pfy-required';
            } else {
                $class .= ' pfy-required-group';
                $attr .= " data-required-group='{$rec['required']}'";
            }
        }

        // handle option "category" -> to hide form elements not belonging to given category
        if (preg_match('/data-category="(.*?)"/', $input, $m)) {
            $categories = explodeTrim(',',$m[1]);
            foreach ($categories as $category) {
                $class .= " pfy-for-category-$category";
            }
        }

        $nameCls = translateToClassName($name);
        if ($type !== $nameCls) {
            $class .= " pfy-$nameCls";
        }

        $dataAttrib = '';
        if ($dataVal = $this->formDataRec[$name]??'') {
            // if data has been received previously (in retainData mode), that has priority:
            if (!is_array($dataVal)) {
                $dataAttrib = " data-value='$dataVal'";
            }

        } else {
            if ($rec['value'] ?? false) {
                $val = $rec['value'];
                if (str_contains($val, '%')) {
                    $val = str_replace(['%today%', '%now%'], [date('Y-m-d'), date('Y-m-d H:i')], $val);
                }
               $dataAttrib = " data-value='$val'";
            }
            if ($rec['preset'] ?? false) {
                $val = $rec['preset'];
                if (str_contains($val, '%')) {
                    $val = str_replace(['%today%', '%now%'], [date('Y-m-d'), date('Y-m-d H:i')], $val);
                }
              $dataAttrib .= " data-preset='$val'";
            }
        }

        // === render type-specific =====================================
        $html = '';
        if ($type === 'hidden') {
            $html = $this->renderFormElement_Hidden($type, $class, $input, $dataAttrib);

        } elseif (($type === 'textarea') && ($rec['reveal'] ?? false)) {
            $html = $this->renderFormElement_textarea($rec, $name, $class, $input, $dataAttrib);

        } elseif ($type === 'literal') {
            $html .= $rec['html'] ?? '';

        } elseif (str_contains(',cancel,submit,reset,button,newrec', ",$type,")) {
            $cls = $rec['class'] ?? '';
            $callback = ($rec['callback']??false);
            if ($callback) {
                $elem->setHtmlAttribute('data-callback', $callback);
            }
            $elem->setHtmlAttribute('title', "{{ pfy-form-$_name-button-title }}");
            $cls .= (str_contains('cancel,submit', $type)) ?" pfy-$type" : " btn-$_name";
            $elem->setHtmlAttribute('class', "pfy-form-button $cls");
            $this->formButtons .= (string)$elem->getControl() . "\n";
            return '';

        } elseif ($type === 'button') {
            $cls = $rec['class'] ?? '';
            $elem->setHtmlAttribute('class', "pfy-form-button $cls");
            $callback = ($rec['callback']??false);
            if ($callback) {
                $elem->setHtmlAttribute('data-callback', $callback);
            }
            $this->formButtons .= (string)$elem->getControl() . "\n";
            return '';

        } elseif (is_array($dataVal)) {
            $html = $this->renderFormElement_choiceTypes($elem, $type, $class, $input, $attr, $label, $dataVal);

        // all other field types (except bypassed and import):
        } elseif (!str_contains(',bypassed,@import,', ",$type,")) {
            $html = $this->renderFormElement_regularTypes($elem, $type, $class, $input, $attr, $label, $dataAttrib);
        }
        return $html;
    } // renderFormElement


    /**
     * @param array $rec
     * @param string $type
     * @param string $name
     * @param string $class
     * @param string $input
     * @return string
     */
    private function renderFormElement_textarea(array $rec, string $name, string $class, string $input, string $dataAttrib): string
    {
        $controllerLabel = $rec['reveal'];
        if ($controllerLabel === true) {
            $controllerLabel = TransVars::getVariable('pfy-form-default-reveal-label');
            $controllerLabel = str_replace('%name%', $name, $controllerLabel);
        }
        $html = <<<EOT

<div class='pfy-elem-wrapper pfy-textarea $class'$dataAttrib><!-- pfy-elem-wrapper -->
	<details class='mdp-accordion'>
		<summary><span>$controllerLabel</span></summary>
		<div class='mdp-accordion-body'>
            <span class="pfy-input-wrapper">
            $input
            </span>
		</div>
	</details>
</div>
<!-- _________________ pfy-elem-wrapper -->


EOT;
        return $html;
    } // renderFormElement_textarea


    /**
     * @param string $type
     * @param string $class
     * @param string $input
     * @param string $dataAttrib
     * @return string
     */
    private function renderFormElement_Hidden(string $type, string $class, string $input, string $dataAttrib): string
    {
        $html = <<<EOT

<div class="pfy-elem-wrapper pfy-$type $class"$dataAttrib><!-- pfy-elem-wrapper -->
$input
</div>
<!-- _________________ pfy-elem-wrapper -->


EOT;
        return $html;
    } // renderFormElement_Hidden


    /**
     * @param string $type
     * @param string $name
     * @param string $class
     * @param string $input
     * @param string $attr
     * @param string $label
     * @return string
     */
    private function renderFormElement_regularTypes(object $elem, string $type, string $class, string $input, string $attr, string $label, string $dataAttrib): string
    {
        // get errors and render them:
        $errors = '';
        if ($elem->hasErrors()) {
            $class .= ' pfy-form-elem-has-error';
            foreach ($elem->getErrors() as $error) {
                $errors .= "<div class='pfy-form-elem-error-msg'>$error</div>\n";
            }
        }

        if ($attr) {
            $label = str_replace('<label', "<label $attr", $label);
        }

        $input = "<span class='pfy-input-wrapper'>$input</span>";

        $html = <<<EOT

<div class="pfy-elem-wrapper pfy-$type $class"$dataAttrib><!-- pfy-elem-wrapper -->

$label
$input
$errors
</div>
<!-- _________________ /pfy-elem-wrapper -->


EOT;
        return $html;
    } // renderFormElement_regularTypes


    private function renderFormElement_choiceTypes(object $elem, string $type, string $class, string $input, string $attr, string $label, array $dataVals): string
    {
        // get errors and render them:
        $errors = '';
        if ($elem->hasErrors()) {
            $class .= ' pfy-form-elem-has-error';
            foreach ($elem->getErrors() as $error) {
                $errors .= "<div class='pfy-form-elem-error-msg'>$error</div>\n";
            }
        }

        if ($attr) {
            $label = str_replace('<label', "<label $attr", $label);
        }
        $dataAttr = " data-value='{$dataVals['_']}'";
        $input = "<span class='pfy-input-wrapper'>$input</span>";

        $html = <<<EOT

<div class="pfy-elem-wrapper pfy-$type $class"$dataAttr><!-- pfy-elem-wrapper -->

$label
$input
$errors
</div>
<!-- _________________ /pfy-elem-wrapper -->


EOT;
        return $html;
    } // renderFormElement_choiceTypes


    /**
     * @return string
     */
    protected function renderFormWrapperHead(): string
    {
        $html = '';
        $formInx = $this->formIndex;
        $id = $this->formOptions['id'];
        $dataSrcInx = " data-src-inx='$formInx'";

        // apply outer table-and-form wrapper:
        if ($this->addFormTableWrapper) {
            $id = $id ? " id='{$id}-wrapper'" : '';
            $class = $this->formOptions['outerWrapperClass'];
            $html .= "<div$id class='pfy-form-and-table-wrapper pfy-form-and-table-wrapper-$formInx $class'$dataSrcInx>\n";
        }

        // in popupMode apply a wrapper that makes the form (incl form-wrapper) invisible:
        if ($this->popupMode) {
            // in popup mode the form is not visible, only appears in popup on request
            $html .= "<div class='pfy-fully-hidden' aria-hidden='true'>\n";
        }

        // apply form wrapper
        $wrapperClass = "pfy-form-wrapper pfy-form-wrapper-$formInx" . $this->formWrapperClass;

        // handle case where url-arg requested presetting given record:
        if ($this->requestedRecKey) {
            $rec = $this->db->find($this->requestedRecKey);
            if ($rec) {
                $this->formDataRec = $rec->data();
                $this->formDataRec['_reckey'] = $this->requestedRecKey;
                if (isset($_GET['asmodified'])) {
                    $this->formDataRec['_isModified'] = true;
                }
            }
            if ($this->keepSubmittedDataInForm) {
                Utils::setSessionVar("form-$this->formIndex", $this->formDataRec, overrideKey:$this->formDataId);
            }
        } elseif ($this->keepSubmittedDataInForm) {
            $this->formDataRec = Utils::getSessionVar("form-$this->formIndex", [], overrideKey:$this->formDataId);
        }

        // handle case where data rec is to be preset:
        if ($this->formDataRec) {
            // case url-arg "?presetForm=ABCDEF&asmodified":
            if ($this->formDataRec['_isModified']??false) {
                $wrapperClass .= ' pfy-form-mark-as-modified';
            } else {
                $wrapperClass .= ' pfy-form-is-preset';
            }
        }
        if ($this->readonly) {
            $wrapperClass .= ' pfy-form-readonly';
        }
        $tableRef = '';
        if ($this->tableId) {
            $tableRef = " data-related-table='{$this->tableId}'";
        }
        $wrapperClass .= $this->keepSubmittedDataInForm? ' pfy-retain-data' : '';
        $html .= "<div id='pfy-form-wrapper-$formInx' class='$wrapperClass'$tableRef>\n";

        return $html;
    } // renderFormWrapperHead


    /**
     * @return string
     * @throws \Exception
     */
    protected function renderFormHead(): string
    {
        $html = '';

        // case confirmationEmail: check whether corresponding field is defined:
        if ($confirmationEmail = $this->formOptions['confirmationEmail']) {
            if (!str_contains($confirmationEmail, '@')) { // $confirmationEmail may be an explicit address, then skip:
                $found = false;
                foreach ($this->formElements as $rec) {
                    if ($rec['name'] === $confirmationEmail) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new \Exception("Error: form option confirmationEmail refers to a field that is not defined: '$confirmationEmail'");
                }
            }
        }

        // schedule option may have found no matching event, in this case show message:
        if ($this->matchingEventAvailable === false) {
            $this->showForm = false;
            return '{{ pfy-form-no-event-found }}';
        }
        if (!$this->showForm) {
            return '';
        }

        if ($this->file) {
            if ($this->formOptions['action'] ?? false) {
                $this->setAction($this->formOptions['action']);
            } else {
                $this->setAction(PFY_PAGE_URL); // this page's URL, poss. including ?xy
            }
        }
        $presetCallback = '';
        if ($pc = ($this->formOptions['presetCallbackJs'] ?? false)) {
            $presetCallback = " data-preset-callback='$pc'";
        }

        if ($this->sideBySide !== null) {
            $icon1 = MdPlusHelper::renderIcon('calendar_split');
            $icon2 = MdPlusHelper::renderIcon('calendar');
            $pressed1 = $this->sideBySide ? 'true' : 'false';
            $pressed2 = !$this->sideBySide ? 'true' : 'false';
            $html .= <<<EOT
<div class="pfy-side-by-side-buttons">
<button class="pfy-button pfy-two-windows" title="{{ pfy-form-sidebyside-on }}" aria-pressed="$pressed1">$icon1</button>
<button class="pfy-button pfy-one-window" title="{{ pfy-form-sidebyside-off }}" aria-pressed="$pressed2">$icon2</button>
</div>
EOT;
        }

        list($id, $formClass) = $this->getHeadAttributes();
        if ($this->hasErrors()) {
            $formClass .= ' pfy-form-has-errors';
        }
        $dataFormInx = "data-src-inx='$this->formIndex'";

        $htmlForm = $this->getRenderer()->render($this, 'begin');
        $htmlForm = preg_replace('/\s*id=".*?"/', '', $htmlForm);
        $htmlForm = "\n<form$id class='$formClass'$presetCallback $dataFormInx" . substr($htmlForm, 5);
        $html .= $htmlForm;
        $html .= $this->getRenderer()->render($this, 'errors');
        $html .= $this->renderFormTopBanner();
        $html .= "\n\n<div class='pfy-elems-wrapper'>\n";

        return $html;
    } // renderFormHead


    /**
     * @return string
     * @throws InvalidArgumentException
     */
    protected function renderFormFields(array|null $formElements = null): string
    {
        if (!$this->showForm) {
            return '';
        }

        if ($formElements !== null) {
            $this->formElements = $formElements;
        }

        foreach ($this->formElements as $key => $rec) {
            if (preg_match('/\W/', $key)) {
                throw new \Exception("Error: fishy character in form-element name '$key'");
            }
        }

        $html = '';
        foreach ($this->formElements as $name => $rec) {
            $html .= $this->renderFormElement($name);
        } // loop over formElements

        return $html;
    } // renderFormFields


    /**
     * @return string
     * @throws \Exception
     */
    protected function renderFormTail(): string
    {
        if (!$this->showForm) {
            return '';
        }

        $html = '';
        $html .= $this->renderFormButtons();

        // add standard hidden fields to identify data: which form, which data-record:
        $html .= $this->_renderFormTail();
        // handle deadline option:
        if ($this->deadlinePassed) {
            $this->showForm = $this->isFormAdmin;
        }

        // handle maxCount option:
        if ($this->maxCountExceeded) {
            $this->showForm = $this->isFormAdmin;
        }

        if ($this->popupMode) {
            $html .= "</div><!-- /pfy-fully-hidden -->\n";
        }
        return $html;
    } // renderFormTail


    /**
     * @return string
     */
    protected function renderFormTableWrapperTail(): string
    {
        $html = '';
        if ($this->addFormTableWrapper) {
            $html = "</div><!-- /pfy-form-and-table-wrapper -->\n";
        }
        return $html;
    } // renderFormTableWrapperTail


    /**
     * @return string
     * @throws InvalidArgumentException
     */
    private function _renderFormTail(): string
    {
        // add standard hidden fields to identify data: which form, which data-record:
        $html = '';
        if ($this->file) {
            $elem = $this['_reckey'];
            $valueToInject = ($this->formDataRec['_reckey']??false);
            if ($recKey = $valueToInject ?: $this->lastCreatedRecKey) { //???
                $elem->setHtmlAttribute('data-value', $recKey);
            }
            $html .= $elem->getControl() . "\n";

            $elem = $this['_dataSrcInx'];
            $html .= $elem->getControl() . "\n";


            $elem = $this['_csrf'];
            $html .= $elem->getControl() . "\n";
        }

        $html .= "</div><!-- /pfy-elems-wrapper -->\n";

        $html .= $this->renderFormBottomBanner();

        $html .= $this->getRenderer()->render($this, 'end'); // </form>

        $html .= "</div><!-- /pfy-form-wrapper -->\n\n\n";
        return $html;
    } // _renderFormTail


    /**
     * @return string
     * @throws \Exception
     */
    private function renderFormTopBanner(): string
    {
        if ($str = $this->formOptions['formTop']) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-top'>$str</div>\n";
        }
        return $str;
    } // renderFormTopBanner


    /**
     * @return string|false
     * @throws \Exception
     */
    private function renderFormHintBanner(): string|false
    {
        if (!($str = ($this->formOptions['formHint']??false)) && $this->requiredInputFound) {
            if ($this->requiredInputFound['_']??false) {
                unset($this->requiredInputFound['_']);
                $str .= "<div>{{ pfy-form-required-info }}</div>";
            }
            if ($this->requiredInputFound) {
                $s = '';
                foreach ($this->requiredInputFound as $r) {
                    $s .= "$r,";
                }
                $s = rtrim($s, ', ');
                $s = "<span class='pfy-form-required-group-marker'>$s</span>";
                $str .= "<div>$s {{ pfy-form-required-group-info }}</div>";
            }
        }
        if ($str) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-hint'>\n$str\n</div><!-- /pfy-form-hint -->\n";
        }
        return $str;
    } // renderFormHintBanner


    /**
     * @return string
     * @throws \Exception
     */
    private function renderFormBottomBanner(): string
    {
        if ($str = ($this->formOptions['formBottom']??false)) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-bottom'>$str</div>\n";
        }
        return $str;
    } // renderFormBottomBanner


    /**
     * @return string
     * @throws \Exception
     */
    private function renderFormButtons(): string
    {
        $html = '';
        if ($this->formButtons) {
            $html = $this->renderFormHintBanner();
            $html .= <<<EOT
<div class="pfy-elem-wrapper pfy-form-buttons">
<span class="pfy-input-wrapper">
$this->formButtons</span>
</div>

EOT;
        }
        return $html;
    } // renderFormButtons


    /**
     * @return string
     * @throws \Exception
     */
    protected function renderDataTable(): string
    {
        if (!($this->formOptions['tableOptions'] && $this->file && $this->isFormAdmin)) {
            return '';
        }

        // to be on the save side: always invoke robots header when displaying form data.
        Page::applyRobotsAttrib();

        $dt = $this->openDataTable();
        $html = $dt ? $dt->render() : '';
        $this->tableId = $dt->getTableId();
        if (!$this->tableTitle) {
            $header = '<div class="pfy-table-data-output-header">{{ pfy-table-data-output-header }}</div>';
        } elseif (preg_match('/\W/', $this->tableTitle)) {
            $header = compileMarkdown($this->tableTitle);
            $header = "<div class='pfy-table-data-output-header'>$header</div>";
        } else {
            $header = "<div class='pfy-table-data-output-header'>$this->tableTitle</div>";
        }
        if ($html) {
            $html = <<<EOT
<div class='pfy-table-data-output-wrapper'>
$header
$html
</div><!-- /pfy-table-data-output-wrapper -->

EOT;
        }

        return $html;
    } // renderDataTable


    /**
     * @return string
     * @throws \Exception
     */
    protected function renderProblemWithFormBanner(): string
    {
        $html = '';
        if ($text = ($this->formOptions['problemWithFormBanner'] ?? false)) {
            $var = ($text === true)? 'pfy-problem-with-form-banner' : $text;
            $banner = TransVars::getVariable($var);
            if ($banner) {
                $banner = markdown($banner);
                $html .= "\n$banner\n";
            }
        }
        return $html;
    } // renderProblemWithFormBanner


    /**
     * @param string $str
     * @return string
     * @throws \Exception
     */
    private function compileFormBanner(string $str): string
    {
        if (($str[0]??'') !== '<') {
            $str = markdown($str);
        }
        if (str_contains($str, '{{')) {
            $str = TransVars::translate($str);
        }
        $str = $this->handleFormBannerValues($str);
        return $str;
    } // compileFormBanner


    /**
     * @param string $str
     * @return string
     * @throws \Exception
     */
    private function handleFormBannerValues(string $str): string
    {
        foreach ($this->auxBannerValues as $key => $value) {
            $str = str_replace("%$key%", $value, $str);
        }

        // %deadline%:
        if (str_contains($str, '%deadline%') && ($deadline = $this->formOptions['deadline'])) {
            if (isset(self::$scheduleRecs[self::$formCounter]['start'])) {
                $t = strtotime(self::$scheduleRecs[self::$formCounter]['start']);
            } else {
                $t = time();
            }

            $deadlineStr = Utils::timeToString($deadline, timeRef: $t);
            $str = str_replace('%deadline%', $deadlineStr, $str);
        }

        // %count%:
        if (str_contains($str, '%count%')) {
            $count = 0;
            $this->openDB();
            if ($this->db) {
                $count = $this->db->count();
            }
            $str = str_replace('%count%', $count, $str);

        }

        // %sum%:
        if (str_contains($str, '%sum%')) {
            $sum = 0;
            $this->openDB();
            if ($this->db) {
                if ($maxCountOn = $this->formOptions['maxCountOn']) {
                    $sum = $this->db->sum($maxCountOn);
                } else {
                    $sum = $this->db->count();
                }
            }
            $str = str_replace('%sum%', $sum, $str);
        }

        // %available%:
        if (str_contains($str, '%available%') && ($maxCount = $this->formOptions['maxCount'])) {
            $this->openDB();
            if ($maxCountOn = $this->formOptions['maxCountOn']) {
                $currCount = $this->db->sum($maxCountOn);
            } else {
                $currCount = $this->db->count();
            }
            $available = $maxCount - $currCount;
            $str = str_replace('%available%', $available, $str);
        }

        // %max% or %total%:
        if (str_contains($str, '%max%') || str_contains($str, '%total%')) {
            $max = $this->formOptions['maxCount']?:'{{ pfy-unlimited }}';
            $str = str_replace(['%max%','%total%'], $max, $str);
        }

        foreach ($this->auxBannerValues as $key => $value) {
            $str = str_replace("%$key%", $value, $str);
        }

        // remove remaining variable patterns from string:
        $str = preg_replace("/\%\w{1,12}\%/", '', $str);

        return $str;
    } // handleFormBannerValues


    /**
     * @param string $input
     * @param string $name
     * @return string
     */
    private function fixInputInsideLabelQuirk(string $input, string $name): string
    {
        if (preg_match_all('|<label\s?(for="(.*?)")?><input (.*?)>(.*?)</label>|', $input, $m)) {
            $input1 = '';
            foreach ($m[2] as $i => $mm) {
                $formCounter = self::$formCounter;
                $elemInx = $this->formElements[$name]['elemInx'];
                if ($m[2][$i]) {
                    $id = $m[2][$i];
                    $for = $m[1][$i];
                } else {
                    $id = $m[2][$i] ?: "pfy-input-$formCounter-$elemInx-" . ($i + 1);
                    $for = "for='$id'";
                }
                $inputAttrs = $m[3][$i];
                $width = '';
                if ($w = $this->formElements[$name]['optionWidth'] ?? false) {
                    $width = " style='min-width:$w'";
                }
                if (str_contains($inputAttrs, 'id=')) {
                    $inputEl = "<input $inputAttrs>";
                } else {
                    $inputEl = "<input id='$id' $inputAttrs>";
                }
                $input1 .= "<span class='pfy-choice-wrapper'$width>$inputEl<label $for>{$m[4][$i]}</label></span>";
            }
            $input = $input1;
        }
        return $input;
    } // fixInputInsideLabelQuirk




    // === Received Data Processing =================================================
    /**
     * @return string
     */
    protected function __processReceivedData(): void
    {
        if (!$this->isSuccess()) {
            return;
        }

        $dataRec = $this->getValues('array');

        // handle 'cancel' button:
        if (isset($_POST['cancel'])) {
            reloadAgent();
        }

        if (($this->formOptions['confirmationText'] === '') || (isset($_GET['quiet']))) {
            $this->showFeedbackInpage = false;
        }

        if (isset($_POST['cancel'])) {
            return;
        }

        // check presence of $formInxReceived:
        $formInxReceived = $dataRec['_dataSrcInx'] ?? false;

        // check if page contains multiple forms, if so, check and skip the other ones:
        $sessKey = "form:" . PFY_PAGE_ID . ":formCount";
        if (kirby()->session()->get($sessKey, false)) {
            // check whether received data applies to currently processed form (e.g. if there are multiple forms in a page):
            if (intval($formInxReceived) !== $this->formIndex) {
                return; // signal 'processing skipped, continue processing'
            }
        }

        $csrf = $_POST['_csrf']??false;
        if (!$csrf || !csrf($csrf)) {
            reloadAgent(null, '{{ pfy-form-session-expired }}');
        }

        $this->restoreBypassedFields($dataRec);

        $this->securityChecks($dataRec);

        // handle 'dataReceivedCallback' on data received:
        if ($this->formOptions['dataReceivedCallback']) {
            list($html, $continueEval) = $this->handleCallback($dataRec);
            if (!$continueEval) {
                $this->formResponse =  $html;
                return;
            }
        }

        $recKey = $dataRec['_reckey']??false;
        
        // handle delete request:
        if ($this->handleDeleteRequest($dataRec, $recKey)) {
            $this->formResponse =  '{{ pfy-form-rec-deleted-confirmation }}';
            return;
        }

        $origDataRec = $dataRec;
        $dataRec = $this->normalizeData($dataRec);
        $this->formDataRec = $dataRec;

        if (is_string($dataRec)) {
            // string means spam detected:
            $this->showForm = false;
            $this->formResponse =  "<div class='pfy-form-error'>$dataRec</div>\n";
            return;
        }

        // handle required groups:
        if ($this->applyRequiredGroupCheck($dataRec)) {
            $this->formErrorState = true;
            return;
        }

        // handle optional 'deadline' and 'maxCount':
        if ($this->deadlinePassed || $this->checkMaxCount($dataRec)) {
            if (!$this->isFormAdmin) {
                return;
            }
        }

        // handle uploads
        $dataRec = $this->handleUploads($dataRec);

        // if 'file' defined, save received data:
        $formErrorResponse = '';
        if ($this->file) {
            $formErrorResponse = $this->storeSubmittedData($dataRec, $recKey);
            if ($formErrorResponse) {
                mylog($formErrorResponse, 'form-log.txt');
                $this->formErrorState = true;

            } else {
                $logMsg = 'Stored: '.PFY_PAGE_URI."[$formInxReceived] ";
                $logMsg .= var_r($dataRec);
                mylog($logMsg, 'form-log.txt');
            }
            if ($this->keepSubmittedDataInForm) {
                $origDataRec['_reckey'] = $this->lastCreatedRecKey;
                foreach ($origDataRec as $key => $value) {
                    if (is_array($value)) {
                        $origDataRec[$key] = implode(',', $value);
                    }
                }
                Utils::setSessionVar("form-$formInxReceived", $origDataRec, overrideKey:$this->formDataId);
                $this->showForm = true;
            }
        }

        if ($this->formErrorState) {
            // error:
            $this->formResponse = "<div class='pfy-form-error'>$formErrorResponse</div>\n";
            return;
        }

        // success:
        if ($this->formOptions['mailTo']) {
            $this->sendOwnerNotification($dataRec);
        }

        if ($this->formOptions['confirmationText']) {
            $formSuccessResponse = $this->formOptions['confirmationText'];
        } else {
            $formSuccessResponse = "{{ pfy-form-submit-success }}";
        }
        $formSuccessResponse = "<div class='pfy-form-success'>$formSuccessResponse</div>\n";

        // handle optional confirmation mail:
        $formSuccessResponse .= $this->sendConfirmationMail($dataRec);

        // write log:
        mylog(strip_tags($formSuccessResponse), 'form-log.txt');

        if (isset($_POST)) {
            unset($_POST);
        }

        if ($this->showFeedbackInpage) {
            if ($formSuccessResponse) {
                $formSuccessResponse = "<div class='pfy-form-response'>\n$formSuccessResponse\n</div><!-- /pfy-form-response -->\n";
            }

            // in case there are multiple forms in the page, hide all others:
            // (nette forms would preset received data in other forms)
            Page::addCss('.pfy-form-and-table-wrapper {display: none;}');
        }
        $this->formResponse = $formSuccessResponse;
    } // __processReceivedData


    /**
     * @param mixed $dataRec
     * @return void
     */
    private function handleUploads(mixed $dataRec): array
    {
        foreach ($dataRec as $key => $formElem) {
            if (is_array($formElem)) {
                $names = '';
                foreach ($formElem as $formElemKey => $formElemVal) {
                    if (is_a($formElemVal, 'Nette\Http\FileUpload')) {
                        if ($name = $this->handleUploadedFile($formElemVal, $key, $dataRec)) {
                            $names .= "$name, ";
                        }
                    }
                }
                if ($names) {
                    $dataRec[$key] = rtrim($names, ', ');
                }

            } else {
                if (is_a($formElem, 'Nette\Http\FileUpload')) {
                    $name = $this->handleUploadedFile($formElem, $key, $dataRec);
                    $dataRec[$key] = $name;
                }
            }
        }
        return $dataRec;
    } // handleUploads


    /**
     * @param string $key
     * @param object $rec
     * @throws \Exception
     */
    private function handleUploadedFile(object $uploadObj, string $key, array $dataRec): string
    {
        if (!$uploadObj->name) {
            return '';
        }

        $path = $this->formElements[$key]['path']??false;
        if ($p = (strpos($path, '$'))) {
            // case given path contains patter '$xy', where xy is name of other data element:
            $k = substr($path, $p+1);
            $k = preg_replace('|\W.*|', '', $k); // remove trailing characters
            if (isset($dataRec[$k])) {
                $path1 = $dataRec[$k];
            }
            $path = fixPath(substr($path, 0, $p).$path1);
        }
        if (!$path) {
            // case nothing specified -> use ~/uploads/:
            $path = '~/uploads/';
        }
        $path = resolvePath($path);
        preparePath($path);
        $filename = $uploadObj->name;
        $filename = basename($filename);
        $filename = str_replace(['..', ' '],['.', '_'], $filename);
        $filename = preg_replace('/[^.\w-]/','', $filename);
        $uploadObj->move($path.$filename);
        return $filename;
    } // handleUploadedFile


    /**
     * @param array $dataRec
     * @return void
     */
    private function restoreBypassedFields(array &$dataRec): void
    {
        $bypassedElements = array_keys($this->bypassedElements);
        foreach ($dataRec as $name => $value) {
            if (in_array($name, $bypassedElements)) {
                $dataRec[$name] = $this->bypassedElements[$name];
            }
        }
    } // restoreBypassedFields


    /**
     * Applies security check(s:
     * - script injection, e.g. "<script>alert('malicious code')</script>"
     * @param array $dataRec
     * @return void
     * @throws \Exception
     */
    private function securityChecks(array &$dataRec): void
    {
        $crit = $this->formOptions['scriptInjectionFilter'];
        $crit = ($crit === true) ? 'admin|localhost' : $crit;
        if (Permission::evaluate($crit)) {
            return; // skip check
        }

        // perform script injection check on overy data element:
        foreach ($dataRec as $name => $value) {
            if ($value && is_string($value) && str_contains($value, '<')) {
                $dataRec[$name] = str_replace(['<', '>'], ['&lt;', '&gt;'], $value);
                mylog("!!! Security check: possible script injection detected in '$name':\n\"$value\"");
            }
        }
    } // securityChecks


    /**
     * @param array $dataRec
     * @return array
     */
    private function normalizeData(array $dataRec): array|string
    {
        $this->origReceivedData = $dataRec;

        foreach ($dataRec as $name => $value) {
            // handle special case "rrule":
            if ($name === 'rrule') {
                $this->saveRepeatedEvents($name, $dataRec);
                continue;
            }

            // handle anti-spam field:
            if ($this->formElements[$name]['antiSpam'] ?? false) {
                if ($value !== '') {
                    mylog("Spam detected: field '$name' was not empty: '$value'.", 'form-log.txt');
                    return TransVars::getVariable('pfy-anti-spam-warning');
                }
                unset($dataRec[$name]);
            }

            // handle 'saveAs' attrib to manipulate data before storing:
            if ($saveAs = ($this->formElements[$name]['saveAs'] ?? false)) {
                while (preg_match('/\$([\w-]+)/', $saveAs, $m)) {
                    $varName = $m[1];
                    $v = $dataRec[$varName] ?? '';
                    $saveAs = str_replace($m[0], "'$v'", $saveAs);
                }
                if (str_contains($saveAs, '%')) {
                    $saveAs = str_replace(['%today%', '%now%'], ['\'' . date('Y-m-d') . '\'', '\'' . date('Y-m-d H:i') . '\''], $saveAs);
                }
                try {
                    $value = eval("return $saveAs;");
                    $dataRec[$name] = $value;
                } catch (\Exception $e) {
                    exit($e);
                }
            }
        }

        // sanitize data:
        foreach ($dataRec as $name => $value) {
            // filter out any fields starting with '_':
            if ($name[0] === '_') {
                unset($dataRec[$name]);

            } elseif ($value === null) {
                $dataRec[$name] = '';

            } elseif (is_array($value) && isset($this->choiceOptions[$name])) {
                $template = $this->choiceOptions[$name];
                $value1 = [];
                $value1[ARRAY_SUMMARY_NAME] = '';
                foreach ($template as $key => $name1) {
                    $value1[$key] = in_array($key, $value);
                    if ($value1[$key]) {
                        $value1[ARRAY_SUMMARY_NAME] .= $key.',';
                    }
                }
                $value1[ARRAY_SUMMARY_NAME] = rtrim($value1[ARRAY_SUMMARY_NAME], ',');
                $dataRec[$name] = $value1;

            // handle comment's reveal-controller: if unchecked, we erase the textarea entry:
            } elseif (str_starts_with($name, 'CommentController')) {
                $commentController = $value;
                unset($dataRec[$name]);

            } elseif (isset($commentController)) {
                // the last element was a reveal-controller -> erase value if it was unchecked:
                if (!$commentController) {
                    $dataRec[$name] = '';
                }
                unset($commentController);
            }

            // case password: store hash rather than original password:
            $type = $this->formElements[$name]['type']??false;
            if ($type === 'password') {
                $dataRec[$name] = password_hash($value, null);
            }
        }
        return $dataRec;
    } // normalizeData


    /**
     * @param string $name
     * @param array $dataRec
     * @return void
     * @throws \Exception
     */
    private function saveRepeatedEvents(string $name, array &$dataRec): void
    {
        $recKey = $dataRec['_reckey']??false;
        $allowedEventFieldNames = ',DTSTART,DTEND,FREQ,UNTIL,COUNT,INTERVAL,WKST,BYWEEKDAY,BYDAY,BYMONTH,';
        if ($dataRec['_freq'] !== 'NONE') {
            $rrule = 'DTSTART:'. Events::convertDatetime($dataRec['start']??'')."\n";
            $rrule .= ($this->formElements[$name]['saveAs'] ?? '');
            $rruleElems = [];
            while (preg_match('/\$([\w-]+)/', $rrule, $m)) {
                $varName = $m[1];
                $v = $dataRec[$varName] ?? '';
                if (is_array($v)) {
                    $v = implode(',', $v);
                }
                $rrule = str_replace($m[0], (string)$v, $rrule);
                $vName = strtoupper(ltrim($varName, '_'));
                if ($v && str_contains($allowedEventFieldNames, ",$vName,") && ($vName !== 'INTERVAL' || $v !== '1')) {
                    $vName = ($vName === 'BYWEEKDAY') ? 'BYDAY' : $vName;
                    $rruleElems[$vName] = $v;
                }
            }
            // "RRULE:FREQ=WEEKLY;COUNT=4;INTERVAL=1;WKST=2024-06-17T20:42;BYDAY=WE,FR;BYMONTH=;"
            $rrule = preg_replace('/(INTERVAL=1;|\w+=;)/', '', $rrule);
            $dataRec[$name] = $rrule;
            $this->executeRRule($rruleElems, $dataRec, $recKey);
        }
    } // saveRepeatedEvents


    /**
     * @param array $rRules
     * @param array $dataRec
     * @param string $recKey
     * @return void
     * @throws \Exception
     */
    private function executeRRule(array $rRules, array $dataRec, string $recKey): void
    {
        $from = $dataRec['start'];
        $startTime = 'T'.substr($from, 11, 5);
        $till = $dataRec['end'];
        $endTime = 'T'.substr($till, 11, 5);

        if ($from) {
            $rRules['DTSTART'] = Events::convertDatetime($from);
        }
        if ($until = $dataRec['_until']??false) {
            $rRules['UNTIL'] = Events::convertDatetime($until);
        } elseif ($count = ($dataRec['_count']??false)) {
            $rRules['COUNT'] = $count;
        }

        $dataRec = array_filter($dataRec, function ($k) {
            return $k[0] !== '_';
        }, ARRAY_FILTER_USE_KEY);
        $dataRec['parentEvent'] = $dataRec['start'];
        $newEvents = [];

        // compile rrule:
        try {
            $rrule = new RRule(array_change_key_case($rRules));

            $event = [];
            foreach ($rrule as $occurrence) {
                $event['start'] = $occurrence->format('Y-m-d') . $startTime;
                $event['end'] = $occurrence->format('Y-m-d') . $endTime;
                $newEvents[] = $event + $dataRec;
            }
        } catch (\Exception $e) {
            throw new \Exception("Error: improple date/time format in Events (".$e->getMessage().")");
        }

        // save newly created events (exclude first as that will be saved later the normal way):
        array_shift($newEvents);
        foreach ($newEvents as $newRec) {
            $res = $this->saveRec($newRec, $recKey);
 //ToDo: eval $res, report errors
        }
    } // executeRRule


    /**
     * @param string $file
     * @return object|false
     * @throws \Exception
     */
    private function openDB(): object|false
    {
        if ($this->db) {
            return $this->db;
        }
        if (is_bool($this->file)) {
            return false;
        }
        $this->db = new DataSet($this->file, $this->formOptions['dbOptions']);

        // remember db-file for use by ajax_server.php, if user is form-admin:
        if ($this->isFormAdmin) {
            $pgUri = PFY_PAGE_ID;
            $sessKey = "db:$pgUri:$this->formIndex:file";
            kirby()->session()->set($sessKey, resolvePath($this->file));
        }
        return $this->db;
    } // openDB


    /**
     * @param array $newRec
     * @param string $file
     * @return false|string // no error | error msg
     * @throws \Exception
     */
    private function storeSubmittedData(array $newRec, string|false $recId = false): bool|string
    {
        foreach ($newRec as $key => $rec) {
            if (is_array($rec)) {
                foreach ($rec as $k => $r) {
                    if (is_a($r, 'Nette\Http\FileUpload')) {
                        unset($newRec[$key][$k]);
                    }
                }
            } else {
                if (is_a($rec, 'Nette\Http\FileUpload')) {
                    unset($newRec[$key]);
                }
            }
        }
        if (!$newRec) {
            return false;
        }
        return  $this->saveRec($newRec, $recId); // false or err-msg
    } // storeSubmittedData


    /**
     * @param array $newRec
     * @param string $recId
     * @return false|string
     * @throws \Exception
     */
    private function saveRec(array $newRec, string $recId): string|false // error if err-msg
    {
        $this->openDB();

        if (!$recId && $this->db->recExists($newRec)) {
            return '{{ pfy-form-warning-record-already-exists }}';
        }

        if (!$recId || $recId === '_create-new_') {
            $recId = createHash();
        }

        $recId = $this->db->addRec($newRec, recKeyToUse: $recId)->recId();
        if (is_string($recId)) {
            $this->lastCreatedRecKey = $recId;
        }
        return false;
    } // saveRec


    /**
     * @return DataTable|false
     * @throws \Exception
     */
    private function openDataTable(): DataTable|false
    {
        if ($this->dataTable && ($this->formIndex === $this->dataTable->inx)) {
            return $this->dataTable;
        }

        $tableOptions = $this->tableOptions;

        $showAllFields = $tableOptions['showAllFields'];
        $fieldNames = $this->fieldNames;
        foreach (['_reckey', '_dataSrcInx', '_csrf'] as $k) {
            if (isset($fieldNames[$k])) {
                unset($fieldNames[$k]);
            }
        }
        foreach ($fieldNames as $key => $fieldLabel) {
            if (!$fieldLabel || !is_string($fieldLabel)) {
                continue;
            }
            if (str_contains($fieldLabel, '{{')) {
                $fieldLabel = TransVars::getVariable(trim($fieldLabel, '{ }'), varNameIfNotFound:true);
            }
            $fieldLabel = rtrim($fieldLabel, ':');
            if (!$showAllFields && ($key[0] === '_')) {
                unset($fieldNames[$key]);
                continue;
            }
            $elem = $this->formElements[$fieldLabel]??[];
            if ($elem['name']??false) {
                $fieldNames[$key] =  $elem['name'];
            } else {
                $fieldNames[$key] =  $fieldLabel;
            }
        }

        if (isset($tableOptions['tableHeaders'])) {
            $tableOptions['headers'] = $tableOptions['tableHeaders'];
            unset($tableOptions['tableHeaders']);
        }
        $tableOptions['headers'] = $tableOptions['headers'] ?: $fieldNames;

        $file = $this->file;
        if ($tableOptions['file']??false) {
            // special case: formOptions[file] != tableOption[file] ==> used where using client cache for large tables
            $file = $tableOptions['file'];
        }
        $this->dataTable = new DataTable($file, $tableOptions);
        return $this->dataTable;
    } // openDataTable


    /**
     * @param array $dataRec
     * @param string $recKey
     * @return bool
     * @throws \Exception
     */
    private function handleDeleteRequest(array $dataRec, string $recKey): bool
    {
        if (!$recKey) {
            return false;
        }

        if (!($dataRec['_delete']??false)) {
            return false;
        }

        $this->openDB();
        if ($rec = $this->db->find($recKey)) {
            $rec->delete(true);
        }
        return true;
    } // handleDeleteRequest


    /**
     * @param $dataRec
     * @return bool
     */
    private function applyRequiredGroupCheck($dataRec): bool
    {
        $errorsFound = false;
        $requiredGroups = [];
        foreach ($dataRec as $key => $value) {
            if (($required = ($this->formElements[$key]['required']??false)) && (!is_bool($required))) {
                $requiredGroups[$required]['names'][] = $key;
                if (!isset($requiredGroups[$required]['all-empty'])) {
                    $requiredGroups[$required]['all-empty'] = true;
                }
                $requiredGroups[$required]['all-empty'] = !$value && $requiredGroups[$required]['all-empty'];
            }
        }
        if ($requiredGroups) {
            foreach ($requiredGroups as $requiredGroup) {
                if ($requiredGroup['all-empty']) {
                    $errorsFound = true;
                    $affectedElems = $requiredGroup['names'];
                    foreach ($affectedElems as $name) {
                        $this[$name]->addError('{{ pfy-form-required-group-empty }}');
                    }
                }

            }
        }
        return $errorsFound;
    } // applyRequiredGroupCheck


    /**
     * @return string|false
     */
    private function checkDeadline(): void
    {
        if ($deadlineStr = $this->formOptions['deadline']??false) {

            if (isset(self::$scheduleRecs[self::$formCounter]['start'])) {
                $t = strtotime(self::$scheduleRecs[self::$formCounter]['start']);
            } else {
                $t = time();
            }
            $deadline = strtotime($deadlineStr, $t);
            // if no time is defined, extend the deadline till midnight:
            if (!str_contains($deadlineStr, 'T')) {
                $deadline += 86400;
            }
            // now check deadline:
            if ($deadline < time()) { // deadline expired:
                // deadline is overridden if visitor is logged in:
                if (!$this->isFormAdmin) {
                    if ($deadlineNotice = ($this->formOptions['deadlineNotice']??false)) {
                        $this->deadlinePassed .= $deadlineNotice;
                    } else {
                        $this->deadlinePassed .= '<div class="pfy-form-issue pfy-form-deadline-expired">{{ pfy-form-deadline-expired }}</div>';
                    }
                } else {
                    $this->deadlinePassed .= TransVars::getVariable('pfy-form-deadline-expired-warning');
                }
            }
        }
    } // checkDeadline


    /**
     * @param array $dataRec
     * @return string|false
     * @throws \Exception
     */
    private function checkMaxCount(array $dataRec = []): bool
    {
        if ($dataRec && ($maxCountOn = $this->formOptions['maxCountOn'])) {
            $pending = $dataRec[$maxCountOn]??1;
        } else {
            $pending = 1;
        }
        list($available, $maxCount, $currCount) = $this->getAvailableAndMaxCount();
        if ($maxCount) {
            if ($pending) {
                $currCount += ($pending - 1);
            }
            if ($currCount >= $maxCount) {
                if (!$this->isFormAdmin) {
                    if ($maxCountNotice = ($this->formOptions['maxCountNotice']??false)) {
                        $this->maxCountExceeded = $maxCountNotice;
                    } else {
                        $this->maxCountExceeded = '<div class="pfy-form-issue pfy-form-maxcount-reached">{{ pfy-form-maxcount-reached }}</div>';
                    }
                } else {
                    $this->maxCountExceeded = TransVars::getVariable('pfy-form-maxcount-reached-warning');
                }
            }
        }
        return (bool)$this->maxCountExceeded;
    } // checkMaxCount




    // === Sending Mails ==================================================================
    /**
     * @param array $dataRec
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function sendOwnerNotification(array $dataRec): void
    {
        $out = '';
        $labelLen = 0;
        foreach ($dataRec as $key => $value) {
            $labelLen = max($labelLen, strlen($key));
        }
        $labelLen += 5;
        $dataRec = $this->origReceivedData + $dataRec;
        foreach ($dataRec as $key => $value) {
            if ($key[0] === '_') {
                continue;
            }
            $type = $this->formElements[$key]['type']??false;
            if ($type === 'password') {
                $value = '*****';
            }
            $key1 = str_pad("$key: ", $labelLen, '. ');
            if (is_array($value)) {
                $value = $value[ARRAY_SUMMARY_NAME]??'';
            }
            $out .= "$key1 $value\n";
            $dataRec[$key] = $value;
        }
        $dataRec['_data_'] = $out;

        list($subject, $message) = $this->getEmailComponents('notificationTemplate', $dataRec, 'pfy-form-owner-notification');

        $to = $this->formOptions['mailTo']?: PageFactory::$webmasterEmail;
        if ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }

        // dev mode -> override $to:
        if (PageFactory::$dev && ($mailOverride = kirby()->option('pgfactory.pagefactory.emailDevModeOverride'))) {
            $to = $mailOverride;
        }

        if (!is_string($to)) {
            mylog("Error sending OwnerNotification to '{$to}'.");
        } elseif (str_contains($to, ',')) {
            $to = explodeTrim(',', $to);
        }
        $this->sendMail($to, $subject, $message, logComment: 'Notification Mail to Owner');
    } // sendOwnerNotification


    /**
     * @param array $dataRec
     * @return mixed
     * @throws \Exception
     */
    private function sendConfirmationMail(array $dataRec): mixed
    {
        if (!($confirmationMail = $this->formOptions['confirmationEmailTo']??false)) {
            return '';
        }
        $dataRec = $this->origReceivedData + $dataRec;
        $eventData = $this->auxBannerValues;
        foreach ($eventData as $key => $value) {
            $value = TransVars::getVariable($value, true);
            if ($value) {
                $eventData[$key] = $value;
            }
        }
        $dataRec += $eventData;
        $dataRec['hostUrl'] = PFY_HOST_URL;

        list($subject, $message) = $this->getEmailComponents('confirmationTemplate', $dataRec, 'pfy-confirmation-response');

        if (str_contains($confirmationMail, '@')) {
            $to = $confirmationMail;
        } else {
            $confirmationMail = str_replace('-', '_', $confirmationMail);
            $to = $dataRec[$confirmationMail]??false;
        }
        if ($to) {
            $this->sendMail($to, $subject, $message, logComment: 'Confirmation Mail to Visitor');
            return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-sent }}</div>\n";
        }
        return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-missing }}</div>\n";
    } // sendConfirmationMail


    /**
     * @param string $varName       name of transvar that contains elements 'subject' and 'message',
     *                              each optionally containing language variants like de: xxx, _: yyy
     * @param array $dataRec
     * @param string $legacyVarName if transver[varName] not exists, falls back to transver[$legacyVarName-subject] resp.
     *                              transver[$legacyVarName-message] resp. transver[$legacyVarName-body]
     *
     * @return array
     */
    private function getEmailComponents(string $varName, array $dataRec, string $legacyVarName = ''): array
    {
        $subject = '';
        $message = '';
        $confirmationEmailTemplateVar =  $this->formOptions[$varName] ?? $varName;
        if ($confirmationEmailTemplate = TransVars::$transVars[$confirmationEmailTemplateVar] ?? false) {
            $subject = $confirmationEmailTemplate['subject']??false;
            $subject = TransVars::selectLangVariantOfTransVar($subject);

            $message = $confirmationEmailTemplate['message']??false;
            $message = TransVars::selectLangVariantOfTransVar($message);
        }

        $subject = $subject ?: TransVars::getVariable($legacyVarName.'-subject', varNameIfNotFound:true);
        $message = $message ?: (TransVars::getVariable($legacyVarName.'-body') ?: TransVars::getVariable($legacyVarName.'-message', varNameIfNotFound:true));

        $dataRec['host'] = PFY_HOST_URL;

        $subject = $this->compileTempate($subject, $dataRec);
        $message = $this->compileTempate($message, $dataRec);

        return [$subject, $message];
    } // getEmailComponents


    /**
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string $debugInfo
     * @return void
     */
    private function sendMail(string|array $to, string $subject, string $body, string $cc = '', $html = '', $logComment = ''): void
    {
        $props = [
            'to' => $to,
            'from' => $this->formOptions['mailFrom'] ?: TransVars::getVariable('webmaster_email'),
            'fromName' => $this->formOptions['mailFromName'] ?: false,
            'subject' => $subject,
            'body' => $body,
        ];
        if ($cc) {
            $props['cc'] = $cc;
        }
        if ($html) {
            $props['body'] = [
                'html' => $html,
                'text' => $body,
            ];
        }

        if (is_array($to)) {
            $to = implode(',', $to);
        }

        new PHPMailer($props);
        if ($logComment) {
            mylog("$logComment $to:\n$subject\n\n$body", 'mail-log.txt');

        } else {
            mylog("$to:\n$subject\n\n$body", 'mail-log.txt');
        }
    } // sendMail





    // === Options Parsing ================================================================
    /**
     * @param array $formOptions
     * @return array
     */
    private function parseOptions(array $formOptions): array
    {
        $formOptions = $formOptions + PFY_FORM_OPTIONS;
        $this->formOptions                  = $formOptions;
        $formOptions                        = &$this->formOptions;

        $formOptions['dbOptions'] = $formOptions['dbOptions'] + PFY_FORM_OPTIONS['dbOptions'];

        // make sure essential options are instantiated:
        $formOptions['confirmationEmail']   = str_replace('-', '_', $formOptions['confirmationEmail']??'');
        $formOptions['emailFieldName']      = str_replace('-', '_', $formOptions['emailFieldName']??'');
        $formOptions['next']                = $formOptions['next'] ?: PFY_FORM_OPTIONS['next'];

        $this->formIndex                    = $formOptions['formInx'] ?? self::$formCounter;
        $this->file                         = $formOptions['file'];
        $this->showFeedbackInpage           = ($formOptions['feedback'][0]??'') === 'i';// inpage|banner
        if (isset($formOptions['showDirectFeedback'])) { // depricated
            $this->showFeedbackInpage = $formOptions['showDirectFeedback'];
            unset($formOptions['showDirectFeedback']);
        }
        $this->recLocking                   = $formOptions['recLocking'];
        $this->formWrapperClass             = $formOptions['wrapperClass']? ' '.$formOptions['wrapperClass'] :'';
        $this->readonly                     = $formOptions['readonly'];
        $this->keepSubmittedDataInForm      = $formOptions['retainData'];
        $this->formDataId                   = ($formOptions['formDataId'] !== null) ? $formOptions['formDataId'] : false;

        $this->sideBySide                   = $formOptions['sideBySide'];
        if ($this->sideBySide !== null) {
            $formOptions['showFeedbackInpage'] = false;
        }
        if ($this->sideBySide) {
            $formOptions['outerWrapperClass'] .= ' pfy-side-by-side';
        }

        if ($formOptions['tableOptions']) {
            $this->tableOptions = $this->parseTableOptions($formOptions['tableOptions']);
        } else {
            $this->tableOptions = false;
        }

        $this->formOptions = $formOptions;

        return $formOptions;
    } // parseOptions


    /**
     * @param array $tableOptions
     * @return array
     */
    private function parseTableOptions(array $tableOptions): array
    {
        $this->showTable = true;

        $tableOptions += PFY_FORM_OPTIONS['tableOptions'];
        if (!isset($tableOptions['permission'])) {
            $tableOptions['permission'] = 'loggedin|localhost';
        }
        if (($tableOptions['mode']??false) === 'popup' || ($tableOptions['editMode']??false) === 'popup') {
            $this->showFeedbackInpage = false;
            $this->popupMode = true;
            $this->formOptions['outerWrapperClass'] .= ' pfy-table-edit-popup';
        }
        $this->keepSubmittedDataInForm |= ($tableOptions['editMode'] === 'save');

        if ($tableOptions['tableTitle']) {
            $this->tableTitle = $tableOptions['tableTitle'];
        }

        if (isset($tableOptions['tableHeaders'])) {
            $tableOptions['headers'] = $tableOptions['tableHeaders'];
            unset($tableOptions['tableHeaders']);
        }

        if ($tableOptions['scrollHints']) {
            $tableOptions['tdClass']            = 'pfy-scroll-hints';
        }
        $tableOptions['mailFrom']               = ($this->formOptions['mailFrom']) ?: PageFactory::$webmasterEmail;
        $tableOptions['mailFieldName']          = ($this->formOptions['confirmationEmail']) ?: $this->formOptions['emailFieldName'];
        return $tableOptions;
    } // parseTableOptions


    /**
     * @param array $elemOptions
     * @return array
     * @throws \Exception
     */
    private function parseElementOptions(array &$elemOptions): array
    {
        $elemOptions += PFY_ELEMENT_OPTIONS;

        $label = $elemOptions['label'] ;
        $name = $elemOptions['name'];

        if ($name && !$label) {
            if ($name === 'cancel') { // handle short-hands for cancel and confirm
                $label = '{{ pfy-cancel }}';

            } elseif ($name === 'submit') {
                $label = '{{ pfy-submit }}';

            } elseif ($name === 'newrec') {
                $name = "_newrec"; // prevent showing up in db / output table

            } else {
                if ($elemOptions['label'] !== null) {
                    $label = $elemOptions['label'];
                } else {
                    $label = $elemOptions['origName'];
                    $label = html_entity_decode($label);
                }
                if ($label) {
                    $label = ucwords(str_replace('_', ' ', $label)) . ':';
                }
            }
        }

        // if elem marked by asterisk, remove it - will be visualized by class required:
        if ($label && is_string($label) && $label[strlen($label) - 1] === '*') {
            $elemOptions['required'] = true;
            $label = str_replace('*', '', $label);
        }

        $elemOptions['name'] = $name;
        $elemOptions['label'] = $label;
        $_name = strtolower($name);

        // handle 'info' option:
        if ($info = $elemOptions['info']) {
            $label .= "<span tabindex='0' class='pfy-form-tooltip-anker'>".INFO_ICON.
                "</span><span class='pfy-form-tooltip'>$info</span>";
        }

        // if label contains HTML, we need to transform it:
        if (str_contains($label, '<')) {
            $label = Html::el('span')->setHtml($label);
        }

        $type = $elemOptions['type'];

        // shorthand:
        if ($type === 'required') {
            $elemOptions['required'] = true;
            $type = 'text';
        }

        $type = $this->determineType($_name, $type);

        if ($type === 'button') {
            $label = rtrim($label, ':');
        }

        if ($elemOptions['class'] === null) {
            $elemOptions['class'] = '';
        }

        // handle 'antiSpam' option:
        if (($elemOptions['antiSpam'] !== null) && $elemOptions['antiSpam']) {
            if ($this->inhibitAntiSpam) {
                $elemOptions['antiSpam'] = false;
                return [null, null, null];
            } else {
                $elemOptions['class'] .= ' pfy-obfuscate';
            }
        }

        // handle autocomplete:
        if ($elemOptions['autocomplete'] !== null) {
            $ac = $elemOptions['autocomplete'];
            if (is_bool($ac)) {
                $ac = $ac ? 'true' : 'false';
            }
            $elemOptions['autocomplete'] = $ac;
        } else {
            if ($acAssoc = option('pgfactory.pagefactory-elements.formAutofillAssoc')) {
                if ($acAssoc[$_name]??false) {
                    $ac = $acAssoc[$_name];
                    $elemOptions['autocomplete'] = $ac;
                }
            }
        }

        if (!str_contains(PFY_FORMS_SUPPORTED_TYPES, ",$type,")) {
            throw new \Exception("Forms: requested type not supported: '$type'");
        }

        // register found $name with global list of field-names (used for table-output):
        if (!str_contains('submit,cancel,newrec', $_name)) {
            $this->fieldNames[$name] = $label;
        }
        $elemOptions['isArray'] = false;

        if (array_key_exists('disabled', $elemOptions)) {
            $elemOptions['disabled'] = ($elemOptions['disabled'] !== false);
        } else {
            $elemOptions['disabled'] = false;
        }

        // check choice options, convert to label:value if string:
        if ($elemOptions['options']) {
            if (is_string($elemOptions['options'])) {
                // parse string like "key:value,..." or "value1,value2...":
                $args = $elemOptions['options'];
                if (($args[0] ?? '') === ',') {
                    $args = "''" . $args;
                }
                $res = [];
                if ($options = parseArgumentStr($args)) {
                    if (preg_match('/^\s*,/', $args)) {
                        // fix special case where first option is empty (which is suppressed by parseArgumentStr():
                        $res[] = '';
                    }
                    foreach ($options as $k => $value) {
                        if (str_starts_with($k, '_anonInx')) {
                            // handle argument without key (identified as "_anonInxN"):
                            $res[$value] = $value;
                        } else {
                            $res[$k] = $value;
                        }
                    }
                }
                $elemOptions['options'] = $res;
            } elseif (!is_array($elemOptions['options'])) {
                throw new \Exception("Error: Form argument 'options' must be of type string or array.");
            }
        }

        return array($label, $name, $type);
    } // parseElementOptions


    /**
     * @return void
     * @throws \Exception
     */
    private function handleScheduleOption(): void
    {
        if (!($eventOptions = $this->formOptions['schedule']??false)) {
            return;
        }
        if (!($src = $eventOptions['src']??false)) {
            if (!($src = $eventOptions['file']??false)) { // allow 'file' as synonyme for 'src'
                throw new \Exception("Form: option 'schedule' without option 'src'.");
            }
        }
        $this->matchingEventAvailable = false;

        $eventOptions['file'] = $src;
        $eventOptions['macroName'] = $this->formOptions['macroName'];
        $sched = new Events($eventOptions);
        $nextEvent = $sched->getNextEvent();

        if (!$nextEvent) { // changed from $nextEvent===false
            return;
        }

        $nextT = date('_Y-m-d', strtotime($nextEvent['start']));
        $file = $this->file;
        $file = fileExt($file, true).$nextT.'.'.fileExt($file);
        $this->file = $file;

        foreach ($nextEvent as $key => $value) {
            if (!is_scalar($value)) {
                $value = json_encode($value);
            }
            $this->auxBannerValues[$key] = $value;
            if ($this->tableTitle) {
                if (preg_match('/(\d{4}-\d\d-\d\d)T(\d\d:\d\d)/', $value, $m)) {
                    $value = str_replace($m[0], "{$m[1]} {$m[2]}", $value);
                }
                $this->tableTitle = str_replace("%$key%", $value, $this->tableTitle);
            }
        }

        if ($maxCount = ($nextEvent['maxCount']??false)) {
            $this->formOptions['maxCount'] = $maxCount;
            $this->tableOptions['minRows'] = $maxCount;
        }

        if ($deadline = ($nextEvent['deadline']??false)) {
            $this->formOptions['deadline'] = $deadline;
        }

        self::$scheduleRecs[self::$formCounter] = $nextEvent;

        $this->matchingEventAvailable = true;
    } // handleScheduleOption




    // ===  Misc Helpers ==================================================================

    /**
     * @param string $input
     * @param string $type
     * @param string $name
     * @return array
     */
    private function applyFormFieldValues(string $input, string $type, string $name): string
    {
        if (!str_contains('button,hidden,cancel,submit,reset,select,multiselect,radio,checkbox,upload', $type)) {
            if (preg_match('/(?<! data-)value="(.*?)"/', $input, $m)) {
                if (!$this->formErrorState) {
                    $input = preg_replace('/(?<! data-)value="(.*?)"/', '', $input);
                } else {
                    $val = $m[1];
                    $input = preg_replace('/(?<! data-)value="(.*?)"/', "data-value=\"$val\"", $input);
                }
            }
        }
        return $input;
    } // applyFormFieldValues


    /**
     * @return array
     * @throws \Exception
     */
    private function getAvailableAndMaxCount(): array
    {
        $available = PHP_INT_MAX - 10;
        $currCount = false;
        if ($maxCount = $this->formOptions['maxCount']) {
            $this->openDB();
            if ($maxCountOn = $this->formOptions['maxCountOn']) {
                $currCount = $this->db->sum($maxCountOn);
            } else {
                $currCount = $this->db->count();
            }
            $available = $maxCount - $currCount;
        }
        return [$available, $maxCount, $currCount];
    } // getAvailableAndMaxCount


    /**
     * @param array $tableOptions
     * @return array
     */
    private function setObfuscatePassword(array $tableOptions): array
    {
        $obfuscateCols = [];
        foreach ($this->formElements as $rec) {
            if (($rec['type']??'') === 'password') {
                $obfuscateCols[] = $rec['name'];
            }
        }
        if ($obfuscateCols) {
            $tableOptions['obfuscateCols'] = $obfuscateCols;
        }
        return $tableOptions;
    } // setObfuscatePassword


    /**
     * @param string $str
     * @param array $dataRec
     * @return string
     */
    private function compileTempate(string $str, array $dataRec): string
    {
        $str = TemplateCompiler::basicCompileTemplate($str, $dataRec);

        if (preg_match_all('/%([\w-]{1,16})%/', $str, $m)) {
            $dataRec = $this->origReceivedData;
            foreach ($m[1] as $i => $v) {
                if (isset($dataRec[$v])) {
                    $str = str_replace($m[0][$i], $dataRec[$v], $str);
                }
            }
        }

        $str = str_replace([' BR ', '\\n', '<br>'], "\n", $str);
        if (str_contains($str, "'{=={'")) {
            $str = str_replace("'{=={'", '{{', $str);
            $str = TransVars::translate($str);
        }
        if (str_contains($str, '{{')) {
            $str = TransVars::translate($str);
        }
        return $str;
    } // $str


    /**
     * @param array $dataRec
     * @return string
     */
    private function propagateDataToVariables(array $dataRec): string
    {
        if ($schedRec = (self::$scheduleRecs[self::$formCounter]??false)) {
            $schedRec['start'] = intlDateFormat('RELATIVE_MEDIUM', $schedRec['start']);
            $schedRec['end'] = intlDateFormat('RELATIVE_MEDIUM', $schedRec['end']);
            $dataRec += $schedRec;
        }

        $dataRec['host'] = PFY_HOST_URL;

        $to = false;
        $emailFieldName = $this->formOptions['confirmationEmail'];
        // add variables for all form values, so they can be used in mail-template:
        foreach ($dataRec as $key => $value) {
            if (is_array($value)) {
                $value = $value[0]?? json_encode($value);
            }
            if ($key === $emailFieldName) {
                $to = $value;
            }
            $value = $value?: TransVars::getVariable('pfy-confirmation-response-element-empty');
            TransVars::setVariable("_{$key}_", $value);
        }
        if ($value = ($this->auxBannerValues['eventBanner']??false)) {
            TransVars::setVariable("_banner_", $value);
        }

        return $to;
    } // propagateDataToVariables


    /**
     * @param array $dataRec
     * @return array
     */
    private function handleCallback(array &$dataRec): array
    {
        if ($this->formOptions['dataReceivedCallback'] instanceof \Closure) {
            $res = $this->formOptions['dataReceivedCallback']($dataRec);
            if (is_array($res)) {
                $html = ($res['html'] ?? ($res[0] ?? ''));
                $continueEval = $res['continueEval'] ?? ($res[1] ?? true);
                $this->showForm = $res['showForm'] ?? ($res[2] ?? true);
                $this->showFeedbackInpage = $res['showFeedbackInpage'] ?? ($res[3] ?? true);
                if (isset($res[4]) || isset($res['dataRec'])) {
                    $dataRec = $res['dataRec'] ?? $res[4];
                }

            } else {
                $html = '';
                $continueEval = (bool)$res;
            }
            return [$html, $continueEval];
        }

        $callbacks = explodeTrim(',', $this->formOptions['dataReceivedCallback']);

        foreach ($callbacks as $callback) {
            if ($callback[0] === '~') {
                $res = $this->handlePhpCallback($callback, $dataRec);
            } else {
                $callback = rtrim($callback, '();');
                if (method_exists($this, $callback)) {
                    $res = $this->$callback($dataRec);

                } elseif (function_exists($callback)) {
                    $res = $callback($dataRec);
                } else {
                    throw new \Exception("Error: function '$callback' not available.");
                }
            }
            if (is_array($res)) {
                $html = ($res['html'] ?? ($res[0] ?? ''));
                $continueEval = $res['continueEval'] ?? ($res[1] ?? true);
                $this->showForm = $res['showForm'] ?? ($res[2] ?? true);
                $this->showFeedbackInpage = $res['showFeedbackInpage'] ?? ($res[3] ?? true);
                if (isset($res[4]) || isset($res['dataRec'])) {
                    $dataRec = $res['dataRec'] ?? $res[4];
                }

            } elseif (is_string($res)) {
                $html = $res;
            } else {
                $html = '';
                $continueEval = (bool)$res;
            }
        }
        return [$html, $continueEval];
    } // handleCallback


    /**
     *
     *   PHP:   return [true, handle($dataRec)];
     * @param string $callback
     * @param array $dataRec
     * @return mixed
     */
    private function handlePhpCallback(string $callback, array &$dataRec): mixed
    {
        $res = true;
        $file = resolvePath($callback);
        if ((fileExt($file) === 'php') && file_exists($file)) {
            list($res, $rec) = require $file;
            if (is_bool($res)) {
                $dataRec = $rec;
                $res = true;
            }
        }
        return $res;
    } // handlePhpCallback


    /**
     * @return void
     * @throws InvalidArgumentException
     */
    private function handleFieldsImport(): void
    {
        foreach ($this->formElements as $name => $rec) {
            $file = $rec;
            if ($name === '@import') {
                if ($file[0] === '~') {
                    $file = resolvePath($file);
                }
                $newFields = loadFile($file, useCaching: true);
                if (!is_array($newFields)) {
                    throw new \Exception("Syntax error in '$rec'.");
                }
                if ($newFields) {
                    $key0 = array_keys($newFields)[0];
                    if (is_numeric($key0)) {
                        $newFields1 = [];
                        foreach ($newFields as $v) {
                            $k = $v['name'] ?? '';
                            unset($v['name']);
                            $a = $v['arguments'] ?? '';
                            $a = trim($a, '{}');
                            if ($a) {
                                $newFields1[$k] = explodeTrimAssoc(',', $a);
                            } else {
                                $newFields1[$k] = [];
                            }
                        }
                        $newFields = $newFields1;
                    }
                }
                $this->formElements = array_splice_associative($this->formElements, $name, 1, $newFields);
                break;
            }
        }
    } // handleFieldsImport


    /**
     * @param array|string|null $_name
     * @param mixed $type
     * @return string
     */
    protected function determineType(array|string|null $_name, mixed $type): string
    {
        // for convenience: check for specific names and automatically apply default type:
        if ($type === false) {
            if (str_starts_with($_name, 'email') || str_starts_with($_name, 'e_mail')) {
                $type = ($type === false) ? 'email' : $type;

            } elseif (str_starts_with($_name, 'passwor')) {
                $type = 'password';

            } elseif ($_name === 'submit') {
                $type = 'submit';

            } elseif ($_name === 'newrec') {
                $type = 'button';

            } elseif (str_contains(',cancel,_cancel,reset,_reset,', ",$_name,")) {
                $type = 'cancel';
            }
        }
        if ($type === false) {
            $type = 'text';
        }
        return $type;
    } // determineType


    /**
     * @return string[]
     */
    protected function getHeadAttributes(): array
    {
        $class = "pfy-form pfy-form-$this->formIndex ".$this->formOptions['class'];
        $id1 = $this->formOptions['id'];
        $id = $id1 ? " id='{$id1}'" : " id='pfy-form-$this->formIndex'";

        if ($this->isFormAdmin) {
            $class .= " pfy-screen-only";
        }
        return [$id, $class];
    } // getHeadAttributes


    /**
     * @return void
     */
    protected function injectScrollToFormJs(): string
    {
        $js = <<<EOT
            domForOne('#pfy-form-top', (el) => {
                el.scrollIntoView({behavior: "smooth"});
            });
EOT;
        Page::addJsReady($js);
        return "<div id='pfy-form-top'></div>\n";
    } // injectScrollToFormJs


    /**
     * @return string
     */
    protected function injectNoShowEnd(): string
    {
        $html = '';
        if ($this->noShowOpened) {
            $html = "</div><!-- /pfy-show-unless-form-data-received-$this->formIndex -->\n";
        }
        return $html;
    } // injectNoShowEnd


    /**
     * @return void
     */
    protected function activateWindowFreeze(): void
    {
        if ($time = ($this->formOptions['formFreezeTime']??false)) {
            if (is_numeric($time)) {
                $time *= 1000;
                $js = "const formFreezeTime = $time;";
            } else {
                $js = "const formFreezeTime = '$time';";
            }
            Page::addJs($js);
        }
    } // activateWindowFreeze

    /**
     * @return void
     */
    private function activatebeforeunloadWarning(): void
    {
        if (!$this->formOptions['beforeunloadWarning']) {
            return;
        }
        $js = <<<EOT
window.addEventListener("beforeunload", (ev) => {
  domForOne('.pfy-form-is-modified', () => {
    ev.preventDefault();
    ev.returnValue = ''; // for legacy browsers
    return false;
  });
});

EOT;
        Page::addJs($js);
    } // activatebeforeunloadWarning


} // PfyForm
