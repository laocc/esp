<?php

use \esp\core\Main;

/**
 * 程序最后执行，exit后也会执行
 */
function shutdown(Main $main)
{
    if (!$main->shutdown()) return;
}