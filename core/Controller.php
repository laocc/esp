<?php
//declare(strict_types=1);

namespace esp\core;

use esp\core\db\Redis;
use esp\core\face\Adapter;
use esp\core\ext\Input;

abstract class Controller
{
    protected $_config;
    protected $_request;
    protected $_response;
    protected $_session;
    protected $_plugs;
    protected $_system;
    private $_input;
    public $_debug;
    public $_buffer;

    /**
     * Controller constructor.
     * @param Dispatcher $dispatcher
     * @throws \Exception
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->_config = &$dispatcher->_config;
        $this->_plugs = &$dispatcher->_plugs;
        $this->_request = &$dispatcher->_request;
        $this->_response = &$dispatcher->_response;
        $this->_session = &$dispatcher->_session;
        $this->_debug = &$dispatcher->_debug;
        $this->_buffer = $this->_config->Redis();
        $this->_system = defined('_SYSTEM') ? _SYSTEM : 'auto';
        if (_CLI) return;

        if (defined('_DEBUG_PUSH_KEY')) {
            register_shutdown_function(function (Request $request) {
                //发送访问记录到redis队列管道中，后面由cli任务写入数据库
                $debug = [];
                $debug['time'] = time();
                $debug['system'] = _SYSTEM;
                $debug['virtual'] = _VIRTUAL;
                $debug['module'] = $request->module;
                $debug['controller'] = $request->controller;
                $debug['action'] = $request->action;
                $debug['method'] = $request->method;
                $this->_buffer->push(_DEBUG_PUSH_KEY, $debug);
            }, $this->_request);
        }
    }

    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param array ...$host
     * @throws \Exception
     */
    final protected function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host($this->_request->referer), array_merge([_HOST], $host))) {
            throw new \Exception('禁止接入', 401);
        }
    }

    final protected function config(...$key)
    {
        return $this->_config->get(...$key);
    }

    /**
     * 发送订阅，需要在swoole\redis中接收
     * @param string $action
     * @param $value
     * @return int
     */
    final public function publish(string $action, $value)
    {
        $channel = $this->_config->get('app.dim.channel');
        if (!$channel) $channel = 'order';
        return $this->_buffer->publish($channel, $action, $value);
    }

    /**
     * 设置视图文件，或获取对象
     * @return View|bool
     */
    final public function getView()
    {
        return $this->_response->getView();
    }

    final public function setView($value)
    {
        $this->_response->setView($value);
    }

    final public function setViewPath(string $value)
    {
        $this->_response->viewPath($value);
    }

    final public function run_user(string $user = 'www')
    {
        if (getenv('USER') !== $user) {
            $cmd = implode(' ', $GLOBALS["argv"]);
            exit("请以www账户运行，CLI模式下请用\n\nsudo -u {$user} -g {$user} -s php {$cmd}\n\n\n");
        }
    }

    /**
     * 标签解析器
     * @return bool|View|Adapter
     */
    final protected function getAdapter()
    {
        return $this->_response->getView()->getAdapter();
    }

    final protected function setAdapter($bool)
    {
        return $this->_response->getView()->setAdapter($bool);
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @return bool|View
     */
    final protected function getLayout()
    {
        return $this->_response->getLayout();
    }

    final protected function setLayout($value)
    {
        $this->_response->setLayout($value);
    }

    /**
     * @return Input
     */
    final protected function input()
    {
        if (is_null($this->_input)) {
            $this->_input = new Input();
        }
        return $this->_input;
    }

    /**
     * @param string $url
     * @param null $data
     * @param array $option
     * @return array
     *
     * $option['type']      请求方式，get,post,upload
     * $option['port']      对方端口
     * $option['gzip']      被读取的页面有gzip压缩
     * $option['headers']   带出的头信息
     * $option['transfer']  返回文本流全部信息，在返回的header里
     * $option['agent']     模拟的客户端UA信息
     * $option['proxy']     代理服务器IP
     * $option['cookies']   带出的Cookies信息，或cookies文件
     * $option['referer']   指定来路URL
     * $option['cert']      带证书
     * $option['charset']   目标URL编码，转换为utf-8
     * $option['redirect']  是否跟着跳转，>0时为跟着跳
     * $option['encode']    将目标html转换为数组，在返回的array里，可选：json,xml
     * $option['host']      目标域名解析成此IP
     * $option['ip']        客户端IP，相当于此cURL变成一个代理服务器
     * $option['lang']      语言，cn或en
     */
    final public function request(string $url, $data = null, array $option = [])
    {
        $response = [];
        $response['error'] = 100;

        if (empty($url)) {
            $response['message'] = 'empty API url';
            return $response;
        }

        if (is_array($data) and empty($option)) [$data, $option] = [null, $data];
        if (!isset($option['headers'])) $option['headers'] = Array();
        if (!is_array($option['headers'])) $option['headers'] = [$option['headers']];

        $cOption = [];

        if (0) {
            $cOption[CURLOPT_VERBOSE] = true;//输出所有的信息，写入到STDERR(直接打印到屏幕)
//        $cOption[CURLOPT_STDERR] = root('/cache/curl');//若不指定，则输出到屏幕
            $cOption[CURLOPT_CERTINFO] = true;//TRUE 将在安全传输时输出 SSL 证书信息到 STDERR。
            $cOption[CURLOPT_FAILONERROR] = true;//当 HTTP 状态码大于等于 400，TRUE 将将显示错误详情。 默认情况下将返回页面，忽略 HTTP 代码。
        }

//        if (isset($option['port'])) $cOption[CURLOPT_PORT] = intval($option['port']);      //端口

        if (isset($option['host'])) {
            if (is_array($option['host'])) {
                $cOption[CURLOPT_RESOLVE] = $option['host'];
            } else {
                if (!is_ip($option['host'])) {
                    $response['message'] = 'host must be a IP address';
                    return $response;
                }
                $urlDom = explode('/', $url);
                if (strpos($urlDom[2], ':')) {//将端口移到port中
                    $dom = explode(':', $urlDom[2]);
                    $urlDom[2] = $dom[0];
                    $option['port'] = intval($dom[1]);
                } else if (!isset($option['port'])) {
                    if (strtolower(substr($url, 0, 5)) === 'https') {
                        $option['port'] = 443;
                    } else {
                        $option['port'] = 80;
                    }
                }
                $cOption[CURLOPT_RESOLVE] = ["{$urlDom[2]}:{$option['port']}:{$option['host']}"];
            }
        }

        if (isset($option['human'])) {
//            $option['headers'][] = "Cache-Control: no-cache";
            $option['headers'][] = "Cache-Control: max-age=0";
            $option['headers'][] = "Connection: keep-alive";
//            $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8";
            $option['headers'][] = "Upgrade-Insecure-Requests: 1";
            $option['headers'][] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.5,image/webp,image/apng,*/*;q=0.8";
        }

        if (isset($option['lang'])) {
            if ($option['lang'] === 'en') {
                $option['headers'][] = "Accept-Language: en-us,en;q=0.8";
            } elseif ($option['lang'] === 'cn') {
                $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8";
            }
        }

        if (isset($option['redirect'])) {
            $cOption[CURLOPT_MAXREDIRS] = max($option['redirect'], 2);//指定最多的 HTTP 重定向次数，最小要为2
            $cOption[CURLOPT_POSTREDIR] = 1;//什么情况下需要再次 HTTP POST 到重定向网址:1 (301 永久重定向), 2 (302 Found) 和 4 (303 See Other)
            $cOption[CURLOPT_FOLLOWLOCATION] = true;//根据服务器返回 HTTP 头中的 "Location: " 重定向
            $cOption[CURLOPT_AUTOREFERER] = true;//根据 Location: 重定向时，自动设置 header 中的Referer:信息
            $cOption[CURLOPT_UNRESTRICTED_AUTH] = 1;//重定向时，时继续发送用户名和密码信息，哪怕主机名已改变
        }

        $cOption[CURLOPT_URL] = $url;                                                      //接收页
        $cOption[CURLOPT_FRESH_CONNECT] = true;                                            //强制新连接，不用缓存中的

        if (isset($option['ip'])) {     //指定客户端IP
            $option['headers'][] = "CLIENT-IP: {$option['ip']}";
            $option['headers'][] = "X-FORWARDED-FOR: {$option['ip']}";
        }

        foreach ($option['headers'] as $k => $h) {
            if (is_string($k)) {
                $option['headers'][] = "{$k}: {$h}";
                unset($option['headers'][$k]);
            }
        }

        if (isset($option['cookies']) and !empty($option['cookies'])) {//带Cookies
            if ($option['cookies'][0] === '/') {
                $cOption[CURLOPT_COOKIEFILE] = $option['cookies'];
                $cOption[CURLOPT_COOKIEJAR] = $option['cookies'];
            } else {
                $cOption[CURLOPT_COOKIE] = $option['cookies'];//直接指定值
            }
        }

        $option['type'] = strtoupper($option['type'] ?? 'get');
        if (!in_array($option['type'], ['GET', 'POST', 'PUT', 'HEAD', 'DELETE', 'UPLOAD'])) $option['type'] = 'GET';
        switch ($option['type']) {
            case "GET" :
                $cOption[CURLOPT_HTTPGET] = true;
                if (!empty($data)) {//GET时，需格式化数据为字符串
                    if (is_array($data)) $data = http_build_query($data);
                    $url .= (!strpos($url, '?') ? '?' : '&') . $data;
                }
                break;

            case "POST":
                if (is_array($data)) $data = json_encode($data, 256);
                $option['headers'][] = "X-HTTP-Method-Override: POST";
                $option['headers'][] = "Expect: ";  //post大于1024时，会带100 ContinueHTTP标头的请求，加此指令禁止
                $cOption[CURLOPT_POST] = true;      //类型为：application/x-www-form-urlencoded
                $cOption[CURLOPT_POSTFIELDS] = $data;
                break;

            case 'UPLOAD':
                $field = (isset($option['field']) ? $option['field'] : 'files');
                $option['headers'][] = "X-HTTP-Method-Override: POST";
                $option['headers'][] = "Content-Type: multipart/form-data; boundary=-------------" . uniqid();

                if (!is_array($data)) {
                    $response['message'] = '上传数据只能为数组，被上传的文件置于files字段内';
                    return $response;
                }
                if (isset($data['files'])) {
                    foreach ($data['files'] as $fil => $file) {
                        $data["{$field}[{$fil}]"] = new \CURLFile($file);
                    }
                    unset($data['files']);
                }
                $cOption[CURLOPT_POST] = true;
                $cOption[CURLOPT_POSTFIELDS] = $data;
                break;

            case "HEAD" :   //这三种不常用，使用前须确认对方是否接受
            case "PUT" :
            case "DELETE":
                //不确定服务器支持这个自定义方法则不要使用它。
                $cOption[CURLOPT_CUSTOMREQUEST] = $option['type'];
                break;
        }

        if (isset($option['auth'])) {
            $cOption[CURLOPT_USERPWD] = $option['auth'];
        }

        //指定代理
        if (isset($option['proxy'])) {
            if (strpos($option['proxy'], ';')) {
                $pro = explode(';', $option['proxy']);
                $cOption[CURLOPT_PROXY] = $pro[0];
                if (!empty($pro[1])) $cOption[CURLOPT_PROXYUSERPWD] = $pro[1];
            } else {
                $cOption[CURLOPT_PROXY] = $option['proxy'];
            }
        }
        if (isset($option['referer']) and $option['referer']) $cOption[CURLOPT_REFERER] = $option['referer'];//来源页
        if (isset($option['gzip']) and $option['gzip']) {//有压缩
            $option['headers'][] = "Accept-Encoding: gzip, deflate";
            $cOption[CURLOPT_ENCODING] = "gzip, deflate";
        }
        if (!empty($option['headers'])) $cOption[CURLOPT_HTTPHEADER] = $option['headers'];     //头信息
        if (isset($option['ua'])) $option['agent'] = $option['ua'];
        if (isset($option['agent'])) {
            $cOption[CURLOPT_USERAGENT] = $option['agent'];
        }

        $cOption[CURLOPT_HEADER] = (isset($option['transfer']) and $option['transfer']);        //带回头信息
        $cOption[CURLOPT_DNS_CACHE_TIMEOUT] = 120;                                              //内存中保存DNS信息，默认120秒
        $cOption[CURLOPT_CONNECTTIMEOUT] = $option['wait'] ?? 10;                               //在发起连接前等待的时间，如果设置为0，则无限等待
        $cOption[CURLOPT_TIMEOUT] = ($option['timeout'] ?? 10);                                 //允许执行的最长秒数，若用毫秒级，用CURLOPT_TIMEOUT_MS
        $cOption[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;                                        //指定使用IPv4解析
        $cOption[CURLOPT_RETURNTRANSFER] = true;                                                //返回文本流，若不指定则是直接打印

        if (strtoupper(substr($url, 0, 5)) === "HTTPS") {
//            $cOption[CURLOPT_HTTP_VERSION]=CURLOPT_HTTP_VERSION_2_0;
//            $cOption[CURLOPT_SSL_VERIFYPEER]=true;
//            $cOption[CURLOPT_SSL_VERIFYHOST]=2;
            $cOption[CURLOPT_SSL_VERIFYPEER] = false;//禁止 cURL 验证对等证书，就是不验证对方证书
            $cOption[CURLOPT_SSL_VERIFYHOST] = false;

            if (isset($option['cert'])) {//证书
                $cOption[CURLOPT_SSLCERTTYPE] = 'PEM';
                $cOption[CURLOPT_SSLKEYTYPE] = 'PEM';
                if (isset($option['cert']['cert'])) $cOption[CURLOPT_SSLCERT] = $option['cert']['cert'];
                if (isset($option['cert']['key'])) $cOption[CURLOPT_SSLKEY] = $option['cert']['key'];
                if (isset($option['cert']['ca'])) $cOption[CURLOPT_CAINFO] = $option['cert']['ca'];
            }
        } else {
            $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) {
            $response['message'] = 'Create Protocol Object Error';
            return $response;
        }
        if ($option['type'] === 'POST') {
            $response['post'] = $data;
        }
        $response['option'] = $cOption;

        curl_setopt_array($cURL, $cOption);
        $response['html'] = curl_exec($cURL);
        $response['info'] = curl_getinfo($cURL);

        if (($err = curl_errno($cURL)) > 0) {
            $response['error'] = $err;
            $response['message'] = curl_error($cURL);
            return $response;
        }
        curl_close($cURL);
        $response['error'] = 0;
        $response['message'] = '';

        if (isset($option['transfer']) and $option['transfer']) {
            $header = function ($text) {
                $line = explode("\r\n", trim($text));
                $arr = Array();
                foreach ($line as $i => $ln) {
                    if (strpos($ln, ':')) {
                        $tmp = explode(':', $ln, 2);
                        $arr[strtoupper($tmp[0])] = trim($tmp[1]);
                    } else {
                        $arr[] = $ln;
                    }
                }
                return $arr;
            };
            $response['header'] = $header(substr($response['html'], 0, $response['info']['header_size']));
            $response['html'] = trim(substr($response['html'], $response['info']['header_size']));
        }

        if (preg_match('/charset=([gbk2312]{3,6})/i', $response['info']['content_type'], $chat)) {
            $response['html'] = mb_convert_encoding($response['html'], 'UTF-8', $chat[1]);

        } else if (isset($option['charset'])) {
            if ($option['charset'] === 'auto') {
                //自动识别gbk/gb2312转换为utf-8
                if (preg_match('/<meta.+?charset=[\'\"]?([gbk2312]{3,6})[\'\"]?/i', $response['html'], $chat)) {
                    $option['charset'] = $chat[1];
                } else {
                    $option['charset'] = null;
                }
            }
            if (is_null($option['charset'])) {
                $response['html'] = mb_convert_encoding($response['html'], 'UTF-8');
            } else {
                $response['html'] = mb_convert_encoding($response['html'], 'UTF-8', $option['charset']);
            }
        }

        if (intval($response['info']['http_code']) !== 200) {
            $response['error'] = intval($response['info']['http_code']);
            if ($response['error'] === 0) $response['error'] = 10;
            $response['message'] = $response['html'];
//            unset($response['html']);
//            return $response;
        }

        if (isset($option['encode']) and in_array($option['encode'], ['json', 'xml'])) {
            if (!empty($response['html'])) {
                if ($option['encode'] === 'json') {
                    $response['array'] = json_decode($response['html'], true);
                    if (empty($response['array'])) {
                        $response['array'] = [];
                        $response['error'] = 500;
                    }
                } else if ($option['encode'] === 'xml') {
                    $response['array'] = (array)simplexml_load_string(trim($response['html']), 'SimpleXMLElement', LIBXML_NOCDATA);
                    if (empty($response['array'])) {
                        $response['array'] = [];
                        $response['error'] = 500;
                    }
                }
            } else {
                $response['error'] = 400;
                $response['array'] = [];
            }
        }


        end:
        return $response;
    }

    /**
     * @return Redis
     */
    final public function getBuffer()
    {
        return $this->_buffer;
    }

    /**
     * @return Session
     * @throws \Exception
     */
    final public function getSession()
    {
        if (is_null($this->_session)) throw new \Exception('当前站点未开启session', 401);
        return $this->_session;
    }

    /**
     * @return Request
     */
    final public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @return Configure
     */
    final public function getConfig()
    {
        return $this->_config;
    }

    /**
     * @return Response
     */
    final public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param $type
     * @param $val
     * @param null $color
     * @return string|array
     * $color 可以直接指定为一个数组
     * $color=true 时，无论是否有预定义的颜色，都返回全部内容
     * $color=false 时，不返回预定义的颜色
     */
    final public function state($type, $val, $color = null)
    {
        if ($val === '' or is_null($val)) return '';
        $value = $this->_config->get("app.state.{$type}.{$val}") ?: $val;
        if ($color === true) return $value;
        if (strpos($value, ':')) {
            $pieces = explode(':', $value);
            if (is_array($color)) return $pieces;
            if ($color === false) return $pieces[0];
            return "<span class='v{$val}' style='color:{$pieces[1]}'>{$pieces[0]}</span>";
        }
        if (empty($color)) return $value;
        if (!isset($color[$val])) return $value;
        return "<span class='v{$val}' style='color:{$color[$val]}'>{$value}</span>";
    }

    /**
     * @return Resources
     */
    final public function getResource()
    {
        return $this->_response->getResource();
    }

    /**
     * @param string $name
     * @return null|Plugin
     */
    final public function getPlugin(string $name)
    {
        $name = ucfirst($name);
        return isset($this->_plugs[$name]) ? $this->_plugs[$name] : null;
    }

    /**
     * @param string $modName
     * @param mixed ...$param
     * @return mixed
     * @throws \Exception
     */
    final public function Model(string $modName, ...$param)
    {
        static $model = [];
        $modName = ucfirst($modName);
        if (isset($model[$modName])) return $model[$modName];

        $base = "/models/main/Base.php";
        if (is_readable($base)) load($base);

        if (!load("/models/main/{$modName}.php")) {
            throw new \Exception("[{$modName}Model] don't exists", 404);
        }

        $mod = '\\models\\main\\' . $modName . 'Model';
        $model[$modName] = new $mod($this, ...$param);
        if (!$model[$modName] instanceof Model) {
            throw new \Exception("{$modName} 须继承自 \\esp\\core\\Model", 404);
        }
        return $model[$modName];
    }

    /**
     * @param string $data
     * @param null $pre
     * @return bool|Debug|EmptyClass
     */
    final public function debug($data = '_R_DEBUG_', $pre = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return new EmptyClass();
//        if (is_null($data)) return $this->_debug;
        if ($data === '_R_DEBUG_') return $this->_debug;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->_debug->relay($data, $pre);
        return $this->_debug;
    }

    /**
     * @param null $data
     * @return bool|Debug|EmptyClass
     */
    final public function debug_mysql($data = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return new EmptyClass();
        if (is_null($data)) return $this->_debug;
        $this->_debug->mysql_log($data);
        return $this->_debug;
    }

    final public function buffer_flush()
    {
        return $this->_buffer->flush();
    }

    final protected function header(...$kv): void
    {
        $this->_response->header(...$kv);
    }

    /**
     * 冲刷(flush)所有响应的数据给客户端
     * 此函数冲刷(flush)所有响应的数据给客户端并结束请求。这使得客户端结束连接后，需要大量时间运行的任务能够继续运行。
     * @return bool
     */
    final protected function finish()
    {
        $this->debug('fastcgi_finish_request');
        return fastcgi_finish_request();
    }

    /**
     * 网页跳转
     * @param string $url
     * @param int $code
     * @return bool
     */
    final protected function redirect(string $url, int $code = 302)
    {
        if (headers_sent($filename, $line)) {
            $this->_debug->relay([
                    "header Has Send:{$filename}({$line})",
                    'headers' => headers_list()]
            )->error("页面已经输出过");
            return false;
        }
        $this->_response->redirect("Location: {$url} {$code}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Location: {$url}", true, $code);
        fastcgi_finish_request();
        if (!is_null($this->_debug)) {
            register_shutdown_function(function () {
                $this->_debug->save_logs('Controller Redirect');
            });
        }
//        return true;
        exit;
    }

    final protected function exit(string $route = '')
    {
        echo $route;
        fastcgi_finish_request();
        if (!is_null($this->_debug)) {
            register_shutdown_function(function () {
                $this->_debug->save_logs('Controller Exit');
            });
        }
        exit;
    }

    final protected function jump($route)
    {
        return $this->reload($route);
    }

    /**
     * 路径，模块，控制器，动作 间跳转，重新分发
     * TODO 此操作会重新分发，当前Response对象将重新初始化，Controller也会按目标重新加载
     * 若这四项都没变动，则返回false
     * @param array ...$param
     * @return bool
     */
    final protected function reload(...$param)
    {
        if (empty($param)) return false;
        $directory = $this->_request->directory;
        $module = $this->_request->module;
        $controller = $action = $params = null;

        if (is_string($param[0]) and is_dir($param[0])) {
            $directory = root($param[0]) . '/';
            array_shift($param);
        }
        if (is_dir($directory . $param[0])) {
            $module = $param[0];
            array_shift($param);
        }
        if (count($param) === 1) {
            list($action) = $param;
        } elseif (count($param) === 2) {
            list($controller, $action) = $param;
        } elseif (count($param) > 2) {
            list($controller, $action) = $param;
            $params = array_slice($param, 2);
        }
        if (!is_string($controller)) $controller = $this->_request->controller;
        if (!is_string($action)) $action = $this->_request->action;

        //路径，模块，控制器，动作，这四项都没变动，返回false，也就是闹着玩的，不真跳
        if ($directory == $this->_request->directory
            and $module == $this->_request->module
            and $controller == $this->_request->controller
            and $action == $this->_request->action
        ) return false;
//        var_dump([$directory,$module,$controller,$action]);

        $this->_request->directory = $directory;
        $this->_request->module = $module;

        if ($controller) ($this->_request->controller = $controller);
        if ($action) ($this->_request->action = $action);
        if ($params) $this->_request->params = $params;
        return $this->_request->loop = true;
    }

    /**
     * 向视图送变量
     * @param $name
     * @param null $value
     * @return $this
     */
    final protected function assign($name, $value = null)
    {
        $this->_response->assign($name, $value);
        return $this;
    }

    final public function __set(string $name, $value)
    {
        $this->_response->assign($name, $value);
        return $this;
    }

    final public function __get(string $name)
    {
        return $this->_response->get($name);
    }

    final protected function set(string $name, $value = null)
    {
        $this->_response->assign($name, $value);
        return $this;
    }

    final protected function get(string $name)
    {
        return $this->_response->get($name);
    }

    final protected function markdown(string $mdFile = null, string $mdCss = '/css/markdown.css?2')
    {
        $this->css($mdCss);
        if ($mdFile) {
            $this->_response->setView($mdFile);
        } else {
            $this->_response->set_value('md', $mdFile);
        }
    }

    /**
     * @param string|null $mdFile
     * @param string $mdCss
     * @throws \Exception
     */
    final protected function md(string $mdFile = null, string $mdCss = '/css/markdown.css?1')
    {
        $this->css($mdCss);
        if ($mdFile) {
            $this->_response->setView($mdFile);
        } else {
            $this->_response->set_value('md', $mdFile);
        }
    }

    /**
     * @param string|null $value
     * @return bool
     * @throws \Exception
     */
    final protected function html(string $value = null)
    {
        return $this->_response->set_value('html', $value);
    }

    /**
     * @param array $value
     * @return bool
     * @throws \Exception
     */
    final protected function json(array $value)
    {
        return $this->_response->set_value('json', $value);
    }

    /**
     * @param string $title
     * @param bool $default
     * @return $this
     */
    final protected function title(string $title, bool $default = false)
    {
        $this->_response->title($title, $default);
        return $this;
    }

    final protected function php(array $value)
    {
        return $this->_response->set_value('php', $value);
    }

    final protected function text(string $value)
    {
        return $this->_response->set_value('text', $value);
    }

    final protected function image(string $value)
    {
        return $this->_response->set_value('image', $value);
    }

    final protected function xml($root, $value = null)
    {
        if (is_array($root)) list($root, $value) = [$value ?: 'xml', $root];
        if (is_null($value)) list($root, $value) = ['xml', $root];
        if (!preg_match('/^\w+$/', $root)) $root = 'xml';
        return $this->_response->set_value('xml', [$root, $value]);
    }

    final protected function ajax($viewFile)
    {
        if ($this->getRequest()->isAjax()) {
            $this->setLayout(false);
            $this->setView($viewFile);
        }
    }

    /**
     * 设置js引入
     * @param $file
     * @param string $pos
     * @return $this
     */
    final protected function js($file, $pos = 'foot')
    {
        $this->_response->js($file, $pos);
        return $this;
    }


    /**
     * 设置css引入
     * @param $file
     * @return $this
     */
    final protected function css($file)
    {
        $this->_response->css($file);
        return $this;
    }

    final protected function concat(bool $run)
    {
        $this->_response->concat($run);
        return $this;
    }

    final protected function render(bool $run)
    {
        $this->_response->autoRun($run);
        return $this;
    }


    /**
     * 设置网页meta项
     * @param string $name
     * @param string $value
     * @return $this
     */
    final protected function meta(string $name, string $value)
    {
        $this->_response->meta($name, $value);
        return $this;
    }


    /**
     * 设置网页keywords
     * @param string $value
     * @return $this
     */
    final protected function keywords(string $value)
    {
        $this->_response->keywords($value);
        return $this;
    }


    /**
     * 设置网页description
     * @param string $value
     * @return $this
     */
    final protected function description(string $value)
    {
        $this->_response->description($value);
        return $this;
    }

    final protected function cache(bool $save = true)
    {
        $this->_response->cache($save);
        return $this;
    }

    /**
     * 注册关门后操作
     * 先注册的先执行，后注册的后执和，框架最后还有debug保存
     * @param callable $fun
     * @param mixed ...$parameter
     */
    final public function shutdown(callable $fun, ...$parameter)
    {
        register_shutdown_function($fun, ...$parameter);
    }


}