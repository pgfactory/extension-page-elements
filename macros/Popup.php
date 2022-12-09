<?php

/*
 * 
 */

namespace Usility\PageFactory;


$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'text'		=> ['[html or string]Text to be displayed in the popup (for small messages, otherwise use '.
            'contentFrom). "content" functions as synonym for "text".', false],

        'contentFrom'		=> ['[string] Selector that identifies content which will be imported and displayed '.
            'in the popup (example: "#box").', false],

        'header'		=> ['[string] Defines the text in the popup header. If false, no header is displayed.', false],

        'triggerSource'		=> ['[true, string, false] If set, the popup opens upon activation of the trigger '.
            'source element (example: "#btn").', true],

        'triggerEvent'		=> ['[click, right-click, dblclick, blur] Specifies the type of event that shall '.
            'open the popup.', false],

        'closeButton'		=> ['[true,false] Specifies whether a close button shall be displayed in the upper '.
            'right corner (default: true).', false],

        'closeOnBgClick'		=> ['[true,false] Specifies whether clicks on the background will close the popup '.
            '(default: true).', false],

        'buttons'		=> ['[Comma-separated-list of button labels] Example: "Cancel,Ok". Predefined: "Cancel", '.
            '"Close", "Ok", "Continue", "Confirm".', false],

        'closeCallback'		=> ['[function or string] A function to be executed upon closing the popup, no '.
            'matter which way closing was initiated (including click on background).', false],

        'onOk'		=> ['[function or string] Callback function invoked when "ok" key is activated', false],
        'onConfirm'		=> ['[function or string] Callback function invoked when "Confirm" key is activated', false],
        'onContinue'		=> ['[function or string] Callback function invoked when "Continue" key is activated', false],
        'onCancel'		=> ['[function or string] Callback function invoked when "Cancel" key is activated', false],
        'onClose'		=> ['[function or string] Callback function invoked when "Close" key is activated', false],

        'callbackArg'		=> ['[any variable] Value or object that will be available inside callback functions.',
            false],

        'id'		=> ['[string] ID to be applied to the popup element. (Default: pfy-popup-N)', false],

        'wrapperClass'		=> ['[string] Class(es) applied to wrapper around Popup element.', false],

        'popupClass'		=> ['[string] Class(es) applied to popup element.', false],

        'containerClass'		=> ['[string] Class(es) applied to container element.', false],

        'buttonsClass'		=> ['[string] Will be applied to buttons defined by "buttons" argument.', false],

        'buttonClasses'		=> ['[Comma-separated-list of classes] Will be applied to corresponding '.
            'buttons defined by "buttons" argument.', false],

        'anker'		=> ['[string] If defined, popup will be placed inside elemented selected by "anker" '.
            '(e.g. ".box"). Default: "body".', false],

    ],
    'summary' => <<<EOT
Displays a popup window.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => 'POPUPS'
];



class Popup extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args, string $argStr): string
    {
        $inx = self::$inx++;

        // option 'triggerButton' -> render button to open popup:
        if (isset($args['triggerButton'])) {
            $label = $args['triggerButton'];
            $buttonId = "pfy-popup-trigger-$inx";
            $out = "\t<button id='$buttonId' class='pfy-button pfy-show-source-btn'>$label</button>\n";
            unset($args['triggerButton']);
            $args['trigger'] = "#$buttonId";
            $args['closeButton'] = true;
        }

        $jsArgs = '';
        foreach ($args as $key => $value) {
            if (is_string(($key))) {
                if ($value === true) {
                    $jsArgs .= "\t$key: true,\n";
                } elseif ($value === false) {
                    $jsArgs .= "\t$key: false,\n";
                } else {
                    $value = str_replace("'", "\\'", $value);
                    $jsArgs .= "\t$key: '$value',\n";
                }
            }
        }

        $jq = <<<EOT

var pfyPopup$inx = pfyPopup({
$jsArgs});

EOT;
        PageFactory::$pg->addJq($jq);

        return '';
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
