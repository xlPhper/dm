<?php

/**
 * mysql配置
 * @author walkor
 */
class Net
{
    public static $net = [
        'WorkerRegisterAddress' => '192.168.0.110:1339',
        'GatewayLanIp' => '192.168.0.110',
        'GatewayRegisterAddress' => '192.168.0.110:1339'
    ];

    public static $testNet = [
        'WorkerRegisterAddress' => '127.0.0.1:1339',
        'GatewayLanIp' => '127.0.0.1',
        'GatewayRegisterAddress' => '127.0.0.1:1339'
    ];

    public static function getConfig($key, $env = '')
    {
        if ($env === '') {
            $env = isset($_SERVER['argv'][2]) && $_SERVER['argv'][2] == 'test' ? 'test' : 'line';
        }
        if ($env == 'test') {
            return self::$testNet[$key];
        } else {
            return self::$net[$key];
        }
    }

}