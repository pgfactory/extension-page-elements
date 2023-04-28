<?php

namespace Usility\PageFactory;

use Nette\Forms\Form;
use Kirby\Email\PHPMailer;
use Usility\MarkdownPlus\Permission;
use Usility\PageFactoryElements\DataTable as DataTable;
use function Usility\PageFactory\translateToIdentifier as translateToIdentifier;
use function Usility\PageFactory\var_r as var_r;


define('ARRAY_SUMMARY_NAME', '_');
const FORMS_SUPPORTED_TYPES =
    ',text,password,email,textarea,hidden,'.
    'url,date,datetime-local,time,datetime,month,number,range,tel,file,'.
    'radio,checkbox,dropdown,select,multiselect,'.
    'button,reset,submit,cancel,';
    // future: toggle,hash,fieldset,fieldset-end,reveal,bypassed,literal,readonly,



mb_internal_encoding("utf-8");

class PfyForm extends Form
{
    private array $options;
    private array $fieldNames = [];
    private array $formElements = [];
    private array $choiceOptions = [];
    private $db = false;
    private $dataTable = false;

    /**
     * @param $options
     */
    public function __construct($options = [])
    {
        $this->options = $options;
        if (isset($_GET['delete'])) {
            if ($_POST['pfy-reckey']??false) {
                $this->openDataTable(); // triggers delete-handler
            }
        }
        parent::__construct();
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

        foreach ($auxOptions as $name => $rec) {
            $rec['name'] = $name;
            $this->addElem($rec);
        }

//ToDo: why not working?
//        if ($options['method'] && (strtolower($options['method']) === 'get')) {
//            $this->setMethod('GET');
//        }
        if ($options['action']??false) {
            $this->setAction($options['action']);
        }
    } // createForm


    /**
     * @return string
     */
    public function renderForm(): string
    {
        $options = &$this->options;
        $this->fireRenderEvents();
        $renderer = parent::getRenderer();

        $renderer->wrappers['controls']['container'] = 'div class="pfy-elems-wrapper"';
        $renderer->wrappers['pair']['container'] = 'div class="pfy-elem-wrapper"';
        $renderer->wrappers['label']['container'] = 'span class="pfy-label-wrapper"';
        $renderer->wrappers['control']['container'] = 'span class="pfy-input-wrapper"';

        $html = $renderer->render($this);

        $id = $options['id']? " id='{$options['id']}'" : '';
        $formClass = $options['class'] ? " {$options['class']}" : '';

        $html = "<form$id class='pfy-form$formClass'" . substr($html, 5);

        if ($options['showData'] && $options['file']) {
            $permissionQuery = $options['showData'];
            if ($permissionQuery === true) {
                $permissionQuery = 'loggedin|localhost';
            }
            $admitted = Permission::evaluate($permissionQuery);
            if ($admitted) {
                $html .= $this->renderDataTable();
            }
        }

        return $html;
    } // renderForm


    /**
     * @param array $options
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public function addElem(array $options): void
    {
        // determine $label, $name, $type and $subType:
        list($label, $name, $type) = $this->determineMainOptions($options);

        $subType = '';
        switch ($type) {
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
                $elem = $this->addTextarea($name, $label);
                break;
            case 'integer':
            case 'number':
                $elem = $this->addInteger($name, $label);
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
                $selectionElems = parseArgumentStr($options['options']);
                if ($type === 'multiselect') {
                    $elem = $this->addMultiSelect($name, $label, $selectionElems);
                    $this->fieldNames = array_merge($this->fieldNames, array_keys($selectionElems));
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
                $this->fieldNames = array_merge($this->fieldNames, array_keys($selectionElems));

            break;
            case 'radio':
                $radioElems = parseArgumentStr($options['options']);
                $elem = $this->addRadioList($name, $label, $radioElems);
                if ($options['preset']??false) {
                    $elem->setDefaultValue($options['preset']);
                }
                $this->formElements[$name]['isArray'] = true;
                $this->formElements[$name]['subKeys'] = array_keys($radioElems);
                break;
            case 'checkbox':
                if ($options['options']??false) {
                    $checkboxes = parseArgumentStr($options['options']);
                    $elem = $this->addCheckboxList($name, $label, $checkboxes);
                    if ($options['preset']??false) {
                        $elem->setDefaultValue($options['preset']);
                    }
                    $this->choiceOptions[$name] = $checkboxes;
                    $this->fieldNames = array_merge($this->fieldNames, array_keys($checkboxes));
                    $this->formElements[$name]['isArray'] = true;
                    $this->formElements[$name]['subKeys'] = array_keys($checkboxes);
                } else {
                    $elem = $this->addCheckbox($name, $label);
                }
                $elem->setHtmlAttribute('class', 'pfy-form-checkbox');
                break;
            case 'cancel':
                $elem = $this->addButton($name, $label);
                break;
            case 'reset':
                $elem = $this->addButton($name, $label);
                break;
            case 'submit':
                $elem = $this->addSubmit($name, $label);
                break;
        }

        if (($options['required']??false) !== false) {
            $elem->setRequired($options['required']);
        }
    } // addElem


/* Future: build form, immediately returning partial html:
    public function renderElem(array $options): string
    {
        list($label, $name, $type) = $this->determineMainOptions($options);
        $this->options = $options;

        $this->fireRenderEvents();
        if ($type === 'head') {
            $html = $this->getRenderer()->render($this, 'begin');
            return $html;

        } elseif ($type === 'tail') {
            $html = $this->getRenderer()->render($this, 'end');
            return $html;
        }

        $html = '';
        switch ($type) {
//            case 'text':
////                $this->addText($name, $label);
//                $html = $this->renderTextInput($options, $name, $label);
//                break;
//            case 'textarea':
//                $html = $this->renderTextarea($options, $name, $label);
//                break;
//            case 'cancel':
////                $this->addButton($name, $label);
//                break;
//            case 'reset':
////                $this->addButton($name, $label);
//                break;
//            case 'submit':
////                $this->addSubmit($name, $label);
//                $html = $this->addSubmit($options, $name, $label);
//                break;
        }


        return $html;
    } // renderElem


    private function renderTextInput($name, $label)
    {
        $elem = parent::addText($name, $label);
        if ($elem && ($this->options['required'] !== false)) {
            $elem->setRequired();
        }

        return $this->renderHtml($elem);
    } // renderTextInput


    private function renderTextarea($name, $label)
    {
        $elem = parent::addTextArea($name, $label);
        if ($elem && ($this->options['required'] !== false)) {
            $elem->setRequired();
        }

        return $this->renderHtml($elem);
    } // renderTextarea


    private function renderHtml($elem)
    {
        $label = (string)$elem->getLabel();
        $input = (string)$elem->getControl();

        $html = <<<EOT
<div class='pfy-form-elem'>
<span class="pfy-label">
$label
</span>
<span class="pfy-input">
$input
</span>
</div>
EOT;
        return $html;
    } // renderHtml
*/


