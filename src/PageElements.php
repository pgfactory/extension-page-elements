<?php

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory;


class PageElements
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pg = $pfy->pg;
        $this->trans = PageFactory::$trans;
        $this->extensionPath = dirname(dirname(__FILE__)).'/';
    } // __construct


} // PageElements