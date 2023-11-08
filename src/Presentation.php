<?php
namespace PgFactory\PageFactoryElements;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\TransVars as TransVars;
use PgFactory\PageFactory\SiteNav as SiteNav;
use PgFactory\MarkdownPlus\MdPlusHelper as MdPlusHelper;
//use function \PgFactory\PageFactory\registerSrcFileProcessor as registerSrcFileProcessor;
//use function \PgFactory\PageFactory\translateToClassName as translateToClassName;
use function \PgFactory\PageFactory\getStaticUrlArg as getStaticUrlArg;

const DEFAULT_FONT_SIZE = '3vw';


class Presentation
{

    public function __construct()
    {
        PageFactory::$pg->addAssets([
            'site/plugins/pagefactory-pageelements/assets/css/-presentation_support.css',
            'site/plugins/pagefactory-pageelements/assets/js/presentation_support.js'
        ]);
        PageFactory::$pg->addBodyTagClass('pfy-presentation-support');

        if (kirby()->option('pgfactory.pagefactory-elements.options.presentationAutoSizing')) {
            PageFactory::$pg->addJs("const presiAutoSizing = true;");
        }

//        if (!$defaultSize = kirby()->option('pgfactory.pagefactory-elements.options.presentationDefaultSize')) {
//        }
        $defaultSize = kirby()->option('pgfactory.pagefactory-elements.options.presentationDefaultSize', DEFAULT_FONT_SIZE);
        PageFactory::$pg->addJs("const presiDefaultFontSize = '$defaultSize';");


        $relLinks = '';
        if ($link = SiteNav::$prev) {
            $link = $link->url();
            $relLinks .= "  <link id='pfy-rel-prev' rel='prev' href='$link'>\n";
        }
        if ($link = SiteNav::$next) {
            $link = $link->url();
            $relLinks .= "  <link id='pfy-rel-next' rel='next' href='$link'>\n";
        }
        PageFactory::$pg->addHead($relLinks);

        PageFactory::$wrapperClass = 'pfy-presentation-section';
        PageFactory::registerSrcFileProcessor('\\PgFactory\\PageFactoryElements\\Presentation::autoSplitSections');

        if ($height = getStaticUrlArg('h', true)) {
            if ($height === 'false') {
                kirby()->session()->remove("pfy.h");
            } else {
                $height = intval($height);
                PageFactory::$pg->addCss("body { --pfy-presi-height:{$height}vw; }");
            }
        }
    } // __construct


    public static function autoSplitSections($mdStr, &$inx, $wrapperTag, $wrapperId, $wrapperClass)
    {
        $html = '';
        $sections = preg_split("/\n#\s/ms", "\n$mdStr", 0, PREG_SPLIT_NO_EMPTY);
        foreach ($sections as $i => $md) {
            if (!trim($md)) {
                continue;
            }

            // find pattern "{: font-size: XYxy }" on H1 line, e.g. "# H1 {: font-size: 20em }"
            $wrapperAttributes = '';
            $lines = explode("\n", $md);
            if (preg_match('/(?<!\\\)\{:\s*(.*?)\s*}/i', $lines[0], $m)) {
                $line = &$lines[0];
                $args = MdPlusHelper::parseInlineBlockArguments($m[1]);
                if ($args['class']) {
                    $wrapperClass .= ' '.$args['class'];
                }
                $wrapperAttributes = ' '.trim(preg_replace("/class='.*?'/", '', $args['htmlAttrs']));
                $line = rtrim(str_replace($m[0], '', $line));
                $md = implode("\n", $lines);
            }

            $wrapperClass = preg_replace("/pfy-part-\d+/", "pfy-part-$inx", $wrapperClass);
            $html1 = TransVars::compile("#$md", $inx, removeComments: false);
            $html .= <<<EOT

<$wrapperTag class='$wrapperClass'$wrapperAttributes>
<div class="pfy-section-inner">
$html1
</div>
</$wrapperTag> <!-- /pfy-part-$inx -->


EOT;
            $inx++;
        }
        $inx--;
        $html = <<<EOT

<div id='$wrapperId'>

$html
</div> <!-- wrapper /$wrapperId -->


EOT;

        return $html;
    } // autoSplitSections

} // Presentation