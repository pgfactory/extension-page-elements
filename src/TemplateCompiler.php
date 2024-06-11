<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\shieldStr;
use function PgFactory\PageFactory\var_r;


const DEFAULT_OPTIONS = [
    'mode' => 'simple', // twig,transVars, replace/simple
    'prefix' => '',
    'element' => '',
    'suffix' => '',
    'separator' => '',
    'selector' => '',
    'asLinks' => false,
    'noDataAvailableText' => 'pfy-no-data-available',
    'includeSystemVariables' => false,
    'markdown' => true,
    'wrapperPrefix' => '',
    'wrapperSuffix' => '',
];

const CUSTOM_PHP_PATH = 'site/templates/custom/';

class TemplateCompiler
{
    private static array $systemVariables = [];
    private static array $template;

    /**
     * @param string|array $template
     * @param mixed $data
     * @param array $auxOptions
     * @return string
     * @throws \Exception
     */
    public static function compile(string|array $template, mixed $data = false, array $auxOptions = []): string
    {
        $useAsElement = ($auxOptions['useAsElement']??false) ?: 'element';

        if ($template === 'help' || $template[$useAsElement] === 'help') {
            $rec0 = reset($data);
            if (is_array($rec0)) {
                $data = $rec0;
            }
            $macroName = $template['_macroName'];
            $macroName = $macroName ? " for '$macroName()'" : '';
            $out = "## Template-Variables$macroName:\n";
            foreach ($data as $k => $v) {
                $out .= "- &#37;$k&#37;\n";
            }
            $out .= "\n## Template-Options:\n\n";
            $out .= shieldStr("<pre>".var_r(DEFAULT_OPTIONS)."</pre>\n");
            $out = \PgFactory\PageFactory\markdown($out);
            return $out;
        }

        if (is_string($template)) {
            $element = $template;
            $template = [];
            $template[$useAsElement] = $element;
        }
        $template = $template + DEFAULT_OPTIONS;
        $includeSystemVariables = ($template['includeSystemVariables']??false) ?: $template['includeSystemVariables'];
        if (isset($auxOptions['compileMarkdown'])) {
            $compileMarkdown = $auxOptions['compileMarkdown'];
        } else {
            $compileMarkdown = ($template['compileMarkdown']??false) ?: ($template['markdown']??false);
        }
        $mode = ($template['mode']??false);

        $prefix = $template['prefix']??'';
        $suffix = $template['suffix']??'';

        $sepPlaceholder = $separator = '';
        if ($template['separator']??false) {
            $sepPlaceholder = '{!!!}';
            $separator = $template['separator'];
        }
        if ($compileMarkdown) {
            $suffix .= "\n";
            $prefix .= "\n";
            $template[$useAsElement] .= "\n";
        }

        self::$template = $template;

        $out = '';
        if ($data) {
            if (!is_array($data)) {
                throw new \Exception('???');
            } else {
                if (!is_array(reset($data))) {
                    $data = [$data];
                }
                $out .= $prefix;
                foreach ($data as $rec) {
                    $elemTempl = self::handleMissingTemplate($template[$useAsElement], $rec);
                    $s = self::compileTemplate($mode, $elemTempl, $rec, $includeSystemVariables);
                    if ($compileMarkdown) {
                        $s = $s[strlen($s) - 1] !== "\n" ? $s . "\n" : $s;
                    }
                    $out .= $s . $sepPlaceholder;
                }
                $out .= $suffix;
                if ($sepPlaceholder) {
                    $out = substr_replace($out, '', strrpos($out, '{!!!}'), 5);
                    $out = str_replace($sepPlaceholder, $separator, $out);
                }
            }
        } elseif ($data !== false) {
            // special case: no data available
            return TransVars::getVariable($template['noDataAvailableText'], true);
        } else {
            $out = self::compileTemplate($mode, $template[$useAsElement], [], $includeSystemVariables);
        }

        $out = str_replace(['\\n', '\\t'], ["\n", "\t"], $out);

        if ($template['wrapperPrefix']??false) {
            $out = $template['wrapperPrefix'] . $out;
        }
        if ($template['wrapperSuffix']??false) {
            $out .= $template['wrapperSuffix'];
        }
        if ($compileMarkdown) {
            $md = new MarkdownPlus();
            if (is_string($compileMarkdown)) {
                $out = $md->compileParagraph($out);
            } else {
                $out = $md->compile($out);
            }
        }
        return $out;
    } // compile


