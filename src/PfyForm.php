<?php

namespace Usility\PageFactory;

use Nette\Forms\Form;
use Nette\Utils\Html;
use voku\helper\HtmlDomParser;
use Kirby\Email\PHPMailer;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactoryElements\DataTable as DataTable;
use function Usility\PageFactory\var_r as var_r;

define('ARRAY_SUMMARY_NAME', '_');
const FORMS_SUPPORTED_TYPES =
    ',text,password,email,textarea,hidden,'.
    'url,date,datetime-local,time,datetime,month,number,integer,range,tel,'.
    'radio,checkbox,dropdown,select,multiselect,upload,multiupload,bypassed,'.
    'button,reset,submit,cancel,';
    // future: toggle,hash,fieldset,fieldset-end,reveal,literal,file,

const INFO_ICON = 'ⓘ';
const MEGABYTE = 1048576;

mb_internal_encoding("utf-8");

require_once __DIR__ . '/ajax_server.php';

class PfyForm extends Form
{
    private array $formOptions;
    private array $elemOptions;
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
    protected bool $showDirektFeedback = true;
    private string $name;
    private string $formWrapperClass = '';
    private string $tableButtons = '';

    /**
     * @param $formOptions
     */
    public function __construct($formOptions = [])
    {
        $this->formIndex = $formOptions['formInx'] ?? 1;
        $this->formOptions = $formOptions;
        if (isset($_GET['delete'])) {
            if ($_POST['pfy-reckey']??false) {
                $_POST['pfy-reckey'] = deObfuscateRecKeys($_POST['pfy-reckey']);
                $this->openDataTable(); // triggers delete-handler
            }
        }

        // open data base
        if ($formOptions['file']??false) {
            $this->openDB();
        }

        // option 'editData':
        if ($editData = ($formOptions['editData'] ?? false)) {
            $this->inhibitAntiSpam = true;
            if ($editData === 'popup' && $this->db && ($this->db->getSize() > 0)) {
                $this->formWrapperClass .= ' pfy-table-edit-popup';
                $this->showDirektFeedback = false;
            } elseif ($editData === true) {
                $this->formOptions['editData'] = 'inpage';
            }
            if (!($formOptions['showData']??false)) {
                $formOptions['showData'] = [
                    'permission' => 'loggedin|localhost',
                    'tableButtons' => 'delete,new,download'
                ];
                $this->formOptions['showData'] = $formOptions['showData'];
            }
        }

        // option 'showData':
        if ($permissionQuery = ($formOptions['showData']??false)) {
            if ($permissionQuery === true) {
                $permissionQuery = 'loggedin|localhost';

            } elseif (is_array($permissionQuery)) {
                $this->tableButtons = $permissionQuery['tableButtons']??'';
                $pq = $permissionQuery['permission']??true;
                if ($pq === true) {
                    $permissionQuery = 'loggedin|localhost';
                } else {
                    $permissionQuery = $pq;
                }
            }
            $this->isFormAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
        }

        parent::__construct();

        PageFactory::$pg->addAssets('POPUPS');
        PageFactory::$pg->addAssets('FORMS');

    } // __construct


    public function renderForm(): string
    {
        $res = false;
        if ($this->isSuccess()) {
            $res = $this->handleReceivedData($this->formOptions['formInx']??1);
            if (!$res && $this->formOptions['showData'] && $this->formOptions['file'] && $this->isFormAdmin) {
                reloadAgent();
            }
        }
        if (!$res) {
            $html = $this->renderFormHtml();
            if ($this->formOptions['showData'] && $this->formOptions['file'] && $this->isFormAdmin) {
                $html .= $this->renderDataTable();
            }

        } else {
            $html = $res;
            if ($this->formOptions['showData'] && $this->formOptions['file'] && $this->isFormAdmin) {
                $html .= $this->renderDataTable();
            }
        }
        $html = <<<EOT

<div class="pfy-form-wrapper pfy-form-wrapper-$this->formIndex$this->formWrapperClass">
<noscript>{{ pfy-noscript-warning }}</noscript>

$html

</div><!-- .pfy-form-wrapper -->
EOT;
        return $html;
    } // renderForm


