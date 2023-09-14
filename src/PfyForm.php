<?php

namespace Usility\PageFactory;

use Kirby\Exception\InvalidArgumentException;
use Nette\Forms\Form;
use Nette\Utils\Html;
use voku\helper\HtmlDomParser;
use Kirby\Email\PHPMailer;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactoryElements\Events as Events;
use Usility\PageFactoryElements\DataTable as DataTable;
use function Usility\PageFactory\var_r as var_r;
use function Usility\PageFactoryElements\array_splice_associative as array_splice_associative;

define('ARRAY_SUMMARY_NAME', '_');
const FORMS_SUPPORTED_TYPES =
    ',text,password,email,textarea,hidden,readonly,'.
    'url,date,datetime-local,time,datetime,month,integer,number,range,tel,'.
    'radio,checkbox,dropdown,select,multiselect,upload,multiupload,bypassed,'.
    'button,reset,submit,cancel,@import,';
    // future: toggle,hash,fieldset,fieldset-end,reveal,literal,file,

const INFO_ICON = 'ⓘ';
const MEGABYTE = 1048576;

mb_internal_encoding("utf-8");


class PfyForm extends Form
{
    private array $formOptions;
    private array $elemOptions;
    private array $tableOptions = [];
    private array $fieldNames = [];
    private array $formElements = [];
    private array $choiceOptions = [];
    private array $bypassedElements = [];
    private $db = false;
    private $file = false;
    private $dataTable = false;
    private int $formIndex = 0;
    private int $index = 0;
    private int $revealInx = 0;
    protected bool $isFormAdmin = false;
    protected bool $inhibitAntiSpam = false;
    protected bool $showDirectFeedback = true;
    protected bool $showForm = true;
    private string $name;
    private string $formWrapperClass = '';
    private string $tableButtons = '';
    private $callback;
    private array $auxBannerValues = [];
    private $matchingEventAvailable = null;
    private $requiredInputFound = false;
    private $addFormTableWrapper = false;
    public static $formInx = 0;

    /**
     * @param $formOptions
     * @throws \Exception
     */
    public function __construct($formOptions = [])
    {
        self::$formInx++;
        $this->formOptions = &$formOptions;
        $tableOptions = [];
        $this->formIndex = $formOptions['formInx'] ?? self::$formInx;

        $tableOptions['showData']           = $formOptions['showData']??false;
        $tableOptions['editTable']          = $formOptions['editData']??false;
        $tableOptions['tableButtons']       = $formOptions['tableButtons']??false;
        $tableOptions['serviceColumns']     = $formOptions['serviceColumns']??false;
        $tableOptions['sort']               = $formOptions['sortData']??false;
        $tableOptions['footers']            = $formOptions['tableFooters']??false;
        $tableOptions['minRows']            = $formOptions['minRows']??false;
        $tableOptions['interactive']        = $formOptions['interactiveTable']??false;
        $tableOptions['includeSystemFields']= $formOptions['includeSystemFields']??false;
        $this->tableOptions                 = $this->parseTableOptions($tableOptions);
        unset($tableOptions);

        // make sure essential options are instantiated:
        $formOptions['file']                = $formOptions['file']??false;
        $formOptions['confirmationText']    = $formOptions['confirmationText']??false;
        $formOptions['mailTo']              = $formOptions['mailTo']??false;
        $formOptions['maxCount']            = $formOptions['maxCount']??false;
        $formOptions['maxCountOn']          = $formOptions['maxCountOn']??false;
        $formOptions['formTop']             = $formOptions['formTop']??false;
        $formOptions['formHint']            = $formOptions['formHint']??false;
        $formOptions['formBottom']          = $formOptions['formBottom']??false;
        $formOptions['confirmationEmail']   = $formOptions['confirmationEmail']??false;
        $formOptions['mailFrom']            = $formOptions['mailFrom']??false;
        $formOptions['mailFromName']        = $formOptions['mailFromName']??false;
        $formOptions['deadline']            = $formOptions['deadline']??false;
        $formOptions['id']                  = $formOptions['id']??false;
        $formOptions['class']               = $formOptions['class']??false;
        $formOptions['action']              = $formOptions['action']??false;
        $formOptions['next']                = $formOptions['next']??'~page/';
        $formOptions['callback']            = $formOptions['callback']??false;
        $formOptions['dbOptions']           = $formOptions['dbOptions']??[];
        $formOptions['dbOptions']['masterFileRecKeyType'] = ($formOptions['dbOptions']['masterFileRecKeyType']??false)?: 'index';

        $this->showDirectFeedback           = $formOptions['showDirectFeedback']??true;
        $recLocking                         = $formOptions['recLocking']??false;
        $this->formWrapperClass             = ($formOptions['wrapperClass']??'')? ' '.$formOptions['wrapperClass'] :'';

        if ($recLocking) {
            PageFactory::$pg->addJs('const pfyFormRecLocking = true;');
        }
        if ($this->tableOptions['tableButtons'] || $this->tableOptions['serviceColumns']) {
            $this->addFormTableWrapper = true;
            $permissionQuery = $this->tableOptions['permission'];
            $this->isFormAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
        }

        $this->handleScheduleOption();

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

        if ($this->tableOptions['editMode']) {
            PageFactory::$pg->addAssets('POPUPS');
        }
        PageFactory::$pg->addAssets('POPUPS');
        PageFactory::$pg->addAssets('REVEAL');
        PageFactory::$pg->addAssets('FORMS');
    } // __construct


