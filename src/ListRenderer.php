<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\fileExt;
use function PgFactory\PageFactory\base_name;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\getDir;

const DEFAULT_ELEMENT_TEMPLATE = '- (link: %url% text:%filename% type:%ext% target:_blank) %description%';
const DEFAULT_FOLDER_ELEMENT_TEMPLATE = '<> <strong>%basename%</strong>';

class ListRenderer
{
    /**
     * @param $options
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function renderUserList($options): string
    {
        $users = Utils::getUsers($options); // -> $options['role'] and $options['reversed']
        // Get labels of all user recs:
        $labels = self::getUserRecLabels($users);

        if ($options['table'] ?? false) {
            $tableOptions = is_array($options['table']) ? $options['table'] : [];
            if (!($tableOptions['headers'] ?? false)) {
                $tableOptions['headers'] = $labels;
            }
            $str = self::renderUserTable($users, $tableOptions);

        } else {
            $templateOptions = $options['template'] ?? [];
            if ($templateOptions === 'help') {
                $out = "<h3>Available Elements for Users:</h3>\n";
                foreach ($labels as $label) {
                    $out .= "<div>\%$label\%</div>\n";
                }
                return $out;

            } elseif (!(($templateOptions['element']??false) || ($templateOptions['file']??false))) {
                $template = "<> %Username%\n";
                foreach ($labels as $label) {
                    if ($label === 'Username') {
                        continue;
                    }
                    $template .= "{% if $label %} $label:    >> %$label%\n {% endif %}\n";
                }
                $template .= "\n<>\n";
                $templateOptions['element'] = $template;
            }
            $templateOptions = TemplateCompiler::sanitizeTemplateOption($templateOptions);
            $str = TemplateCompiler::compile($users, $templateOptions);
        }
        if (!$str) {
            $text = TransVars::getVariable('pfy-list-empty', true);
            $str = "<div class='pfy-list-empty'>$text</div>";
        }
        return $str;
    } // renderUserList


    /**
     * @param array $options
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function renderSubpages(array $options): string
    {
        $templateOptions = TemplateCompiler::sanitizeTemplateOption($options['template']??[]);
        $template = TemplateCompiler::getTemplate($templateOptions);

        // set default template if none is defined:
        if (!$template) {
            if (($options['asLinks']??false) || ($templateOptions['asLinks']??false)) {
                $template = '- (link: ~/%pageUrl% text:%filename% target:_blank)';
            } else {
                $template = '- %filename%';
            }
        }

        $page = $options['page']??'';
        if ($page === '~page/' || $page === '\\~page/') {
            $pages = page()->children()->listed();
        } elseif ($page === '/' || $page === '~/') {
            $pages = site()->children()->listed();
        } else {
            if (str_starts_with($page, '~/')) {
                $page = substr($page, 2);
            }
            $pageObj = page($page);
            if (!$pageObj) {
                throw new \Exception("Error: page '$page' not found (by macro list())");
            }
            $pages = $pageObj->children()->listed();
        }

        if ($options['reversed'] ?? false) {
            $pages = $pages->flip();
        }

        return self::renderSubpagesByTemplate($pages, $template, $templateOptions);
    } // renderSubpages


    /**
     * @param array $options
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function renderFolderContent(array $options): string
    {
        $templateOptions = TemplateCompiler::sanitizeTemplateOption($options['template']??[]);
        $template = TemplateCompiler::getTemplate($templateOptions);

        // set default template if none is defined:
        if (!$template) {
            if ($options['asLinks'] ?? false) {
                $template = DEFAULT_ELEMENT_TEMPLATE;
            } else {
                $template = '- %filename%';
            }
        }
        if (!($templateOptions['folderElement'] ?? false)) {
            $templateOptions['folderElement'] = DEFAULT_FOLDER_ELEMENT_TEMPLATE;
        }

        $reversed = $options['reversed'] ?? false;

        $path = $options['path'] ?? '';
        $path = Utils::resolvePath($path);
        $dir = getDir($path);
        if (!$dir || !is_array($dir)) {
            return '';
        }

        if ($reversed) {
            rsort($dir);
        }

        // assemble data:
        $data = [];
        foreach ($dir as $file) {
            $filename = basename($file);
            $fileExt = fileExt($file);
            $name = base_name(rtrim($file,'/'), false);
            $date = '';
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) {
                $date = $m[1];
            }
            $filePath = '~/'.$file;
            $rec = [
                'url'       => $filePath,
                'path'      => $filePath,
                'filename'  => $filename,
                'ext'       => $fileExt,
                'pagename'  => $filename,
                'name'      => $name,
                'slug'      => $filename,
                'date'      => $date,
            ];
            $data[] = $rec;
        }

        return TemplateCompiler::compile($data, $templateOptions);
    } // renderFolderContent


    /**
     * @param array $users
     * @param array $options
     * @return string
     * @throws \Exception
     */
    private static function renderUserTable(array $users, array $options): string
    {
        return (new DataTable($users, $options))->render();
    } // renderUserTable


