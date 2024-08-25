<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\PageFactory\PageFactory;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\base_name;
use function PgFactory\PageFactory\dir_name;
use function PgFactory\PageFactory\fixPath;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\getDir;
use function PgFactory\PageFactory\getDirDeep;
use function PgFactory\PageFactory\fileExt;

const DEFAULT_ELEMENT_TEMPLATE = "- (link: %url% text:%basename%.%ext% type:%ext% target:_blank) %description%\n";

const DEFAULT_FOLDER_ELEMENT_TEMPLATE = '<> <strong>%label%</strong>';

class Dir
{
    public static $inx = 1;
    private $path;
    private $origPathLen = '';
    private $id;
    private $class = '';
    private $wrapperClass;
    private $includeFiles;
    private $includeFolders;
    private $exclude;
    private $markdown;
    private $modifiers;
    private $maxAge;
    private $replaceOnElem;
    private $reverse;
    private $reverseFolders;
    private $deep;
    private $hierarchical;
    private $download;
    private $pattern = '';
    private $replace = '';
    private $templateOptions = [];
    private string|array $folderTemplate;
    private string|array $template;


    public function __construct()
    {
        PageFactory::$pg->addAssets('media/plugins/pgfactory/pagefactory-pageelements/css/-dir.css');
    } // __construct


    /**
     * @param $args
     * @return mixed|string
     * @throws \Exception
     */
    public function render($args)
    {
        $inx = self::$inx++;

        list($path, $pattern) = $this->parseOptions($args, $inx);
        $this->origPathLen = strlen($path);
        $dirOffset = get('dir');
        if ($dirOffset === '.') {
            $dirOffset = '';
        }
        $goBack = $header = '';
        if ($dirOffset) {
            $path = "$path$dirOffset/";
            $href = PageFactory::$pageUrl .'?dir=' . dirname($dirOffset);
            $goBack = "\n<div class='pfy-dir-go-back'><a href='$href'>{{ pfy-dir-go-back }}</a></div>";
            $header = "<div class='pfy-dir-sub-header'>{{ pfy-dir-sub-folder }}$dirOffset{{ pfy-dir-sub-folder-tail }}</div>";
        }

        if ($this->hierarchical) {
            $this->template = TemplateCompiler::getTemplate($this->templateOptions);
            $this->folderTemplate = TemplateCompiler::getTemplate($this->templateOptions, useAsElement: 'folderElement');
            $str = $this->renderDirHierarchical($path, $pattern, 1);

        } else {
            $str = $this->renderDir($path, $pattern);
        }

        // case help: list available variables
        if (str_starts_with($str, '<h2>Template-Variables')) {
            return $str;
        }

        if ($this->markdown) {
            $md = new MarkdownPlus();
            $str = $md->compile(ltrim($str, "\n"));
        }

        if ($str !== '{{ pfy-dir-empty }}') {
            $str = <<<EOT

<div{$this->id}{$this->wrapperClass}>$header$goBack
$str   
</div>
EOT;
        }

        return $str;
    } // render