    /**
     * @param array|null $formOptions
     * @param array $formElements
     * @return void
     */
    public function createForm(array|null $formOptions, array $formElements): void
    {
        if ($formOptions !== null) {
            $this->options = $formOptions;
        }
        // $formOptions['recId'] may contain a recId to use as _formInx, if not, we use formIndex:
        if ($formOptions['recId']??false) {
            $this->addElem(['type' => 'hidden', 'name' => '_formInx', 'value' => $formOptions['recId'], 'default' => '']);
        } else {
            $this->addElem(['type' => 'hidden', 'name' => '_formInx', 'value' => $this->formIndex, 'default' => $this->formIndex]);
        }

        foreach ($formElements as $name => $rec) {
            if (!is_array($rec)) {
                throw new \Exception("Syntax error in Forms option '$name'");
            }
            $rec['name'] = $name;
            $this->addElem($rec);
        }

        if ($formOptions['action']??false) {
            $this->setAction($formOptions['action']);
        } else {
            $this->setAction(PageFactory::$pageUrl);
        }
    } // createForm


    /**
     * @return string
     */
    private function renderFormHtml(): string
    {
        $formOptions = &$this->formOptions;

        // handle deadline option:
        if ($str = $this->handleDeadline()) {
            return $str;
        }

        // handle maxCount option:
        if ($str = $this->handleMaxCount()) {
            return $str;
        }

        $this->fireRenderEvents();
        $renderer = parent::getRenderer();

        $renderer->wrappers['controls']['container'] = 'div class="pfy-elems-wrapper"';
        $renderer->wrappers['pair']['container'] = 'div class="pfy-elem-wrapper"';
        $renderer->wrappers['label']['container'] = 'span class="pfy-label-wrapper"';
        $renderer->wrappers['control']['container'] = 'span class="pfy-input-wrapper"';

        $html = $renderer->render($this);

        $id = $formOptions['id']? " id='{$formOptions['id']}'" : '';
        $formClass = $formOptions['class'] ? " {$formOptions['class']}" : '';

        if ($this->isFormAdmin) {
            $formClass = " screen-only$formClass";
        }

        $html = "<form$id class='pfy-form pfy-form-$this->formIndex$formClass'" . substr($html, 5);

        $html = $this->injectFormElemClasses($html);
        $html = $this->handleFormTop($html);
        $html = $this->handleFormHint($html);
        $html = $this->handleFormBottom($html);

        return $html;
    } // renderFormHtml


