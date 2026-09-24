<?php
declare(strict_types=1);

namespace esp\core;


abstract class Plugin
{

    /**
     * 1.在路由之前触发
     * @param Request $request
     * @param Response $response
     */
    public function router(Request $request, Response $response)
    {
    }


    /**
     * 2.分发循环开始之前被触发，在这之前，如果有缓存，就直接显示，不进入这里，直接运行最后的end
     * @param Request $request
     * @param Response $response
     */
    public function dispatch(Request $request, Response $response)
    {
    }


    /**
     * 3.显示开始之前被触发
     * @param Request $request
     * @param Response $response
     * @param $value
     */
    public function display(Request $request, Response $response, &$value)
    {
    }

    /**
     * 4. 执行fastcgi_finish_request之前，但如果display返回了非null值，则跳过finish直接到shutdown
     * @param Request $request
     * @param Response $response
     * @param $value
     * @return void
     */
    public function finish(Request $request, Response $response, &$value)
    {
    }

    /**
     * 5. 执行register_shutdown_function之前，
     * @param Request $request
     * @param Response $response
     * @param $value
     * @return void
     */
    public function shutdown(Request $request, Response $response, &$value)
    {
    }


    /**
     * 6.结束之后触发，到了这里，服务器与客户端已经断开了，也就是在这之后不能操作任何与客户端交互的内容
     *
     * @param Request $request
     * @param Response $response
     * @param $value
     */
    public function end(Request $request, Response $response, &$value)
    {
    }

}
