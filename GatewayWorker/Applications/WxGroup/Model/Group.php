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
//        $db = Db::instance(Events::getDb());
//        $info = $db->select('*')->from(self::$table)->where("WeixinID = :WeixinID")->where("ChatroomID = :ChatroomID")
//            ->bindValues(['WeixinID' => $Message['WeixinID'], 'ChatroomID' => $Message['ChatroomID']])->row();
//        if(!isset($info['GroupID'])){
//            $db->insert(self::$table)->cols([
//                'WeixinID'  =>  $Message['WeixinID'],
//                'ChatroomID'    =>  $Message['ChatroomID'],
//                'Name'  =>  $Message['Name'],
//                'UserNum'   =>  $Message['UserNum'],
//                'QRCode'    =>  $Message['QRCode'],
//                'QRCodeDate'    =>  date("Y-m-d H:i:s"),
//                'AddDate'   =>  date("Y-m-d H:i:s"),
//                'Type'  =>  $Message['Type']
//            ])->query();
//        }
        return true;
    }

    public static function update($Message)
    {
        $db = Db::instance(Events::getDb());
        //查询是否有这个群
        $info = $db->select('*')->from(self::$table)->where("ChatroomID = :ChatroomID")
            ->bindValues(['ChatroomID' => $Message['ChatroomID']])->row();
        if(!isset($info['GroupID'])){
            $db->insert(self::$table)->cols([
                'ChatroomID'    =>  $Message['ChatroomID'],
                'Name'  =>  $Message['Name'],
                'UserNum'   =>  $Message['UserNum'],
                'QRCode'    =>  $Message['QRCode'],
                'QRCodeDate'    =>  date("Y-m-d H:i:s"),
                'AddDate'   =>  date("Y-m-d H:i:s"),
                'Type'  =>  $Message['Type']
            ])->query();
            $GroupID = $db->lastInsertId();
        }else {
            $data = [
                'Name' => $Message['Name'],
                'UserNum' => $Message['UserNum'],
                'QRCode' => $Message['QRCode'],
                'UpdateDate'    =>  date("Y-m-d H:i:s"),
            ];
            $db->update(self::$table)->cols($data)->where("ChatroomID = :ChatroomID")
                ->bindValues(["ChatroomID" => $Message['ChatroomID']])->query();
            $GroupID = $info['GroupID'];
        }
        Group_Weixin::join($GroupID, $Message['WeixinID']);
        return true;
    }
}