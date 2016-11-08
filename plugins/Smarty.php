<?php
namespace wbf\plugins;

use wbf\core\Config;
use wbf\core\Plugin;
use wbf\core\Request;
use wbf\core\Response;


class Smarty extends Plugin
{
    public function routeBefore(Request $request)
    {
//        pre($request);
    }


    public function routeAfter(Request $request)
    {
//        pre($request);
    }

    public function dispatchAfter(Request $request, Response $response)
    {
        $_adapter = new \Smarty();
        $_adapter->setCompileDir(root('smarty/cache'));
//        $_adapter->setCacheDir(root('smarty/cache'));
//        $_adapter->caching = true;
//        $_adapter->cache_lifetime = 120;
        $response->registerAdapter($_adapter);
    }


    public function shutdown(Request $request, Response $response)
    {
//        pre($response->getAdapter());
    }

}