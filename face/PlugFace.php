<?php

namespace esp\face;

use esp\core\Request;
use esp\core\Response;


/**
 * 插件需实现以下所有方法
 * Interface PlugFace
 * @package esp\core
 */
interface PlugFace
{

    /**
     * 1.在路由之前触发
     * @param Request $request
     * @param Response $response
     */
    public function routeBefore(Request $request, Response $response);


    /**
     * 2.路由结束之后触发
     * @param Request $request
     * @param Response $response
     */
    public function routeAfter(Request $request, Response $response);


    /**
     * 3.分发循环开始之前被触发，在这之前，如果有缓存，就直接显示，不进入这里，直接运行最后的mainEnd
     * @param Request $request
     * @param Response $response
     */
    public function dispatchBefore(Request $request, Response $response);


    /**
     * 4.分发之前触发
     * @param Request $request
     * @param Response $response
     */
    public function dispatchAfter(Request $request, Response $response, &$value);


    /**
     * 5.显示开始之前被触发
     * @param Request $request
     * @param Response $response
     */
    public function displayBefore(Request $request, Response $response, &$value);


    /**
     * 6.显示之后触发，在此之后保存缓存
     * @param Request $request
     * @param Response $response
     */
    public function displayAfter(Request $request, Response $response, &$value);


    /**
     * 7.结束之后触发，到了这里，服务器与客户端已经断开了，也就是在这之后不能操作任何与客户端交互的内容
     * @param Request $request
     * @param Response $response
     */
    public function mainEnd(Request $request, Response $response, &$value);
}