    /**
     * @param array $options
     * @param string|null $selector
     * @return string|array
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function getTemplate(array $options, string $selector = null): string|array
    {
        $template = TemplateCompiler::sanitizeTemplateOption($options);

        $selector = ($selector??false) ?: (($template['selector']??false) ?: ($options['selector'] ?? ''));

        $file = ($template['file']??false) ?: $template['element'] ?? '';
        if ($file && ($file[0] === '~')) {
            $templateFile = resolvePath($file);
            if (file_exists($templateFile)) {
                $template1 = loadFile($templateFile);
                if (isset($template['element'])) { unset($template['element']); }
                if (isset($template['file'])) { unset($template['file']); }
                if (is_string($template1)) {
                    $template['element'] = $template1;
                } else {
                    // template definition may contain multiple templates:
                    $template1 = self::selectTemplate($template1, $selector);
                    $template = $template + $template1;
                }
            }
        } elseif (is_array($template)) {
            // template definition may contain multiple templates:
            $template = self::selectTemplate($template, $selector);
        }

        $template = $template + DEFAULT_OPTIONS;

        return $template;
    } // getTemplate


    /**
     * @param array|string $options
     * @return array
     */
    public static function sanitizeTemplateOption(array|string &$options): array
    {
        if (is_string($options)) {
            $tmp = $options;
            $options = [];
            $options['template'] = [];
            $options['template']['element'] = $tmp;
        }

        if (!isset($options['template'])) {
            $options['template'] = [];
        } elseif (is_string($options['template'])) {
            $tmpl = $options['template'];
            $options['template'] = [];
            $options['template']['element'] = $tmpl;
        }
        if ($options['asLinks']??false) {
            $options['template']['asLinks'] = $options['asLinks'];
        }
        $options['markdown'] = ($options['markdown']??false) ?: ($options['compileMarkdown']??false);

        $options['template']['_macroName'] = $options['macroName']??'';

        return $options['template'];
    } // sanitizeTemplateOption


