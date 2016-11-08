<?php
namespace esp\plugins;

use esp\core\Config;
use esp\core\Plugin;
use esp\core\Request;
use esp\core\Response;


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

    /**
     * 分发之后触发
     * @param Request $request
     * @param Response $response
     */
    public function dispatchAfter(Request $request, Response $response)
    {
        if ($response->getType()) return;

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