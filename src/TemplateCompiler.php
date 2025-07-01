<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\shieldStr;
use function PgFactory\PageFactory\var_r;

const EVENT_INDEX_PLACEHOLDER = '%%';
const DEFAULT_OPTIONS = [
    'prefix' => '',
    'element' => '',
    'file' => '',
    'templates' => null,
    'suffix' => '',
    'separator' => '',
    'selector' => '',
    'asLinks' => false,
    'noDataAvailableText' => 'pfy-no-data-available',
    'removeUndefinedPlaceholders' => false,
    'markdown' => true,
    'wrapperPrefix' => '',
    'wrapperSuffix' => '',
    'newlineReplace' => '<br>',
];

define('CUSTOM_PHP_PATH', PFY_KIRBY_BASE_PATH . 'site/templates/custom/');

class TemplateCompiler
{
    private static array $templateOptions;

    /**
     * @param array $template // -> sanitized templateOptions
     * @param mixed $data
     * @param array $templateOptions
     * @return string
     * @throws \Exception
     */
    public static function compile(mixed $data = false, array $templateOptions = [], string $categorySelector = 'category', string $elementSelector = 'element'): string
    {
        if (!$data) {
            if ($templateOptions['noDataAvailableText']??false) {
                return TransVars::getVariable($templateOptions['noDataAvailableText'], true);
            } else {
                return '';
            }
        }
        if (!is_array($data)) {
            throw new \Exception('pfy-templatecompiler-bad-data');
        }
        if (!is_array(reset($data))) {
            $data = [$data];
        }

        self::$templateOptions = $templateOptions;

        if (($templateOptions['element']??false) === 'help') {
            return self::handleHelpRequest($data);
        }

        if ($newlineReplace = $templateOptions['newlineReplace']??false) {
            self::newlineReplace($data, $newlineReplace);
        }

        $compileMarkdown        = $templateOptions['markdown']??false;
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
        }


        $out = '';
        foreach ($data as $i => $rec) {
            // allow selection of category and element from fields in $rec:
            $categorySel = $rec[$categorySelector] ?? $categorySelector;
            $elementSel  = $rec[$elementSelector] ?? $elementSelector;
            // get the applicable template:
            $template = self::getTemplate($templateOptions, $categorySel, $elementSel);
            if (is_array($template)) {
                $prefix = (string)(($template['prefix']??false) ?: $prefix);
                $suffix = (string)(($template['suffix']??false) ?: $suffix);
                $template = (string)($template[$elementSel]??'');
            } elseif (trim($template)) {
                if ($compileMarkdown) {
                    $template .= "\n";
                }
            } else {
                $template = self::handleMissingTemplate($rec);
            }

            $s = self::compileTemplate($template, $rec);

            if ($s && $compileMarkdown) {
                $s = $s[strlen($s) - 1] !== "\n" ? $s . "\n" : $s;
            }
            if (str_contains($s, EVENT_INDEX_PLACEHOLDER)) {
                $s = str_replace(EVENT_INDEX_PLACEHOLDER, $i+1, $s);
            }
            $out .= $s . $sepPlaceholder;
        }
        $out = "$prefix$out$suffix";
        if ($sepPlaceholder) {
            $out = substr_replace($out, '', strrpos($out, '{!!!}'), 5);
            $out = str_replace($sepPlaceholder, $separator, $out);
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
            if (is_string($compileMarkdown)) { // compile as paragraph if markdown option was a string, e.g. 'markdown: p'
                $out = $md->compileParagraph($out);
            } else {
                $out = $md->compile($out);
            }
        }
        return $out;
    } // compile


    /**
     * @param array $templateOptions
     * @param string $categoryField
     * @param string $elementSelector
     * @return string|array
     */
    public static function getTemplate(mixed $templateOptions, string $categoryField = 'category', string $elementSelector = 'element'): string|array
    {
        $templates = $templateOptions['templates']??false;
        if (!$templates) { // if field 'templates' is not set, try to fall back to 'template'
            $templates = $templateOptions['template']??'';
        }
        if ($templates) {
            if (is_array($templates)) {
                if (isset($templates[$categoryField])) {
                    $tmplateToUse = $templates[$categoryField];
                } elseif (isset($templates[$elementSelector])) {
                    $tmplateToUse = $templates[$elementSelector];
                } elseif (isset($templates['_'])) {
                    $tmplateToUse = $templates['_'];
                } else {
                    $tmplateToUse = reset($templates);
                }
            } else {
                $tmplateToUse = (string) $templates;
            }
        } else {
            $tmplateToUse = $templateOptions[$categoryField] ??= $templateOptions[$elementSelector] ?? '';
        }
        return $tmplateToUse;
    } // getTemplate


    /**
     * @param array|string $options
     * @return array
     */
    public static function sanitizeTemplateOption(array|string $options): array
    {
        $templateOptions = DEFAULT_OPTIONS;
        if (is_string($options)) {
            $templateOptions['element'] = $options;
            // shortcut: "template: ~page/file.txt":
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
                $templateOptions['templates'] = $templ;
            } elseif (is_string($templ)) {
                $templateOptions['element'] = $templ;
            }
        }

        return $templateOptions;
    } // sanitizeTemplateOption


    /**
     * @param string $template
     * @param array $vars
     * @return string
     */
    private static function compileTemplate(string $template, array $vars): string
    {
        $template = str_replace(['\\n', '\\t'], ["\n", "\t"], $template);
        $template = self::basicCompileTemplate($template, $vars);
        TransVars::setTempVariables($vars);
        $template = TwigLight::compile($template);
        $str = TransVars::translate($template);
        TransVars::setTempVariables([]);
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
     * @param string $template
     * @param array $vars
     * @return string
     */
    private static function handleMissingTemplate(array $vars): string
    {
        $template = '';
        // if no template available, just output all fields in $vars as <dl>:
        foreach (array_keys($vars) as $key) {
            if (($key[0]??'') === '_') {
                continue;
            }
            $template .= "<dt>$key:</dt><dd>{{ $key }}</dd>\n";
        }
        $template = "<dl class='pfy-dl-as-table'>\n$template\n</dl>\n<hr>\n";
        return $template;
    } // handleMissingTemplate


    /**
     * @param mixed $data
     * @return string
     * @throws \Exception
     */
    private static function handleHelpRequest(mixed $data): string
    {
        $rec0 = reset($data);
        if (is_array($rec0)) {
            $data = $rec0;
        }
        $macroName = self::$templateOptions['_macroName']??'';
        $macroName = $macroName ? " for '$macroName()'" : '';
        $out = "## Template-Variables$macroName:\n";
        foreach ($data as $k => $v) {
            $out .= "&#37;$k&#37;  \n";
        }
        $out .= "\n## Template-Options:\n\n";
        $out .= shieldStr("<pre>" . var_r(DEFAULT_OPTIONS) . "</pre>\n");
        $out = \PgFactory\PageFactory\markdown($out);
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