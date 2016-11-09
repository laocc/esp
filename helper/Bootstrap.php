<?php
use \esp\core\Kernel;
use \esp\plugins;

final class Bootstrap
{
    public function _initDebug(Kernel $kernel)
    {
        $debug = new plugins\Debug($kernel);
        $kernel->setPlugin('debug', $debug);
        $debug->star();
    }


    public function _initSmarty(Kernel $kernel)
    {
        $kernel->setPlugin('smarty', new plugins\Smarty());

    }

}