    /**
     * @param string $template
     * @param array $vars
     * @param bool $removeUndefinedPlaceholders
     * @return string
     */
    public static function basicCompileTemplate(string $template, array $vars, bool $removeUndefinedPlaceholders = false): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace(['{{ '.$key.' }}', '%'.$key.'%'], $value, $template);
        }
        if ($removeUndefinedPlaceholders) {
            self::removeUndefinedPlaceholders($template);
        }
        return $template;
    } // basicCompileTemplate


    /**
     * @param string $phpFile
     * @param array $vars
     * @return string
     */
    public static function phpCompileTemplate(string $phpFile, array $vars): string
    {
        $phpFile = CUSTOM_PHP_PATH . $phpFile;
        $fun = include $phpFile;
        $out = $fun($vars);
        return $out;
    } // phpCompileTemplate


    /**
     * @param string $mode
     * @param string $template
     * @param array $vars
     * @param bool $includeSystemVariables
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private static function compileTemplate(string $mode, string $template, array $vars, bool $includeSystemVariables = false): string
    {
        $template = str_replace(['\\n', '\\t'], ["\n", "\t"], $template);
        if ($mode === 'twig') {
            $str = self::twigCompileTemplate($template, $vars, $includeSystemVariables);

        } elseif (stripos('TransVars', $mode) !== false) {
            $str = self::transvarCompileTemplate($template, $vars, removeUndefinedPlaceholders: true);

        } elseif (stripos('php', $mode) !== false) {
            $str = self::phpCompileTemplate(self::$template['file'], $vars);

        } else {
            $str = self::basicCompileTemplate($template, $vars, removeUndefinedPlaceholders: true);
        }
        return $str;
    } // compileTemplate


    /**
     * @param string $template
     * @param array $vars
     * @param bool $includeSystemVariables
     * @param $removeUndefinedPlaceholders
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private static function twigCompileTemplate(string $template, array $vars, bool $includeSystemVariables = false, $removeUndefinedPlaceholders = false): string
    {
        if ($includeSystemVariables) {
            if (!self::$systemVariables) {
                self::$systemVariables = TransVars::$variables;
            }
            $vars = $vars + self::$systemVariables;
            $functions = TransVars::findAllMacros('forTwig');
        } else {
            $functions = [];
        }

        $template = TemplateCompiler::basicCompileTemplate($template, $vars);

        $loader = new \Twig\Loader\ArrayLoader([
            'index' => $template,
        ]);
        if (PageFactory::$debug) {
            $params = ['debug' => true];
        } else {
            $params = ['debug' => false, 'cache' => 'site/cache/twig/'];
        }
        $twig = new \Twig\Environment($loader, $params);
        $twig->addFilter(new \Twig\TwigFilter('intlDate', 'PgFactory\PageFactoryElements\twigIntlDateFilter'));
        $twig->addFilter(new \Twig\TwigFilter('intlDateFormat', 'PgFactory\PageFactoryElements\twigIntlDateFormatFilter'));

        foreach ($functions as $name => $function) {
            $twig->addFunction(new \Twig\TwigFunction($name, $function, ['is_safe' => ['html']]));
        }
        $out = $twig->render('index', $vars);

        if ($removeUndefinedPlaceholders) {
            self::removeUndefinedPlaceholders($out);
        }
        return $out;
    } // twigCompileTemplate


    /**
     * @param string $template
     * @param array $vars
     * @param bool $removeUndefinedPlaceholders
     * @return string
     */
    private static function transvarCompileTemplate(string $template, array $vars, bool $removeUndefinedPlaceholders = false): string
    {
        $template = preg_replace('/ % ([\w-]{1,20}) % /msx', "{{ $1 }}", $template);
        $template = self::basicCompileTemplate($template, $vars);
        $str = TransVars::translate($template);
        return $str;
    } // transvarCompileTemplate


    /**
     * @param string $template
     * @return void
     */
    private static function removeUndefinedPlaceholders(string &$template): void
    {
        $template = preg_replace('/(?<!\\\)%[\w.-]{1,20}%/', '', $template);
        $template = preg_replace('/(?<!\\\)\{\{.{1,20}}}/', '', $template);
    } // removeUndefinedPlaceholders


    /**
     * @param array|string $template
     * @param string|null $selector
     * @return string|array
     */
    private static function selectTemplate(array|string $template, string $selector = null): string|array
    {
        if (is_string($template)) {
            return $template;
        }

        if (isset($template[$selector])) {
            $template = $template[$selector];
        } elseif (isset($template['_'])) {
            $template = $template['_'];
        }

        if (is_string($template)) {
            $tmpl = $template;
            $template = [];
            $template['element'] = $tmpl;
        }
        return $template;
    } // selectTemplate


    /**
     * @param string $template
     * @param array $vars
     * @return string
     */
    private static function handleMissingTemplate(string $template, array $vars): string
    {
        if (trim($template)) {
            return $template;
        }

        // if no template available, just output all fields in $vars as <dl>:
        foreach (array_keys($vars) as $key) {
            $template .= "$key:\n: {{ $key }}\n";
        }
        $template .= "\n\n";
        return $template;
    } // handleMissingTemplate

} // TemplateCompiler