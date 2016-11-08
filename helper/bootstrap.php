<?php
use \wbf\core\Kernel;
use \wbf\plugins;

final class Bootstrap
{
    public function _initTemp(Kernel $kernel)
    {
        $kernel->setPlugin('smarty', new plugins\Smarty());
        $kernel->setPlugin('test', new plugins\Temp());
    }


    public function _initSmarty(Kernel $kernel)
    {

    }

}