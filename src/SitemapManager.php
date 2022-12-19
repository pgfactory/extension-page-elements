<?php

namespace Usility\PageFactoryElements;
use Usility\PageFactory\PageFactory as PageFactory;
use function \Usility\PageFactory\fileTime;
use function \Usility\PageFactory\getFile;
use function \Usility\PageFactory\getDirDeep;
use function \Usility\PageFactory\mylog;
use function \Usility\PageFactory\decodeStr;
use function \Usility\PageFactory\translateToFilename;
use function \Usility\PageFactory\preparePath;
use function \Usility\PageFactory\reloadAgent;
use function \Usility\PageFactory\rrmdir;


const SITEMAP_FILE          = 'site/config/sitemap.txt';
const SITEMAP_CONTROL_FILE  = 'content/site.txt';

class SitemapManager
{

    private static array $supportedLanguages;
    private static bool $modified;
    private static bool $allowNonPfyPages;

    /**
     * There is a control file 'site/config/sitemap.txt'. And there is the actual site structure.
     * -> Determines which is newer, modifies the older accordingly.
     * Last-modified of actual site structure is maintained by file 'content/site.txt'.
     * @return void
     */
    public static function updateSitemap(): void
    {
        // only run in debug mode:
        if (!PageFactory::$debug || !self::updateNecessary()) {
            return;
        }
        self::$allowNonPfyPages = PageFactory::$config['allowNonPfyPages']??false;

        // determine what needs to be updated:
        $tSitemapFile = fileTime(SITEMAP_FILE);
        $tSitemapControlFile = fileTime(SITEMAP_CONTROL_FILE);
        if ($tSitemapFile > $tSitemapControlFile) {
            // sitemap is newer, so we need to update the content folder's directory structure:
            self::updateContentFolder();
        }
        self::writeSitemapFile();
    } // updateSitemap


    /**
     * Compares filemtime of SITEMAP_FILE with SITEMAP_CONTROL_FILE.
     * Also compares hash in SITEMAP_FILE with hash from actual dir-structure.
     * If either ones differ, returns true.
     * @return bool
     */
    public static function updateNecessary()
    {
        // check whether sitemap file differs from actual content folder:
        $tSitemapFile = fileTime(SITEMAP_FILE);
        $tSitemapControlFile = fileTime(SITEMAP_CONTROL_FILE);
        $firstLine = '';
        if (file_exists(SITEMAP_FILE)) {
            $firstLine = fgets(fopen(SITEMAP_FILE, 'r'));
        }
        $contentHash = md5(implode('', getDirDeep('content/*', true)));
        $contentHash = "// hash: $contentHash\n";
        return (($firstLine !== $contentHash) || ($tSitemapFile !== $tSitemapControlFile));
    } // updateNecessary


    /**
     * Updates SITEMAP_FILE according to current dir-structure, keeps text beyond __END__ in place.
     * @return void
     * @throws \Exception
     */
    public static function writeSitemapFile(): void
    {
        $zap = getFile(SITEMAP_FILE, 'z');
        if ($zap) {
            $zap = "\n\n$zap";
        }
        $contentHash = md5(implode('', getDirDeep('content/*', true)));
        $contentHash = "// hash: $contentHash\n";
        $siteStructure = self::readSiteStructure();
        file_put_contents(SITEMAP_FILE, "$contentHash$siteStructure$zap");
        touch(SITEMAP_CONTROL_FILE);
        mylog("Sitemap file updated according current site-structure");
    } // writeSitemapFile