    /**
     * @param array $elemOptions
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function addElem(array $elemOptions): void
    {
        $this->index++;
        // determine $label, $name, $type and $subType:
        list($label, $name, $type) = $this->parseMainOptions($elemOptions);
        if ($name === null) { // this is the case if antiSpam is suppressed by edit-rec option
            return;
        }
        $this->elemOptions = &$elemOptions;

        $subType = '';
        switch ($type) {
            case 'hidden':
                $elem = $this->addHidden($name, $elemOptions['value']??'');
                if ($id = $elemOptions['id']??false) {
                    $elem->setHtmlId($id);
                }
                break;
            case 'bypassed':
                $this->bypassedElements[$name] = $elemOptions['value']??'';
                $elem = $this->addHidden($name, '');
                if ($id = $elemOptions['id']??false) {
                    $elem->setHtmlId($id);
                }
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
                $elem = $this->addTextareaElem($name, $label, $elemOptions);
                break;
            case 'integer':
            case 'number':
                $type = 'number';
                $elem = $this->addNumberElem($name, $label, $elemOptions);
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
                $elem = $this->addSelectElem($name, $label, $elemOptions, $type);
                break;
            case 'radio':
                $elem = $this->addRadioElem($name, $label, $elemOptions);
                break;
            case 'checkbox':
                $elem = $this->addCheckboxElem($name, $label, $elemOptions);
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
            case 'cancel':
            case 'reset':
                $elem = $this->addButton('_cancel', $label);
                break;
            case 'submit':
                $elem = $this->addSubmit($name, $label);
                break;
        }

        $class = "pfy-$type";

        // handle 'required' option:
        if (($elemOptions['required']??false) !== false) {
            $elem->setRequired($elemOptions['required']);
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
        if ($preset = ($elemOptions['preset']??($elemOptions['value']??($elemOptions['default']??false)))) {
            $elem->setDefaultValue($preset);
            if (($elemOptions['default']??false) !== '') {
                // $elemOptions['default'] === ''  means don't provide default:
                $elem->setHtmlAttribute('data-default', $preset);
            }
        }

        // handle min:
        if ($min = ($elemOptions['min']??false)) {
            $elem->setHtmlAttribute('min', $min);
        }

        // handle max -> take into account case maxCount:
        if ($max = ($elemOptions['max']??false)) {
            if ($this->name === ($this->formOptions['maxCountOn']??false)) {
                // if sign-up limitation is active, limit max input if necessary, unless privileged:
                list($available, $maxCount) = $this->getAvailableAndMaxCount();
                if ($maxCount && !$this->isFormAdmin) {
                    $max = min($max, $available);
                }
            }
            $elem->setHtmlAttribute('max', $max);
        }

        // handle 'description' option:
        if ($str = ($elemOptions['description']??false)) {
            $str = Html::el('span')->setHtml($str);
            $elem->setOption('description', $str);
        }

        // handle 'antiSpam' option:
        if ($antiSpam = ($elemOptions['antiSpam']??false)) {
            $elem->setHtmlAttribute('data-check', $antiSpam);
            $elem->setHtmlAttribute('aria-hidden', 'true');
            unset($this->fieldNames[$name]);
            unset($this->formElements[$name]);
        }

        // note: 'info' option handled in parseMainOptions()
    } // addElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\TextArea
     * @throws \Exception
     */
    private function addTextareaElem(string $name, string $label, array $elemOptions): object
    {
        if ($revealLabel = ($elemOptions['reveal']??false)) {
            $this->revealInx++;
            $targetId = "#pfy-reveal-container-$this->revealInx";
            PageFactory::$pg->addAssets('REVEAL');

            $elem1 = $this->addCheckbox("CommentController$this->revealInx", $revealLabel);
            $elem1->setHtmlAttribute('class', 'pfy-reveal-controller');
            $elem1->setHtmlAttribute('aria-controls', $targetId);
            $elem1->setHtmlAttribute('data-reveal-target', $targetId);
            $elem1->setHtmlAttribute('data-icon-closed', '+');
            $elem1->setHtmlAttribute('data-icon-open', '∣');
            $label = $elemOptions['label']??'';
        }
        $elem = $this->addTextarea($name, $label);
        if ($revealLabel) {
            $this->elemOptions['class'] = ($elemOptions['class']??'') ? $elemOptions['class'].' pfy-reveal-target':'pfy-reveal-target';
            $elem->setHtmlAttribute('data-revealed-by', $targetId);
        }
        return $elem;
    } // addTextareaElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @return object|\Nette\Forms\Controls\TextInput
     */
    private function addNumberElem(string $name, string|object $label, array $elemOptions): object
    {
        $elem = $this->addInteger($name, $label);
        return $elem;
    } // addNumberElem


