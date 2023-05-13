<?php

namespace HCode;

use Hcode\Page;

class PageAdmin extends Page
{
    public function __construct($opts = [], $tpl_dir = '/views/admin/')
    {
        parent::__construct($opts, $tpl_dir);
    }
}
