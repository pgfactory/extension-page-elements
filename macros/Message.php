<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'text' => ['Text to be displayed.', false],
        'msg' => ['Synonyme for "text".', false],
        'mdCompile' => ['If true, text will be md-compiled before being displayed.', false],
    ],
    'summary' => <<<EOT
Briefly displays a notification message in the upper right corner.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => 'POPUPS'
];



class Message extends Macros
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
        $msg = $args['text'] ?: $args['msg'];

        if ($msg) {
            if (strpos($msg, '{{') !== false) {
                $msg = $this->trans->translate($msg);
            }
            if ($args['mdCompile']) {
                $msg = \Usility\PageFactory\compileMarkdown($msg);
            }

            PageFactory::$pg->requireFramework();
            PageFactory::$pg->addAssets('MESSAGES');
            $html = "\t\t<div class='pfy-msgbox'>$msg</div>\n";
            PageFactory::$pg->addBodyEndInjections($html);
        }
    return '';
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