    /**
     * @param string $path
     * @param string $pattern
     * @param int $level
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function renderDir(string $path, string $pattern, string $class = ''): string
    {
        if ($this->deep) {
            $dir0 = getDirDeep($path . $pattern);
        } else {
            $dir0 = getDir($path . $pattern);
        }

        $dir = $this->selectElements($dir0);
        $str = '';
        $subdir = '';

        $dir = $this->sortDir($dir);

        $data = $this->extractFilesDescriptorVars($dir);
        if (sizeof($data) === 1) {
            $data = reset($data);
        }

        $template = TemplateCompiler::getTemplate($this->templateOptions);
        $currLevelFiles = TemplateCompiler::compile($template, $data, $this->templateOptions);

        $class = ($this->class ?: 'pfy-dir') . " $class";

        if ($currLevelFiles && preg_match('/^<(ul|ol)/', $currLevelFiles)) {
            $currLevelFiles = substr($currLevelFiles, 0, 3) . " class='$class'>" . $subdir . substr($currLevelFiles, 4);
            $str .= $currLevelFiles;

        } elseif ($subdir) {
            $str .= "<ul class='$class'>\n$subdir\n</ul>";
        } else {
            $str .= $currLevelFiles;
        }
        return $str;
    } // renderDir


    private function renderDirHierarchical(string $path, string $pattern, int $level = 1): string
    {
        $out = '';
        $folders = getDir($path, type: 'folders');
        foreach ($folders as $folder) {
            $subdir = $this->renderDirHierarchical($folder, $pattern, $level+1);

            $fileVars = $this->extractFileDescriptorVars(rtrim($folder, '/'));
            $templateOptions = $this->templateOptions;
            $templateOptions['markdown'] = false;
            $label = TemplateCompiler::compile($this->folderTemplate, $fileVars, $templateOptions);
            $p = '';
            if (preg_match('/^(<\d*>\s*)(.*)/', $label, $m)) {
                $p = $m[1];
                $label = $m[2];
            }

            $subdir = <<<EOT

$p$label

$subdir
$p

EOT;
            $out .= $this->markdown($subdir);
        }
        $out .= $this->renderDir($path, $pattern, "pfy-dir-lvl-$level");
        return $out;
    } // renderDirHierarchical


    /**
     * @param $file
     * @return false|string
     */
    private function parseUrlFile($file)
    {
        if (is_dir($file)) {
            return '';
        }
        $ext = fileExt($file);
        if (!$ext || !file_exists($file) || (!str_contains('webloc,lnk,url', $ext))) {
            return false;
        }
        $str = file_get_contents($file);
        if (preg_match('|url=(.*)|ixm', $str, $m)) {    // Windows link
            $url = trim($m[1]);
        } elseif (preg_match('|<string>(.*)</string>|ixm', $str, $m)) { // Mac link
            $url = $m[1];
        } else {
            $url = trim($str);
        }
        return $url;
    } // parseUrlFile


    /**
     * @param array $dir
     * @return mixed
     */
    private function selectElements(array $dir): mixed
    {
        if (!$this->includeFolders) {
            $dir = array_filter($dir, function ($file) {
                return !is_dir($file);
            });
        }
        if (!$this->includeFiles) {
            $dir = array_filter($dir, function ($file) {
                return !is_file($file);
            });
        }
        if ($maxAge = $this->maxAge) {
            $dir = array_filter($dir, function ($file) use ($maxAge) {
                return (filemtime($file) > $maxAge);
            });
        }

        if ($this->exclude) {
            $dir = preg_grep($this->exclude, $dir, PREG_GREP_INVERT);
        }
        return $dir;
    } // selectElements


    /**
     * @param array $dir
     * @param $reverse
     * @return array
     */
    private function sortDir(array $dir, $reverse = null): array
    {
        usort($dir, function ($a, $b) {
            return strnatcasecmp(basename($a), basename($b));
        });
        $reverse = ($reverse??false) ?: $this->reverse;
        if ($reverse) {
            $dir = array_reverse($dir);
        }
        return $dir;
    } // sortDir


    /**
     * @param array $dir
     * @return array
     */
    private function extractFilesDescriptorVars(array $dir): array
    {
        $data = array_map(array($this, 'extractFileDescriptorVars'), $dir);
        return $data;
    } // extractFilesDescriptorVars


    /**
     * @param string $file
     * @return array
     */
    private function extractFileDescriptorVars(string $file): array
    {
        $date = '';
        if (preg_match('/\d{4}-\d\d-\d\d/', basename($file), $m)) {
            $date = $m[0];
        }
        if (!($url = $this->parseUrlFile($file))) {
            if (str_starts_with($file, 'content/')) {
                $url = PageFactory::$appRootUrl . substr(preg_replace('|/\d+_|', '/', $file), 8);
            } else {
                $url = PageFactory::$appRootUrl . $file;
            }
        }
        $filename = basename($file);
        if ($this->replaceOnElem) {
            $filename = preg_replace($this->pattern, $this->replace, $filename);
        }
        $basename   = base_name($filename, false);
        $basename   = str_replace(['(', ')', '_', '~'], ['&#40;', '&#41;', '&#95;', '&#126;'], $basename);
        $label      = str_replace('_', ' ', $basename);
        $type       = is_file($file)? 'file' : 'folder';
        $path       = dirname($file) . '/';
        $subPath    = '';
        if ($type !== 'file') {
            $subPath = str_replace('/', '%2F', substr($file, $this->origPathLen));
        }
        $decription = '';
        if (file_exists("$file.txt")) {
            $decription = file_get_contents("$file.txt");
        }
        require_once 'site/plugins/pagefactory-pageelements/src/pe_helper.php';
        $out = [
            'file'          => $file,
            'filename'      => $filename,
            'basename'      => $basename,
            'label'         => $label,
            'name'          => $basename,
            'ext'           => fileExt($filename),
            'url'           => $url,
            'path'          => $path,
            'subpath'       => $subPath,
            'type'          => $type,
            'filedate'      => filemtime($file),
            'size'          => is_file($file) ? sizetostr($file) : '',
            'dateInName'    => $date,
            'description'   => $decription,
        ];
        return $out;
    } // extractFileDescriptorVars

