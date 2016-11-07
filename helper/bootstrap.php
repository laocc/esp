<?php
use \wbf\core\Kernel;

class Bootstrap
{
    public function _initTemp(Kernel $kernel)
    {
        $kernel->setPlugin('test', new Temp());
    }


    public function _initSmarty(Kernel $kernel)
    {

    }

}