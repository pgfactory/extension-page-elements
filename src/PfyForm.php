<?php

namespace Usility\PageFactory;

use Nette\Forms\Form;
use Nette\Utils\Html;
use Kirby\Email\PHPMailer;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactoryElements\DataTable as DataTable;
use function Usility\PageFactory\translateToIdentifier as translateToIdentifier;
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


class PfyForm extends Form
{
    private array $options;
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
    private string $name;

    /**
     * @param $options
     */
    public function __construct($options = [])
    {
        $this->formIndex = $options['formInx'] ?? 1;
        $this->options = $options;
        if (isset($_GET['delete'])) {
            if ($_POST['pfy-reckey']??false) {
                $this->openDataTable(); // triggers delete-handler
            }
        }

        if ($permissionQuery = ($options['showData']??false)) {
            if ($permissionQuery === true) {
                $permissionQuery = 'loggedin|localhost';
            }
            $this->isFormAdmin = Permission::evaluate($permissionQuery, allowOnLocalhost: PageFactory::$debug);
        }

        parent::__construct();
        PageFactory::$pg->addAssets('POPUPS');
        PageFactory::$pg->addAssets('FORMS');
    } // __construct


    /**
     * @param array|null $options
     * @param array $auxOptions
     * @return void
     */
    public function createForm(array|null $options, array $auxOptions): void
    {
        if ($options !== null) {
            $this->options = $options;
        }

        $this->addElem(['type' => 'hidden', 'name' => '_formInx', 'value' => $this->formIndex]);

        foreach ($auxOptions as $name => $rec) {
            if (!is_array($rec)) {
                throw new \Exception("Syntax error in Forms option '$name'");
            }
            $rec['name'] = $name;
            $this->addElem($rec);
        }

        if ($options['action']??false) {
            $this->setAction($options['action']);
        } else {
            $this->setAction(PageFactory::$pageUrl);
        }
    } // createForm


    /**
     * @return string
     */
    public function renderForm(): string
    {
        $options = &$this->options;

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

        $id = $options['id']? " id='{$options['id']}'" : '';
        $formClass = $options['class'] ? " {$options['class']}" : '';

        if ($this->isFormAdmin) {
            $formClass = " screen-only$formClass";
        }

        $html = "<form$id class='pfy-form pfy-form-$this->formIndex$formClass'" . substr($html, 5);

        if ($options['showData'] && $options['file'] && $this->isFormAdmin) {
            $html .= $this->renderDataTable();
        }

        $html = $this->injectFormElemClasses($html);
        $html = $this->handleFormReveals($html);
        $html = $this->handleFormTop($html);
        $html = $this->handleFormHint($html);
        $html = $this->handleFormBottom($html);

        $html = <<<EOT

<div class="pfy-form-wrapper pfy-form-wrapper-$this->formIndex">
<noscript>{{ pfy-noscript-warning }}</noscript>

$html

</div><!-- .pfy-form-wrapper -->
EOT;
        return $html;
    } // renderForm


