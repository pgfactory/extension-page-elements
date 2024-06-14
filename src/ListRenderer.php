<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\fileExt;
use function PgFactory\PageFactory\resolvePath;
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function renderUserList($options): string
    {
        if ($options['selector']??false) {
            $options['role'] = $options['selector'];
        } elseif ($options['role']??false) {
            $options['selector'] = $options['role'];
        }
        $template = TemplateCompiler::getTemplate($options);
        $users = Utils::getUsers($options); // -> $options['role'] and $options['reversed']
        $str = TemplateCompiler::compile($template, $users);

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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function renderSubpages(array $options): string
    {
        $template = TemplateCompiler::getTemplate($options);

        // set default template if none is defined:
        if (!$template['element']) {
            if ($options['asLinks']??false) {
                $template['element'] = '- (link: ~/%pageUrl% text:%filename% target:_blank)';
            } else {
                $template['element'] = '- %filename%';
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
            if (!$page = page($page)) {
                throw new \Exception("Error: page '$page' not found (by macro list())");
            }
            $pages = $page->children()->listed();
        }

        $data = [];
        if ($options['reversed']??false) {
            $pages = $pages->flip();
        }

        foreach ($pages as $page) {
            if (!self::checkVisibility($page)) {
                continue;
            }
            $url = $page->url();
            $path = (string)$page->root();
            $filename = (string)$page->title();
            $slug = $page->slug();
            $pageUrl = $page->id();
            $shortUrl = dirname($_SERVER["SCRIPT_NAME"]).'/'.$pageUrl;
            $date = '';
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $m)) {
                $date = $m[1];
            }

            $rec = [
                'url'       => $url,
                'shortUrl'  => $shortUrl,
                'filename'  => $filename,
                'pagename'  => $filename,
                'name'      => $filename,
                'path'      => $path,
                'slug'      => $slug,
                'pageUrl'   => $pageUrl,
                'date'      => $date,
            ];
            $data[] = $rec;
        }

        $out =  TemplateCompiler::compile($template, $data);
        return $out;
    } // renderSubpages


    /**
     * @param array $options
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function renderFolderContent(array $options): string
    {
        $template = TemplateCompiler::getTemplate($options);

        // set default template if none is defined:
        if (!$template['element']) {
            if ($options['asLinks']??false) {
                $template['element'] = DEFAULT_ELEMENT_TEMPLATE;
            } else {
                $template['element'] = '- %filename%';
            }
        }
        if (!$template['folderElement']) {
            $template['folderElement'] = DEFAULT_FOLDER_ELEMENT_TEMPLATE;
        }

        $reversed = ($options['reversed']??false);

        $path = $options['path'] ?? '';
        $path = resolvePath($path);
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

        $out =  TemplateCompiler::compile($template, $data);
        return $out;
    } // renderFolderContent


    /**
     * @param object $page
     * @return bool
     */
    private static function checkVisibility(object $page): bool
    {
        if ($visibility = $page->visible()->value()) {
            $visible = Permission::evaluate($visibility);
            if (!$visible) {
                return false;
            }
        }
        if ($showFrom = $page->showfrom()->value()) {
            if (strtotime($showFrom) > time()) {
                return false;
            }
        }
        if ($showTill = $page->showtill()->value()) {
            if (strtotime($showTill) < time()) {
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

        $templateOptions = $options['options'] ?? false;
        $tmpl = ($options['template'] ?? false) ?: $templateOptions;
        if (!$tmpl) {
            $template = $defaultTemplate;

        } elseif (is_string($tmpl)) {
            if ($tmpl[0] === '~') {
                $templateFile = resolvePath($tmpl);
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

        $templateOptions['mode'] = ($templateOptions['mode'] ?? false) ?: ($options['mode'] ?? false) ?: 'simple';
        $templateOptions['compileMarkdown'] = false;

        $wrapperBegin = ($template['wrapperBegin'] ?? false) ?: ($templateOptions['wrapperBegin'] ?? false) ?: $templateOptions['prefix'] ?? '';
        $wrapperBegin = $wrapperBegin ? "$wrapperBegin\n" : '';
        $wrapperBegin = str_replace(['\\n', '\\t'], ["\n", "\t"], $wrapperBegin);

        $wrapperEnd = ($template['wrapperEnd'] ?? false) ?: ($templateOptions['wrapperEnd'] ?? false) ?: $templateOptions['suffix'] ?? '';
        $wrapperEnd = $wrapperEnd ? "$wrapperEnd\n" : '';
        $wrapperEnd = str_replace(['\\n', '\\t'], ["\n", "\t"], $wrapperEnd);
        return array($template, $templateOptions, $wrapperBegin, $wrapperEnd);
    } // parseFolderArgs

} // class ListRenderer