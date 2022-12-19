<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
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

        'closeCallback'		=> ['[function or string] A function to be executed upon closing the popup, no '.
            'matter which way closing was initiated (including click on background).', false],

        'callbackArg'		=> ['[any variable] Value or object that will be available inside callback functions.',
            false],

        'id'		=> ['[string] ID to be applied to the popup element. (Default: pfy-popup-N)', false],

        'wrapperClass'		=> ['[string] Class(es) applied to wrapper around Popup element.', false],

        'popupClass'		=> ['[string] Class(es) applied to popup element.', false],

        'containerClass'		=> ['[string] Class(es) applied to container element.', false],

        'buttonsClass'		=> ['[string] Will be applied to buttons defined by "buttons" argument.', false],
    ],
    'summary' => <<<EOT
Displays some content in a large popup hovering over the page.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => 'POPUPS'
];



class Overlay extends Macros
{
    public static $inx = 1;


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
            unset($args['triggerButton']);
            $args['trigger'] = "#$buttonId";
            $args['closeButton'] = true;
        }

        $args['popupClass'] = 'pfy-overlay';
        $args['anker'] = 'body';
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
