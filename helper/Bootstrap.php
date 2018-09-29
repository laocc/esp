<?php

use \esp\core\Dispatcher;
use \esp\library\gd\Image;
use \esp\plugs\Example;

final class Bootstrap
{

    /**
     * 判断当前访问是不是一个缩略图格式，
     * 若是，直接创建且后面不再执行
     * TODO 需自行设计缩略图格式
     * @param Dispatcher $dispatcher
     */
    public function _initThumbs(Dispatcher $dispatcher)
    {
        if (_CLI or !getenv('REQUEST_FILENAME')) return;
        $pattern = '/^\/(.+?)\.(jpg|gif|png|bmp|jpeg)\_(\d{1,4})(x|v|z)(\d{1,4})\.\2(?:[\?\#].*)?$/i';
        if (preg_match($pattern, getenv('REQUEST_URI'))) {
            exit(Image::thumbs());
        }
    }

    /**
     * @param Dispatcher $dispatcher
     */
    public function _initDefine(Dispatcher $dispatcher)
    {
        define('_DEBUG', is_file(_ROOT . '/cache/debug.lock'));
    }

    /**
     * 引入注册插件示例
     * @param Dispatcher $dispatcher
     */
    public function _initRegPlugs(Dispatcher $dispatcher)
    {
        $dispatcher->setPlugin(new Example());
    }

}