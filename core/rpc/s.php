<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', dirname(__DIR__));
include '../kernel/autoload.php';


class UserModel
{
    /**
     * 用户注册
     * @param $username
     * @param $password
     * @return string
     */
    public function registerAction($username, $password)
    {
        echo json_encode(["{$username}注册成功:{$password}"], 256);
//        return "{$username}注册成功:{$password}";
    }

    /**
     * 用户登录
     * @param $username
     * @param $password
     * @return array
     */
    public function loginAction($username, $password)
    {
        //业务代码
        return [$username, $password];
    }

    public function testAction($id, $data)
    {
        return [
            'len' => strlen($data) / 3,
//            'data' => $data,
        ];
    }

}

$sev = new \laocc\rpc\Server(new UserModel());
$sev->action = 'Action';
$sev->token = 'myToken';
$sev->password = 'pwd';
$sev->agent = 'myAgent';
$sev->sign = $sev::SIGN_C_S | $sev::SIGN_S_C;
//$sev->shield(['loginAction']);
$sev->listen();