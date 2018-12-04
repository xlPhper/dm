<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 17:21
 */

use \GatewayWorker\Lib\Db;

class Weixin
{
    public static $table = 'weixins';

    public static function updateStatus($ClientID, $Message)
    {
        $db = Db::instance(Events::getDb());
        $sql = "select * from weixins where Weixin = '{$Message['Weixin']}'";
        $info = $db->row($sql);
        if(isset($info['WeixinID'])){
            if($info['ClientID'] <> $ClientID){
                $db->update(self::$table)->cols([
                    'OnlineStatus'  =>  'ONLINE',
                    'WorkStatus'    =>  'FREE',
                    'ClientID'  =>  $ClientID,
                    'NetworkType'   =>  $Message['NetworkType'],
                    'UpdateDate'   =>  date("Y-m-d H:i:s")
                ])->where("WeixinID = '{$info['WeixinID']}'")->query();
                return $info['WeixinID'];
            }
        }else{
            $db->insert(self::$table)->cols([
                'Weixin'  =>  $Message['Weixin'],
                'ClientID'  =>  $ClientID,
                'OnlineStatus'  =>  'ONLINE',
                'WorkStatus'    =>  'FREE',
                'NetworkType'   =>  $Message['NetworkType'],
                'AddDate'   =>  date("Y-m-d H:i:s")
            ])->query();
            return $db->lastInsertId();
        }
        return false;
    }

    public static function offline($ClientID = null)
    {
        $db = Db::instance(Events::getDb());
        $db->update(self::$table)->cols([
            'ClientID'  =>  '',
            'OnlineStatus'  =>  'OFFLINE',
        ]);
        if(null !== $ClientID){
            $db->where("ClientID = '{$ClientID}'");
        }
        $db->query();
    }

    public static function setFree($ClientID)
    {
        $db = Db::instance(Events::getDb());
        $db->update(self::$table)->cols([
            'WorkStatus'  =>  'FREE'
        ])->where("ClientID = '{$ClientID}'");
        $db->query();
    }

    public static function getInfoByWeixin($Weixin)
    {
        $db = Db::instance(Events::getDb());
        $sql = "select * from ".self::$table." where Weixin = '{$Weixin}'";
        return $db->row($sql);
    }

    public static function getInfoByWeixinId($WeixinId)
    {
        $db = Db::instance(Events::getDb());
        $sql = "select * from ".self::$table." where WeixinID = '{$WeixinId}'";
        return $db->row($sql);
    }
}