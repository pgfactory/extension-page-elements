<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Download;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\base_name;
use function PgFactory\PageFactory\dir_name;
use function PgFactory\PageFactory\fixPath;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\getDir;
use function PgFactory\PageFactory\getDirDeep;
use function PgFactory\PageFactory\fileExt;
use function PgFactory\PageFactory\shieldStr;

const DEFAULT_LINK_ELEMENT_TEMPLATE = "- (link: %url% text:%filename% type:%ext% target:_blank) %description%\n";
const DEFAULT_DOWNLOAD_ELEMENT_TEMPLATE = "- (link: %download% text:%filename% type:%ext% target:_blank download:true) %description%\n";

const DEFAULT_FOLDER_ELEMENT_TEMPLATE = '<> <strong>%label%</strong>';
const DEFAULT_FOLDER_DOWNLOAD_ICON = '<span title="{{ pfy-dir-download-icon-tooltip }}" data-url="%url%">:cloud_download_alt:</span>';

const PFY_DIR_OPTIONS = [
    'inx'=> 0,
    'template'=> [
        'element'=> DEFAULT_LINK_ELEMENT_TEMPLATE,
        'folderElement'=> DEFAULT_FOLDER_ELEMENT_TEMPLATE,
        'markdown'=> true,
    ],
    'path'=> '',
    'id'=> '',
    'class'=> '',
    'include'=> '',
    'exclude'=> '',
    'markdown'=> false,
    'maxAge'=> false,
    'replaceOnElem'=> '',
    'modifiers'=> '',
    'permission'=> '',
    'asLinks'=> false,
    'enableFolderDownload'=> false,
];


class Dir
{
    public static $inx = 1;
    private $path;
    private static string $rootPath;
    private $url;
    private string $absPath;
    private int $absPathLen;
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
    private $replacePattern = '';
    private $replace = '';
    private $templateOptions = [];
    private bool $permission;
    private bool $enableFolderDownload;


