<?php

namespace Usility\PageFactoryElements;
use Usility\PageFactory\PageFactory as PageFactory;


class Overlay extends PageElements
{
    public static $inx = 1;
    public function render(mixed $content, $mdCompile = true)
    {
        $inx = self::$inx++;
        $jsOptions = '';

        if (is_string($content)) {
            if ($mdCompile) {
                $content = \Usility\PageFactory\compileMarkdown($content);
            }
            $content = <<<EOT
    <div id='pfy-overlay-$inx' class='pfy-overlay' style="display: none;">
        <div class="pfy-overlay-inner">
$content
        </div>
    </div>
EOT;
            $this->pg->addBodyEndInjections($content);
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
        PageFactory::$pg->addJq("pfyPopup($jsOptions);");

        $this->addAssets('POPUPS');
        PageFactory::$pg->setBodyTagClass('pfy-overlay-open');
        return '';
    } // render


    public function set(mixed $options, $mdCompile = false): void
    {
        $this->render($options, $mdCompile);
    } // set

} // Overlay