    /**
     * @param string $name
     * @param string $label
     * @param array $elemOptions
     * @param string $type
     * @return object|\Nette\Forms\Controls\MultiSelectBox|\Nette\Forms\Controls\SelectBox
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addSelectElem(string $name, string $label, array $elemOptions, string $type): object
    {
        $selectionElems = parseArgumentStr($elemOptions['options']);
        if ($type === 'multiselect') {
            $elem = $this->addMultiSelect($name, $label, $selectionElems);
            $this->formElements[$name]['isArray'] = true;
            $this->formElements[$name]['subKeys'] = array_keys($selectionElems);
        } else {
            $elem = $this->addSelect($name, $label, $selectionElems);
        }
        if ($preset = ($elemOptions['preset']??false)) {
            // only single preset possible due to Nette Forms:
            //            if (str_contains($preset, ',')) {
            //                $presets = explodeTrim(',', $preset);
            //                foreach ($presets as $preset) {
            //                    $elem->setDefaultValue($preset);
            //                }
            //            } else {
            //                $elem->setDefaultValue($preset);
            //            }
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
    private function addRadioElem(string $name, string $label, array &$elemOptions): object
    {
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
    private function addCheckboxElem(string $name, string $label, array &$elemOptions): object
    {
        if ($elemOptions['options']??false) {
            $checkboxes = parseArgumentStr($elemOptions['options']);
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
    public function handleReceivedData(int $formInx): string
    {
        $html = '';
        $dataRec = $this->getValues(true);

        // handle 'cancel' button:
        if (isset($_POST['cancel'])) {
            reloadAgent();
        }

        if (($this->formOptions['confirmationText'] === '') || (isset($_GET['quiet']))) {
            $this->showDirektFeedback = false;
        }

        // check presence of $formToken:
        $formToken = $dataRec['_formInx']??false;
        if (!$formToken || isset($_POST['cancel'])) {
            return '';
        }

        // _formInx either contains form-index (integer) or an recKey (hash string, valid only per session).
        // So, figure out, which it is. If recKey -> replace it with real recId:
        $recKey = false;
        if (!intval($formToken)) {
            // restore real recKey:
            $recKey = deObfuscateRecKeys($formToken);
        }

        $dataRec = $this->normalizeData($dataRec);

        if (is_string($dataRec)) {
            // string means spam detected:
            return "<div class='pfy-form-error'>$dataRec</div>\n";
        }

        // handle optional 'deadline' and 'maxCount':
        if ($this->handleDeadline() || $this->handleMaxCount($dataRec)) {
            return '';
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
            }
        }
        // Future: customResponseEvaluation
        //        if ($fun = ($this->formOptions['customResponseEvaluation']??false)) {
        //           if (function_exists($fun)) {
        //               $html = $fun();
        //           }
        //        } elseif ($this->formOptions['file']) {
        //            $this->file = $this->formOptions['file'];
        //            $err = $this->storeSubmittedData($dataRec);
        //            if ($err) {
        //                $err = TransVars::getVariable($err, true);
        //                $html = "<div class='pfy-form-error'>$err</div>\n";
        //            }
        //        }

        // if no error (i.e. error-message in $html) -> notify owner & create feedback:
        if (!$html) {
            if ($this->formOptions['mailTo']) {
                if (!isAdminOrLocalhost()) {
                    $this->notifyOwner($dataRec);
                } else {
                    PageFactory::$pg->setMessage('Admin: notifyOwner skipped.');
                }
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
        if ($this->showDirektFeedback) {
            $html .= "<div class='pfy-form-success-continue'>{{ pfy-form-success-continue }}</div>\n";
        }

        // write log:
        $logText = strip_tags($html);
        mylog($logText, 'form-log.txt');

        // handle indirect feedback -> via message:
        if ($html && !$this->showDirektFeedback) {
            reloadAgent(message: $logText);
        }

        return $html;
    } // handleReceivedData


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
            if ($this->formElements[$name]['args']['antiSpam']??false) {
                if ($value !== '') {
                    mylog("Spam detected: field '$name' was not empty: '$value'.", 'form-log.txt');
                    return 'pfy-anti-spam-warning';
                }
                unset($dataRec[$name]);
            }
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
        $this->db = new DataSet($this->formOptions['file'], [
            'masterFileRecKeyType' => 'index',
        ]);
        $sessKey = 'form:'.PageFactory::$slug.':file';
        PageFactory::$session->set($sessKey, $this->formOptions['file']);
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
        if (str_contains($subject, '%host')) {
            $subject = str_replace('%host', PageFactory::$hostUrl, $subject);
        }

        $body = TransVars::getVariable('pfy-form-owner-notification-body');
        $body = str_replace(['%data', '%host', '\n'], [$out, PageFactory::$hostUrl, "\n"], $body);

        $to = $formOptions['mailTo']?: TransVars::getVariable('webmaster_email');
        $this->sendMail($to, $subject, $body, 'Notification Mail to Onwer');
    } // notifyOwner


    /**
     * @return DataTable|false
     * @throws \Exception
     */
    private function openDataTable(): DataTable|false
    {
        if ($this->dataTable || (!$this->formOptions['file']??false)) {
            return $this->dataTable;
        }
        $file = resolvePath($this->formOptions['file'], relativeToPage: true);
        $fieldNames = $this->fieldNames;
        if (isset($fieldNames['_formInx'])) {
            unset($fieldNames['_formInx']);
        }
        $tableOptions = [
            'tableHeaders' => $fieldNames,
            'interactive' => $this->formOptions['interactiveTable']??false,
            'footers' => $this->formOptions['tableFooters']??false,
            'minRows' => $this->formOptions['minRows']??false,
            'sort' =>    $this->formOptions['sortData']??false,

            'masterFileRecKeyType' => 'index',
            'includeSystemElements' => $this->formOptions['includeSystemFields']??false,
            'markLocked' => true,
            ];

        if ($tableButtons = $this->tableButtons) {
            if ($tableButtons === true || $tableButtons === '1') {
                $tableButtons = 'delete,download';
            }
            if ($editData = ($this->formOptions['editData'] ?? false)) {
                $tableButtons .= ',edit';
                $tableOptions['editRecs'] = str_contains($editData, 'inp') ? 'inpage' : 'popup';
            }
            $tableOptions['tableButtons'] = $tableButtons;
        }

        $tableOptions = $this->setObfuscatePassword($tableOptions);
        
        $this->dataTable = new DataTable($file, $tableOptions);
        return $this->dataTable;
    } // openDataTable