    /**
     * @param array $options
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function addElem(array $options): void
    {
        $this->index++;
        // determine $label, $name, $type and $subType:
        list($label, $name, $type) = $this->parseMainOptions($options);

        $subType = '';
        switch ($type) {
            case 'hidden':
                $elem = $this->addHidden($name, $options['value']??'');
                if ($id = $options['id']??false) {
                    $elem->setHtmlId($id);
                }
                break;
            case 'bypassed':
                $this->bypassedElements[$name] = $options['value']??'';
                $elem = $this->addHidden($name, '');
                if ($id = $options['id']??false) {
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
                $elem = $this->addTextareaElem($name, $label, $options);
                break;
            case 'integer':
            case 'number':
                $type = 'number';
                $elem = $this->addNumberElem($name, $label, $options);
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
                $elem = $this->addSelectElem($name, $label, $options, $type);
                break;
            case 'radio':
                $elem = $this->addRadioElem($name, $label, $options);
                break;
            case 'checkbox':
                $elem = $this->addCheckboxElem($name, $label, $options);
                break;
            case 'upload':
                $elem = $this->addUpload($name, $label);
                $elem->addRule($this::Image, 'File must be JPEG, PNG, GIF or WebP');
                if ($mb = ($options['maxMegaByte']??false)) {
                    $elem->addRule($this::MaxFileSize, "Maximum size is $mb MB", MEGABYTE * $mb);
                }
                break;
            case 'multiupload':
                $elem = $this->addMultiUpload($name, $label);
                $elem->addRule($this::Image, 'File must be JPEG, PNG, GIF or WebP');
                if ($mb = ($options['maxMegaByte']??false)) {
                  $elem->addRule($this::MaxFileSize, "Maximum size is $mb MB", MEGABYTE * $mb);
                }
                break;
            case 'cancel':
            case 'reset':
                $elem = $this->addSubmit('cancel', $label);
                break;
            case 'submit':
                $elem = $this->addSubmit($name, $label);
                break;
        }

        $class = "pfy-$type";

        // handle 'required' option:
        if (($options['required']??false) !== false) {
            $elem->setRequired($options['required']);
        }

        // handle 'disabled' option:
        if (($options['disabled']??false) !== false) {
            $elem->setDisabled();
        }

        // handle 'readonly' option:
        if (($options['readonly']??false) !== false) {
            $elem->setHtmlAttribute('readonly', '');
        }

        // handle 'class' option:
        if (($class1 = ($options['class']??''))) {
            $class .= " $class1";
        }
        $elem->setHtmlAttribute('class', trim($class));

        // handle placeholders:
        if ($placeholder = ($options['placeholder']??false)) {
            $elem->setHtmlAttribute('placeholder', $placeholder);
        }
        if ($default = ($options['default']?? ($options['value']??false))) {
            $elem->setDefaultValue($default);
        }

        // handle min/max
        if ($min = ($options['min']??false)) {
            $elem->setHtmlAttribute('min', $min);
        }
        if ($max = ($options['max']??false)) {
            list($available, $maxCount) = $this->getAvailableAndMaxCount();
            // if sign-up limitation is active, limit max input if necessary, unless privileged:
            if ($maxCount && !$this->isFormAdmin) {
                $max = min($max, $available);
            }
            $elem->setHtmlAttribute('max', $max);
        }

        // handle 'description' option:
        if ($str = ($options['description']??false)) {
            $str = Html::el('span')->setHtml($str);
            $elem->setOption('description', $str);
        }

        // handle 'antiSpam' option:
        if ($antiSpam = ($options['antiSpam']??false)) {
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
     * @param array $options
     * @return object|\Nette\Forms\Controls\TextArea
     * @throws \Exception
     */
    private function addTextareaElem(string $name, string $label, array $options): object
    {
        if ($revealLabel = ($options['reveal']??false)) {
            $this->revealInx++;
            PageFactory::$pg->addAssets('REVEAL');
            $elem1 = $this->addCheckbox("{$name}Controller$this->revealInx", $revealLabel);
            $elem1->setHtmlAttribute('class', 'pfy-reveal-controller');
            $elem1->setHtmlAttribute('data-reveal', $this->revealInx);
            $elem1->setHtmlAttribute('data-reveal-target', "#pfy-reveal-container-$this->revealInx");
            $elem1->setHtmlAttribute('data-icon-closed', '+');
            $elem1->setHtmlAttribute('data-icon-open', '∣');
            $label = $options['label']??'';
        }
        $elem = $this->addTextarea($name, $label);
        if ($revealLabel) {
            $elem->setHtmlAttribute('data-revealed-by', $this->revealInx);
        }
        return $elem;
    } // addTextareaElem