    /**
     * @param $args
     * @param int $inx
     * @return array
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function parseOptions($args, int $inx): array
    {
        $options = $args;
        if (!isset($options['template'])) {
            $options['template'] = [];
        } elseif (is_string($options['template'])) {
            $options['template'] = [];
            $options['template']['element'] = $options['template'];
        }
        $options['template']['element'] ??= DEFAULT_ELEMENT_TEMPLATE;
        $options['template']['folderElement'] ??= DEFAULT_FOLDER_ELEMENT_TEMPLATE; // wrap in accordion
        $options['template']['markdown'] ??= true;

        $templateOptions = TemplateCompiler::sanitizeTemplateOption($options['template']??[]);
        $templateOptions['noDataAvailableText'] = '';

        $this->templateOptions = $templateOptions;

        $this->path = $args['path'];
        $this->id = $args['id'];
        $this->wrapperClass = $args['class'];
        $this->includeFiles = str_contains(strtolower($args['include']), 'files');
        $this->includeFolders = str_contains(strtolower($args['include']), 'folders');
        $this->exclude = $args['exclude'];
        $this->markdown = $args['markdown']??true;
        $this->maxAge = $args['maxAge'];
        $this->replaceOnElem = $args['replaceOnElem'];
        $this->modifiers = strtoupper($args['modifiers']);
        $this->modifiers = preg_replace('/\W+/', ',', $this->modifiers);
        $this->modifiers = ','.str_replace(' ','', $this->modifiers).',';

        $this->deep = str_contains($this->modifiers, 'DEEP');
        $this->hierarchical = false;
        if (str_contains($this->modifiers, 'HIERARCHICAL')) {
            $this->hierarchical = true;
            $this->deep = false;
        }

        $this->download = str_contains($this->modifiers, 'DOWNLOAD');
        $this->reverse = str_contains($this->modifiers, ',REVERSE,');
        $this->reverseFolders = str_contains($this->modifiers, ',REVERSE_FOLDERS,');

        if ($this->replaceOnElem) {
            list($this->pattern, $this->replace) = explodeTrim(',', $this->replaceOnElem);
        }

        if ($this->maxAge) {
            if (!is_numeric($this->maxAge)) {
                $this->maxAge = strtotime($this->maxAge);
            } else {
                $this->maxAge = time() - 86400 * $this->maxAge;
            }
        }

        if ($this->download) {
            $this->download = ' download';
        }
        if ($filename = base_name($this->path)) {
            $pattern = $filename;
        } else {
            $pattern = '*';
        }
        $this->pattern = $pattern;
        $this->path = dir_name($this->path);
        if ($this->path) {
            $this->path = fixPath($this->path);
            if ($this->path[0] !== '~') {
                $this->path = '~page/' . $this->path;
            }
        } else {
            $this->path = '~page/';
        }


        if ($this->id) {
            $this->id = " id='{$this->id}'";
        } elseif ($this->id === false) {
            $this->id = " id='pfy-dir-$inx'";
        }
        if ($this->wrapperClass) {
            $this->wrapperClass = " class='{$this->wrapperClass}'";
        }
        $path = resolvePath($this->path);
        preparePath($path);
        return array($path, $pattern);
    } // parseOptions


    private function markdown(string $str): string
    {
        $md = new MarkdownPlus();
        return $md->compile($str);
    } // markdown

} // Dir

