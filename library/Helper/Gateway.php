<?php
require_once APPLICATION_PATH . '/../GatewayWorker/Applications/WxGroup/Events.php';
require_once APPLICATION_PATH . '/../GatewayClient/Gateway.php';
use GatewayClient\Gateway;
//Gateway::$registerAddress = '127.0.0.1:1238';
Gateway::$registerAddress = '192.168.0.110:1339';

class Helper_Gateway extends Gateway
{
    public static function initConfig()
    {
        if (APPLICATION_ENV == 'testing') {
            Gateway::$registerAddress = '127.0.0.1:1339';
        }

        return new self;
    }
    /**
     * 向某个微信号发消息
     * @param string $clientID
     * @param string $message
     * @return boolean
     */
    public function push($client_id,$message)
    {
        if(!Gateway::isOnline($client_id)){
            return false;//offline 不在线不发
        }
        return Gateway::sendToClient($client_id, $message);
    }
}