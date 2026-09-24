<?php

namespace esp\face;

use esp\core\Request;
use esp\core\Response;


/**
 * 插件需实现以下所有方法
 *
 * 方法名须与 Dispatcher::plugsHook() 中实际调用的钩子名一致：
 * router / dispatch / display / finish / shutdown / end
 * 与 esp\core\Plugin 中定义的钩子一一对应
 *
 * Interface PlugFace
 * @package esp\core
 */
interface PlugFace
{

    /**
     * 1.在路由之前触发
     * 返回非null时，该返回值被直接显示并结束整个流程
     * @param Request $request
     * @param Response $response
     */
    public function router(Request $request, Response $response);


    /**
     * 2.路由之后、控制器之前触发，若命中了页面缓存则不进入这里
     * 返回非null时，该返回值被直接显示并结束整个流程
     * @param Request $request
     * @param Response $response
     */
    public function dispatch(Request $request, Response $response);


    /**
     * 3.控制器之后、显示之前触发
     * 返回非null时，该返回值被直接显示，并跳过第4个finish
     * @param Request $request
     * @param Response $response
     * @param $value
     */
    public function display(Request $request, Response $response, &$value);


    /**
     * 4.显示之后、fastcgi_finish_request()之前触发
     * @param Request $request
     * @param Response $response
     * @param $value
     */
    public function finish(Request $request, Response $response, &$value);


    /**
     * 5.收尾处、register_shutdown_function()之前触发
     * @param Request $request
     * @param Response $response
     * @param $value
     */
    public function shutdown(Request $request, Response $response, &$value);


    /**
     * 6.run()最末尾触发，此时客户端已断开，此后再不能操作任何与客户端交互的内容
     * @param Request $request
     * @param Response $response
     * @param $value
     */
    public function end(Request $request, Response $response, &$value);
}
