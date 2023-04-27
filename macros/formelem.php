<?php
namespace Usility\PageFactory;

use Nette\Forms\Form;
use function Usility\PageFactory\translateToIdentifier as translateToIdentifier;

require_once __DIR__ . '/../vendor/autoload.php';


//if (!defined('FORMS_SUPPORTED_TYPES')) {
//    define('FORMS_SUPPORTED_TYPES', ',head,tail,,text,textarea,number,choice,checkbox,radio,button,upload,hidden,button,required,');
//}

/*
 * PageFactory Macro (and Twig Function)
 */

//$pfyForms = [];
//$pfyFormsCurr = -1;

function formelem($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'label' => ['(string) Label for field', false],
            'type' => ['[head,tail or field-type]', false],
            'name' => ['(string) Name for data element', false],
            'required' => ['(bool) Required', null],
        ],
        'summary' => <<<EOT

# $funcName()

Supported field types:

- text
- textarea
- ...

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $html = $sourceCode;
    }

    // assemble output:
    $label = $options['label'];
    if (!$options['type'] && str_contains(',head,tail,', ",$label,")) {
        $options['type'] = $label;
    }
    $type = $options['type'];
    if ($type === 'head') {
        if (!isset($GLOBALS['pfyFormsCurr'])) {
            $GLOBALS['pfyFormsCurr'] = -1;
        }
        $GLOBALS['pfyFormsCurr']++;
        $formInx = $GLOBALS['pfyFormsCurr'];
        $form = new PfyForm;
        $GLOBALS['pfyForms'][$formInx] = $form;
        if ($form->isSuccess()) {
            $html .= $form->handleReceivedData();
            return $html;
//        } else {
//            $html .= $form->renderElem($options);
//            $form->fireRenderEvents();
//            $html .= $form->getRenderer()->render($form);
        }
    }

    $formInx = $GLOBALS['pfyFormsCurr'];
    if (!isset($GLOBALS['pfyForms'][$formInx])) {
        throw new \Exception("Forms: you need to call formelem(head) first");
    } else {
        $form = $GLOBALS['pfyForms'][$formInx];
    }

//    $form->render('begin');
//    $html = $form->getRenderer()->render($form, 'begin');
//    $renderer = $form->getRenderer();
//    $renderer->wrappers['controls']['container'] = 'div class="pfy-form-elems"';
//    $renderer->wrappers['pair']['container'] = 'div class="pfy-form-elem"';
//    $renderer->wrappers['label']['container'] = 'span class="pfy-label"';
//    $renderer->wrappers['control']['container'] = 'span class="pfy-input"';

    $html .= $form->renderElem($options);

//    if ($type === 'tail') {
//
//
////        $c = $form->getControls();
////        $x = $form['firstname'];
////        foreach ($form as $key => $elem) {
////            echo $key;
////        }
//
////        $html .= (string)$form;
//
//
//        //$str = markdown($str); // markdown-compile
//        return shieldStr($html, 'inline'); // shield from further processing if necessary
//    } else {
//        $form->addElem($options);
//    }

    //PageFactory::$pg->requireFramework();
    //PageFactory::$pg->addAssets('XY');

    return $html;
}



/*
class PfyForm extends Form
{

    public function __construct()
    {
        parent::__construct();
    }


    public function renderElem(array $options): string
    {
        $label = $options['label'];
        $name = $options['name'] ?: translateToIdentifier($label, toCamelCase: true);
        $type = $options['type']?:'text';
        if ($type === 'required') {
            $options['required'] = true;
            $type = 'text';
        }
        if (!str_contains(FORMS_SUPPORTED_TYPES, ",$type,")) {
            throw new \Exception("Forms: requested type not supported: '$type'");
        }

        $this->fireRenderEvents();
        if ($type === 'head') {
            $html = $this->getRenderer()->render($this, 'begin');
            return $html;

        } elseif ($type === 'tail') {
            $html = $this->getRenderer()->render($this, 'end');
            return $html;
        }

        switch ($type) {
            case 'text':
                $html = $this->renderTextInput($options, $name, $label);
                break;
            case 'textarea':
                $html = $this->renderTextarea($options, $name, $label);
                break;
        }


        return $html;
    } // renderElem


    private function renderTextInput($options, $name, $label)
    {
        $elem = parent::addText($name, $label);
        if ($elem && ($options['required'] !== false)) {
            $elem->setRequired();
        }

        return $this->renderHtml($elem);
    } // renderTextInput


    private function renderTextarea($options, $name, $label)
    {
        $elem = parent::addTextArea($name, $label);
        if ($elem && ($options['required'] !== false)) {
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



    public static function handleReceivedData(): string
    {
        $data = parent::getValues();
        $html = var_export($data, true);
        $html = "<pre>$html</pre>";
        return $html;
    } // handleReceivedData



} // PfyForm
*/