    /**
     * @param string $name
     * @param string $label
     * @param array $options
     * @return object|\Nette\Forms\Controls\TextInput
     */
    private function addNumberElem(string $name, string|object $label, array $options): object
    {
        $elem = $this->addInteger($name, $label);
        return $elem;
    } // addNumberElem


    /**
     * @param string $name
     * @param string $label
     * @param array $options
     * @param string $type
     * @return object|\Nette\Forms\Controls\MultiSelectBox|\Nette\Forms\Controls\SelectBox
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addSelectElem(string $name, string $label, array $options, string $type): object
    {
        $selectionElems = parseArgumentStr($options['options']);
        if ($type === 'multiselect') {
            $elem = $this->addMultiSelect($name, $label, $selectionElems);
            $this->formElements[$name]['isArray'] = true;
            $this->formElements[$name]['subKeys'] = array_keys($selectionElems);
        } else {
            $elem = $this->addSelect($name, $label, $selectionElems);
        }
        if ($options['preset']??false) {
            $elem->setDefaultValue($options['preset']);
        }
        if ($options['prompt']??false) {
            $elem->setPrompt($options['prompt']);
        }
        $this->choiceOptions[$name] = $selectionElems;

        if ($options['splitOutput']??false) {
            $this->addFieldNames($name, $selectionElems);
        }
        return $elem;
    } // addSelectElem


    /**
     * @param string $name
     * @param string $label
     * @param array $options
     * @return object|\Nette\Forms\Controls\RadioList
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addRadioElem(string $name, string $label, array &$options): object
    {
        $radioElems = parseArgumentStr($options['options']);
        $elem = $this->addRadioList($name, $label, $radioElems);
        if ($options['preset']??false) {
            $elem->setDefaultValue($options['preset']);
        }
        $this->formElements[$name]['isArray'] = true;
        $this->formElements[$name]['subKeys'] = array_keys($radioElems);
        // handle option 'horizontal':
        $options['class'] .= (($layout = ($options['layout']??false)) && ($layout[0] !== 'h')) ? '' : ' pfy-horizontal';

        if ($options['splitOutput']??false) {
            $this->addFieldNames($name, $radioElems);
        }
        return $elem;
    } // addRadioElem


    /**
     * @param string $name
     * @param string $label
     * @param array $options
     * @return object|\Nette\Forms\Controls\Checkbox|\Nette\Forms\Controls\CheckboxList
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function addCheckboxElem(string $name, string $label, array &$options): object
    {
        if ($options['options']??false) {
            $checkboxes = parseArgumentStr($options['options']);
            $elem = $this->addCheckboxList($name, $label, $checkboxes);
            if ($options['preset']??false) {
                $elem->setDefaultValue($options['preset']);
            }
            $this->choiceOptions[$name] = $checkboxes;
            if ($options['splitOutput']??false) {
                $this->addFieldNames($name, $checkboxes);
            }
            $this->formElements[$name]['isArray'] = true;
            $this->formElements[$name]['subKeys'] = array_keys($checkboxes);

            // handle option 'horizontal':
            $options['class'] .= (($layout = ($options['layout']??false)) && ($layout[0] !== 'h')) ? '' : ' pfy-horizontal';

        } else {
            $options['class'] .= ' pfy-single-checkbox';
            $elem = $this->addCheckbox($name, $label);
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

        if (($dataRec['_formInx']??false) !== (string)$formInx) {
            return '';
        }

        $dataRec = $this->normalizeData($dataRec);

        if (is_string($dataRec)) {
            // string means spam detected:
            return "<div class='pfy-form-error'>$dataRec</div>\n";
        }

        if ($maxCountOn = ($this->options['maxCountOn']??false)) {
            $pending = $dataRec[$maxCountOn]??1;
        } else {
            $pending = 1;
        }

        if ($this->handleDeadline() || $this->handleMaxCount($pending)) {
            return '';
        }

        $this->handleUploads($dataRec);

        if ($fun = ($this->options['customResponseEvaluation'])) {
           if (function_exists($fun)) {
               $html = $fun();
           }
        } elseif ($this->options['file']) {
            $this->file = $this->options['file'];
            $err = $this->storeSubmittedData($dataRec);
            if ($err) {
                $err = TransVars::getVariable($err, true);
                $html = "<div class='pfy-form-error'>$err</div>\n";
            }
        }

        if (!$html) {
            if ($this->options['mailTo']) {
                $this->notifyOwner($dataRec);
            }

            if ($this->options['confirmationText']) {
                $html = $this->options['confirmationText'];
            } else {
                $html = "<div class='pfy-form-success'>{{ pfy-form-submit-success }}</div>\n";
            }
        }
        $html .= $this->handleConfirmationMail($dataRec);
        $html .= "<div class='pfy-form-success-continue'>{{ pfy-form-success-continue }}</div>\n";

        $logText = strip_tags($html);
        mylog($logText, 'form-log.txt');
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
            if ($name === '_formInx') {
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

            } elseif (str_contains($name, 'CommentController')) {
                $commentController = $value;
                unset($dataRec[$name]);

            } elseif (isset($commentController)) {
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
        if (!$this->options['file']) {
            return false;
        }
        $this->db = new DataSet($this->options['file'], [
            'masterFileRecKeyType' => 'index',
        ]);
        return $this->db;
    } // openDB


    /**
     * @param array $newRec
     * @param string $file
     * @return false|string
     * @throws \Exception
     */
    private function storeSubmittedData(array $newRec): false|string
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
        if (!$this->db) {
            $this->openDB();
        }