    /**
     * Modifies the site structure according to the sitemap.txt file.
     * @return void
     * @throws \Exception
     */
    private static function updateContentFolder(): void
    {
        $sitemap = getFile(SITEMAP_FILE, removeComments: 'c');
        self::$supportedLanguages = kirby()->languages()->codes();
        $paths = [];
        $paths[-1] = 'content/';
        $requiredFolders = [];

        $lastLevel = 99;
        $inx[0] =  $inx[1] =  $inx[2] =  $inx[3] =  $inx[4] =  $inx[5] = 0;
        self::$modified = false;
        foreach (explode("\n", $sitemap) as $item) {
            $draft = false;
            $unlisted = false;
            if (!trim($item)) {
                continue;
            } elseif (preg_match('/^(\s*)#(.*)/', $item, $m)) {
                $item = $m[1].$m[2];
                $draft = true;
            } elseif (preg_match('/^(\s*)\^(.*)/', $item, $m)) {
                $item = $m[1].$m[2];
                $unlisted = true;
            } elseif (@substr($item, 0, 2) === '//') {
                continue;
            }

            // read page arguments:
            if (preg_match('/: \s* \{ .* }/x', $item)) {
                $rec = decodeStr($item, 'yaml');
                $name = trim(array_keys($rec)[0]);
                $rec = reset($rec);

            // no (valid) arguments, strip possible noise:
            } else {
                $item = preg_replace('/:.*/', '', $item);
                $name = trim($item);
                $rec = [];
            }

            // determine level (either \t or 4x blanks per level):
            $indent = preg_replace('/\S.*/', '', $item);
            if (str_contains($indent, "\t")) {
                $level = strlen($indent);
            } else {
                $level = intval(strlen($indent) / 4);
            }

            // reset item counter of current level when going to a higher level:
            if ($level < $lastLevel) {
                $inx[$level+1] = 0;
            }
            $lastLevel = $level;

            // folder:
            $folder0 = $rec['folder']??false;

            // $baseName is Page name converted to lower case with blanks and special characters changed to '_':
            $baseName = translateToFilename($name, '');

            // assemble propre path:
            if ($draft) {
                $folder = $paths[$level - 1] . "_drafts/$baseName/";

            } elseif ($unlisted) {
                $folder = $paths[$level - 1] . "$baseName/";

            } else {
                $inx[$level]++;
                $folder = $paths[$level - 1] . $inx[$level] . "_$baseName/";
            }

            if ($folder0 && file_exists($folder0)) {
                if ($folder !== $folder0) {
                    $draft0 = basename(dirname($folder0)) === '_drafts';
                    if ($draft || $draft0) {
                        if (!$draft0 && $draft) {
                            preparePath(dirname($folder));
                            rename($folder0, $folder);

                        } elseif ($draft0 && !$draft) {
                            rename($folder0, $folder);

                        } else {
                            throw new \Exception("Should be impossible...");
                        }
                    } else {
                        rename($folder0, $folder);
                    }
                    self::$modified = true;
                }
            } else {
                // make folder if it doesn't exist:
                if (!file_exists($folder)) {
                    preparePath($folder);
                    self::$modified = true;
                }
            }

            // keep track of current folder:
            $paths[$level] = $folder;

            // keep track of folders defined in sitemap-file:
            $requiredFolders[$folder] = true;

            // create initial files in new folder:
            self::$modified = self::updateMetaFiles($folder, $name, $baseName) || self::$modified;
        }

        // find and remove folders not defined in sitemap-file:
        $deletedFolders = self::removeUnusedFolders($requiredFolders);

        // if content structure was modified, we need to reload agent:
        if (self::$modified || $deletedFolders) {
            touch(SITEMAP_CONTROL_FILE);
            mylog("Site-structure updated according to sitemap file");
            reloadAgent('',$deletedFolders);
        }
    } // updateContentFolder