    /**
     * @param object $page
     * @return bool
     */
    private static function checkVisibility(object $page): bool
    {
        // check visibility variable in meta-file:
        if ($visibility = $page->visible()->value()) {
            $visible = Permission::evaluate($visibility);
            if (!$visible) {
                return false;
            }
        }

        // check and evaluate time constraints (showFrom,showTill) from variables in meta-file:
        $showFrom = $page->showfrom()->value();
        $showTill = $page->showtill()->value();
        if ($showFrom || $showTill) {
            if (!MdPlusHelper::isNowVisible($showFrom, $showTill)) {
                return false;
            }
        }
        return true;
    } // checkVisibility


    /**
     * @param array $options
     * @param string $target
     * @return array
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function parseFolderArgs(array $options, string $target = ''): array
    {
        $asLinks = $options['asLinks']??false;
        $template = '';
        $emptyTemplate = [
            'prefix' => '',
            'element' => '',
            'suffix' => '',
        ];
        $defaultTemplate = [
            'prefix' => '',
            'element' => $asLinks ? '- (link:%url% text:%filename% target:_blank)' : "- %filename%",
            'suffix' => '',
        ];

        $templateOptions = $options['options'] ?? [];
        $tmpl = ($options['template'] ?? false) ?: ($templateOptions ?: false);
        if (!$tmpl) {
            $template = $defaultTemplate;

        } elseif (is_string($tmpl)) {
            if (str_starts_with($tmpl, '~')) {
                $templateFile = Utils::resolvePath($tmpl);
                if (file_exists($templateFile)) {
                    $template = loadFile($templateFile);
                }
            } else {
                $template = $emptyTemplate;
                $template['element'] = $tmpl;
            }

        } elseif (is_array($tmpl)) {
            $template = ($tmpl['element'] ?? '') ?: $tmpl['file'] ?? '';
            $templateOptions = $tmpl + $emptyTemplate;
        }
        if (!is_array($templateOptions)) {
            $templateOptions = [];
        }

        $templateOptions['mode'] = ($templateOptions['mode'] ?? false) ?: ($options['mode'] ?? false) ?: 'simple';
        $templateOptions['compileMarkdown'] = false;

        $wrapperBegin = (is_array($template) ? ($template['wrapperBegin'] ?? false) : false)
            ?: ($templateOptions['wrapperBegin'] ?? false)
            ?: ($templateOptions['prefix'] ?? '');
        $wrapperBegin = $wrapperBegin ? "$wrapperBegin\n" : '';
        $wrapperBegin = str_replace(['\\n', '\\t'], ["\n", "\t"], $wrapperBegin);

        $wrapperEnd = (is_array($template) ? ($template['wrapperEnd'] ?? false) : false)
            ?: ($templateOptions['wrapperEnd'] ?? false)
            ?: ($templateOptions['suffix'] ?? '');
        $wrapperEnd = $wrapperEnd ? "$wrapperEnd\n" : '';
        $wrapperEnd = str_replace(['\\n', '\\t'], ["\n", "\t"], $wrapperEnd);
        return [$template, $templateOptions, $wrapperBegin, $wrapperEnd];
    } // parseFolderArgs


    /**
     * @param \Kirby\Toolkit\Collection|\Kirby\Cms\Pages $pages
     * @param array|string $template
     * @param array $templateOptions
     * @return string
     * @throws \Exception
     */
    private static function renderSubpagesByTemplate(\Kirby\Toolkit\Collection|\Kirby\Cms\Pages $pages, array|string $template, array $templateOptions): string
    {
        $data = [];
        foreach ($pages as $page) {
            if (!self::checkVisibility($page)) {
                continue;
            }
            $url = $page->url();
            $path = (string)$page->root();
            $filename = (string)$page->title();
            $slug = $page->slug();
            $pageUrl = $page->id();
            $shortUrl = dirname($_SERVER["SCRIPT_NAME"]) . '/' . $pageUrl;
            $date = '';
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) {
                $date = $m[1];
            }

            $rec = [
                'url' => $url,
                'shortUrl' => $shortUrl,
                'filename' => $filename,
                'pagename' => $filename,
                'name' => $filename,
                'path' => $path,
                'slug' => $slug,
                'pageUrl' => $pageUrl,
                'date' => $date,
            ];
            $data[] = $rec;
        }

        return TemplateCompiler::compile($data, $templateOptions);
    } // renderSubpagesByTemplate


    /**
     * @param array|string $users
     * @return array
     */
    private static function getUserRecLabels(array|string $users): array
    {
        $labels = [];
        foreach ($users as $user) {
            foreach ($user as $key => $val) {
                $labels[$key] = true;
            }
        }
        $labels = array_keys($labels);
        return $labels;
    } // getUserRecLabels

} // class ListRenderer