    /**
     * @return string
     * @throws \Exception
     */
    public function renderForm($formElements): string
    {
        $this->formElements = $formElements;
        $this->fireRenderEvents();
        $html = $this->renderFormHead();
        $html .= $this->renderFormFields();
        $html .= $this->renderFormTail();
        return $html;
    } // renderForm


    /**
     * @param array|null $formOptions
     * @param array $formElements
     * @return void
     * @throws InvalidArgumentException
     */
    public function createForm(): void
    {
        // handle '@import' => import form element defs from file:
        $this->handleFieldsImport();

        // add fields:
        foreach ($this->formElements as $name => $elemOptions) {
            if (!is_array($elemOptions)) {
                throw new \Exception("Syntax error in Forms option '$name'");
            }
            $this->addElement($name);
        }
    } // createForm


    /**
     * @param array $elemOptions
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function addElement(string $elemName, array $elemOptions = null): void
    {
        $this->index++;
        if ($elemOptions === null) {
            $elemOptions = &$this->formElements[$elemName];
            $elemOptions['name'] = $elemName;
        }


        // determine $label, $name, $type and $subType:
        list($label, $name, $type) = $this->parseMainOptions($elemOptions);
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
            case 'number':
                $elem = $this->addIntegerElem($name, $label);
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
                $elem = $this->addUpload($name, $label);
                $elem->addRule($this::Image, 'File must be JPEG, PNG, GIF or WebP');
                if ($mb = ($elemOptions['maxMegaByte']??false)) {
                    $elem->addRule($this::MaxFileSize, "Maximum size is $mb MB", MEGABYTE * $mb);
                }
                break;
            case 'multiupload':
                $elem = $this->addMultiUpload($name, $label);
                $elem->addRule($this::Image, 'File must be JPEG, PNG, GIF or WebP');
                if ($mb = ($elemOptions['maxMegaByte']??false)) {
                  $elem->addRule($this::MaxFileSize, "Maximum size is $mb MB", MEGABYTE * $mb);
                }
                break;
            case 'button':
                $elem = $this->addButton($name, $label);
                break;
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
            $id = "frm-$type-$this->formIndex-$this->index";
            $elem->setHtmlId($id);
        }

        $class = "pfy-$type";

        // handle 'required' option:
        if (($elemOptions['required']??false) !== false) {
            $elem->setRequired($elemOptions['required']);
            $this->requiredInputFound = true;
        }

        // handle 'disabled' option:
        if (($elemOptions['disabled']??false) !== false) {
            $elem->setDisabled();
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
            $elem->setHtmlAttribute('data-preset', $preset);
        }

        // handle compute-saveAs:
        if ($saveAs = ($elemOptions['saveAs']??false)) {
            $this->formElements[$name]['saveAs'] = $saveAs;
        }

        // handle min:
        if ($min = ($elemOptions['min']??false)) {
            $elem->setHtmlAttribute('min', $min);
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
            $elem->setHtmlAttribute('max', $max);
        }

        // handle 'wrapperId' option:
        if ($wrapperId = ($elemOptions['wrapperId']??false)) {
            $elem->setHtmlAttribute('data-wrapper-id', $wrapperId);
        }

        // handle 'antiSpam' option:
        if ($antiSpam = ($elemOptions['antiSpam']??false)) {
            $elem->setHtmlAttribute('data-check', $antiSpam);
            $elem->setHtmlAttribute('aria-hidden', 'true');
            $elem->setHtmlAttribute('tabindex', '-1');
            $elem->setOmitted();
            unset($this->fieldNames[$name]);
        }

        // note: 'info' option handled in parseMainOptions()
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
        if ($revealLabel = ($elemOptions['reveal']??false)) {
            $this->revealInx++;
            $targetId = "pfy-reveal-container-{$this->formIndex}_$this->revealInx";
            PageFactory::$pg->addAssets('REVEAL');

            $elem1 = $this->addCheckbox("CommentController{$this->formIndex}_$this->revealInx", $revealLabel);
            $elem1->setHtmlAttribute('class', 'pfy-reveal-controller');
            $elem1->setHtmlAttribute('aria-controls', $targetId);
            $elem1->setHtmlAttribute('data-reveal-target', "#$targetId");
            $elem1->setHtmlAttribute('data-icon-closed', '+');
            $elem1->setHtmlAttribute('data-icon-open', '∣');
            $label = $elemOptions['label']??'';
        }
        $elem = $this->addTextarea($name, $label);
        if ($revealLabel) {
            $this->elemOptions['class'] = ($elemOptions['class']??'') ? $elemOptions['class'].' pfy-reveal-target':'pfy-reveal-target';
            $elem->setHtmlAttribute('data-reveal-target-id', $targetId);
        }
        return $elem;
    } // addTextareaElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\TextInput
     */
    private function addIntegerElem(string $name, string|object $label): object
    {
        $elem = $this->addInteger($name, $label);
        return $elem;
    } // addIntegerElem


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
        $selectionElems = parseArgumentStr($elemOptions['options']);
        foreach ($selectionElems as $key => $value) {
            if (!$value) {
                $selectionElems[$key] = '{{ pfy-form-select-empty-option }}';
            }
        }
        if ($type === 'multiselect') {
            $elem = $this->addMultiSelect($name, $label, $selectionElems);
            $this->formElements[$name]['isArray'] = true;
            $this->formElements[$name]['subKeys'] = array_keys($selectionElems);
        } else {
            $elem = $this->addSelect($name, $label, $selectionElems);
        }
        if ($preset = ($elemOptions['preset']??false)) {
            $elem->setHtmlAttribute('data-preset', $preset);
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
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\RadioList
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addRadioElem(string $name, string $label): object
    {
        $elemOptions = &$this->formElements[$name];
        $radioElems = parseArgumentStr($elemOptions['options']);
        $elem = $this->addRadioList($name, $label, $radioElems);
        if ($elemOptions['preset']??false) {
            $elem->setHtmlAttribute('data-preset', $elemOptions['preset']);
        }
        $this->formElements[$name]['isArray'] = true;
        $this->formElements[$name]['subKeys'] = array_keys($radioElems);
        // handle option 'horizontal':
        $elemOptions['class'] .= (($layout = ($elemOptions['layout']??false)) && ($layout[0] !== 'h')) ? '' : ' pfy-horizontal';

        if ($elemOptions['splitOutput']??false) {
            $this->addFieldNames($name, $radioElems);
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
            if (is_string($elemOptions['options'])) {
                $checkboxes = parseArgumentStr($elemOptions['options']);
            } else {
                $checkboxes = $elemOptions['options'];
            }
            $elem = $this->addCheckboxList($name, $label, $checkboxes);
            if ($elemOptions['preset']??false) {
                $elem->setHtmlAttribute('data-preset', $elemOptions['preset']);
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
            $elem = $this->addCheckbox($name, $label);
            if ($elemOptions['preset']??false) {
                $elem->setHtmlAttribute('data-preset', $elemOptions['preset']);
                $elem->setHtmlAttribute('value', $elemOptions['preset']);
            }
        }

        $elem->setHtmlAttribute('class', "pfy-form-checkbox");
        return $elem;
    } // addCheckboxElem


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
    public function handleReceivedData(): mixed
    {
        $html = false;
        $dataRec = $this->getValues(true);

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
            return '';
        }

        // check whether received data applies to currently processed form (e.g. if there are multiple forms in a page):
        if (intval($formInxReceived) !== $this->formIndex) {
            return null; // signal 'processing skipped, continue processing'
        }

        $csrf = $_POST['_csrf']??false;
        if (!$csrf || !csrf($csrf)) {
            reloadAgent(null, '{{ pfy-form-session-expired }}');
        }

        // handle 'callback' on data received:
        if ($this->formOptions['callback']) {
            list($html, $continueEval) = $this->handleCallback($dataRec);
            if (!$continueEval) {
                return $html;
            }
        }

        $recKey = $dataRec['_reckey']??false;

        $dataRec = $this->normalizeData($dataRec);

        if (is_string($dataRec)) {
            // string means spam detected:
            return "<div class='pfy-form-error'>$dataRec</div>\n";
        }

        // handle optional 'deadline' and 'maxCount':
        if ($this->handleDeadline() || $this->handleMaxCount($dataRec)) {
            if (!$this->isFormAdmin) {
                return '';
            }
        }

        // handle uploads
        $this->handleUploads($dataRec);

        // if 'file' defined, save received data:
        if ($this->formOptions['file']) {
            $this->file = $this->formOptions['file'];
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
        }

        // handle optional confirmation mail:
        $html .= $this->handleConfirmationMail($dataRec);

        // add 'continue...' if direct feedback is active:
        if ($this->showDirectFeedback) {
            $html .= "<div class='pfy-form-success-continue'><a href='{$this->formOptions['next']}'>{{ pfy-form-success-continue }}</a></div>\n";
        }

        // write log:
        $logText = strip_tags($html);
        mylog($logText, 'form-log.txt');

        // handle indirect feedback -> via message:
        if ($html && !$this->showDirectFeedback) {
            reloadAgent(message: $logText);
        }

        if (isset($_POST)) {
            unset($_POST);
        }
        $this->showForm = false;
        return $html;
    } // handleReceivedData


    /**
     * @param mixed $recs
     * @return void
     */
    private function handleUploads(mixed $recs): void
    {
        foreach ($recs as $key => $rec) {
            if (is_array($rec)) {
                foreach ($rec as $k => $r) {
                    if (is_a($r, 'Nette\Http\FileUpload')) {
                        $this->handleUploadedFile($key, $r);
                        unset($recs[$key][$k]);
                    }
                }
            } else {
                if (is_a($rec, 'Nette\Http\FileUpload')) {
                    $this->handleUploadedFile($key, $rec);
                }
            }
        }
    } // handleUploads


    /**
     * @param $key
     * @param $rec
     * @return void
     * @throws \Exception
     */
    private function handleUploadedFile($key, $rec)
    {
        $path = $this->formElements[$key]['args']['path']??false;
        $path = resolvePath($path);
        preparePath($path);
        $filename = $rec->name;
        $filename = basename($filename);
        $filename = str_replace('..','.', $filename);
        $filename = preg_replace('/[^.\w-]/','', $filename);
        $rec->move($path.$filename);
    } // handleUploadedFile($key, $rec)


    /**
     * @param array $dataRec
     * @return array
     */
    private function normalizeData(array $dataRec): array|string
    {
        $bypassedElements = array_keys($this->bypassedElements);
        foreach ($dataRec as $name => $value) {
            // handle anti-spam field:
            if ($this->formElements[$name]['args']['antiSpam'] ?? false) {
                if ($value !== '') {
                    mylog("Spam detected: field '$name' was not empty: '$value'.", 'form-log.txt');
                    return 'pfy-anti-spam-warning';
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

            if (in_array($name, $bypassedElements)) {
                $dataRec[$name] = $this->bypassedElements[$name];
            }
        }
        return $dataRec;
    } // normalizeData


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
            kirby()->session()->set($sessKey, $this->formOptions['file']);
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
        $this->openDB();

        if (!$recId && $this->db->recExists($newRec)) {
            return 'pfy-form-warning-record-already-exists';
        }

        $res = $this->db->addRec($newRec, recKeyToUse: $recId);
        if (is_string($res)) {
            return $res;
        }
        return false;
    } // storeSubmittedData


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

        $subject = TransVars::getVariable('pfy-form-owner-notification-subject');
        if (str_contains($subject, '%host%')) {
            $subject = str_replace('%host%', PageFactory::$hostUrl, $subject);
        }

        $body = TransVars::getVariable('pfy-form-owner-notification-body');
        $body = str_replace(['%data%', '%host%', '\n'], [$out, PageFactory::$hostUrl, "\n"], $body);

        $to = $formOptions['mailTo']?: TransVars::getVariable('webmaster_email');
        $this->sendMail($to, $subject, $body, 'Notification Mail to Onwer');
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

        $file = resolvePath($this->formOptions['file'], relativeToPage: true);

        $fieldNames = $this->fieldNames;
        if (isset($fieldNames['_formInx'])) {
            unset($fieldNames['_formInx']);
        }
        $tableOptions['tableHeaders']         = $fieldNames;
        $tableOptions['masterFileRecKeyType'] = 'index';
        $tableOptions['tdClass']              = 'pfy-scroll-hints';
        $tableOptions['markLocked']           = true;
        $tableOptions['obfuscateRecKeys']     = true;

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
        $tableOptions['permission'] = false;
        $tableOptions['tableButtons'] = false;
        $tableOptions['serviceColumns'] = false;
        $tableOptions['editMode'] = false;
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
            } else {
                $tableOptions['permission'] = $editTable['permission'] ?? 'localhost,loggedin';
                $tableOptions['tableButtons'] = $editTable['tableButtons'] ?? 'download';
                $tableOptions['serviceColumns'] = $editTable['serviceColumns'] ?? 'select,num';
                $tableOptions['editMode'] = $editTable['mode'] ?? 'inpage';
            }
            PageFactory::$pg->applyRobotsAttrib();

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
            PageFactory::$pg->applyRobotsAttrib();
        }

