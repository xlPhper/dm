<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\Db;


require dirname(__FILE__) . "/Model/Device.php";
require dirname(__FILE__) . "/Model/Task.php";
require dirname(__FILE__) . "/Model/Weixin.php";
require dirname(__FILE__) . "/Model/Group.php";
require dirname(__FILE__) . "/Model/Group/Url.php";
require dirname(__FILE__) . "/Model/Gather/Number.php";
require dirname(__FILE__) . "/Model/Message.php";
require dirname(__FILE__) . "/Model/MonitorWord.php";
require_once dirname(__FILE__) . "/../../../library/Helper/OpenEncrypt.php";

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    public static $env = 'line';
    public static $db = 'db';
    public static $slaveDb = 'slaveDb';

    public static function setEnv()
    {
        self::$env = isset($_SERVER['argv'][2]) && $_SERVER['argv'][2] == 'test' ? 'test' : 'line';
    }

    public static function getEnv()
    {
        return self::$env;
    }

    public static function setDb()
    {
        self::$db = self::getEnv() == 'test' ? 'testDb' : 'db';
        self::$slaveDb = self::getEnv() == 'test' ? 'testSlaveDb' : 'slaveDb';
    }

    public static function getDb()
    {
        return self::$db;
    }

    public static function getSlaveDb()
    {
        return self::$slaveDb;
    }

    public static function onWorkerStart(){
        self::setEnv();
        self::setDb();
        require_once dirname(__FILE__) . '/OnMessage.php';
    }

    public static function onWorkerStop($businessWorker)
    {
        //Weixin::offline();
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id) {
        // 向当前client_id发送数据
        $db = self::getDb();
        $slaveDb = self::getSlaveDb();
        $lanIp = Net::getConfig('GatewayLanIp', self::getEnv());
        Gateway::sendToClient($client_id, json_encode(["msg" => "Hello $client_id, db is {$db}:{$slaveDb}, ip is {$lanIp}"]));
        // 向所有人发送
        //Gateway::sendToAll("$client_id login\n");
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message) {
        onMessage($client_id, $message);
   }
   
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id) {
       // 向所有人发送 
       //GateWay::sendToAll("$client_id logout");
       //Device::offline($client_id);
       Device::offline($client_id);
   }
}
