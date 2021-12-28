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
    $this->pfy->pg->addAssets(
        [
            $this->extensionPath.'scss/overlay.scss',
            $this->extensionPath.'js/overlay.js'
        ]
    );
    return $str;
    } // render

} // Overlay