    /**
     * @return string
     * @throws \Exception
     */
    private function renderDataTable(): string
    {
        $ds = $this->openDataTable();
        $html = $ds ? $ds->render() : '';
        if ($html) {
            $html = <<<EOT
<div class='pfy-table-data-output-wrapper'>
<h2>{{ pfy-table-data-output-header }}</h2>
$html
</div>
EOT;
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

        if ($label[strlen($label) - 1] === '*') {
            $elemOptions['required'] = true;
            $label = substr($label, 0, -1);
        }

        $label0 = str_replace(['*',':'], '', $label);

        // handle 'info' option:
        if ($info = ($elemOptions['info'] ?? false)) {
            $label .= "<span tabindex='0' class='pfy-tooltip-anker'>".INFO_ICON.
                "</span><span class='pfy-tooltip'>$info</span>";
        }

        // if label contains HTML, we need to transform it:
        if (str_contains($label, '<')) {
            $label = Html::el('span')->setHtml($label);
        }

        $type = isset($elemOptions['type']) ? ($elemOptions['type']?:'text'): 'text';
        if ($type === 'required') {
            $elemOptions['required'] = true;
            $type = 'text';
        }

        $_name = strtolower($name);
        // for convenience: check for specific names and automatically apply default type:
        if ($type === 'text') {
            if (str_starts_with($_name, 'email')) {
                $type = 'email';
            } elseif (str_starts_with($_name, 'passwor')) {
                $type = 'password';
            } elseif ($_name === 'submit') {
                $type = 'submit';
            } elseif (str_contains(',cancel,_cancel,reset,_reset,', ",$_name,")) {
                $type = 'cancel';
            }
        }

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
            $this->formElements[$name] = [
                'name' => $name,
                'label' => $label0,
                'type' => $type,
                'args' => $args,
                'isArray' => false,
            ];
        }

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

        // check choice options, convert to key:value if scalar:
        if (isset($elemOptions['options'])) {
            $o = parseArgumentStr($elemOptions['options']);
            $out = '';
            foreach ($o as $k => $v) {
                if (is_int($k)) {
                    $out .= "$v:'$v',";
                } else {
                    $out .= "$k:'$v',";
                }
            }
            $out= rtrim($out, ',');
            $elemOptions['options'] = rtrim($out, ',');
        }

        $this->name = $name;
        return array($label, $name, $type);
    } // parseMainOptions


