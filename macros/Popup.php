<?php

/*
 * 
 */

namespace Usility\PageFactory;

$extensionPath = dirname(dirname(__FILE__)).'/';

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'text'              => ['Text to be displayed in the popup (for small messages, otherwise use contentFrom). '.
                                '"content" works as synonym for "text". ', false],
        'contentFrom'       => ['Selector that identifies content which will be imported and displayed in the popup '.
                                '(example: "#box"). ', false],
        'contentRef'        => ['Selector that identifies content which will be wrapped and popped up. '.
                                '(rather for internal use - event handlers are preserved, but usage is a bit tricky). ',
                                false],
        'triggerSource'     => ['If set, the popup opens upon activation of the trigger source element (example: "#btn"). ',
                                false],
        'triggerEvent'      => ['[click, right-click, dblclick, blur] Specifies the type of event that shall open '.
                                'the popup (default: click). ', 'click'],
        'closeButton'       => ['Specifies whether a close button shall be displayed in the upper right corner '.
                                '(default: true). ', true],
        'closeOnBgClick'    => ['Specifies whether clicks on the background will close the popup (default: true). ',
                                true],
        'buttons'           => ['(Comma-separated-list of button labels) Example: "Cancel,Ok". ', false],
        'callbacks'         => ['(Comma-separated-list of function names) Example: "onCancel,onOk". ', false],
        'id'                => ['ID to be applied to the popup element. (Default: pfy-popup-N)', false],
        'buttonClass'       => ['(Comma-separated-list of classes). Will be applied to buttons defined by '.
                                '"buttons" argument.', ''],
        'wrapperClass'      => ['Class(es) applied to wrapper around Popup element. ', ''],
        'anker'             => ['(selector) If defined, popup will be placed inside elemented selected by "anker". '.
                                '(Not available for "contentRef"). Default: "body". ', ''],
    ],
    'summary' => <<<EOT
[Short description of macro.]
EOT,
    'mdCompile' => false,
    'assetsToLoad' => [
        $extensionPath.'scss/popup.scss',
        $extensionPath.'js/popup.js',
        $extensionPath.'third-party/javascript-md5/md5.min.js',
        $extensionPath.'third-party/tooltipster/js/tooltipster.bundle.min.js',
        $extensionPath.'third-party/tooltipster/css/tooltipster.bundle.min.css',
    ],
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
        $out = '';
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

pfyPopup({
$jsArgs});

EOT;
        PageFactory::$pg->addJq($jq);
//        $this->addModules('POPUPS');

        return $out;
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
