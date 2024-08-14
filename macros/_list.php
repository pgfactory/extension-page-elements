<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

use PgFactory\PageFactoryElements\ListRenderer;
use PgFactory\PageFactoryElements\TemplateCompiler;

/**
 * @param $argStr
 * @return array|string
 * @throws \Kirby\Exception\InvalidArgumentException
 */
return function ($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'type' => ['[users,variables,macros,subpages,dir] Selects the objects to be listed.', false],
            'page' => ['Defines the page of which to list subpages.', null],
            'path' => ['Defines the path (aka directory) of which to list elements.', null],
            'selector' => ['[string] Used to select categories of data. For "users", specifies a user\'s data field. '.
                'Also used to select template if multiple are available.', null],
            'selectorOp' => ['Defines an operand used in user filtering (e.g. "==", "!=" etc.), .', '!='],
            'selectorValue' => ['Defines a value used in user filtering.', null],
            'role' => ['In case of type=user, selects the type of user (admin,staff, etc).".', null],
            'template' => ['File containing a markdown for rendering elements. Can be text or filename (.txt or .yaml).'
                , null],
            'prefix' => ['Optional text that is rendered before the output.', null],
            'suffix' => ['Optional text that is rendered after the output', null],
            'markdown' => ['If true, output is markdown compiled.', null],
            'reversed' => ['If true, output is rendered in reversed order.', false],
            'asLinks' => ['If true and type=subpages or dir, listed elements are wrapped in &lt;a> tags.', false],
            'wrapperClass' => ['Class applied to the wrapper tag.', null],
            'wrapperTag' => ['Defines the wrapper tag. If false, no wrapper is applied.', 'div'],
        ],
        'summary' => <<<EOT
# list()

Renders a list of requested type.

Available ``types``:

- ``variables``     10em>> lists all variables
- ``macros``        10em>> lists all macros including their help text
- ``users``         10em>> lists all users
- ``subpages``      10em>> lists all sub-pages
- ``dir``           10em>> lists elements in a given folder using a template for rendering

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $sourceCode, $inx) = $str;
    }

    $str = '';
    // assemble output:
    $type = $options['type'].' ';
    $type1 = $type[0];

    $class = $options['class']??'';
    $markdown = $options['markdown']??null;

    $prefix = $options['prefix'] ?? '';
    $prefix = str_replace('\\n', "\n", $prefix);
    $suffix = $options['suffix'] ?? '';
    $suffix = str_replace('\\n', "\n", $suffix);

    if ($type1 === 'v') {     // variables
        $str = TransVars::renderVariables();
        $str = shieldStr($str);
        $class = 'pfy-variables';

    } elseif ($type1 === 'm') {   // macros
        $str = Macros::renderMacros();
        $class = 'pfy-macros';

    } elseif ($type1 === 'u') {   // users
        $str = ListRenderer::renderUserList($options);
        $class = 'pfy-users';
        $markdown = !($markdown === null) && ($options['markdown']??false);

   } elseif ($type1 === 'd' || ($options['path']??false)) {     // dir
       $str = ListRenderer::renderFolderContent($options);
       $class = 'pfy-list-dir';

   } elseif (($type1 === 's') || ($options['page']??false)) {   // sub-pages
       $str = ListRenderer::renderSubpages($options);
       $class = 'pfy-subpages';
   }

   $str = "$prefix\n$str\n$suffix\n";

    if ($markdown) {
        $str = markdown($str);
    }

    if ($options['wrapperTag']??false) {
        $tag = $options['wrapperTag'];
        $str = <<<EOT
<$tag class='pfy-list pfy-list-$inx $class'>
$str
</$tag>
EOT;
    }

    return $sourceCode.$str;
};