    /**
     * Reads the actual site structure.
     * @param object $subtree
     * @return string
     */
    private static function readSiteStructure(mixed $subtree = false): string
    {
        if (!$subtree) {
            $out = self::readSiteStructure(site()->children());
            $out .= self::readSiteStructure(site()->drafts());
            return $out;
        }
        $out = '';
        $len = strlen(getcwd())+1;
        foreach ($subtree as $pg) {
            $p = (string)$pg;
            if (str_starts_with($p, 'assets') || str_starts_with($p, 'error')) {
                continue;
            }
            $depth = $pg->depth();
            $indent = str_repeat('    ', $depth-1);
            $path = substr($pg->root(), $len).'/';
            $unlisted = (preg_match('/^\d+_/', basename($path)))? '': '^';
            $title = html_entity_decode($pg->title()->html());

            if ($depth === 1) {
                $out .= "\n";
            }
            if (basename(dirname($path)) === '_drafts') {
                $out .= "#$indent$title: { folder: '$path' }\n";
            } else {
                $out .= "$unlisted$indent$title: { folder: '$path' }\n";
            }

            $listed = $pg->children()->listed();
            if (!$listed->isEmpty()) {
                $out .= self::readSiteStructure($listed);
            }

            $unlisted = $pg->children()->unlisted();
            if (!$unlisted->isEmpty()) {
                $out .= self::readSiteStructure($unlisted);
            }

            $drafts = $pg->drafts();
            if (!$drafts->isEmpty()) {
                $out .= self::readSiteStructure($drafts);
            }
        }
        return $out;
    } // readSiteStructure


    /**
     * For given folder, checks whether txt-file's 'Title:' line is up-to-date, corrects if necessary.
     * Also checks whether md file exists, creates it if necessary
     * @param string $folder
     * @param string $name
     * @param string $baseName
     * @return bool
     */
    private static function updateMetaFiles(string $folder, string $name, string $baseName): bool
    {
        if (!self::$allowNonPfyPages) { // means 'don't check metafiles'
            return false;
        }

        $modified = false;
        $defaultLang = PageFactory::$defaultLanguage;
        // initialize/update meta-files:
        foreach (self::$supportedLanguages as $lang) {
            $txtFile = $folder . "z_pfy.$lang.txt";
            if (!file_exists($txtFile)) {
                file_put_contents($txtFile, "Title: $name\n");
                $modified = true;
            } else {
                $txt = file_get_contents($txtFile);
                if (preg_match('/Title: (.*?)\n/', $txt, $m)) {
                    if ($lang === $defaultLang) {
                        $txt = str_replace($m[0], "Title: $name\n", $txt);
                        file_put_contents($txtFile, $txt);
                    }
                } else {
                    $txt = "Title: $name\n";
                    file_put_contents($txtFile, $txt);
                }
            }
        }

        // initialize/update md-file:
        $mdFiles = getDir($folder.'*.md');
        if (!$mdFiles) {
            $mdFile = $folder . "1_$baseName.md";
            if ($modified && !file_exists($mdFile)) {
                file_put_contents($mdFile, "\n# $name\n\n");
            }
        }
        return $modified;
    } // updateMetaFiles


    /**
     * updateContentFolder() keeps track of folders used -> $requiredFolders.
     * This method loops over all existing folders in 'content/' and deletes those that are
     * not stated in $requiredFolders
     * @param array $requiredFolders
     * @return string
     */
    private static function removeUnusedFolders(array $requiredFolders): string
    {
        $doDelete = isset($_GET['delete-folders']);
        $requiredFolders = array_keys($requiredFolders);
        $actualFolders = getDirDeep('content/', onlyDir: true);
        $deletedFolders = '';
        foreach ($actualFolders as $folder) {
            if (($folder === 'content/') ||
                str_starts_with($folder, 'content/assets/') ||
                str_starts_with($folder, 'content/error/') ||
                str_ends_with($folder, '_drafts/')) {
                continue;
            }
            if (!in_array($folder, $requiredFolders)) {
                $deletedFolders .= "$folder\n";
                if ($doDelete) {
                    rrmdir($folder);
                }
            }
        }
        if ($deletedFolders) {
            if ($doDelete) {
                $deletedFolders = "Folders deleted:\n$deletedFolders";
            } else {
                $url = PageFactory::$pageUrl.'?delete-folders';
                $msg = <<<EOT
<h1>Sitemap Manager</h1>
<p>These folders should be deleted:</p>
<pre>
$deletedFolders
</pre>
<p>Click <a href="$url">here</a> to delete these folders automatically.</p>
EOT;
                exit($msg);
            }
        }
        return $deletedFolders;
    } // removeUnusedFolders

} // SitemapManager