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
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactoryElements\Events as Events;
use PgFactory\PageFactoryElements\TemplateCompiler;
use RRule\RRule;
use PgFactory\PageFactoryElements\DataTable as DataTable;
use function PgFactory\PageFactory\var_r as var_r;
use function PgFactory\PageFactoryElements\array_splice_associative as array_splice_associative;
use function PgFactory\PageFactoryElements\intlDateFormat as intlDateFormat;

define('ARRAY_SUMMARY_NAME', '_');
const FORMS_SUPPORTED_TYPES =
    ',text,password,email,textarea,hidden,readonly,'.
    'url,date,datetime-local,time,datetime,month,integer,number,float,range,tel,'.
    'radio,checkbox,dropdown,select,multiselect,upload,multiupload,bypassed,'.
    'event,'.
    'button,reset,submit,cancel,@import,literal,';
    // future: toggle,hash,fieldset,fieldset-end,reveal,literal,file,

const INFO_ICON = 'ⓘ';
const MEGABYTE = 1048576;
const DEFAULT_KEEP_OLD_DATA_DURATION = 3; // month

mb_internal_encoding("utf-8");


class PfyForm extends Form
{
    private array $formOptions;
    private array $elemOptions;
    private array $tableOptions = [];
    private array $fieldNames = [];
    protected array $formElements = [];
    private array $choiceOptions = [];
    private array $bypassedElements = [];
    private $db = false;
    private $dataTable = false;
    protected static $formInx = 0; // internal form count
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
    protected bool $showDirectFeedback = true;
    protected    mixed $formResponse = '';
    protected  bool $isFormAdmin = false;
    protected bool $showForm = true;
    private static bool $initialized = false;

    private static array $scheduleRecs = [];

