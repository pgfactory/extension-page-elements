<?php

namespace Usility\PageFactory\PageElements;


class Overlay extends PageElements
{
    public function render($str, $mdCompile = true)
    {
        if ($mdCompile) {
            $str = \Usility\PageFactory\compileMarkdown($str);
        }
        $str = <<<EOT
    <div id='pfy-overlay' class='pfy-overlay'><button class='pfy-close-overlay'>✕</button>
$str
    </div>
EOT;
        $this->addAssets('OVERLAYS');
        return $str;
    } // render



    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        $this->pg->addBodyEndInjections($str);
    } // set

} // Overlay