<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 9:14
 */

use \GatewayWorker\Lib\Db;

class Group
{
    public static $table = 'groups';

    public static function join($Message)
    {
        $db = Db::instance(Events::getDb());
        $info = $db->select('*')->from(self::$table)->where("WeixinID = :WeixinID")->where("ChatroomID = :ChatroomID")
            ->bindValues(['WeixinID' => $Message['WeixinID'], 'ChatroomID' => $Message['ChatroomID']])->row();
        if(!isset($info['GroupID'])){
            $db->insert(self::$table)->cols([
                'WeixinID'  =>  $Message['WeixinID'],
                'ChatroomID'    =>  $Message['ChatroomID'],
                'Name'  =>  $Message['Name'],
                'UserNum'   =>  $Message['UserNum'],
                'QRCode'    =>  $Message['QRCode'],
                'QRCodeDate'    =>  date("Y-m-d H:i:s"),
                'AddDate'   =>  date("Y-m-d H:i:s"),
                'Type'  =>  $Message['Type']
            ])->query();
        }
        return true;
    }
}