    /**
     * @param $formOptions
     * @throws \Exception
     */
    public function __construct($formOptions = [])
    {
        self::$formInx++;
        $this->formOptions = &$formOptions;
        $tableOptions = $formOptions['tableOptions']??[];
        $this->formIndex = $formOptions['formInx'] ?? self::$formInx;

        $tableOptions['showData']           = $formOptions['showData']??false;
        $tableOptions['editTable']          = $formOptions['editData']??false;
        $tableOptions['permission']         = $formOptions['permission']??false;
        $tableOptions['tableButtons']       = $formOptions['tableButtons']??false;
        $tableOptions['serviceColumns']     = $formOptions['serviceColumns']??false;
        $tableOptions['sort']               = $formOptions['sortData']??false;
        $tableOptions['footers']            = $formOptions['tableFooters']??false;
        $tableOptions['minRows']            = $formOptions['minRows']??false;
        if ($formOptions['interactiveTable']??false) {
            $tableOptions['interactive']    = $formOptions['interactiveTable'];
        }
        $tableOptions['includeSystemFields']= $formOptions['includeSystemFields']??false;
        $this->tableOptions                 = $this->parseTableOptions($tableOptions);
        unset($tableOptions);

        // make sure essential options are instantiated:
        $formOptions['file']                = $formOptions['file']??false;
        $formOptions['confirmationText']    = $formOptions['confirmationText']??false;
        $formOptions['mailTo']              = $formOptions['mailTo']??false;
        $formOptions['maxCount']            = $formOptions['maxCount']??false;
        $formOptions['maxCountOn']          = $formOptions['maxCountOn']??false;
        $formOptions['labelWidth']          = $formOptions['labelWidth']??false;
        $formOptions['formTop']             = $formOptions['formTop']??false;
        $formOptions['formHint']            = $formOptions['formHint']??false;
        $formOptions['formBottom']          = $formOptions['formBottom']??false;
        $formOptions['confirmationEmail']   = str_replace('-', '_', $formOptions['confirmationEmail']??'');
        $formOptions['emailFieldName']      = str_replace('-', '_', $formOptions['emailFieldName']??'');
        $formOptions['mailFrom']            = $formOptions['mailFrom']??false;
        $formOptions['mailFromName']        = $formOptions['mailFromName']??false;
        $formOptions['deadline']            = $formOptions['deadline']??false;
        $formOptions['id']                  = $formOptions['id']??false;
        $formOptions['class']               = $formOptions['class']??false;
        $formOptions['wrapperClass']        = $formOptions['wrapperClass']??false;
        $formOptions['action']              = $formOptions['action']??false;
        $formOptions['next']                = $formOptions['next']??'~page/';
        $formOptions['callback']            = $formOptions['callback']??false;
        $formOptions['tableOptions']        = $formOptions['tableOptions']??[];
        $formOptions['dbOptions']           = $formOptions['dbOptions']??[];
        $formOptions['dbOptions']['keepDataDuration']   = $formOptions['dbOptions']['keepDataDuration']?? DEFAULT_KEEP_OLD_DATA_DURATION;
        $formOptions['dbOptions']['keepDataOnField']    = $formOptions['dbOptions']['keepDataOnField']?? false;
        $formOptions['dbOptions']['masterFileRecKeyType'] = ($formOptions['dbOptions']['masterFileRecKeyType']??false)?: 'index';
        $formOptions['dbOptions']['includeMeta'] = ($formOptions['dbOptions']['includeMeta']??false)?: true;

        $this->showDirectFeedback           = $formOptions['showDirectFeedback']??true;
        $recLocking                         = $formOptions['recLocking']??false;
        $this->formWrapperClass             = $formOptions['wrapperClass']? ' '.$formOptions['wrapperClass'] :'';
        $this->tableTitle                   = $formOptions['tableTitle']??false;

        if ($recLocking) {
            PageFactory::$pg->addJs('const pfyFormRecLocking = true;');
        }
        if ($this->tableOptions['tableButtons'] || $this->tableOptions['serviceColumns']) {
            $this->addFormTableWrapper = true;
            $permissionQuery = $this->tableOptions['permission'];
            $this->isFormAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
        }

        $this->handleScheduleOption();
        $this->checkDeadline(); // -> sets $this->deadlinePassed and $this->formResponse
        $this->checkMaxCount();

        // open database:
        if ($formOptions['file']) {
            $this->openDB();
        }

        // in popup-mode prevent announceEmptyTable:
        if ($this->tableOptions['editMode'] === 'popup') {
            $this->formWrapperClass .= ' pfy-table-edit-popup';
            $this->showDirectFeedback = false;
        }
        
        // prevent announceEmptyTable by default in case minRows is active:
        if ($this->tableOptions['minRows']) {
            $this->tableOptions['announceEmptyTable'] = false;
        }
        parent::__construct();

        if (!self::$initialized) {
            self::$initialized = true;

            if ($this->tableOptions['editMode']) {
                PageFactory::$pg->addAssets('POPUPS');
            }
            PageFactory::$pg->addAssets('POPUPS');
            PageFactory::$pg->addAssets('REVEAL');
            PageFactory::$pg->addAssets('FORMS');

            if ($formOptions['init'] ?? true) {
                PageFactory::$pg->addJsReady('pfyFormsHelper.init();');
            }
            $this->activateWindowFreeze();
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

        $this->handleReceivedData();
        $formResponse = $this->deadlinePassed . $this->maxCountExceeded . $this->formResponse;
        if (!$this->showDirectFeedback && $this->formResponse) {
            reloadAgent(message: strip_tags($this->formResponse));
        }

        if ($formResponse) {
            $this->injectNoShowCssRule();
            $formTopBanner = $this->renderFormTopBanner();

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

        if (!$this->showForm && $this->showDirectFeedback) {
            // normal case after data received -> show response, hide form:
            $html .= $this->injectNoShowCssRule();
        }

        // assemble form:
        $html .= $this->renderFormWrapperHead();        // pfy-form-and-table-wrapper
                                                        //    pfy-form-wrapper
        $html .= $this->renderFormHead();               //      form
                                                        //        pfy-elems-wrapper
        $html .= $this->renderFormFields();             //          pfy-elem-wrapper ...

        $html .= $this->renderFormTail();               //        /pfy-elems-wrapper
                                                        //      /form
                                                        //    /pfy-form-wrapper
        $html .= $this->renderDataTable();              //    pfy-table-data-output-wrapper/
        $html .= $this->renderFormTableWrapperTail();   // /pfy-form-and-table-wrapper
        $html .= $this->renderProblemWithFormBanner();  // pfy-problem-with-form-hint/

        $html .= $this->injectNoShowEnd();
        $html .= "<!-- === /pfy form widget === -->\n\n";
        return $html;
    } // renderForm


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
            if ($rec === false) {
                unset($formElements[$name]);
                continue;
            }
            $rec['origName'] = trim($name);
            $name = translateToIdentifier($name);
            $this->formElements[$name] = $rec;
        }

        $inx = $this->formIndex;
        // handle option labelWidth:
        if ($lWidth = $this->formOptions['labelWidth']) {
            PageFactory::$pg->addCss(".pfy-form-$inx { --pfy-form-label-width: $lWidth}\n");
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

        // standard hidden fields for internal bookkeeping:
        $this->addElement('', ['type' => 'hidden', 'name' => '_reckey', 'value' => $this->formOptions['recId'] ?? '', 'preset' => '']);
        $this->addElement('', ['type' => 'hidden', 'name' => '_formInx', 'value' => $this->formIndex, 'preset' => $this->formIndex]);
        $this->addElement('', ['type' => 'hidden', 'name' => '_csrf', 'value' => ($csrf = csrf()), 'preset' => $csrf]);

        $this->fireRenderEvents();

    } // createForm






    // === private methods ========================================================

    /**
     * Renders element identified by $name.
     * @param string $name
     * @return string
     */
    protected function renderFormElement(string $name): string
    {
        $html = '';
        $rec = $this->formElements[$name];

        // special case: type literal -> just output literal
        if (($rec['type']??false) === 'literal') {
            return $rec['html']??'';
        }

        $_name = strtolower($name);
        try {
            $elem = $this[$name];
        } catch (\Exception $e) {
            try {
                $name = "_$name";
                $elem = $this[$name];
            } catch (\Exception $e) {
                exit($e);
            }
        }
        $label = (string)$elem->getLabel();
        $label = str_replace(['&lt;','&gt;'], ['<','>'], $label);

        if (str_contains(($rec['label'] ?? ''), '*')) {
            $rec['required'] = true;
        }
        $label = "<span class='pfy-label-wrapper'>$label</span>";
        $input = (string)$elem->getControl();
        $input = str_replace(['&lt;','&gt;'], ['<','>'], $input);

        // fix for NetteForm's quirk: input outside of label in choice fields
        if (str_contains($input, 'type="radio"') || str_contains($input, 'type="checkbox"')) {
            if (preg_match_all('|<label\s?(for="(.*?)")?><input (.*?)>(.*?)</label>|', $input, $m)) {
                $input1 = '';
                foreach ($m[2] as $i => $mm) {
                    $formInx = self::$formInx;
                    $elemInx = $this->formElements[$name]['elemInx'];
                    if ($m[2][$i]) {
                        $id = $m[2][$i];
                        $for = $m[1][$i];
                    } else {
                        $id = $m[2][$i] ?: "pfy-input-$formInx-$elemInx-".($i+1);
                        $for = "for='$id'";
                    }
                    $input1 .= "<span class='pfy-choice-wrapper'><input id='$id' {$m[3][$i]}><label $for>{$m[4][$i]}</label></span>";
                }
                $input = $input1;
            }
        }
        $type = $this->determineType($_name, $rec['type'] ?? false);
        $attr = '';

        // if nette forms applied a value, turn it into a data-preset:
        if (!str_contains('button,hidden,cancel,submit,reset,select,multiselect,radio,checkbox,upload', $type) && str_contains($input, 'value="')) {
            $input = preg_replace('/value="(.*?)"/', "data-preset=\"$1\"", $input);
        }

        if ($type === 'password') {
            $icon = svg('site/plugins/pagefactory-pageelements/assets/icons/show.svg') .
                svg('site/plugins/pagefactory-pageelements/assets/icons/hide.svg');
            $input .= "<button type='button' class='pfy-form-show-pw' aria-pressed='false'>$icon</button>";
        }
        if ($description = ($rec['description'] ?? '')) {
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

        if ($type === 'hidden') {
            $html = $input;

        } elseif (($type === 'textarea') && ($rec['reveal'] ?? false)) {
            $inx = $rec['revealInx'];
            $controllerLabel = $rec['reveal'];
            $html = <<<EOT
<div class="pfy-elem-wrapper pfy-reveal-controller">
<span class="pfy-reveal-controller-label"><label for="frm-CommentController$inx" class=""><input type="checkbox" name="CommentController$inx" class="pfy-reveal-controller" aria-controls="pfy-reveal-container-$inx" data-reveal-target="#pfy-reveal-container-$inx" data-icon-closed="+" data-icon-open="∣" id="frm-CommentController$inx" aria-expanded="false">$controllerLabel</label></span>
</div>
EOT;

            $html .= <<<EOT
<div id='pfy-reveal-container-$inx' class="pfy-reveal-container" aria-hidden="true">
<!-- pfy-elem-wrapper -->
<div class="pfy-elem-wrapper pfy-$type $class">
<span class="pfy-input-wrapper">
$input
</span>
</div><!-- /pfy-elem-wrapper -->
</div>

EOT;

        } elseif ($type === 'literal') {
            $html .= $this->formElements[$_name]['html'] ?? '';

        } elseif (str_contains(',cancel,submit,reset,', ",$type,")) {
            $cls = $this->formElements[$_name]['class'] ?? '';
            $elem->setHtmlAttribute('class', "pfy-$type button $cls");
            $this->formButtons .= (string)$elem->getControl();
            return '';

        } elseif ($type === 'button') {
            $cls = $this->formElements[$_name]['class'] ?? '';
            $elem->setHtmlAttribute('class', "pfy-form-button button $cls");
            $this->formButtons .= (string)$elem->getControl();
            return '';

            // all other field types (except bypassed and import):
        } elseif (!str_contains(',bypassed,@import,', ",$type,")) {
            // get errors and render them:
            $errors = '';
            if ($this[$name]->hasErrors()) {
                $class .= ' pfy-form-elem-has-error';
                foreach ($this[$name]->getErrors() as $error) {
                    $errors .= "<div class='pfy-form-elem-error-msg'>$error</div>\n";
                }
            }

            if ($attr) {
                $label = str_replace('<label', "<label $attr", $label);
            }

            $input = "<span class='pfy-input-wrapper'>$input</span>";
            $html = <<<EOT
<!-- pfy-elem-wrapper -->
<div class="pfy-elem-wrapper pfy-$type $class">
$label
$input
$errors
</div><!-- /pfy-elem-wrapper -->

EOT;
        }
        return $html;
    } // renderFormElement


    /**
     * Creates an element in NetteForms.
     * This is the place where field types are interpreted.
     * @param array $elemOptions
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    protected function addElement(string $elemName, array $elemOptions = null): void
    {
        $this->elemInx++;
        // if $elemOptions not suppliedm use $this->formElements
        if ($elemOptions === null) {
            $elemOptions = &$this->formElements[$elemName];
            $elemOptions['name'] = $elemName;
        }

        $elemOptions['elemInx'] = $this->elemInx;

        // determine $label, $name, $type and $subType:
        list($label, $name, $type) = $this->parseOptions($elemOptions);
        if ($name === null) { // this is the case if antiSpam is suppressed by edit-rec option
            return;
        }

        $subType = '';
        switch ($type) {
            case 'hidden':
                $elem = $this->addHidden($name, $elemOptions['value']??'');
                break;
            case 'bypassed':
                $this->bypassedElements[$name] = $elemOptions['value']??'';
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
            case 'button':
                $elem = $this->addButton($name, $label);
                break;
            case 'literal':
                return; // nothing to do
            case 'cancel':
            case 'reset':
                $elem = $this->addButton('_cancel', $label);
                if ($next = ($elemOptions['next']??false)) {
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

        // handle presets (resp. value / default):
        if ($preset = ($elemOptions['preset']??($elemOptions['value']??false))) {
            if (is_bool($preset)) {
                $preset = $preset?'true':'false';
            }
            if (str_contains($preset, '%')) {
                $preset = str_replace(['%today%', '%now%'], [date('Y-m-d'), date('Y-m-d H:i')], $preset);
            }
            $elem->setHtmlAttribute('data-preset', $preset);
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
        if ($min = ($elemOptions['min']??false)) {
            $elem->addRule(self::Min, 'Min value: %d', $min);
        }

        // handle max -> take into account case maxCount:
        if ($max = ($elemOptions['max']??false)) {
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

        // note: 'info' option handled in parseOptions()
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
        if ($revealLabel = ($elemOptions['reveal']??false)) {
            // add checkbox to open reveal-container:
            $this->revealInx++;
            $inx = "{$this->formIndex}_$this->revealInx";
            $elemOptions['revealInx'] = $inx;
            $targetId = "pfy-reveal-container-$inx";
            PageFactory::$pg->addAssets('REVEAL');

            $elem1 = $this->addCheckbox("CommentController$inx", $revealLabel);
            $elem1->setHtmlAttribute('class', 'pfy-reveal-controller');
            $elem1->setHtmlAttribute('aria-controls', $targetId);
            $elem1->setHtmlAttribute('data-reveal-target', "#$targetId");
            $elem1->setHtmlAttribute('data-icon-closed', '+');
            $elem1->setHtmlAttribute('data-icon-open', '∣');
            $label = $elemOptions['label']??'';
        }

        $elem = $this->addTextarea($name, $label);
        if ($revealLabel) {
            $elem->setHtmlAttribute('data-reveal-target-id', $targetId);
        }
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
            PageFactory::$pg->addAssets('REVEAL');
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
            PageFactory::$pg->addAssets('REVEAL');
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
        if ($type === 'dropdown') {
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
     * @return string
     */
    protected function handleReceivedData(): void
    {
        if (!$this->isSuccess()) {
            return;
        }

        $html = false;
        $dataRec = $this->getValues('array');

        // handle 'cancel' button:
        if (isset($_POST['cancel'])) {
            reloadAgent();
        }

        if (($this->formOptions['confirmationText'] === '') || (isset($_GET['quiet']))) {
            $this->showDirectFeedback = false;
        }

        // check presence of $formInxReceived:
        $formInxReceived = $dataRec['_formInx']??false;
        if ($formInxReceived === false || isset($_POST['cancel'])) {
            return;
        }

        // check whether received data applies to currently processed form (e.g. if there are multiple forms in a page):
        if (intval($formInxReceived) !== $this->formIndex) {
            return; // signal 'processing skipped, continue processing'
        }

        $csrf = $_POST['_csrf']??false;
        if (!$csrf || !csrf($csrf)) {
            reloadAgent(null, '{{ pfy-form-session-expired }}');
        }

        $this->restoreBypassedFields($dataRec);

        // handle 'callback' on data received:
        if ($this->formOptions['callback']) {
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

        $dataRec = $this->normalizeData($dataRec);

        if (is_string($dataRec)) {
            // string means spam detected:
            $this->showForm = false;
            $this->formResponse =  "<div class='pfy-form-error'>$dataRec</div>\n";
            return;
        }

        // handle optional 'deadline' and 'maxCount':
        if ($this->applyRequiredGroupCheck($dataRec)) {
            return;
        }

        // handle optional 'deadline' and 'maxCount':
        if ($this->deadlinePassed || $this->checkMaxCount($dataRec)) {
            if (!$this->isFormAdmin) {
                return;
            }
        }

        // handle uploads
        $this->handleUploads($dataRec);

        // if 'file' defined, save received data:
        if ($this->formOptions['file']) {
            $err = $this->storeSubmittedData($dataRec, $recKey);
            if ($err) {
                $err = TransVars::getVariable($err, true);
                $html = "<div class='pfy-form-error'>$err</div>\n";
                mylog($err, 'form-log.txt');
            } else {
                $logMsg = 'Stored: '.PageFactory::$pageId."[$formInxReceived] ";
                $logMsg .= var_r($dataRec);
                mylog($logMsg, 'form-log.txt');
            }
        }

        // if no error (i.e. error-message in $html) -> notify owner & create feedback:
        if (!$html) {
            if ($this->formOptions['mailTo']) {
                $this->notifyOwner($dataRec);
            }

            if ($this->formOptions['confirmationText']) {
                $response = $this->formOptions['confirmationText'];
            } else {
                $response = "{{ pfy-form-submit-success }}";
            }
            $html = "<div class='pfy-form-success'>$response</div>\n";

            // handle optional confirmation mail:
            $html .= $this->handleConfirmationMail($dataRec);
        }


        // add 'continue...' if direct feedback is active:
        if ($this->showDirectFeedback) {
            $next = $this->formOptions['next'] ?: '~page/';
            $class = 'pfy-form-success-continue';
            if ($next === '~page/') {
                $class .= ' pfy-form-continue-same';
            }
            $html .= "<div class='$class'><a href='$next'>{{ pfy-form-success-continue }}</a></div>\n";
        }

        // write log:
        $logText = strip_tags($html);
        mylog($logText, 'form-log.txt');

        if (isset($_POST)) {
            unset($_POST);
        }
        $this->showForm = false;
        if ($html) {
            $html = "<div class='pfy-form-response'>\n$html\n</div><!-- /pfy-form-response -->\n";

            // in case there are multiple forms in the page, hide all others:
            // (nette forms would preset received data in other forms)
            PageFactory::$pg->addCss('.pfy-form-and-table-wrapper {display: none;}');
        }
        $this->formResponse =  $html;
    } // handleReceivedData


    /**
     * @param mixed $dataRec
     * @return void
     */
    private function handleUploads(mixed $dataRec): void
    {
        foreach ($dataRec as $key => $rec) {
            if (is_array($rec)) {
                foreach ($rec as $k => $r) {
                    if (is_a($r, 'Nette\Http\FileUpload')) {
                        $this->handleUploadedFile($key, $r, $dataRec);
                        unset($dataRec[$key][$k]);
                    }
                }
            } else {
                if (is_a($rec, 'Nette\Http\FileUpload')) {
                    $this->handleUploadedFile($key, $rec, $dataRec);
                }
            }
        }
    } // handleUploads


    /**
     * @param string $key
     * @param object $rec
     * @throws \Exception
     */
    private function handleUploadedFile(string $key, object $rec, array $dataRec): void
    {
        $path = $this->formElements[$key]['path']??false;
        if ($p = (strpos($path, '$'))) {
            // case given path contains patter '$xy', where xy is name of other data element:
            $k = substr($path, $p+1);
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
        $filename = $rec->name;
        $filename = basename($filename);
        $filename = str_replace(['..', ' '],['.', '_'], $filename);
        $filename = preg_replace('/[^.\w-]/','', $filename);
        $rec->move($path.$filename);
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
     * @param array $dataRec
     * @return array
     */
    private function normalizeData(array $dataRec): array|string
    {
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
                        $value1[ARRAY_SUMMARY_NAME] .= $key.', ';
                    }
                }
                $value1[ARRAY_SUMMARY_NAME] = rtrim($value1[ARRAY_SUMMARY_NAME], ', ');
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
        if (!$this->formOptions['file']) {
            return false;
        }
        $this->db = new DataSet($this->formOptions['file'], $this->formOptions['dbOptions']);

        // remember db-file for use by ajax_server.php, if user is form-admin:
        if ($this->isFormAdmin) {
            $sessKey = "db:" . PageFactory::$pageId . ":$this->formIndex:file";
            kirby()->session()->set($sessKey, resolvePath($this->formOptions['file']));
        }
        return $this->db;
    } // openDB


    /**
     * @param array $newRec
     * @param string $file
     * @return false|string
     * @throws \Exception
     */
    private function storeSubmittedData(array $newRec, string|false $recId = false): false|string
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
        return $this->saveRec($newRec, $recId);
    } // storeSubmittedData


    /**
     * @param array $newRec
     * @param string $recId
     * @return false|string
     * @throws \Exception
     */
    private function saveRec(array $newRec, string $recId)
    {
        $this->openDB();

        if (!$recId && $this->db->recExists($newRec)) {
            return 'pfy-form-warning-record-already-exists';
        }

        $res = $this->db->addRec($newRec, recKeyToUse: $recId);
        if (is_string($res)) {
            return $res;
        }
        return false;
    } // saveRec


    /**
     * @param array $dataRec
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function notifyOwner(array $dataRec): void
    {
        $formOptions = $this->formOptions;
        $out = '';
        $labelLen = 0;
        foreach ($dataRec as $key => $value) {
            $labelLen = max($labelLen, strlen($key));
        }
        $labelLen += 5;
        foreach ($dataRec as $key => $value) {
            if ($key[0] === '_') {
                continue;
            }
            $type = $this->formElements[$key]['type']??false;
            if ($type === 'password') {
                $value = '*****';
            }
            $key = str_pad("$key: ", $labelLen, '. ');
            if (is_array($value)) {
                $value = $value[ARRAY_SUMMARY_NAME]??'';
            }
            $out .= "$key$value\n";
        }
        TransVars::setVariable("_data_", $out);

        if ($label = ($this->formOptions['ownerNotificationLabel']??'')) {
            TransVars::setVariable('pfy-form-owner-notification-label', $label);
        }

        $this->propagateDataToVariables($dataRec);

        $subject = TransVars::getVariable('pfy-form-owner-notification-subject');
        $subject = preg_replace('/%([\w-]*)%/', "{{ _$1_ }}", $subject);
        $subject = TransVars::translate($subject);

        $message = TransVars::getVariable('pfy-form-owner-notification-body');
        $message = preg_replace('/%([\w-]*)%/', "{{ _$1_ }}", $message);
        $message = TransVars::translate($message);

        $to = $formOptions['mailTo']?: TransVars::getVariable('webmaster_email');
        if (str_contains($to, ',')) {
            $to = explodeTrim(',', $to);
        }
        $this->sendMail($to, $subject, $message, logComment: 'Notification Mail to Onwer');
    } // notifyOwner



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
        if ($this->formOptions['tableOptions']??false) {
            $tableOptions = array_merge($tableOptions, $this->formOptions['tableOptions']);
        }

        $file = resolvePath($this->formOptions['file'], relativeToPage: true);

        $showAllFields = $tableOptions['showAllFields']??false;
        $fieldNames = $this->fieldNames;
        foreach (['_reckey', '_formInx', '_csrf'] as $k) {
            if (isset($fieldNames[$k])) {
                unset($fieldNames[$k]);
            }
        }
        foreach ($fieldNames as $fieldName) {
            if (!$showAllFields && ($fieldName[0] === '_')) {
                unset($fieldNames[$fieldName]);
                continue;
            }
            $elem = $this->formElements[$fieldName];
            if ($elem['label']??false) {
                $fieldNames[$fieldName] = trim($elem['label'], ': ');
            }
        }

        $tableOptions['tableHeaders']         = $fieldNames;
        $tableOptions['masterFileRecKeyType'] = 'index';
        $tableOptions['tdClass']              = 'pfy-scroll-hints';
        $tableOptions['markLocked']           = false; //true;
        $tableOptions['obfuscateRecKeys']     = true;
        $tableOptions['shieldCellContent']    = $this->formOptions['tableOptions']['shieldCellContent']??false;
        $tableOptions['mailFrom']             = ($this->formOptions['mailFrom']??false) ?: PageFactory::$webmasterEmail;
        $tableOptions['mailFieldName']        = ($this->formOptions['confirmationEmail']??false) ?: $this->formOptions['emailFieldName']??false;
        $tableOptions['includeTimestamp']     = ($tableOptions['includeTimestamp']??false) ?: $this->formOptions['tableOptions']['includeTimestamp']??true;

        $tableOptions = $this->setObfuscatePassword($tableOptions);
        
        $this->dataTable = new DataTable($file, $tableOptions);
        return $this->dataTable;
    } // openDataTable


    /**
     * @param array $tableOptions
     * @return array
     */
    private function parseTableOptions(array $tableOptions): array
    {
        $tableOptions['file'] = $this->formOptions['file']??false;
        $tableOptions['permission'] = $tableOptions['permission']??false;
        $tableOptions['tableButtons'] = $tableOptions['tableButtons']??false;
        $tableOptions['serviceColumns'] = $tableOptions['serviceColumns']??false;
        $tableOptions['editMode'] = $tableOptions['editMode']??false;
        $showData = $tableOptions['showData'];

        $editTable = $tableOptions['editTable'];
        if (!$tableOptions['file'] || (!$showData && !$editTable)) {
            return $tableOptions;
        }

        // handle editTable:
        if ($editTable) {
            if ($editTable === true) {
                $tableOptions['permission'] = 'localhost,loggedin';
                $tableOptions['tableButtons'] = 'delete,download';
                $tableOptions['serviceColumns'] = 'select,num';
                $tableOptions['editMode'] = 'inpage';
            } elseif ($editTable === 'popup') {
                $tableOptions['permission'] = 'localhost,loggedin';
                $tableOptions['tableButtons'] = 'delete,download,add';
                $tableOptions['serviceColumns'] = 'select,num,edit';
                $tableOptions['editMode'] = 'popup';
            } elseif (is_array($editTable)) {
                $tableOptions = $editTable + $tableOptions;
                $tableOptions['permission'] = $editTable['permission'] ?? 'localhost,loggedin';
                $tableOptions['tableButtons'] = $editTable['tableButtons'] ?? 'download';
                $tableOptions['serviceColumns'] = $editTable['serviceColumns'] ?? 'select,num';
                $tableOptions['editMode'] = $editTable['mode'] ?? 'inpage';
            } else {
                $tableOptions['editMode'] = $editTable['mode'] ?? 'inpage';
            }

        // handle showData:
        } elseif ($showData) {
            if ($showData === true) {
                $tableOptions['permission'] = 'localhost,loggedin';
                $tableOptions['tableButtons'] = 'download';
                $tableOptions['serviceColumns'] = 'num';
            } else {
                $tableOptions['permission'] = $showData['permission']??'localhost,loggedin';
                $tableOptions['tableButtons'] = $showData['tableButtons']??'download';
                $tableOptions['serviceColumns'] = $showData['serviceColumns']??'';
            }
        }

        unset($tableOptions['editTable']);

        return $tableOptions;
    } // parseTableOptions


    /**
     * @param array $elemOptions
     * @return array
     * @throws \Exception
     */
    private function parseOptions(array &$elemOptions): array
    {
        $label = $elemOptions['label'] ?? false;
        $name = $elemOptions['name'] ?? false;

        if (!$label && $name) {
            if ($name === 'cancel') { // handle short-hands for cancel and confirm
                $label = '{{ pfy-cancel }}';
            } elseif ($name === 'submit') {
                $label = '{{ pfy-submit }}';
            } else {
                if (isset($elemOptions['label'])) {
                    $label = $elemOptions['label'];
                } else {
                    $label = ($elemOptions['origName']??false) ?: '';
                    $label = html_entity_decode($label);
                }
                if ($label) {
                    $label = ucwords(str_replace('_', ' ', $label)) . ':';
                }
            }
        }

        // if elem marked by asterisk, remove it - will be visualized by class required:
        if ($label && $label[strlen($label) - 1] === '*') {
            $elemOptions['required'] = true;
            $label = str_replace('*', '', $label);
        }

        $elemOptions['name'] = $name;
        $elemOptions['label'] = $label;
        $_name = strtolower($name);

        // handle 'info' option:
        if ($info = ($elemOptions['info'] ?? false)) {
            $label .= "<button type='button' class='pfy-form-tooltip-anker'>".INFO_ICON.
                "</button><span class='pfy-form-tooltip'>$info</span>";
        }

        // if label contains HTML, we need to transform it:
        if (str_contains($label, '<')) {
            $label = Html::el('span')->setHtml($label);
        }

        $type = $elemOptions['type']??false;

        // shorthand:
        if ($type === 'required') {
            $elemOptions['required'] = true;
            $type = 'text';
        }

        $type = $this->determineType($_name, $type);

        if (!isset($elemOptions['class'])) {
            $elemOptions['class'] = '';
        }

        // handle 'antiSpam' option:
        if (($elemOptions['antiSpam']??false) !== false) {
            if ($this->inhibitAntiSpam) {
                $elemOptions['antiSpam'] = false;
                return [null, null, null];
            } else {
                $elemOptions['class'] .= ' pfy-obfuscate';
            }
        }


        if (!str_contains(FORMS_SUPPORTED_TYPES, ",$type,")) {
            throw new \Exception("Forms: requested type not supported: '$type'");
        }

        // register found $name with global list of field-names (used for table-output):
        if (!str_contains('submit,cancel,hidden', $_name)) {
            $this->fieldNames[$name] = $name;
        }
        $elemOptions['isArray'] = false;

        if (array_key_exists('disabled', $elemOptions)) {
            $elemOptions['disabled'] = ($elemOptions['disabled'] !== false);
        } else {
            $elemOptions['disabled'] = false;
        }

        // check choice options, convert to label:value if string:
        if (!isset($elemOptions['options'])) {
            $elemOptions['options'] = false;
        } elseif (is_string($elemOptions['options'])) {
            $elemOptions['options'] = explodeTrimAssoc(',', $elemOptions['options'], splitOnLastMatch:true);
        } elseif (!is_array($elemOptions['options'])) {
            throw new \Exception("Error: Form argument 'options' must be of type string or array.");
        }

        $elemOptions['autoGrow'] = $elemOptions['autoGrow']??true;

        return array($label, $name, $type);
    } // parseOptions


    /**
     * @return string
     */
    protected function renderFormWrapperHead(): string
    {
        $html = '';
        $formInx = $this->formIndex;

        if ($this->addFormTableWrapper) {
            $html .= "<div class='pfy-form-and-table-wrapper'>\n";
        }

        $wrapperClass = "pfy-form-wrapper pfy-form-wrapper-$formInx" . $this->formWrapperClass;
        $html .= "<div id='pfy-form-wrapper-$formInx' class='$wrapperClass'>\n";
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

        // schedule option may have found no matching event, in this case show message:
        if ($this->matchingEventAvailable === false) {
            $this->showForm = false;
            return '{{ pfy-form-no-event-found }}';
        }
        if (!$this->showForm) {
            return '';
        }

        if ($this->formOptions['action'] ?? false) {
            $this->setAction($this->formOptions['action']);
        } else {
            $this->setAction($_SERVER['REQUEST_URI']); // this page's URL, poss. including ?xy
        }

        list($id, $formClass, $aria) = $this->getHeadAttributes();
        if ($this->hasErrors()) {
            $formClass .= ' pfy-form-has-errors';
        }

        $htmlForm = $this->getRenderer()->render($this, 'begin');
        $htmlForm = "<form$id class='$formClass'$aria" . substr($htmlForm, 5);
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
        $elem = $this['_reckey'];
        $html .= $elem->getControl()."\n";

        $elem = $this['_formInx'];
        $html .= $elem->getControl()."\n";

        $elem = $this['_csrf'];
        $html .= $elem->getControl()."\n";

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
            $str = "\n<div class='pfy-form-hint'>$str</div>\n";
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
<div class="pfy-elem-wrapper button pfy-cancel pfy-submit">
<span class="pfy-input-wrapper">$this->formButtons</span>
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
        if (!(($this->tableOptions['editMode'] || $this->tableOptions['showData']) && $this->formOptions['file'] && $this->isFormAdmin)) {
            return '';
        }

        // to be on the save side: always invoke robots header when displaying form data.
        PageFactory::$pg->applyRobotsAttrib();

        $ds = $this->openDataTable();
        $noData = !$ds->getSize();
        if (!$ds->announceEmptyTable && $noData) {
            $emptyRec = [];
            foreach ($this->formElements as $key => $element) {
                if ($key[0] === '_') {
                    continue;
                }
                if ($element['isArray']) {
                    $emptyRec[$key] = [];
                    $emptyRec[$key]['_'] = '';
                    foreach ($element['subKeys'] as $subKey) {
                        $emptyRec[$key][$subKey] = '';
                    }

                } else {
                    $emptyRec[$key] = '';
                }
            }
            $ds->addRec($emptyRec);
        }
        $html = $ds ? $ds->render() : '';
        $header = '';
        if ($this->tableOptions['editMode'] !== 'popup') {
            if ($this->tableTitle) {
                $header .= compileMarkdown($this->tableTitle);
            } else {
                $header = '<p class="pfy-table-data-output-header">{{ pfy-table-data-output-header }}</p>';
            }
        }
        if ($html) {
            $html = <<<EOT
<div class='pfy-table-data-output-wrapper'>
$header
$html
</div><!-- /pfy-table-data-output-wrapper -->

EOT;
        }
        // if data was empty and we added an empty rec, remove it now:
        if ($noData) {
            $ds->purge();
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
            $deadlineStr = Utils::timeToString($deadline);
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
        return $str;
    } // handleFormBannerValues


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
        if ($deadlineStr = $this->formOptions['deadline']) {
            $deadline = strtotime($deadlineStr);
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

        if ($nextEvent === false) {
            return;
        }

        $nextT = date('_Y-m-d', strtotime($nextEvent['start']));
        $file = $this->formOptions['file'];
        $file = fileExt($file, true).$nextT.'.'.fileExt($file);
        $this->formOptions['file'] = $file;

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

        self::$scheduleRecs[self::$formInx] = $nextEvent;

        $this->matchingEventAvailable = true;
    } // handleScheduleOption


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
        $obfuscateRows = [];
        foreach ($this->formElements as $rec) {
            if (($rec['type']??'') === 'password') {
                $obfuscateRows[] = $rec['name'];
            }
        }
        if ($obfuscateRows) {
            $tableOptions['obfuscateRows'] = $obfuscateRows;
        }
        return $tableOptions;
    } // setObfuscatePassword


    /**
     * @param array $dataRec
     * @return mixed
     * @throws \Exception
     */
    private function handleConfirmationMail(array $dataRec): mixed
    {
        if (!$this->formOptions['confirmationEmail']) {
            return '';
        }
        $eventData = $this->auxBannerValues;
        foreach ($eventData as $key => $value) {
            $value = TransVars::getVariable($value, true);
            if ($value) {
                $eventData[$key] = $value;
            }
        }
        $dataRec += $eventData;

        $subject = $this->getEmailComponent('subject', $dataRec);
        $message = $this->getEmailComponent('message', $dataRec);
        $to = $dataRec[$this->formOptions['confirmationEmail']]??false;
        if ($to) {
            $this->sendMail($to, $subject, $message, logComment: 'Confirmation Mail to Visitor');
            return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-sent }}</div>\n";
        }
        return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-missing }}</div>\n";
    } // sendConfirmationMail


    /**
     * @return array
     * @throws \Exception
     */
    private function getEmailComponent(string $selector, array $dataRec): string
    {
        $confirmationEmailTemplate = ($this->formOptions['confirmationEmailTemplate']??true);
        if ($confirmationEmailTemplate === true) {
            $template = TransVars::getVariable("pfy-confirmation-response-$selector");
            $out = TemplateCompiler::basicCompileTemplate($template, $dataRec);

        } else {
            $templateOptions = TemplateCompiler::sanitizeTemplateOption($confirmationEmailTemplate);
            $template = TemplateCompiler::getTemplate($templateOptions, $selector);
            $out = TemplateCompiler::compile($template, $dataRec, $templateOptions);
        }
        $out = str_replace([' BR ', '\\n'], "\n", $out);
        return $out;
    } // getEmailComponent


    /**
     * @param array $dataRec
     * @return string
     */
    private function propagateDataToVariables(array $dataRec): string
    {
        if ($schedRec = (self::$scheduleRecs[self::$formInx]??false)) {
            $schedRec['start'] = intlDateFormat('RELATIVE_MEDIUM', $schedRec['start']);
            $schedRec['end'] = intlDateFormat('RELATIVE_MEDIUM', $schedRec['end']);
            $dataRec += $schedRec;
        }

        $dataRec['host'] = PageFactory::$hostUrl;

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


    /**
     * @param array $dataRec
     * @return array
     */
    private function handleCallback(array &$dataRec): array
    {
        if ($this->formOptions['callback'] instanceof \Closure) {
            $res = $this->formOptions['callback']($dataRec);
            if (is_array($res)) {
                $html = ($res['html'] ?? ($res[0] ?? ''));
                $continueEval = $res['continueEval'] ?? ($res[1] ?? true);
                $this->showForm = $res['showForm'] ?? ($res[2] ?? true);
                $this->showDirectFeedback = $res['showDirectFeedback'] ?? ($res[3] ?? true);
                if (isset($res[4]) || isset($res['dataRec'])) {
                    $dataRec = $res['dataRec'] ?? $res[4];
                }

            } else {
                $html = '';
                $continueEval = (bool)$res;
            }
            return [$html, $continueEval];
        }

        $callbacks = explodeTrim(',', $this->formOptions['callback']);

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
                $this->showDirectFeedback = $res['showDirectFeedback'] ?? ($res[3] ?? true);
                if (isset($res[4]) || isset($res['dataRec'])) {
                    $dataRec = $res['dataRec'] ?? $res[4];
                }

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
     * @return void
     */
    private function handleComposedFields(): void
    {
        foreach ($this->formElements as $name => $rec) {
            $type = ($rec['type']??false);
            if ($type === 'event') {
                $this->composeEventElement($name, $rec);
            }
        }
    } // handleComposedFields


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
        $aria = '';
        if (($this->tableOptions['editMode'] ?? false) === 'popup') {
            $class .= " pfy-fully-hidden";
            $aria = 'aria-hidden="true"';
        }
        return array($id, $class, $aria);
    } // getHeadAttributes


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

            // startLabel:
            if (!($startLabel = TransVars::getVariable("pfy-form-event-$startName-label"))) {
                $startLabel = TransVars::getVariable('pfy-form-event-start-label');
                if (preg_match('/\W$/', $startLabel)) {
                    $startLabel =$startLabel . $suffix . substr($startLabel, -1);

                } else {
                    $startLabel = $startLabel . $suffix;
                }
            }

            // endLabel:
            if (!($endLabel = TransVars::getVariable("pfy-form-event-$endName-label"))) {
                $endLabel = TransVars::getVariable('pfy-form-event-end-label');
                if (preg_match('/\W$/', $endLabel)) {
                    $endLabel = $endLabel . $suffix . substr($endLabel, -1);

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
        $eventElements[$endName] = [
            'type' => 'datetime-local',
            'label' => $endLabel,
            'class' => 'pfy-event-elem pfy-event-elem-till',
            'relatedField' => $startName,
            'defaultEventDuration' => ($this->formElements[$name]['defaultEventDuration']??0),
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
                'NONE:{{ pfy-form-rrule-none-option }},'.
                'DAILY:{{ pfy-form-rrule-daily-option }},'.
                'WEEKLY:{{ pfy-form-rrule-weekly-option }},'.
                'MONTHLY:{{ pfy-form-rrule-monthly-option }},'.
                'YEARLY:{{ pfy-form-rrule-yearly-option }}',
        ];

        $eventElements['_repeatEventBody'] = [
            'type'      => 'literal',
            'html'      => "</summary>\n<div class='pfy-form-rrule-body-wrapper'",
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
        PageFactory::$pg->addCss($css);
        PageFactory::$pg->addBodyTagClass('pfy-form-data-received');
        $this->noShowOpened = true;
        return "<div class='pfy-show-unless-form-data-received-$this->formIndex'>\n";
    } // injectNoShowCssRule


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
        if ($time = ($this->formOptions['windowFreezeTime']??false)) {
            $js = "pfyFormsHelper.freezeWindowAfter('$time');";
            PageFactory::$pg->addJsReady($js);
        }
    } // activateWindowFreeze

} // PfyForm
