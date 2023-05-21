<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function reveal($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'label' => ['Text that prepresents the controlling element.', false],
            'target' => ['[css selector] CSS selector of the DIV that shall be revealed, e.g. "#box"', false],
            'class' => ['(optional) A class that will be applied to the controlling element.', false],
            'symbol' => ['[\'plus-minus\'|character(s)] If defined, the symbol on the left hand side of the label '.
                'will be modified. (currently just "triangle" implemented.)', false],
            'symbol-rotation' => ['[deg2|deg1,deg2] Defines rotation angle of icon for end state (resp. start and end state).', false],
            'frame' => ['(true, class) If true, class "pfy-reveal-frame" is added, painting a frame around the element by default.', false],
            'shadow' => ['If true, adds a shadow to make it look like opening a drawer.', null],
        ],
        'summary' => <<<EOT
# reveal()

Displays a clickable label. When clicked, opens and closes the target element specified in argument ``target``.

### Styling Variables:
#### Controller:
- ``\--pfy-reveal-bg``
- ``\--pfy-reveal-border``
- ``\--pfy-reveal-controller-height``

#### Target-Container:
- ``\--pfy-reveal-container-border``
- ``\--pfy-reveal-container-padding``

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $sourceCode, $inx, $funcName) = $str;
        $out = $sourceCode;
    }

    // assemble output:
    PageFactory::$pg->requireFramework();
    PageFactory::$pg->addAssets('REVEAL');

    $id = "pfy-reveal-controller-$inx";
    $class = $args['class'];

    if ($args['frame']) {
        $class = $class? "$class pfy-reveal-frame": 'pfy-reveal-frame';
        if ($args['shadow'] !== false) {
            $args['shadow'] = true;
        }
    }

    $deg1 = $deg2 = false;
    $icon = $args['symbol'];
    // standard symbol: triangle
    $iconClosed = '▷';
    $iconOpen = '▷';

    // 'plus-minus' is second standard symbol:
    if (str_starts_with($args['symbol'], 'plu')) {
        $iconClosed = '+';
        $iconOpen = '–';
        $deg1 = 0;
        $deg2 = 180;

    } elseif ($icon) {
        if (str_contains($icon, ',')) {
            list($iconClosed, $iconOpen) = explodeTrim(',', $icon);
        } else {
            $iconClosed = $icon;
            $iconOpen = $icon;
        }
    }

    if ($args['symbol-rotation']) {
        $deg1 = $deg2 = $args['symbol-rotation'];
        if (str_contains($deg1, ',')) {
            list($deg1, $deg2) = explodeTrim(',', $deg1);
        }
    }

    if ($deg1 !== false) {
        $css = <<<EOT
#pfy-reveal-controller-$inx::before {
  transform: rotate( {$deg1}deg );
}
#pfy-reveal-controller-$inx:checked::before {
  transform: rotate( {$deg2}deg );
}
EOT;
        PageFactory::$pg->addCss($css);
    }
    if ($args['shadow']) {
        $class .= ' pfy-target-shadow';
    }
    $class = $class? " $class": '';

    $out .= "\n\t<input id='$id' class='pfy-reveal-controller' type='checkbox' ".
        "data-reveal-target='{$args['target']}' data-icon-closed='$iconClosed' data-icon-open='$iconOpen'>".
        "\n\t\t<label for='$id'>{$args['label']}</label>\n";

    $out = "\t<div class='pfy-reveal-controller-wrapper-$inx pfy-reveal-controller-wrapper$class'>$out\t</div>\n";
    $out = shieldStr($out); // shield from further processing if necessary

    return $out;
}

