<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'parameters' => [
        'label' => ['Text that prepresents the controlling element.', false],
        'target' => ['[css selector] CSS selector of the DIV that shall be revealed, e.g. "#box"', false],
        'class' => ['(optional) A class that will be applied to the controlling element.', false],
        'symbol' => ['[\'triangle\'|character(s)] If defined, the symbol on the left hand side of the label will be modified. (currently just "triangle" implemented.)', false],
        'symbol-rotation' => ['[deg].', '0,90'],
        'frame' => ['(true, class) If true, class "pfy-reveal-frame" is added, painting a frame around the element by default.', false],
    ],
    'summary' => <<<EOT
Displays a clickable label. When clicked, opens and closes the target element specified in argument ``target``.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => ['REVEAL'],
];



class Reveal extends Macros
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

        $id = "pfy-reveal-controller-$inx";
        $class = $args['class'];

        if ($args['frame']) {
            $class = $class? "$class pfy-reveal-frame": 'pfy-reveal-frame';
        }

        $icon = $args['symbol'];
        $iconClosed = '+';
        $iconOpen = '–';
        if (stripos($args['symbol'], 'tri') !== false) {
            $iconClosed = '▷';
            $iconOpen = '▷';
        } elseif ($icon) {
            if (str_contains($icon, ',')) {
                list($iconClosed, $iconOpen) = explodeTrim(',', $icon);
            } else {
                $iconClosed = $icon;
                $iconOpen = $icon;
            }
        }
        if ($icon) {
            $deg1 = $deg2 = $args['symbol-rotation'];
            if (str_contains($deg1, ',')) {
                list($deg1, $deg2) = explodeTrim(',', $deg1);
            }
        } else {
            $deg1 = 0;
            $deg2 = 180;
        }

        if ($inx === 1) {
            $css = <<<EOT
#pfy input.pfy-reveal-controller::before {
  transform: rotate( {$deg1}deg );
}
#pfy input.pfy-reveal-controller:checked::before {
  transform: rotate( {$deg2}deg );
}
EOT;
            PageFactory::$pg->addCss($css);
        }

        $class = $class? " $class": '';
        $out = '';
        $out .= "\n\t<input id='$id' class='pfy-reveal-controller' type='checkbox' data-reveal-target='{$args['target']}' data-icon-closed='$iconClosed' data-icon-open='$iconOpen'>".
            "\n\t\t<label for='$id'>{$args['label']}</label>\n";

        $out = "\t<div class='pfy-reveal-controller-wrapper$class'>$out\t</div>\n";

        return $out;
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