    /**
     * @throws \Kirby\Exception\Exception
     */
    public function __construct()
    {
        Assets::addAssets('DIR');
        Assets::addAssets('POPUPS');
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
        if (!$this->permission) {
            return '{{ pfy-insufficient-access-permissions }}';
        }

        self::$rootPath = $path;

        $this->origPathLen = strlen($path);
        $dirOffset = get('dir');
        if ($dirOffset === '.') {
            $dirOffset = '';
        }
        $goBack = $header = '';
        if ($dirOffset) {
            $path = "$path$dirOffset/";
            $href = PFY_PAGE_URL .'?dir=' . dirname($dirOffset);
            $goBack = "\n<div class='pfy-dir-go-back'><a href='$href'>{{ pfy-dir-go-back }}</a></div>";
            $header = "<div class='pfy-dir-sub-header'>{{ pfy-dir-sub-folder }}$dirOffset{{ pfy-dir-sub-folder-tail }}</div>";
        }

        if ($this->hierarchical) {
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
        kirby()->session()->set('pfy.downloadPermission', $this->permission);

        return $str;
    } // render


    /**
     * @param string $path
     * @param string $pattern
     * @param int $level
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
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

        if (!$data) {
            return $str;
        }

        $templateOptions = TemplateCompiler::sanitizeTemplateOption($this->templateOptions);
        $currLevelFiles = TemplateCompiler::compile($data, $templateOptions);

        $class = ($this->class ?: 'pfy-dir') . " $class";

        if ($currLevelFiles && preg_match('/^<(ul|ol)/', $currLevelFiles)) {
            $currLevelFiles = substr($currLevelFiles, 0, 3) . " class='$class'>" . $subdir . substr($currLevelFiles, 4);
            $str .= $currLevelFiles;

        } elseif ($subdir) {
            $str .= "<ul class='$class'>\n$subdir\n</ul>";
        } else {
            $str .= $currLevelFiles;
        }

        $realLocations = kirby()->session()->get('pfy.realLocations', []);
        $realLocations += $dir;
        kirby()->session()->set('pfy.realLocations', $realLocations);

        return $str;
    } // renderDir


    /**
     * @param string $path
     * @param string $pattern
     * @param int $level
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function renderDirHierarchical(string $path, string $pattern, int $level = 1): string
    {
        $out = '';
        $folders = getDir($path, type: 'folders');

        // reverse order of folders if option REVERSE_FOLDERS is active:
        if ($this->reverseFolders) {
            $folders = array_reverse($folders);
        }

        foreach ($folders as $folder) {
            $subdir = $this->renderDirHierarchical($folder, $pattern, $level+1);
            $subdir = shieldStr($subdir);

            $basename = basename(rtrim($folder, '/'));
            $fileVars = $this->extractFileDescriptorVars($basename, $folder);
            $templateOptions = $this->templateOptions;
            $templateOptions['markdown'] = false;
            $label = TemplateCompiler::compile($fileVars, $templateOptions, elementSelector:'folderElement');
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
            $subdir = $this->markdown($subdir);
            $out .= $subdir;
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
        $filenames = array_map('basename', $dir);
        if ($this->replaceOnElem) {
            $filenames = array_map(function($e) {
                return preg_replace($this->replacePattern, $this->replace, $e);
            }, $filenames);
        }
        if (preg_match('/^\d+_/', reset($filenames))) {
            $filenames = array_map(function($e) {
                return preg_replace('/^(\d)_/', "0$1_", $e);
            }, $filenames);
        }
        $dir = array_combine($filenames, $dir);

        ksort($dir);

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
        $data = [];
        foreach ($dir as $filename => $path) {
            $data[$filename] = $this->extractFileDescriptorVars($filename, $path);
        }
        return $data;
    } // extractFilesDescriptorVars


    /**
     * @param string $file
     * @return array
     */
    private function extractFileDescriptorVars(string $filename, string $file): array
    {
        $url = $download = $path = $type = $date = $subPath = $label = $slug = $pageId = $pageIndex = $pageIndex2 = $title = $decription = $basename = '';

        // folder:
        if (is_dir($file)) {
            $type = 'folder';
            if (str_starts_with($file, PFY_KIRBY_BASE_PATH . 'content/')) {
                // it's a page folder:
                $basename = $filename;
                $path = substr($file, strlen(PFY_KIRBY_BASE_PATH . 'content/'));
                $path = preg_replace('|^\d+_|', '', $path);
                $path = preg_replace('|/\d+_|', '/', $path);
                $subPath = rtrim($path, '/');
                $page = page($subPath);
                $url = $page->url();
                $slug = $page->slug();
                $pageId = $page->id();
                $pageIndex = (string)$page->num();
                $pageIndex2  = str_pad($pageIndex,2, '0', STR_PAD_LEFT);
                $label = $page->title()->value();
            } else {
                // folder outside of /content:
                $basename = $filename;
                $label = $basename;
                $name = $basename;
                $subPath = substr($file, $this->absPathLen);
                $url = $this->url . $subPath;
            }
            $subPath = str_replace('/', '%2F', substr($file, $this->origPathLen));

        // file:
        } elseif (is_file($file)) {
            $type       = 'file';
            $url        = $this->parseUrlFile($file);
            if (!$url) {
                $file1 = dirname($file) . '/' . urlencode(basename($file));
                $url = str_replace(PFY_DOCROOT, PFY_HOST_URL, $file1);
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'url' || $ext === 'webloc') {
                $download = $url;
                $filename = substr($filename,0, - (strlen($ext) + 1));
            } else {
                $download = str_replace(self::$rootPath, '', $file);
                $download = PFY_PAGE_URL . "?download=$download";
            }
            $basename   = base_name($filename, false);
            $label      = str_replace('_', ' ', $basename);
            $basename   = str_replace(['(', ')', '_', '~'], ['&#40;', '&#41;', '&#95;', '&#126;'], $basename);
            $path       = dirname($file) . '/';
            if (preg_match('/\d{4}-\d\d-\d\d/', $filename, $m)) {
                $date = $m[0];
            }

            if (file_exists("$file.txt")) {
                $decription = file_get_contents("$file.txt");
            }
        }

        $url = str_replace(' ', '%20', $url); // convert blanks in file names
        require_once __DIR__ . '/pe_helper.php';
        $out = [
            'file'          => $file,
            'filename'      => str_replace(['(', ')', '_', '~'], ['&#40;', '&#41;', '&#95;', '&#126;'], $filename),
            'filename_raw'  => $filename,
            'basename'      => $basename,
            'slug'          => $slug,
            'pageIndex'     => $pageIndex,
            'pageIndex2'    => $pageIndex2, // 2-digit pageIndex
            'label'         => $label,
            'name'          => $basename,
            'ext'           => fileExt($filename),
            'url'           => $url,
            'download'      => $download,
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
        $options = $args + PFY_DIR_OPTIONS;
        $this->modifiers = strtoupper($options['modifiers']??'');

        if (is_string($options['template'])) {
            $options['template'] = [];
            $options['template']['element'] = $options['template'];
        }
        $this->enableFolderDownload = $args['enableFolderDownload'];

        if (str_contains($this->modifiers, 'DOWNLOAD')) {
            $options['template']['element'] ??= DEFAULT_DOWNLOAD_ELEMENT_TEMPLATE;
        } else {
            $options['template']['element'] ??= DEFAULT_LINK_ELEMENT_TEMPLATE;
        }

        $options['template']['folderElement'] ??= DEFAULT_FOLDER_ELEMENT_TEMPLATE; // wrap in accordion
        if ($this->enableFolderDownload) {
            $options['template']['folderElement'] .= DEFAULT_FOLDER_DOWNLOAD_ICON;
        }
        $options['template']['markdown'] ??= true;

        $templateOptions = TemplateCompiler::sanitizeTemplateOption($options['template']??[]);
        $templateOptions['noDataAvailableText'] = '';

        $this->templateOptions = $templateOptions;

        $this->path = $options['path'];
        $this->absPath = Utils::resolvePath($options['path']);
        $this->absPathLen = strlen($this->absPath);
        $this->url = Utils::resolveUrls(PFY_PROTECTED_DOWNLOAD_PATH);
        $this->id = $options['id'];
        $this->wrapperClass = $options['class'];
        $this->includeFiles = str_contains(strtolower($options['include']), 'files');
        $this->includeFolders = str_contains(strtolower($options['include']), 'folders');
        $this->exclude = $options['exclude'];
        $this->markdown = $options['markdown']??false;
        $this->maxAge = $options['maxAge'];
        $this->replaceOnElem = $options['replaceOnElem'];

        $this->modifiers = preg_replace('/\W+/', ',', $this->modifiers);
        $this->modifiers = ','.str_replace(' ','', $this->modifiers).',';

        $this->deep = str_contains($this->modifiers, 'DEEP');
        $this->hierarchical = false;
        if (str_contains($this->modifiers, 'HIERARCHICAL')) {
            $this->hierarchical = true;
            $this->deep = false;
        }

        $this->reverse = str_contains($this->modifiers, ',REVERSE,');
        $this->reverseFolders = str_contains($this->modifiers, ',REVERSE_FOLDERS,');

        if ($this->replaceOnElem) {
            list($this->replacePattern, $this->replace) = explodeTrim(',', $this->replaceOnElem);
            if (!str_contains('/|#', $this->replacePattern[0])) {
                $this->replacePattern = "#$this->replacePattern#";
            }
        }

        if ($this->maxAge) {
            if (!is_numeric($this->maxAge)) {
                $this->maxAge = strtotime($this->maxAge);
            } else {
                $this->maxAge = time() - 86400 * $this->maxAge;
            }
        }

        if ($filename = base_name($this->path)) {
            $pattern = $filename;
        } else {
            $pattern = '*';
        }
        $this->path = dir_name($this->path);
        if ($this->path) {
            $this->path = fixPath($this->path);
            if (!str_starts_with($this->path, '~')) {
                $this->path = '~page/' . $this->path;
            }
        } else {
            $this->path = '~page/';
        }

        $this->permission = Permission::evaluate($options['permission']);

        if ($this->id) {
            $this->id = " id='{$this->id}'";
        } elseif ($this->id === false) {
            $this->id = " id='pfy-dir-$inx'";
        }
        if ($this->wrapperClass) {
            $this->wrapperClass = " class='{$this->wrapperClass}'";
        }
        $path = Utils::resolvePath($this->path);
        preparePath($path);
        return array($path, $pattern);
    } // parseOptions


    /**
     * @param string $str
     * @return string
     * @throws \Exception
     */
    private function markdown(string $str): string
    {
        $md = new MarkdownPlus();
        return $md->compile($str);
    } // markdown

} // Dir