        if ($this->db->recExists($newRec)) {
            return 'pfy-form-warning-record-already-exists';
        }

        $res = $this->db->addRec($newRec);
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
        $options = $this->options;
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

        $to = $options['mailTo']?: TransVars::getVariable('webmaster_email');
        $this->sendMail($to, $subject, $body, 'Notification Mail to Onwer');
    } // notifyOwner


    /**
     * @return DataTable|false
     * @throws \Exception
     */
    private function openDataTable(): DataTable|false
    {
        if ($this->dataTable || (!$this->options['file']??false)) {
            return $this->dataTable;
        }
        $file = resolvePath($this->options['file'], relativeToPage: true);
        $fieldNames = $this->fieldNames;
        if (isset($fieldNames['_formInx'])) {
            unset($fieldNames['_formInx']);
        }
        $tableOptions = [
            'tableHeaders' => $fieldNames,
            'tableButtons' => true,
            'footers' => $this->options['tableFooters']??false,
            'minRows' => $this->options['minRows']??false,
            'sort' =>    $this->options['sortData']??false,

            'masterFileRecKeyType' => 'index',
            'includeSystemElements' => $this->options['includeSystemFields']??false,
            ];

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

        return $html;
    } // renderDataTable


    /**
     * @param array $options
     * @return array
     * @throws \Exception
     */
    private function parseMainOptions(array &$options): array
    {
        $args = [];
        $label = $options['label'] ?? false;
        $name = $options['name'] ?? false;

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
            $options['required'] = true;
            $label = substr($label, 0, -1);
        }

        $label0 = str_replace(['*',':'], '', $label);

        // handle 'info' option:
        if ($info = ($options['info'] ?? false)) {
            $label .= "<span tabindex='0' class='pfy-tooltip-anker'>".INFO_ICON.
                "</span><span class='pfy-tooltip'>$info</span>";
        }

        // if label contains HTML, we need to transform it:
        if (str_contains($label, '<')) {
            $label = Html::el('span')->setHtml($label);
        }

        $type = isset($options['type']) ? ($options['type']?:'text'): 'text';
        if ($type === 'required') {
            $options['required'] = true;
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

        if (!isset($options['class'])) {
            $options['class'] = '';
        }

        if ($path = ($options['path']??false)) {
            $args['path'] = $path;
        }

        // handle 'antiSpam' option:
        if (($options['antiSpam']??false) !== false) {
            $options['class'] .= ' pfy-obfuscate';
            $args['antiSpam'] = $options['antiSpam'];
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

        if (array_key_exists('required', $options)) {
            $options['required'] = ($options['required'] !== false);
        } else {
            $options['required'] = false;
        }
        if (array_key_exists('disabled', $options)) {
            $options['disabled'] = ($options['disabled'] !== false);
        } else {
            $options['disabled'] = false;
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
        //ToDo: propagate 'aria-hidden' to wrapper
        $l1 = strlen('<div class="pfy-elem-wrapper');
        $p = 0;
        while (($p = strpos($html, '<input type="', $p)) !== false) {
            $s = substr($html, $p);
            if (!preg_match('/class="(.+?)"/', $s, $m)) {
                $p += 20;
                continue;
            }
            $class = $m[1];
            $l = strlen($html);
            // find backwards the .pfy-elem-wrapper of the reveal-controller:
            $p1 = strrpos($html, '<div class="pfy-elem-wrapper', $p-$l) + $l1;
            $s1 = substr($html, 0, $p1) ;
            $s2 = substr($html, $p1);
            $html = "$s1 $class$s2";
            $p += strlen($class) + 10;
        }

        $p = 0;
        while (($p = strpos($html, '<textarea', $p)) !== false) {
            $l = strlen($html);
            // find backwards the .pfy-elem-wrapper of the reveal-controller:
            $p1 = strrpos($html, '<div class="pfy-elem-wrapper', $p-$l) + $l1;
            $s1 = substr($html, 0, $p1) ;
            $s2 = substr($html, $p1);
            $html = "$s1 pfy-textarea$s2";
            $p += 20;
        }
        return $html;
    } // injectFormElemClasses


    /**
     * @param string $html
     * @return string
     */
    private function handleFormReveals(string $html): string
    {
        $i = 1;
        // loop over instances of elements to reveal:
        while (preg_match('/data-reveal="(.*?)"/', $html, $m)) {
            $controllerInx = $m[1];
            // p now points into <textarea>:
            $p = strpos($html, $m[0]);
            // remove the original attrib, it's obsolete now:
            $html = str_replace(" {$m[0]}", '', $html);

            $l = strlen($html);
            // find backwards the .pfy-elem-wrapper of the reveal-controller:
            $p1 = strrpos($html, '<div class="pfy-elem-wrapper', $p-$l);
            $p2 = strpos($html, '</div>', $p1);
            $s1 = substr($html,0, $p1);
            $s2 = substr($html, $p1, $p2 - $p1 + 6);
            $s2 = str_replace('class="pfy-elem-wrapper"', "class=\"pfy-elem-wrapper pfy-reveal-controller-wrapper-$controllerInx pfy-reveal-controller-wrapper\"", $s2);
            $s3 = substr($html, $p2+6);
            $html = "$s1$s2$s3";
            $l = strlen($html);

            // find corresponding <textarea> contains attrib 'data-revealed-by':
            $p = strpos($html, "data-revealed-by=\"$controllerInx\"", $p2 + strlen(" pfy-reveal-controller-wrapper-$controllerInx pfy-reveal-controller-wrapper"));
            // find backwards the enclosing div.pfy-elem-wrapper:
            $p1 = strrpos($html, '<div class="pfy-elem-wrapper', $p-$l);
            $p2 = strpos($html, '</div>', $p);
            $s1 = substr($html,0, $p1);
            $s2 = substr($html, $p1, $p2 - $p1 + 6);
            $s3 = substr($html, $p2+6);
            $html = <<<EOT

$s1

<div id='pfy-reveal-container-$i' class="pfy-reveal-container">
$s2
</div><!-- pfy-reveal-container-$i -->

$s3

EOT;
            $i++;
        }
        return $html;
    } // handleFormReveals


    /**
     * @param string $html
     * @return string
     */
    private function handleFormTop(string $html): string
    {
        if ($str = ($this->options['formTop']??false)) {
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
        if ($str = ($this->options['formBottom']??false)) {
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
        if ($str = ($this->options['formHint']??false)) {
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
        if (str_contains($str, '%deadline') && ($deadline = ($this->options['deadline']??false))) {
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
                if ($maxCountOn = ($this->options['maxCountOn']??false)) {
                    $sum = $this->db->sum($maxCountOn);
                } else {
                    $sum = $this->db->count();
                }
            }
            $str = str_replace('%sum', $sum, $str);
        }

        // %available:
        if (str_contains($str, '%available') && ($maxCount = ($this->options['maxCount']??false))) {
            $this->openDB();
            if ($maxCountOn = ($this->options['maxCountOn']??false)) {
                $currCount = $this->db->sum($maxCountOn);
            } else {
                $currCount = $this->db->count();
            }
            $available = $maxCount - $currCount;
            $str = str_replace('%available', $available, $str);
        }

        // %max or %total:
        if (str_contains($str, '%max') || str_contains($str, '%total')) {
            $max = $this->options['maxCount']??'{{ pfy-unlimited }}';
            $str = str_replace(['%max','%total'], $max, $str);
        }
        return $str;
    } // handleFormBannerValues


    /**
     * @return string|false
     */
    private function handleDeadline(): string|false
    {
        if ($deadlineStr = ($this->options['deadline']??false)) {
            $deadline = strtotime($deadlineStr);
            // if no time is defined, extend the deadline till midnight:
            if (!str_contains($deadlineStr, 'T')) {
                $deadline += 86400;
            }
            // now check deadline:
            if ($deadline < time()) { // deadline expired:
                // deadline is overridden if visitor is logged in:
                if (!$this->isFormAdmin) {
                    if ($deadlineNotice = ($this->options['deadlineNotice'] ?? false)) {
                        return $deadlineNotice;
                    } else {
                        return '{{ pfy-form-deadline-expired }}';
                    }
                } else {
                    $this->options['formTop'] = "{{ pfy-form-deadline-expired-warning }}" . $this->options['formTop'];
                }
            }
        }
        return false;
    } // handleDeadline


    /**
     * @param $pending
     * @return string|false
     * @throws \Exception
     */
    private function handleMaxCount(int $pending = 0): string|false
    {
        list($available, $maxCount, $currCount) = $this->getAvailableAndMaxCount();
        if ($maxCount) {
            if ($pending) {
                $currCount += ($pending - 1);
            }
            if ($currCount >= $maxCount) {
                if (!$this->isFormAdmin) {
                    if ($maxCountNotice = ($this->options['maxCountNotice'] ?? false)) {
                        return $maxCountNotice;
                    } else {
                        return '{{ pfy-form-maxcount-reached }}';
                    }
                } else {
                    $this->options['formTop'] = "{{ pfy-form-maxcount-reached-warning }}" . $this->options['formTop'];
                }
            }
        }
        return false;
    } // handleMaxCount


    private function getAvailableAndMaxCount(): array
    {
        $available = PHP_INT_MAX - 10;
        $currCount = false;
        if ($maxCount = ($this->options['maxCount']??false)) {
            $this->openDB();
            $maxCountOn = ($this->options['maxCountOn'] ?? false);
            if ($maxCountOn === $this->name) {
                if ($maxCountOn = ($this->options['maxCountOn'] ?? false)) {
                    $currCount = $this->db->sum($maxCountOn);
                } else {
                    $currCount = $this->db->count();
                }
                $available = $maxCount - $currCount;
            }
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
        if (!$this->options['confirmationEmail']??false) {
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
        $confirmationEmailTemplate = ($this->options['confirmationEmailTemplate']??true);
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
        $emailFieldName = $this->options['confirmationEmail'];
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
            'from' => $this->options['mailFrom'] ?: TransVars::getVariable('webmaster_email'),
            'fromName' => $this->options['mailFromName'] ?: false,
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
