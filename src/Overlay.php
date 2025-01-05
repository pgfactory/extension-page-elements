<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\PageFactory\Page;
use function PgFactory\PageFactory\compileMarkdown;


class Overlay extends PageElements
{
    public static $inx = 1;

    /**
     * @param mixed $content
     * @param $mdCompile
     * @return string
     */
    public function render(mixed $content, $mdCompile = true)
    {
        $inx = self::$inx++;
        $jsOptions = '';

        if (is_string($content)) {
            if ($mdCompile) {
                $content = compileMarkdown($content);
            }
            $content = <<<EOT
    <div id='pfy-overlay-$inx' class='pfy-overlay' style="display: none;">
        <div class="pfy-overlay-inner">
$content
        </div>
    </div>
EOT;
            Page::addBodyEndInjections($content);
            $jsOptions = <<<EOT
    contentFrom: '#pfy-overlay-$inx .pfy-overlay-inner',
    popupClass: 'pfy-overlay',
EOT;

        } elseif (is_array($content)) {
            foreach ($content as $key => $option) {
                if (is_bool($option)) {
                    $option = $option?'true':'false';
                } else {
                    $option = "\"$option\"";
                }
                $jsOptions .= "\t$key: $option,\n";
            }
            $jsOptions .= "\tpopupClass: 'pfy-overlay',\n";
        }
        $jsOptions = "{\n$jsOptions }";
        Page::addJsReady("pfyPopup($jsOptions);");

        $this->addAssets('POPUPS');
        Page::addBodyTagClass('pfy-overlay-open');
        return '';
    } // render


    /**
     * @param mixed $options
     * @param $mdCompile
     * @return void
     */
    public function set(mixed $options, $mdCompile = false): void
    {
        $this->render($options, $mdCompile);
    } // set

} // Overlay