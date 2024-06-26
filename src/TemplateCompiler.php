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
    'mode' => null, // twig,transVars, replace/simple
    'prefix' => '',
    'element' => '',
    'file' => '',
    'templates' => null,
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
    private static array $templateOptions;
    private static string $filename;

    /**
     * @param array $template // -> sanitized templateOptions
     * @param mixed $data
     * @param array $templateOptions
     * @return string
     * @throws \Exception
     */
    public static function compile(string $template, mixed $data = false, array $templateOptions = []): string
    {

        if ($help = self::handleHelpRequest($template, $data)) {
            return $help;
        }

        $includeSystemVariables = ($templateOptions['includeSystemVariables']??false);
        $compileMarkdown        = $templateOptions['markdown']??false;
        $mode                   = $templateOptions['mode'];
        $prefix                 = $templateOptions['prefix']??'';
        $suffix                 = $templateOptions['suffix']??'';

        $sepPlaceholder = $separator = '';
        if ($templateOptions['separator']??false) {
            $sepPlaceholder = '{!!!}';
            $separator = $templateOptions['separator'];
        }
        if ($compileMarkdown) {
            $suffix .= "\n";
            $prefix .= "\n";
            $template .= "\n";
        }

        self::$templateOptions = $templateOptions;

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
                    $elemTempl = self::handleMissingTemplate($template, $rec);
                    $s = self::compileTemplate($mode, $elemTempl, $rec, $includeSystemVariables);
                    if ($s && $compileMarkdown) {
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
            return TransVars::getVariable($templateOptions['noDataAvailableText'], true);
        } else {
            $out = self::compileTemplate($mode, $template, [], $includeSystemVariables);
        }

        $out = str_replace(['\\n', '\\t'], ["\n", "\t"], $out);

        if ($templateOptions['wrapperPrefix']??false) {
            $out = $templateOptions['wrapperPrefix'] . $out;
        }
        if ($templateOptions['wrapperSuffix']??false) {
            $out .= $templateOptions['wrapperSuffix'];
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
     * @param array $templateOptions
     * @param string|null $selector
     * @return string|array
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function getTemplate(mixed $templateOptions, string $selector = null, string $useAsElement = 'element'): string|array
    {
        self::$filename = '';

        $selector = ($selector??false) ?: ($templateOptions['selector'] ?? '');
        $templates = $templateOptions['templates']??false;
        if ($templates) {
            $template = self::selectTemplate($templates, $selector, $useAsElement);
        } else {
            $template = $templateOptions['element']??'';
        }

        return $template;
    } // getTemplate


    /**
     * @param array|string $templateOptions
     * @return array
     */
    public static function sanitizeTemplateOption(array|string $options): array
    {
        $templateOptions = DEFAULT_OPTIONS;
        if (is_string($options)) {
            $templateOptions['element'] = $options;

        } else {
            foreach (DEFAULT_OPTIONS as $key => $value) {
                if (isset($options[$key]) && !str_contains('element', $key)) {
                    $templateOptions[$key] = $options[$key];
                }
            }
        }

        // special case: for convenience, element may contain file:
        if (($options['element']??false) && ($options['element'][0] === '~')) {
            $templateOptions['file'] = $options['element'];
            $templateOptions['element'] = '';
        }

        if ($templateOptions['file']) {
            $templateOptions['templates'] = loadFile($templateOptions['file']);
        }

        if ($templateOptions['mode'] === null) {
            $templateOptions['mode'] = kirby()->option('pgfactory.pagefactory-elements.options.templateCompilerDefaultMode', 'simple');
        }
        return $templateOptions;
    } // sanitizeTemplateOption


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
        $template = self::basicCompileTemplate($template, $vars, removeUndefinedPlaceholders: true);

        if ($mode === 'twig') {
            $str = self::twigCompileTemplate($template, $vars, $includeSystemVariables);

        } elseif (stripos('TransVars', $mode) !== false) {
            $str = self::transvarCompileTemplate($template);

        } elseif (stripos('php', $mode) !== false) {
            $str = self::phpCompileTemplate(self::$templateOptions['file'], $vars);
        }
        return $str;
    } // compileTemplate


    /**
     * @param string $template
     * @param array $vars
     * @param bool $removeUndefinedPlaceholders
     * @return string
     */
    public static function basicCompileTemplate(string $template, array $vars, bool $removeUndefinedPlaceholders = false): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('%!'.$key.'!%', shieldStr($value, 'immutable'), $template);
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

        $templateName = self::$filename ?: 'twig-template';

        $templateOptions = [];
        $templateOptions[$templateName] = $template;
        $loader = new \Twig\Loader\ArrayLoader($templateOptions);
        if (PageFactory::$debug) {
            $params = ['debug' => true];
        } else {
            $params = ['debug' => false, 'cache' => 'site/cache/twig/'];
        }
        try {
            $twig = new \Twig\Environment($loader, $params);
            $twig->addFilter(new \Twig\TwigFilter('intlDate', 'PgFactory\PageFactoryElements\twigIntlDateFilter'));
            $twig->addFilter(new \Twig\TwigFilter('intlDateFormat', 'PgFactory\PageFactoryElements\twigIntlDateFormatFilter'));

            foreach ($functions as $name => $function) {
                $twig->addFunction(new \Twig\TwigFunction($name, $function, ['is_safe' => ['html']]));
            }
            $out = $twig->render($templateName, $vars);

            if ($removeUndefinedPlaceholders) {
                self::removeUndefinedPlaceholders($out);
            }
        } catch (\Twig\Error\SyntaxError $e) {
            $errMsg = $e->getMessage();
            $errMsg = "<div class='pfy-error'>Error in Twig-template:<br>$errMsg</div>";
            PageFactory::$pg->setOverlay($errMsg, false);
            $out = $errMsg;
        }
        return $out;
    } // twigCompileTemplate


    /**
     * @param string $template
     * @param array $vars
     * @param bool $removeUndefinedPlaceholders
     * @return string
     */
    private static function transvarCompileTemplate(string $template): string
    {
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
    private static function selectTemplate(array|string $template, string $selector = null, string $useAsElement = 'element'): string|array
    {
        if (is_string($template)) {
            return $template;
        }

        if (isset($template[$selector])) {
            $template = $template[$selector];
        } elseif (isset($template['_'])) {
            $template = $template['_'];
        }

        if (is_array($template)) {
            if (isset($template[$useAsElement])) {
                $template = $template[$useAsElement];
            } else {
                $template = reset($template);
            }
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


    /**
     * @param string $template
     * @param mixed $data
     * @return string
     * @throws \Exception
     */
    private static function handleHelpRequest(string $template, mixed $data): string
    {
        $out = '';
        if ($template === 'help') {
            $rec0 = reset($data);
            if (is_array($rec0)) {
                $data = $rec0;
            }
            $macroName = self::$templateOptions['_macroName'];
            $macroName = $macroName ? " for '$macroName()'" : '';
            $out = "## Template-Variables$macroName:\n";
            foreach ($data as $k => $v) {
                $out .= "- &#37;$k&#37;\n";
            }
            $out .= "\n## Template-Options:\n\n";
            $out .= shieldStr("<pre>" . var_r(DEFAULT_OPTIONS) . "</pre>\n");
            $out = \PgFactory\PageFactory\markdown($out);
        }
        return $out;
    } // handleHelpRequest

} // TemplateCompiler