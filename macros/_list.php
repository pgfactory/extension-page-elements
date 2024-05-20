<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

/**
 * @param $argStr
 * @return array|string
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function _list($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'type' => ['[users,variables,functions,subpages] Selects the objects to be listed.', false],
            'page' => ['Defines the page of which to list subpages.', '\~page/'],
            'path' => ['Defines the path (aka directory) of which to list elements.', null],
            'template' => ['File containing a markdown template for rendering elements found in "path"'.
                'If file is not found, the string is used as template.', null],
            'asLinks' => ['If true and type=subpages, listed elements are wrapped in &lt;a> tags.', false],
            'wrapperTag' => ['Defines the wrapper tag. If false, no wrapper is applied.', 'div'],
            'options' => ['{template,prefix,suffix,separator,listWrapperTag} Specifies how to render the list.', false],
//            'order' => ['', ''],
            'reversed' => ['If true, output is rendered in reversed order.', false],
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

Available ``options`` for  type **users**:

- ``template``      10em>> should contain placeholders like '%name%' and '%email%' or any fields defined in Kirby's user admin
- ``prefix``        10em>> string to prepend to each element
- ``suffix``        10em>>  string to append to each element
- ``separator``     10em>>  string placed between elements
- ``listWrapperTag``    10em>> 'ul' or 'ol' or tag or false (for no wrapper) 

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $sourceCode, $inx) = $str;
    }

    // assemble output:
    $type = $options['type'].' ';
    $type1 = $type[0];
    if (isset($options['order']) && (($options['order']?:' ')[0] === 'r')) {
        $options['reversed'] = true;
    }
    $class = '';

   if ($type1 === 'v') {     // variables
        $str = TransVars::renderVariables();
        $str = shieldStr($str);
        $class = 'pfy-variables';

    } elseif ($type1 === 'm') {   // macros
        $str = TransVars::renderMacros();
        $class = 'pfy-macros';

    } elseif ($type1 === 'u') {   // users
        $str = ListElements::renderUserList($options);
        $class = 'pfy-users';

    } else if ($type1 === 'd') {     // datasources
        $str = ListElements::renderListOfDatasources($options);
        $class = 'pfy-list-dir';

    } elseif ($type1 === 's') {   // sub-pages
        $str = ListElements::renderSubpages($options);
        $class = 'pfy-subpages';
    }

    if ($tag = $options['wrapperTag']) {
        $str = <<<EOT
<$tag class='pfy-list pfy-list-$inx $class'>
$str
</$tag>
EOT;
    }

    return $sourceCode.$str;
}


class ListElements
{
    public static function renderListOfDatasources($options): string
    {
        $reversed = ($options['reversed']??false);
        $path = $options['path'] ?? '';
        $template = self::getTemplate($options);

        $path = resolvePath($path);
        $dir = getDir($path);

        if ($reversed) {
            rsort($dir);
        }

        $out = '';
        foreach ($dir as $file) {
            $filename = base_name($file, false);
            $date = '';
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) {
                $date = $m[1];
            }
            $html = Utils::compileTemplate($template, [
                'file' => "~/$file",
                'filename' => $filename,
                'date' => $date,
                ]);
            $out .= $html . "\n\n";
        }

        return $out;
    } // renderListOfDatasources


    public static function renderSubpages(array $options): string
    {
        $asLinks = $options['asLinks']??false;
        $reversed = ($options['order']??' ')[0] === 'r';
        $page = $options['page']??'';
        if ($page === '~page/' || $page === '\\~page/') {
            $pages = page()->children()->listed();
        } elseif ($page === '/' || $page === '~/') {
            $pages = site()->children()->listed();
        } else {
            if (!$page = page($page)) {
                throw new \Exception("Error: page '$page' not found (by macro list())");
            }
            $pages = $page->children()->listed();
        }
        $str = '';

        if ($reversed) {
            $pages = $pages->flip();
        }

        foreach ($pages as $page) {
            $elem = $page->title()->value();
            if ($asLinks) {
                $url = $page->url();
                $elem = "<a href='$url'>$elem</a>";
            }
            $str .= "<li>$elem</li>\n";
        }
        if ($str) {
            $str = "<ul>\n$str</ul>\n";
        } else {
            $text = TransVars::getVariable('pfy-list-empty', true);
            $str = "<div class='pfy-list-empty'>$text</div>";
        }
        return $str;
    } // renderSubpages


    /**
     * @return string
     */
    public static function renderUserList($options): string
    {
        $options1 = (array)$options['options'] ?? [];
        $template = self::getTemplate($options);
        $templatePrefix = $options['templatePrefix'] ?? '';
        $templatePostfix = $options['templatePostfix'] ?? '';
        if (is_array($template)) {
            $templatePrefix = $template['head'] ?? '';
            $templatePostfix = $template['tail'] ?? '';
            $template = $template['row'] ?? '';
        }

        $str = $templatePrefix."\n";
        $users = Utils::getUsers($options1);
        foreach ($users as $user) {
            $s = Utils::compileTemplate($template, $user);
            $str .= "$s\n";
        }
        $str .= "$templatePostfix\n";

        if (!$str) {
            $text = TransVars::getVariable('pfy-list-empty', true);
            $str = "<div class='pfy-list-empty'>$text</div>";
        }
        $html = markdown($str);
        return $html;
    } // renderUserList


    private static function getTemplate(array $options): string|array
    {
        $template = $options['template'] ?? '';
        $templateFile = resolvePath($template);
        if (file_exists($templateFile)) {
            $template = loadFile($templateFile);
        }
        if (is_array($template)) {
            foreach ($template as $key => $elem) {
                $template[$key] = preg_replace('/ % ([\w-]{1,20}) % /msx', "{{ $1 }}", $elem);
            }
        } else {
            $template = preg_replace('/ % ([\w-]{1,20}) % /msx', "{{ $1 }}", $template);
        }
        return $template;
    } // getTemplate

} // ListElements