        unset($tableOptions['editTable']);

        return $tableOptions;
    } // parseTableOptions


    /**
     * @return string
     * @throws \Exception
     */
    private function renderDataTable(): string
    {
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
            $header = '<h2>{{ pfy-table-data-output-header }}</h2>';
        }
        if ($html) {
            $html = <<<EOT
<div class='pfy-table-data-output-wrapper'>
$header
$html
</div>
EOT;
        }
		// if data was empty and we added an empty rec, remove it now:
        if ($noData) {
            $ds->purge();
        }

        return $html;
    } // renderDataTable


    /**
     * @param array $elemOptions
     * @return array
     * @throws \Exception
     */
    private function parseMainOptions(array &$elemOptions): array
    {
        $args = [];
        $label = $elemOptions['label'] ?? false;
        $name = $elemOptions['name'] ?? false;

        if (isset($elemOptions['options'])) {
            $options = $elemOptions['options'];
            if ($options && is_string($options) && $options[0] === '$') {
                $elemOptions['options'] = handleDataImportPattern($options);
            }
        }

        // case: only label given:
        if ($label && !$name) {
            $name = $label;

        // case: only name given:
        } elseif (!$label && $name) {
            if ($name === 'cancel') { // handle short-hands for cancel and confirm
                $label = '{{ pfy-cancel }}';
            } elseif ($name === 'submit') {
                $label = '{{ pfy-submit }}';
            } else {
                $label = ucwords(str_replace('_', ' ', $name)) . ':';
            }
        }

        // shape name: replace '-' with '_' and remove all non-alpha:
        $name = str_replace('-', '_', $name);
        $name = preg_replace('/\W/', '', $name);

        // if elem marked by asterisk, remove it - will be visualized by class required:
        if ($label[strlen($label) - 1] === '*') {
            $elemOptions['required'] = true;
            $label = rtrim(substr($label, 0, -1));
        }

        $label0 = str_replace(['*',':'], '', $label);
        $elemOptions['label'] = $label0; //???

        // handle 'info' option:
        if ($info = ($elemOptions['info'] ?? false)) {
            $label .= "<button type='button' class='pfy-tooltip-anker'>".INFO_ICON.
                "</button><span class='pfy-tooltip'>$info</span>";
        }

        // if label contains HTML, we need to transform it:
        if (str_contains($label, '<')) {
            $label = Html::el('span')->setHtml($label);
        }

        $type = isset($elemOptions['type']) ? ($elemOptions['type']?:false): false;

        // shorthand:
        if ($type === 'required') {
            $elemOptions['required'] = true;
            $type = 'text';
        }

        $_name = strtolower($name);
        $type = $this->determineType($_name, $type);

        if (!isset($elemOptions['class'])) {
            $elemOptions['class'] = '';
        }

        if ($path = ($elemOptions['path']??false)) {
            $args['path'] = $path;
        }

        // handle 'antiSpam' option:
        if (($elemOptions['antiSpam']??false) !== false) {
            if ($this->inhibitAntiSpam) {
                $elemOptions['antiSpam'] = false;
                return [null, null, null];
            } else {
                $elemOptions['class'] .= ' pfy-obfuscate';
                $args['antiSpam'] = $elemOptions['antiSpam'];
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

        if (array_key_exists('required', $elemOptions)) {
            $elemOptions['required'] = ($elemOptions['required'] !== false);
        } else {
            $elemOptions['required'] = false;
        }
        if (array_key_exists('disabled', $elemOptions)) {
            $elemOptions['disabled'] = ($elemOptions['disabled'] !== false);
        } else {
            $elemOptions['disabled'] = false;
        }

        // check choice options, convert to key:value if string:
        if (is_string($elemOptions['options']??false)) {
            $o = parseArgumentStr($elemOptions['options']);
            $out = '';
            foreach ($o as $k => $v) {
                $v = str_replace("'", "\\'", $v);
                if (is_int($k)) {
                    $out .= "$v:'$v',";
                } else {
                    $out .= "$k:'$v',";
                }
            }
            $out = rtrim($out, ',');
            $elemOptions['options'] = rtrim($out, ',');
        }

        return array($label, $name, $type);
    } // parseMainOptions


    /**
     * @return string
     * @throws \Exception
     */
    private function renderFormTop(): string
    {
        if ($str = $this->formOptions['formTop']) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-top'>$str</div>\n";
        }
        return $str;
    } // renderFormTop


    /**
     * @return string
     * @throws \Exception
     */
    function renderFormBottom(): string
    {
        if ($str = ($this->formOptions['formBottom']??false)) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-bottom'>$str</div>\n";
        }
        return $str;
    } // renderFormBottom


    /**
     * @return string|false
     * @throws \Exception
     */
    private function renderFormHint(): string|false
    {
        if (!($str = ($this->formOptions['formHint']??false)) && $this->requiredInputFound) {
            $str = '{{ pfy-form-required-info }}';
        }
        if ($str) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-hint'>$str</div>\n";
        }
        return $str;
    } // renderFormHint


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
     * @return string|false
     */
    private function handleDeadline(): string|false
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
                        return $deadlineNotice;
                    } else {
                        return '{{ pfy-form-deadline-expired }}';
                    }
                } else {
                    return TransVars::getVariable('pfy-form-deadline-expired-warning');
                }
            }
        }
        return false;
    } // handleDeadline


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
            throw new \Exception("Form: option 'schedule' without option 'file'.");
        }
        $this->matchingEventAvailable = false;

        unset($eventOptions['file']);
        $sched = new Events($src, $eventOptions);
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
        }

        if ($maxCount = ($nextEvent['maxCount']??false)) {
            $this->formOptions['maxCount'] = $maxCount;
            $this->tableOptions['minRows'] = $maxCount;
        }
        $this->matchingEventAvailable = true;
    } // handleScheduleOption


    /**
     * @param array $dataRec
     * @return string|false
     * @throws \Exception
     */
    private function handleMaxCount(array $dataRec = []): string|false
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
                        return $maxCountNotice;
                    } else {
                        return '{{ pfy-form-maxcount-reached }}';
                    }
                } else {
                    return TransVars::getVariable('pfy-form-maxcount-reached-warning');
                }
            }
        }
        return false;
    } // handleMaxCount


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

        list($subject, $message) = $this->getEmailComponents();
        $to = $this->propagateDataToVariables($dataRec);
