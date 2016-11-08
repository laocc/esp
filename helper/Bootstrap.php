<?php
use \esp\core\Kernel;
use \esp\plugins;

final class Bootstrap
{
    public function _initTemp(Kernel $kernel)
    {
        $kernel->setPlugin('test', new plugins\Temp());
    }


    public function _initSmarty(Kernel $kernel)
    {
        $kernel->setPlugin('smarty', new plugins\Smarty());

    }

}