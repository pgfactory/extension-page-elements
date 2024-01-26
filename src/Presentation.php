<?php
namespace PgFactory\PageFactoryElements;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\SiteNav;
use PgFactory\PageFactory\TransVars as TransVars;
use PgFactory\MarkdownPlus\MdPlusHelper as MdPlusHelper;
use function \PgFactory\PageFactory\getStaticUrlArg as getStaticUrlArg;

const DEFAULT_FONT_SIZE = '3vw';


class Presentation
{

    public function __construct()
    {
        $presActive = getStaticUrlArg('present')? 'true': 'false';
        PageFactory::$pg->addJs("const presentationActive = $presActive;");
        if ($presActive === 'true') {
            PageFactory::$pg->addBodyTagClass('pfy-presentation-active');
            PageFactory::$pg->addAssets([
                'site/plugins/pagefactory-pageelements/assets/css/-presentation_support.css',
                'site/plugins/pagefactory-pageelements/assets/js/presentation_support.js'
            ]);
        }

        PageFactory::$pg->addAssets([
            'site/plugins/pagefactory-pageelements/assets/css/-presentation_support_base.css',
        ]);

        if (kirby()->option('pgfactory.pagefactory-elements.options.presentationAutoSizing')) {
            PageFactory::$pg->addJs("const presiAutoSizing = true;");
        }

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

        $pageNr = SiteNav::$pageNr;
        PageFactory::$pg->addBodyTagAttributes("data-currpagenr='$pageNr'");

        PageFactory::$wrapperClass = 'pfy-presentation-section';
        PageFactory::registerSrcFileProcessor('\\PgFactory\\PageFactoryElements\\Presentation::autoSplitSections');

        // url-arg "height":
        if ($height = getStaticUrlArg('height', true)) {
            if ($height === 'false') {
                kirby()->session()->remove("pfy.h");
            } else {
                $height = intval($height);
                PageFactory::$pg->addCss("body { --pfy-presi-height:{$height}vw; }");
                if (isset($_GET['height'])) {
                    $_height = 100 - $height + 0.5;
                    $html = "<input id='height-slider' type='range' name='height' min='0' max='100' value='$_height'>";
                    PageFactory::$pg->addBodyEndInjections($html);
                    $js = <<<EOT
const url = window.location.href.replace(/\?.*/, '');
const \$input = document.querySelector("#height-slider");
\$input.addEventListener("input", (event) => {
    const newVal = 100 - parseInt( \$input.value);
    mylog(`new height: \${newVal}`);
    window.location.href = url + '?height=' + newVal;
});

EOT;
                    PageFactory::$pg->addJsReady($js);
                    $css = <<<EOT
body  {
    position: relative;
}

#height-slider {
    position: absolute;
    top: 0;
    right: 0;
    -webkit-appearance: slider-vertical; /* replace when standard completed */
    height: 100vw;
    background: yellow;
    width: 1em;
    z-index: 99;
}

EOT;
                    PageFactory::$pg->addCss($css);
                }
            }
        }
    } // __construct


    public static function autoSplitSections($mdStr, &$inx, $wrapperTag, $wrapperId, $wrapperClass)
    {
        $html = '';
        $sections = preg_split("/\n#\s/ms", "\n$mdStr", 0, PREG_SPLIT_NO_EMPTY);
        if ($autoSlideNumbering = page()->autoslidenumbering()->value()) {
            $autoSlideNumbering = ($autoSlideNumbering === 'false') ? false : (($autoSlideNumbering === 'true')? true: $autoSlideNumbering);
        } else {
            $autoSlideNumbering = kirby()->option('pgfactory.pagefactory-elements.options.autoSlideNumbering');
        }
        foreach ($sections as $i => $md) {
            if (!trim($md)) {
                continue;
            }

            // find pattern "{: font-size: XYxy }" on H1 line, e.g. "# H1 {: font-size: 20em }"
            $wrapperAttributes = '';
            $lines = explode("\n", $md);
            if (preg_match('/(?<!\\\)\{:\s*(.*?)\s*}/i', $lines[0], $m)) {
                $line = &$lines[0];

                // special case '!font-size' -> ignored in non-presentation mode, but interpreted here:
                $m[1] = preg_replace('/!font-size/', 'font-size', $m[1]);
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

            // auto slide numbering:
            if ($autoSlideNumbering) {
                // normal numbering:
                if ($autoSlideNumbering === true) {
                    $pageNr = SiteNav::$pageNr;
                    $html1 = "<h1 id='$inx'><span class='pfy-slide-nr'>$pageNr.$inx</span>" . substr($html1, 4);

                } else {
                    // first page is TOC -> start number on second page:
                    if ($inx > 1) {
                        $pageNr = SiteNav::$pageNr;
                        $inx_ = ($inx > 1) ? $inx - 1 : '';
                        $html1 = "<h1 id='$inx_'><span class='pfy-slide-nr'>$pageNr.$inx_</span>" . substr($html1, 4);
                    }
                }
            }
            $html .= <<<EOT

<$wrapperTag class='$wrapperClass'$wrapperAttributes data-inx='$inx'>
<div class="pfy-section-inner">
$html1
</div><!-- /pfy-section-inner -->
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