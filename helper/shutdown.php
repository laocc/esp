<?php

use \esp\core\Kernel;

/**
 * 程序最后执行，exit后也会执行
 */
function shutdown(Kernel $kernel)
{
    if (!$kernel->shutdown()) return;


}