    /**
     * @param string $html
     * @return string
     */
    private function injectFormElemClasses(string $html): string
    {
        $dom = HtmlDomParser::str_get_html($html);

        // check for errors, log them:
        $errors = $dom->findMultiOrFalse('.error');
        if ($errors) {
            foreach ($errors as $error) {
                $errorStr = strip_tags($error);
                $parent = $error->parentNode();
                $input = $parent->findOneOrFalse('input, textarea');
                $name = $input->getAttribute('name');
                $ip = $_SERVER['REMOTE_ADDR'];
                if (option('pgfactory.pagefactory-elements.options.log-ip', false)) {
                    $errorStr = "[$ip]  $name: $errorStr";
                }
                mylog($errorStr, 'form-log.txt');
            }
        }

        $revealController = false;
        $elements = $dom->findMultiOrFalse('.pfy-elem-wrapper');
        if ($elements) {
            foreach ($elements as $e) {
                $wrapperClass = $e->getAttribute('class');
                // input, textarea:
                $inputs = $e->findMultiOrFalse('input, textarea');
                if ($inputs) {
                    $cl = [];
                    foreach ($inputs as $input) {
                        $cl = array_merge($cl, explode(' ', $input->getAttribute('class')));
                    }
                    $class = implode(' ', array_combine($cl, $cl));
                    $e->setAttribute('class', trim("$wrapperClass $class"));
                }

                // select:
                $select = $e->findOneOrFalse('select');
                if ($select) {
                    $class = $select->getAttribute('class');
                    $e->setAttribute('class', trim("$wrapperClass $class"));
                }


                // aria-hidden:
                $aria = $e->findOneOrFalse('[aria-hidden]');
                if ($aria) {
                    $e->setAttribute('aria-hidden', $aria);
                }

                // reveal:
                if ($revealController) {
                    $revealController = false;
                    continue;
                } else {
                    $revealController = $e->findOneOrFalse('[data-reveal-target]');
                    if ($revealController) {
                        $revealController->setAttribute('aria-expanded', 'false');
                        $id = $revealController->getAttribute('data-reveal-target');
                        if ($id) {
                            $inx = preg_replace('/\D/', '', $id);
                            $target = $e->nextNonWhitespaceSibling();
                            if ($target) {
                                $input = $target->findOneOrFalse('input, textarea');
                                if ($input) {
                                    $class = $input->getAttribute('class');
                                    $target->setAttribute('class', trim("$wrapperClass $class"));
                                }
                                $innerHtml = (string)$target;
                                $outerHtml = <<<EOT
<div id='pfy-reveal-container-$inx' class="pfy-reveal-container" aria-hidden="true">
$innerHtml
</div>
EOT;
                                $target->outerhtml = str_replace("'", '"', $outerHtml);
                            }
                        }
                    }
                } // reveal
            } // $elements
        }

         $html = (string)$dom;
        return $html;
    } // injectFormElemClasses



