<?php

namespace Usility\PageFactory\PageElements;


class Overlay extends PageElements
{
    public function render($str, $mdCompile)
    {
        if ($mdCompile) {
            $str = \Usility\PageFactory\compileMarkdown($str);
        }
        $str = <<<EOT
    <div id='lzy-overlay' class='lzy-overlay'><button class='lzy-close-overlay'>✕</button>
$str
    </div>
EOT;
        $this->addAssets('OVERLAY');
        return $str;
    } // render



    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        $this->pg->addBodyEndInjections($str);
    } // set

} // Overlay