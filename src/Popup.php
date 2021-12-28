<?php

namespace Usility\PageFactory\PageElements;

class Popup extends PageElements
{
    public function render($msg, $mdCompile)
    {
        $html = '';
        if ($msg) {
            if (strpos($msg, '{{') !== false) {
                $msg = $this->trans->translate($msg);
            }
            if ($mdCompile) {
                $msg = \Usility\PageFactory\compileMarkdown($msg);
            }

            $html = "\t\t<div class='lzy-msgbox'>$msg</div>\n";
            $this->pg->requireJQuery();
        }
        return $html;
    } // render

} // Popup