//ToDo: Twig instead of TransVars?
        $subject = TransVars::translate($subject);
        $message = TransVars::translate($message);
        if ($to) {
            $this->sendMail($to, $subject, $message, 'Confirmation Mail to Visitor');
            return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-sent }}</div>\n";
        }
        return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-missing }}</div>\n";
    } // sendConfirmationMail


    /**
     * @return array
     * @throws \Exception
     */
    private function getEmailComponents(): array
    {
        $confirmationEmailTemplate = ($this->formOptions['confirmationEmailTemplate']??true);
        if ($confirmationEmailTemplate === true) {
            $subject = '{{ pfy-confirmation-response-subject }}';
            $template = '{{ pfy-confirmation-response-message }}';

        } else {
            $confirmationEmailTemplate1 = resolvePath($confirmationEmailTemplate, relativeToPage: true);
            if (!is_file($confirmationEmailTemplate1)) {
                throw new \Exception("Forms confirmationEmail: template  '$confirmationEmailTemplate' not found");
            }
            $template = fileGetContents($confirmationEmailTemplate1);
            if (preg_match("/^subject:(.*?)\n/i", $template, $m)) {
                $subject = trim($m[1]);
                $template = trim(str_replace($m[0], '', $template));
            } else {
                $subject = '{{ pfy-form-confirmation-email-subject }}';
            }
        }
        return [$subject, $template];
    } // getEmailComponents


    /**
     * @param array $dataRec
     * @return string
     */
    private function propagateDataToVariables(array $dataRec): string
    {
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
        return $to;
    } // propagateDataToVariables


    /**
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string $debugInfo
     * @return void
     */
    private function sendMail(string $to, string $subject, string $body, string $debugInfo = ''): void
    {
        $props = [
            'to' => $to,
            'from' => $this->formOptions['mailFrom'] ?: TransVars::getVariable('webmaster_email'),
            'fromName' => $this->formOptions['mailFromName'] ?: false,
            'subject' => $subject,
            'body' => $body,
        ];

        new PHPMailer($props);
        mylog("$subject\n\n$body", 'mail-log.txt');
    } // sendMail


    /**
     * @param array $dataRec
     * @return array
     */
    private function handleCallback(array &$dataRec): array
    {
        $callback = $this->formOptions['callback'];
        $res = $callback($dataRec);

        if (is_array($res)) {
            $html         = ($res['html']?? ($res[0]??''));
            $continueEval = $res['continueEval']?? ($res[1]??true);
            $this->showForm   = $res['showForm']?? ($res[2]??true);
            $this->showDirectFeedback = $res['showDirectFeedback']?? ($res[3]??true);
            if (isset($res[4]) || isset($res['dataRec'])) {
                $dataRec   = $res['dataRec'] ?? $res[4];
            }

        } else {
            $html = '';
            $continueEval = (bool)$res;
        }
        return [$html, $continueEval];
    } // handleCallback


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
                                $newFields1[$k] = parseArgumentStr($a);
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
    public function determineType(array|string|null $_name, mixed $type): string
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
    public function getHeadAttributes(): array
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
     * @return string
     * @throws \Exception
     */
    public function renderFormHead(): string
    {
        $html = '';
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
            $this->setAction(PageFactory::$pageUrl);
        }
        $html .= "<!-- pfy-form-wrapper -->\n";
        $formInx = self::$formInx;
        $wrapperClass = "pfy-form-wrapper pfy-form-wrapper-$formInx" . $this->formWrapperClass;

        if ($this->addFormTableWrapper) {
            $html .= "<div class='pfy-form-and-table-wrapper'>\n";
        }

        $html .= "<div class='$wrapperClass'>\n";

        list($id, $formClass, $aria) = $this->getHeadAttributes();

        $htmlForm = $this->getRenderer()->render($this, 'begin');
        $htmlForm = "<form$id class='$formClass'$aria" . substr($htmlForm, 5);
        $html .= $htmlForm;
        $html .= $this->getRenderer()->render($this, 'errors');
        $html .= $this->renderFormTop();
        $html .= "\n<div class='pfy-elems-wrapper'>\n";

        return $html;
    } // renderFormHead


    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public function renderFormFields(array|null $formElements = null): string
    {
        if ($formElements !== null) {
            $this->formElements = $formElements;
        }
        $this->createForm();

        foreach ($this->formElements as $key => $rec) {
            if (preg_match('/\W/', $key)) {
                $key1 = str_replace('-', '_', $key);
                $key1 = preg_replace('/\W/', '', $key1);
                $this->formElements = array_splice_associative($this->formElements, $key, 1, [$key1 => $rec]);
            }
        }

        $html = '';
        $formButtons = [];
        foreach ($this->formElements as $name => $rec) {
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
            if (str_contains(($rec['label']??''), '*')) {
                $rec['required'] = true;
            }
            $label = "<span class='pfy-label-wrapper'>$label</span>";
            $input = (string)$elem->getControl();
            $type = $this->determineType($_name, $rec['type']??false);

            if ($type === 'password') {
                $icon = svg('site/plugins/pagefactory-pageelements/assets/icons/show.svg').
                        svg('site/plugins/pagefactory-pageelements/assets/icons/hide.svg');
                $input .= "<button type='button' class='pfy-form-show-pw' aria-pressed='false'>$icon</button>";
            }
            if ($description = ($rec['description']??'')) {
                $input .= "<span class='pfy-form-field-description'>$description</span>";
            }

            $class = $rec['class'];
            $class .= ($rec['required']??false) ? ' required' : '';

            if ($type === 'hidden') {
                $html .= $input;

            } elseif (($type === 'textarea') && ($rec['reveal']??false)) {
                $inx = self::$formInx."_$this->revealInx";
                $controllerLabel = $rec['reveal'];
                $html .= <<<EOT
<div class="pfy-elem-wrapper pfy-reveal-controller">
<span class="pfy-reveal-controller-label"><label for="frm-CommentController$inx" class=""><input type="checkbox" name="CommentController$inx" class="pfy-reveal-controller" aria-controls="pfy-reveal-container-$inx" data-reveal-target="#pfy-reveal-container-$inx" data-icon-closed="+" data-icon-open="∣" id="frm-CommentController$inx" aria-expanded="false">$controllerLabel</label></span>
</div>
EOT;

                $html .= <<<EOT
<div id='pfy-reveal-container-$inx' class="pfy-reveal-container" aria-hidden="true">
<!-- pfy-elem-wrapper -->
<div class="pfy-elem-wrapper pfy-$type$class">
<span class="pfy-input-wrapper">
$input
</span>
</div><!-- /pfy-elem-wrapper -->
</div>

EOT;

            } elseif (str_contains(',cancel,submit,reset,', ",$type,")) {
                $elem->setHtmlAttribute('class', "pfy-$type button");
                $h = (string)$elem->getControl();
                $formButtons[] = $h;

            } elseif (!str_contains(',bypassed,@import,', ",$type,")) {
                $input = "<span class='pfy-input-wrapper'>$input</span>";
                $html .= <<<EOT
<!-- pfy-elem-wrapper -->
<div class="pfy-elem-wrapper pfy-$type$class">
$label
$input
</div><!-- /pfy-elem-wrapper -->

EOT;
            }
        }
        if ($formButtons) {
            $html .= $this->renderFormHint();
            $btns = implode(' ', $formButtons);
            $html .= <<<EOT
<div class="pfy-elem-wrapper pfy-cancel button pfy-submit">
<span class="pfy-input-wrapper">$btns</span>
</div>

EOT;
        }
        return $html;
    } // renderFormFields


    /**
     * @return string
     * @throws \Exception
     */
    public function renderFormTail(): string
    {
        // add standard hidden fields to identify data: which form, which data-record:
        $html = $this->_renderFormTail();

        $msg = '';
        if ($this->isSuccess()) {
            $msg = $this->handleReceivedData();
            $html .= $msg;
        }

        if (!$msg) {
            // handle deadline option:
            if ($msg = $this->handleDeadline()) {
                $this->showForm = $this->isFormAdmin;
                $html .= $msg;
            }

            // handle maxCount option:
            if ($msg = $this->handleMaxCount()) {
                $this->showForm = $this->isFormAdmin;
                $html .= $msg;
            }
        }

        if (!$this->showForm) {
            // render result of (successful) data reception:
            $css = ".pfy-form-{$this->formIndex},\n".
                ".pfy-show-unless-form-data-received,\n".
                ".pfy-show-unless-form-data-received-$this->formIndex {display:none;}";
            PageFactory::$pg->addCss($css);
        }

        if (($this->tableOptions['editMode'] || $this->tableOptions['showData']) && $this->formOptions['file'] && $this->isFormAdmin) {
            $html .= $this->renderDataTable();
        }
        if ($this->addFormTableWrapper) {
            $html .= "</div>\n<!-- /pfy-form-and-table-wrapper -->\n";
        }

        return $html;
    } // renderFormTail


    /**
     * @return string
     * @throws InvalidArgumentException
     */
    private function _renderFormTail(): string
    {
        // add standard hidden fields to identify data: which form, which data-record:
        $html = '';
        $this->addElement('', ['type' => 'hidden', 'name' => '_reckey', 'value' => $formOptions['recId'] ?? '', 'preset' => '']);
        $elem = $this['_reckey'];
        $html .= $elem->getControl()."\n";

        $this->addElement('', ['type' => 'hidden', 'name' => '_formInx', 'value' => $this::$formInx, 'preset' => $this::$formInx]);
        $elem = $this['_formInx'];
        $html .= $elem->getControl()."\n";

        $this->addElement('', ['type' => 'hidden', 'name' => '_csrf', 'value' => ($csrf = csrf()), 'preset' => $csrf]);
        $elem = $this['_csrf'];
        $html .= $elem->getControl()."\n";

        $html .= "</div><!-- /pfy-elems-wrapper -->\n";

        $html .= $this->renderFormBottom();

        $html .= $this->getRenderer()->render($this, 'end'); // </form>

        $html .= "</div>\n<!-- /pfy-form-wrapper -->\n";
        return $html;
    } // _renderFormTail

} // PfyForm
