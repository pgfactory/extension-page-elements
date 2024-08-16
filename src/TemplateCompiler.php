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
    'removeUndefinedPlaceholders' => false,
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

        $compileMarkdown        = $templateOptions['markdown']??false;
        $mode                   = $templateOptions['mode']??'simple';
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
                    $s = self::compileTemplate($mode, $elemTempl, $rec);
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
            $out = self::compileTemplate($mode, $template, []);
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
    public static function getTemplate(mixed &$templateOptions, string $selector = null, string $useAsElement = 'element'): string|array
    {
        self::$filename = '';

        $selector = ($selector??false) ?: ($templateOptions['selector'] ?? '');
        $templates = $templateOptions['templates']??false;
        if ($templates) {
            list($tmplateToUse, $tmplateRec) = self::selectTemplate($templates, $selector, $useAsElement);

            // $tmplateRec may contain additional values, such as prefix or suffix -> propagate to $templateOptions:
            if (is_array($tmplateRec)) {
                foreach ($tmplateRec as $key => $value) {
                    if (isset($templateOptions[$key])) {
                        $templateOptions[$key] = $value;
                    }
                }
            }
        } else {
            $tmplateToUse = $templateOptions[$useAsElement]??'';
        }

        return $tmplateToUse;
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
            if (($templateOptions['element'][0]??'') === '~') {
                $templateOptions['file'] = $templateOptions['element'];
                $templateOptions['element'] = '';
            }
        } else {
            $templateOptions = $options + DEFAULT_OPTIONS;
        }

        // special case: for convenience, element may contain file:
        if ($options['element']??false) {
            if ($options['element'][0] === '~') {
                $templateOptions['file'] = $options['element'];
                $templateOptions['element'] = '';
            } else {
                $templateOptions['element'] = $options['element'];
            }
        }

        if ($templateOptions['file']) {
            $templ = loadFile($templateOptions['file']);
            if (is_array($templ)) {
                foreach ($templ as $key => $value) {
                    if (is_string($value)) {
                        $templ[$key] = str_replace(['\\n', '\\t'], ["\n", "\t"], $templ[$key]);
                    } else {
                        foreach ($value as $k => $v) {
                            $templ[$key][$k] = str_replace(['\\n', '\\t'], ["\n", "\t"], $templ[$key][$k]);
                        }
                    }
                }
            }
            $templateOptions['templates'] = $templ;
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
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private static function compileTemplate(string $mode, string $template, array $vars): string
    {
        $template = str_replace(['\\n', '\\t'], ["\n", "\t"], $template);
        $str = $template = self::basicCompileTemplate($template, $vars);

        if ($mode === 'twig') {
            $str = self::twigCompileTemplate($template, $vars);

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
    public static function basicCompileTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_string($value)) {
                $template = str_replace('%!' . $key . '!%', shieldStr($value, 'immutable'), $template);
                $template = str_replace(['{{ ' . $key . ' }}', '%' . $key . '%'], $value, $template);
            }
        }
        if (self::$templateOptions['removeUndefinedPlaceholders']??false) {
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
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private static function twigCompileTemplate(string $template, array $vars): string
    {
        if (self::$templateOptions['includeSystemVariables']??false) {
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

            if (self::$templateOptions['removeUndefinedPlaceholders']??false) {
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
     * @param string $useAsElement
     * @return string|array
     */
    private static function selectTemplate(array|string $template, string $selector = null, string $useAsElement = 'element'): string|array
    {
        $selectedTemplate = $template;
        if (is_string($template)) {
            return [$selectedTemplate, $template];
        }

        if (isset($template[$selector])) {
            $template = $template[$selector];
        } elseif (isset($template['_'])) {
            $template = $template['_'];
        }

        if (is_array($template)) {
            if (isset($template[$useAsElement])) {
                $selectedTemplate = $template[$useAsElement];
            } else {
                $selectedTemplate = reset($template);
            }
        } else {
            $selectedTemplate = $template;
        }
        return [$selectedTemplate, $template];
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