    /**
     * @param string $html
     * @return string
     */
    private function handleFormTop(string $html): string
    {
        if ($str = ($this->formOptions['formTop']??false)) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-top'>$str</div>\n";
            $p = strpos($html, '<div class="pfy-elems-wrapper">');
            $html = substr($html, 0, $p) . $str . substr($html, $p);
        }
        return $html;
    } // handleFormTop


    /**
     * @param string $html
     * @return string
     */
    private function handleFormBottom(string $html): string
    {
        if ($str = ($this->formOptions['formBottom']??false)) {
            $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-bottom'>$str</div>\n";

            $p = strpos($html, '</form>');
            $html = substr($html, 0, $p) . $str . substr($html, $p);
        }
        return $html;
    } // handleFormBottom


    /**
     * @param string $html
     * @return string
     */
    private function handleFormHint(string $html): string
    {
        if (!($str = ($this->formOptions['formHint']??false))) {
            $str = '{{ pfy-form-required-info }}';
        }
        $str = $this->compileFormBanner($str);
            $str = "\n<div class='pfy-form-hint'>$str</div>\n";
            if ($p = strpos($html, 'type="submit"')) {
                $l = strlen($html);
                $p1 = strrpos($html, '<div class="pfy-elem-wrapper', $p-$l);
                if ($p1 !== false) {
                    $s1 = substr($html, 0, $p1);
                    $s2 = substr($html, $p1);
                    $html = $s1 . $str . $s2;
                }
            }
        return $html;
    } // handleFormHint


    /**
     * @param string $str
     * @return string
     * @throws \Exception
     */
    private function compileFormBanner(string $str): string
    {
        if ($str[0] !== '<') {
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
        // %deadline:
        if (str_contains($str, '%deadline') && ($deadline = ($this->formOptions['deadline']??false))) {
            $deadlineStr = Utils::timeToString($deadline);
            $str = str_replace('%deadline', $deadlineStr, $str);
        }

        // %count:
        if (str_contains($str, '%count')) {
            $count = 0;
            $this->openDB();
            if ($this->db) {
                $count = $this->db->count();
            }
            $str = str_replace('%count', $count, $str);

        }

        // %sum:
        if (str_contains($str, '%sum')) {
            $sum = 0;
            $this->openDB();
            if ($this->db) {
                if ($maxCountOn = ($this->formOptions['maxCountOn']??false)) {
                    $sum = $this->db->sum($maxCountOn);
                } else {
                    $sum = $this->db->count();
                }
            }
            $str = str_replace('%sum', $sum, $str);
        }

        // %available:
        if (str_contains($str, '%available') && ($maxCount = ($this->formOptions['maxCount']??false))) {
            $this->openDB();
            if ($maxCountOn = ($this->formOptions['maxCountOn']??false)) {
                $currCount = $this->db->sum($maxCountOn);
            } else {
                $currCount = $this->db->count();
            }
            $available = $maxCount - $currCount;
            $str = str_replace('%available', $available, $str);
        }

        // %max or %total:
        if (str_contains($str, '%max') || str_contains($str, '%total')) {
            $max = $this->formOptions['maxCount']??'{{ pfy-unlimited }}';
            $str = str_replace(['%max','%total'], $max, $str);
        }
        return $str;
    } // handleFormBannerValues


    /**
     * @return string|false
     */
    private function handleDeadline(): string|false
    {
        if ($deadlineStr = ($this->formOptions['deadline']??false)) {
            $deadline = strtotime($deadlineStr);
            // if no time is defined, extend the deadline till midnight:
            if (!str_contains($deadlineStr, 'T')) {
                $deadline += 86400;
            }
            // now check deadline:
            if ($deadline < time()) { // deadline expired:
                // deadline is overridden if visitor is logged in:
                if (!$this->isFormAdmin) {
                    if ($deadlineNotice = ($this->formOptions['deadlineNotice'] ?? false)) {
                        return $deadlineNotice;
                    } else {
                        return '{{ pfy-form-deadline-expired }}';
                    }
                } else {
                    $this->formOptions['formTop'] = "{{ pfy-form-deadline-expired-warning }}" . $this->formOptions['formTop'];
                }
            }
        }
        return false;
    } // handleDeadline


    /**
     * @param array $dataRec
     * @return string|false
     * @throws \Exception
     */
    private function handleMaxCount(array $dataRec = []): string|false
    {
        if ($dataRec && ($maxCountOn = ($this->formOptions['maxCountOn']??false))) {
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
                    if ($maxCountNotice = ($this->formOptions['maxCountNotice'] ?? false)) {
                        return $maxCountNotice;
                    } else {
                        return '{{ pfy-form-maxcount-reached }}';
                    }
                } else {
                    $this->formOptions['formTop'] = "{{ pfy-form-maxcount-reached-warning }}" . $this->formOptions['formTop'];
                }
            }
        }
        return false;
    } // handleMaxCount


    private function getAvailableAndMaxCount(): array
    {
        $available = PHP_INT_MAX - 10;
        $currCount = false;
        if ($maxCount = ($this->formOptions['maxCount']??false)) {
            $this->openDB();
            if ($maxCountOn = ($this->formOptions['maxCountOn'] ?? false)) {
                $currCount = $this->db->sum($maxCountOn);
            } else {
                $currCount = $this->db->count();
            }
            $available = $maxCount - $currCount;
        }
        return [$available, $maxCount, $currCount];
    } // getAvailableAndMaxCount

    
    private function setObfuscatePassword(array $tableOptions): array
    {
        $obfuscateRows = [];
        foreach ($this->formElements as $key => $rec) {
            if ($rec['type'] === 'password') {
                $obfuscateRows[] = $rec['name'];
            }
        }
        if ($obfuscateRows) {
            $tableOptions['obfuscateRows'] = $obfuscateRows;
        }
        return $tableOptions;
    } // setObfuscatePassword


    private function handleConfirmationMail(array $dataRec): mixed
    {
        if (!$this->formOptions['confirmationEmail']??false) {
            return '';
        }

        list($subject, $message) = $this->getEmailComponents();
        $to = $this->propagateDataToVariables($dataRec);
        $subject = TransVars::translate($subject);
        $message = TransVars::translate($message);
        if ($to) {
            $this->sendMail($to, $subject, $message, 'Confirmation Mail to Visitor');
            return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-sent }}</div>\n";
        }
        return "<div class='pfy-form-confirmation-email-sent'>{{ pfy-form-confirmation-email-missing }}</div>\n";
    } // sendConfirmationMail


    private function getEmailComponents(): array
    {
        $confirmationEmailTemplate = ($this->formOptions['confirmationEmailTemplate']??true);
        if ($confirmationEmailTemplate === true) {
            $subject = '{{ pfy-confirmation-response-subject }}';
            $template = '{{ pfy-confirmation-response-message }}';

        } else {
            $confirmationEmailTemplate = resolvePath($confirmationEmailTemplate, relativeToPage: true);
            if (!file_exists($confirmationEmailTemplate)) {
                throw new \Exception("Forms confirmationEmail: confirmationEmailTemplate not found");
            }
            $template = fileGetContents($confirmationEmailTemplate);
            if (preg_match("/^subject:(.*?)\n/i", $template, $m)) {
                $subject = trim($m[1]);
                $template = trim(str_replace($m[0], '', $template));
            } else {
                $subject = '{{ pfy-form-confirmation-email-subject }}';
            }
        }
        return [$subject, $template];
    } // getEmailComponents


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
            $value = $value?: '{{ pfy-confirmation-response-element-empty }}';
            TransVars::setVariable("__{$key}__", $value);
        }
        return $to;
    } // propagateDataToVariables


    private function sendMail(string $to, string $subject, string $body, string $debugInfo = ''): void
    {
        $props = [
            'to' => $to,
            'from' => $this->formOptions['mailFrom'] ?: TransVars::getVariable('webmaster_email'),
            'fromName' => $this->formOptions['mailFromName'] ?: false,
            'subject' => $subject,
            'body' => $body,
        ];

        if (PageFactory::$isLocalhost) {
            $props['body'] = "\n\n" . $props['body'];
            $text = var_r($props);
            $html = "<pre>$debugInfo:\n$text</pre>";
            PageFactory::$pg->setOverlay($html);
        } else {
            new PHPMailer($props);
        }
    } // sendMail

} // PfyForm
