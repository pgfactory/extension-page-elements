<?php

namespace Usility\PageFactoryElements;
use Usility\PageFactory\PageFactory as PageFactory;


class Overlay extends PageElements
{
    public static $inx = 1;
    public function render(mixed $options, $mdCompile = true)
    {
        $inx = self::$inx++;
        $jsOptions = '';

        if (is_string($options)) {
            if ($mdCompile) {
                $options = \Usility\PageFactory\compileMarkdown($options);
            }
            $options = <<<EOT
    <div id='pfy-overlay-$inx' class='pfy-overlay' style="display: none;">
$options
    </div>
EOT;
            $this->pg->addBodyEndInjections($options);
            $jsOptions = <<<EOT
    contentFrom: 'pfy-overlay-$inx',
    popupClass: 'pfy-overlay',
EOT;

        } elseif (is_array($options)) {
            foreach ($options as $key => $option) {
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