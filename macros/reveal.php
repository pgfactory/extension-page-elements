<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

return function ($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'controller' => ['[css selector] CSS selector of ', '.mdp-accordion-controller'],
            'target' => ['[css selector] CSS selector of the DIV that shall be revealed, e.g. "#box"', false],
            'label' => ['Text that prepresents the controlling element.', ''],
            'class' => ['(optional) A class that will be applied to the controlling element.', false],
            'icon' => ['[character(s)] If defined, the symbol on the left hand side of the label '.
                'will be modified. e.g. "＋,—".', false],
            'iconRotation' => ['[deg2|deg1,deg2] Defines rotation angle of icon for end state (resp. start and end state).', '0deg,90deg'],
            'frame' => ['(true, class) If true, class "pfy-reveal-frame" is added, painting a frame around the element by default.', false],
            'shadow' => ['If true, adds a shadow to make it look like opening a drawer.', null],
        ],
        'summary' => <<<EOT
# reveal()

Displays a clickable label. When clicked, opens and closes the target element specified in argument ``target``.

### Styling Variables:
#### Controller:
- ``\--pfy-reveal-bg``
- ``\--pfy-reveal-border``  (e.g. ``\--pfy-reveal-border: 1px solid gray;``)
- ``\--pfy-reveal-controller-height``

#### Target-Container:
- ``\--pfy-reveal-container-border``  (e.g. ``\--pfy-reveal-container-border: 1px solid gray;``)
- ``\--pfy-reveal-container-padding`` (e.g. ``\--pfy-reveal-container-padding: 1em;``)

## Example
Without separate controller element:

    \{{ reveal(target: "#box", **label: "Show"**) }}
    
    @@@ #box
    This is the target element.
    @@@

or with a form element to control the target:

    \{{ reveal(**controller: "#chbx"**, target: "#box2") }}
    
    <input type='checkbox' id='chbx'> <label for='chbx'>Show</label>
    
    @@@ #box2
    This is the target element.
    @@@

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $sourceCode, $inx, $funcName) = $str;
    }

    // assemble output:
    Assets::addAssets('REVEAL');

    $jsOptions = json_encode($options, JSON_PRETTY_PRINT);
    $js = <<<EOT
new RevealAccordion($jsOptions);
EOT;
    Page::addJsReady($js);
    return $sourceCode;
};

