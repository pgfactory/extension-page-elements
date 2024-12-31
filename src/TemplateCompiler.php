<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\shieldStr;
use function PgFactory\PageFactory\strPosMatching;
use function PgFactory\PageFactory\var_r;

const EVENT_INDEX_PLACEHOLDER = '%%';
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
    'newlineReplace' => '<br>',
];

define('CUSTOM_PHP_PATH', PFY_APP_BASE_PATH . 'site/templates/custom/');

class TemplateCompiler
{
    private static array $systemVariables = [];
    private static array $templateOptions;

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

        if ($newlineReplace = $templateOptions['newlineReplace']??false) {
            self::newlineReplace($data, $newlineReplace);
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
                foreach ($data as $i => $rec) {
                    $elemTempl = self::handleMissingTemplate($template, $rec);
                    $s = self::compileTemplate($mode, $elemTempl, $rec);
                    if ($s && $compileMarkdown) {
                        $s = $s[strlen($s) - 1] !== "\n" ? $s . "\n" : $s;
                    }
                    if (str_contains($s, EVENT_INDEX_PLACEHOLDER)) {
                        $s = str_replace(EVENT_INDEX_PLACEHOLDER, $i+1, $s);
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
     */
    private static function compileTemplate(string $mode, string $template, array $vars): string
    {
        $template = str_replace(['\\n', '\\t'], ["\n", "\t"], $template);
        $template = self::basicCompileTemplate($template, $vars);
        $template = TransVars::preprocess($template);
        $str = TransVars::translate($template);
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


    /**
     * @param mixed $data
     * @param string $replaceNewlineWith
     * @return void
     */
    private static function newlineReplace(mixed &$data, string $replaceNewlineWith): void
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && str_contains($value, $replaceNewlineWith)) {
                $data[$key] = str_replace("\n", $replaceNewlineWith, $value);
            } elseif (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (is_string($v) && str_contains($v, "\n")) {
                        $data[$key][$k] = str_replace("\n", $replaceNewlineWith, $v);
                    }
                }
            }
        }
    } // newlineReplace

} // TemplateCompiler