    /**
     * @return string
     */
    public function handleReceivedData(): string
    {
        $html = '';
        $dataRec = $this->getValues(true);
        $dataRec = $this->normalizeData($dataRec);

        if ($this->options['file']) {
            $err = $this->storeSubmittedData($dataRec, $this->options['file']);
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
                $html = '{{ form-submit-success }}';
            }
        }
        $html .= TransVars::getVariable('form-success-continue', true);
        return $html;
    } // handleReceivedData


    /**
     * @param array $dataRec
     * @return array
     */
    private function normalizeData(array $dataRec): array
    {
        foreach ($dataRec as $name => $value) {
            if (is_array($value) && isset($this->choiceOptions[$name])) {
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
            }
        }
        return $dataRec;
    } // normalizeData


    /**
     * @param string $file
     * @return object|false
     * @throws \Exception
     */
    private function openDB(string $file): object|false
    {
        if (!$file) {
            return false;
        }
        $this->db = new DataSet($file);
        return $this->db;
    } // openDB


    /**
     * @param array $newRec
     * @param string $file
     * @return false|string
     * @throws \Exception
     */
    private function storeSubmittedData(array $newRec, string $file): false|string
    {
        if (!$this->db) {
            $this->openDB($file);
        }

        if ($this->db->recExists($newRec)) {
            return 'form-warning-record-already-exists';
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
            $key = str_pad("$key: ", $labelLen, '. ');
            if (is_array($value)) {
                $value = $value[ARRAY_SUMMARY_NAME]??'';
            }
            $out .= "$key$value\n";
        }
        $webmasterMail = TransVars::getVariable('webmaster_email');
        $subject = TransVars::getVariable('form-owner-notification-subject');
        $body = TransVars::getVariable('form-owner-notification-body');
        $body = str_replace(['%data%', '\n'], [$out, "\n"], $body);
        $props = [
            'to'        => $options['mailTo']?: $webmasterMail,
            'from'      => $options['mailFrom']?: $webmasterMail,
            'fromName'  => $options['mailFromName']?: $webmasterMail,
            'subject'   => $subject,
            'body'      => $body,
        ];


        if (PageFactory::$isLocalhost) {
            $props['body'] = "\n\n".$props['body'];
            $text = var_r($props);
            $html = "<pre>Notification Mail to Onwer:\n$text</pre>";
            PageFactory::$pg->setOverlay($html);
        } else {
            $mailer = new PHPMailer($props, true);
            $mailer->send(true);
        }
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
        $tableOptions = [
            'dataStructure' => $this->formElements,
            'tableButtons' => true,
            'tableHeaders' => true,
//            'tableHeaders' => $this->fieldNames,
            'elementSubKeys' => $this->choiceOptions,
            'footers' => $this->options['tableFooters']??false,

            'masterFileRecKeyType' => 'index',
//            'masterFileRecKeys' => 'uid',
//            'masterFileRecKeySort' => true,
//            'masterFileRecKeySortOnElement' => 'first_name',
            ];
        $this->dataTable = new DataTable($file, $tableOptions);
//$this->dataTable->flush();
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
    private function determineMainOptions(array &$options): array
    {
        $label = $options['label'] ?? false;
        $name = $options['name'] ?? false;
        if ($label && !$name) {
            $name = $label;
        } elseif (!$label && $name) {
            $label = ucwords(str_replace('_', ' ', $name)).':';
        }
        $name = str_replace('-', '_', $name);
        $name = preg_replace('/\W/', '', $name);

        if ($label[strlen($label) - 1] === '*') {
            $options['required'] = true;
            $label = substr($label, 0, -1);
        }

        $type = isset($options['type']) ? ($options['type']?:'text'): 'text';
        if ($type === 'required') {
            $options['required'] = true;
            $type = 'text';
        }

        if ($name === 'submit') {
            $type = 'submit';
        } elseif (($name === 'cancel') || ($name === '_cancel')) {
            $type = 'cancel';
        } elseif (($name === 'reset') || ($name === '_reset')) {
            $type = 'reset';
        }

        if (!str_contains(FORMS_SUPPORTED_TYPES, ",$type,")) {
            throw new \Exception("Forms: requested type not supported: '$type'");
        }

        // register found $name with global list of field-names (used for table-output):
        if (!str_contains('submit,reset,cancel,hidden',$name)) {
            $this->fieldNames[] = $name;
            $this->formElements[$name] = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'isArray' => false,
            ];
        }

        $options['required'] = $options['required']??false;

        return array($label, $name, $type);
    } // determineMainOptions


} // PfyForm
